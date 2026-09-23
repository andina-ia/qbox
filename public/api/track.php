<?php
// track.php — registra eventos de analytics (público).
// POST { type, metadata } → 204

declare(strict_types=1);
require __DIR__ . '/_lib.php';

if (method() !== 'POST') json_out(['error' => 'Método no permitido'], 405);

$body = json_body(4096);
$type = (string)($body['type'] ?? '');
$allowed = ['page_view', 'cotizador_use', 'showroom_click', 'whatsapp_click'];
if (!in_array($type, $allowed, true)) json_out(['error' => 'Tipo inválido'], 400);

$meta = $body['metadata'] ?? [];
if (!is_array($meta)) $meta = [];
$meta = array_slice($meta, 0, 10, true);
foreach ($meta as $k => $v) {
    $meta[$k] = is_scalar($v) ? cut((string)$v, 300) : null;
}

db()->prepare('INSERT INTO qbox_events (type, metadata, created_at) VALUES (?, ?, NOW())')
    ->execute([$type, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);

http_response_code(204);
exit;
