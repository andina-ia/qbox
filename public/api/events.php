<?php
// events.php — lectura de analytics para el dashboard (solo admin).
// GET ?days=7 (0 = todo) → [{ type, metadata, created_at }] (máx. 1000, más recientes primero)
// + resumen de totales por tipo en el período (header-independiente): ?summary=1

declare(strict_types=1);
require __DIR__ . '/_lib.php';

if (method() !== 'GET') json_out(['error' => 'Método no permitido'], 405);
if (!current_admin()) json_out(['error' => 'Sesión expirada. Volvé a ingresar.'], 401);

$days = max(0, min(3650, (int)($_GET['days'] ?? 7)));
$where = $days > 0 ? 'WHERE created_at >= (NOW() - INTERVAL ' . $days . ' DAY)' : '';
$pdo = db();

$counts = [];
foreach ($pdo->query("SELECT type, COUNT(*) n FROM qbox_events $where GROUP BY type") as $r) {
    $counts[$r['type']] = (int)$r['n'];
}

$rows = $pdo->query("SELECT type, metadata, created_at FROM qbox_events $where ORDER BY created_at DESC, id DESC LIMIT 1000")->fetchAll();
$tz = new DateTimeZone(date_default_timezone_get());
foreach ($rows as &$r) {
    $r['metadata'] = json_decode((string)$r['metadata'], true) ?: new stdClass();
    $r['created_at'] = (new DateTime($r['created_at'], $tz))->format(DATE_ATOM);
}
json_out(['counts' => $counts, 'events' => $rows]);
