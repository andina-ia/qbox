<?php
// config.php — contenido editable del sitio, guardado en MySQL.
// GET  → público. Devuelve { content, cotizador, clients, seo, media, _updated }.
// POST { section, value } → solo admin (sesión + X-CSRF-Token). Guarda al instante.

declare(strict_types=1);
require __DIR__ . '/_lib.php';

$pdo = db();

if (method() === 'GET') {
    $out = [];
    $updated = null;
    foreach ($pdo->query('SELECT section, value, updated_at FROM qbox_config') as $r) {
        $v = json_decode($r['value'], true);
        if ($v !== null) $out[$r['section']] = $v;
        if ($updated === null || $r['updated_at'] > $updated) $updated = $r['updated_at'];
    }
    $out['_updated'] = $updated;
    json_out($out);
}

if (method() !== 'POST') json_out(['error' => 'Método no permitido'], 405);

$admin = require_admin();
$body = json_body(524288);
$section = (string)($body['section'] ?? '');
if (!in_array($section, QBOX_SECTIONS, true)) {
    json_out(['error' => 'Sección inválida. Permitidas: ' . implode(', ', QBOX_SECTIONS)], 400);
}
if (!array_key_exists('value', $body)) json_out(['error' => 'Falta el campo value'], 400);

$value = validate_section($section, $body['value']);

$pdo->prepare(
    'INSERT INTO qbox_config (section, value, updated_by) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)'
)->execute([$section, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $admin['email']]);

json_out(['ok' => true, 'section' => $section]);

/* ───────── validación por sección ───────── */

function str_or_empty($v, int $max = 5000): string
{
    return is_scalar($v) ? cut(trim((string)$v), $max) : '';
}

function validate_section(string $section, $v)
{
    switch ($section) {
        case 'content':
            if (!is_array($v)) bad('content debe ser un objeto');
            $out = [];
            foreach ($v as $k => $val) {
                if (is_string($k) && preg_match('/^[a-zA-Z0-9_]{1,60}$/', $k)) $out[$k] = str_or_empty($val);
            }
            return $out;

        case 'cotizador':
            if (!is_array($v)) bad('cotizador debe ser un objeto');
            $out = [];
            foreach (['rateModulo', 'rateSemi'] as $k) {
                if (!isset($v[$k]) || !is_numeric($v[$k]) || $v[$k] <= 0 || $v[$k] > 100000) bad("$k debe ser un número positivo");
                $out[$k] = $v[$k] + 0;
            }
            return $out;

        case 'clients':
            if (!is_array($v) || array_values($v) !== $v) bad('clients debe ser una lista');
            $out = [];
            foreach (array_slice($v, 0, 200) as $c) {
                $name = is_array($c) ? str_or_empty($c['name'] ?? '', 120) : str_or_empty($c, 120);
                if ($name === '') continue;
                $out[] = ['name' => $name, 'active' => is_array($c) ? (bool)($c['active'] ?? true) : true];
            }
            return $out;

        case 'seo':
            if (!is_array($v)) bad('seo debe ser un objeto');
            $out = [];
            foreach ($v as $page => $d) {
                if (!is_string($page) || !preg_match('/^[a-z0-9-]{1,40}$/', $page) || !is_array($d)) continue;
                $out[$page] = [
                    'title' => str_or_empty($d['title'] ?? '', 200),
                    'desc' => str_or_empty($d['desc'] ?? '', 500),
                    'keywords' => str_or_empty($d['keywords'] ?? '', 1000),
                ];
            }
            return $out;

        case 'media':
            if (!is_array($v)) bad('media debe ser un objeto');
            $out = [];
            foreach ($v as $cat => $urls) {
                if (!in_array($cat, QBOX_MEDIA_CATS, true) || !is_array($urls)) continue;
                $clean = [];
                foreach ($urls as $u) {
                    $u = str_or_empty($u, 500);
                    // Solo rutas locales del sitio o URLs https.
                    if (preg_match('#^/(uploads|assets)/[A-Za-z0-9._/-]+$#', $u) || preg_match('#^https://[^\s"<>]+$#', $u)) $clean[] = $u;
                }
                $out[$cat] = $clean;
            }
            return $out;
    }
    bad('Sección desconocida');
}

function bad(string $msg): void
{
    json_out(['error' => $msg], 400);
}
