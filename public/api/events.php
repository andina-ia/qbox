<?php
// events.php — métricas del dashboard (solo admin). Todo se calcula acá, sobre
// TODOS los eventos del período (no sobre una muestra), en hora de Argentina.
// GET ?days=7|30|90|0(todo)

declare(strict_types=1);
require __DIR__ . '/_lib.php';

if (method() !== 'GET') json_out(['error' => 'Método no permitido'], 405);
if (!current_admin()) json_out(['error' => 'Sesión expirada. Volvé a ingresar.'], 401);

const TZ_LOCAL = 'America/Argentina/Buenos_Aires';
const SESSION_GAP = 1800; // eventos del cotizador sin sesión: >30 min de pausa = otra persona

$days = max(0, min(3650, (int)($_GET['days'] ?? 7)));
$pdo = db();
$tz = new DateTimeZone(TZ_LOCAL);

// Desfase real de la base respecto de UTC (NOW() puede estar en UTC o en hora local).
$dbOffsetMin = (int)$pdo->query('SELECT TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), NOW())')->fetchColumn();
$toEpoch = function (string $dbDatetime) use ($dbOffsetMin): int {
    return (int)strtotime($dbDatetime . ' UTC') - $dbOffsetMin * 60;
};

$where = $days > 0 ? 'WHERE created_at >= (NOW() - INTERVAL ' . $days . ' DAY)' : '';
$st = $pdo->query("SELECT id, type, metadata, created_at FROM qbox_events $where ORDER BY created_at ASC, id ASC");

$visits = 0;
$hours = array_fill(0, 24, 0);
$daily = [];               // 'YYYY-MM-DD' => [pv, cot, contact]
$waBySource = [];
$cotRows = [];             // [epoch, sid|null, type]
$recent = [];
$firstDay = null;

$dayKey = function (int $epoch) use ($tz): string {
    return (new DateTime('@' . $epoch))->setTimezone($tz)->format('Y-m-d');
};
$bump = function (string $day, int $idx) use (&$daily) {
    if (!isset($daily[$day])) $daily[$day] = [0, 0, 0];
    $daily[$day][$idx]++;
};

foreach ($st as $r) {
    $epoch = $toEpoch($r['created_at']);
    $meta = json_decode((string)$r['metadata'], true) ?: [];
    $day = $dayKey($epoch);
    if ($firstDay === null) $firstDay = $day;

    switch ($r['type']) {
        case 'page_view':
            $visits++;
            $bump($day, 0);
            $hours[(int)(new DateTime('@' . $epoch))->setTimezone($tz)->format('G')]++;
            break;
        case 'cotizador_use':
            $cotRows[] = [$epoch, isset($meta['sid']) ? (string)$meta['sid'] : null, (string)($meta['type'] ?? ''), $meta];
            continue 2; // el cotizador se agrega a "recientes" agrupado por persona (más abajo)
        case 'whatsapp_click':
        case 'showroom_click': // botón viejo de agenda: cuenta como contacto
            $src = $r['type'] === 'showroom_click' ? 'agenda (anterior)' : (string)($meta['source'] ?? 'otro');
            $waBySource[$src] = ($waBySource[$src] ?? 0) + 1;
            $bump($day, 2);
            break;
    }
    $recent[] = ['type' => $r['type'], 'metadata' => $meta, 'epoch' => $epoch];
    if (count($recent) > 200) $recent = array_slice($recent, -100);
}

// ── Cotizador: agrupar en "personas" (sesiones) y quedarse con su última elección ──
$sessions = [];            // key => [startEpoch, lastEpoch, lastType, lastMeta, ajustes]
$openLegacy = null;
foreach ($cotRows as [$epoch, $sid, $type, $meta]) {
    if ($sid !== null) {
        $k = 's:' . $sid;
    } else {
        if ($openLegacy === null || $epoch - $sessions[$openLegacy][1] > SESSION_GAP) {
            $openLegacy = 'l:' . $epoch;
        }
        $k = $openLegacy;
    }
    if (!isset($sessions[$k])) $sessions[$k] = [$epoch, $epoch, $type, $meta, 0];
    $sessions[$k][1] = $epoch;
    $sessions[$k][3] = $meta;
    $sessions[$k][4]++;
    if ($type !== '') $sessions[$k][2] = $type;
}
$cotPeople = count($sessions);
$typeCount = [];
foreach ($sessions as [$start, $last, $type, $lastMeta, $n]) {
    $bump($dayKey($start), 1);
    if ($type !== '') $typeCount[$type] = ($typeCount[$type] ?? 0) + 1;
}
arsort($typeCount);

// ── Serie diaria completa (días sin actividad = 0), en orden cronológico ──
$today = new DateTime('now', $tz);
$start = $days > 0
    ? (clone $today)->modify('-' . ($days - 1) . ' days')
    : ($firstDay ? new DateTime($firstDay, $tz) : clone $today);
$series = [];
for ($d = clone $start; $d->format('Y-m-d') <= $today->format('Y-m-d'); $d->modify('+1 day')) {
    $k = $d->format('Y-m-d');
    $v = $daily[$k] ?? [0, 0, 0];
    $series[] = ['date' => $k, 'visits' => $v[0], 'cotizador' => $v[1], 'contacts' => $v[2]];
    if (count($series) > 3700) break;
}

// ── Actividad reciente: una fila por persona en el cotizador (su elección final),
//    igual criterio que los números de arriba ──
foreach ($sessions as [$start, $last, $type, $lastMeta, $n]) {
    $recent[] = ['type' => 'cotizador_use', 'metadata' => array_merge($lastMeta, ['type' => $type, 'ajustes' => $n]), 'epoch' => $last];
}
usort($recent, function ($a, $b) { return $b['epoch'] <=> $a['epoch']; });
$recentOut = [];
foreach (array_slice($recent, 0, 50) as $e) {
    $recentOut[] = [
        'type' => $e['type'],
        'metadata' => $e['metadata'] ?: new stdClass(),
        'created_at' => (new DateTime('@' . $e['epoch']))->setTimezone($tz)->format(DATE_ATOM),
    ];
}

$contacts = array_sum($waBySource);
$peakHour = null;
if ($visits > 0) {
    $max = max($hours);
    $peakHour = ['hour' => array_search($max, $hours, true), 'visits' => $max];
}

json_out([
    'range_days' => $days,
    'visits' => $visits,
    'cotizador_people' => $cotPeople,
    'contacts' => $contacts,
    'budget_requests' => $waBySource['cotizador'] ?? 0,
    'contacts_by_source' => $waBySource,
    'conversion' => $visits > 0 ? round($contacts / $visits * 100, 1) : 0,
    'cotizador_types' => $typeCount,
    'peak_hour' => $peakHour,
    'series' => $series,
    'recent' => $recentOut,
    // Compatibilidad con versiones anteriores del panel que puedan estar en caché del navegador.
    'counts' => [
        'page_view' => $visits,
        'cotizador_use' => $cotPeople,
        'showroom_click' => 0,
        'whatsapp_click' => $contacts,
    ],
    'events' => $recentOut,
]);
