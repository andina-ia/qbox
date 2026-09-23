<?php
// upload.php — subida y borrado de imágenes (solo admin).
// POST multipart: file=<imagen>, category=<modulos|stands|...>
//   → { url: "/uploads/<cat>/<archivo>" }
// POST JSON { action:'delete', url:"/uploads/..." } → { ok }
// Las imágenes se guardan en public_html/uploads/, que NO se pisa al redeployar.

declare(strict_types=1);
require __DIR__ . '/_lib.php';

if (method() !== 'POST') json_out(['error' => 'Método no permitido'], 405);
require_admin();

$root = dirname(__DIR__) . '/uploads';
$ctype = (string)($_SERVER['CONTENT_TYPE'] ?? '');

// ── Borrado ──
if (stripos($ctype, 'application/json') === 0) {
    $body = json_body(4096);
    if (($body['action'] ?? '') !== 'delete') json_out(['error' => 'Acción inválida'], 400);
    $url = (string)($body['url'] ?? '');
    if (!preg_match('#^/uploads/([a-z-]+)/([A-Za-z0-9._-]+)$#', $url, $m) || !in_array($m[1], QBOX_MEDIA_CATS, true)) {
        json_out(['ok' => true, 'skipped' => true]); // no es un archivo nuestro: nada que borrar
    }
    $path = $root . '/' . $m[1] . '/' . $m[2];
    if (is_file($path)) @unlink($path);
    json_out(['ok' => true]);
}

// ── Subida ──
$cat = (string)($_POST['category'] ?? '');
if (!in_array($cat, QBOX_MEDIA_CATS, true)) json_out(['error' => 'Categoría inválida'], 400);

$f = $_FILES['file'] ?? null;
if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $code = is_array($f) ? (int)$f['error'] : UPLOAD_ERR_NO_FILE;
    $msg = in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ? 'La imagen supera el límite del servidor' : 'No se recibió la imagen';
    json_out(['error' => $msg], 400);
}
if ($f['size'] > 8 * 1024 * 1024) json_out(['error' => 'La imagen supera los 8 MB'], 400);

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
if (!isset($allowed[$mime]) || @getimagesize($f['tmp_name']) === false) {
    json_out(['error' => 'Formato no permitido (JPG, PNG, WEBP o GIF)'], 400);
}

$base = pathinfo((string)$f['name'], PATHINFO_FILENAME);
$base = strtolower(preg_replace('/[^A-Za-z0-9_-]+/', '-', $base) ?? '');
$base = trim(substr($base, 0, 50), '-') ?: 'imagen';
$name = date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '-' . $base . '.' . $allowed[$mime];

$dir = $root . '/' . $cat;
if (!is_dir($dir) && !mkdir($dir, 0755, true)) json_out(['error' => 'No se pudo crear la carpeta de destino'], 500);
if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) json_out(['error' => 'No se pudo guardar la imagen'], 500);
@chmod($dir . '/' . $name, 0644);

json_out(['url' => '/uploads/' . $cat . '/' . $name]);
