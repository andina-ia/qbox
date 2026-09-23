<?php
// auth.php — login del panel.
// GET                         → { authed, email?, csrf? }
// POST { action:'login', email, pass } → { ok, email, csrf }
// POST { action:'logout' }    → { ok }
// POST { action:'password', current, next } (requiere sesión) → { ok }

declare(strict_types=1);
require __DIR__ . '/_lib.php';

start_session();

if (method() === 'GET') {
    $a = current_admin();
    json_out($a ? ['authed' => true, 'email' => $a['email'], 'csrf' => csrf_token()] : ['authed' => false]);
}

if (method() !== 'POST') json_out(['error' => 'Método no permitido'], 405);

$body = json_body(8192);
$action = (string)($body['action'] ?? '');
$pdo = db();

if ($action === 'login') {
    $ip = client_ip();
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $pass = (string)($body['pass'] ?? '');

    // Límite: 8 intentos fallidos por IP cada 15 minutos.
    $q = $pdo->prepare('SELECT COUNT(*) FROM qbox_login_attempts WHERE ip = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)');
    $q->execute([$ip]);
    if ((int)$q->fetchColumn() >= 8) {
        json_out(['error' => 'Demasiados intentos. Esperá 15 minutos.'], 429);
    }

    $st = $pdo->prepare('SELECT id, email, password_hash FROM qbox_admins WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $row = $st->fetch();

    if (!$row || !password_verify($pass, $row['password_hash'])) {
        $pdo->prepare('INSERT INTO qbox_login_attempts (ip, email, created_at) VALUES (?, ?, NOW())')
            ->execute([$ip, substr($email, 0, 190)]);
        usleep(400000); // frena fuerza bruta
        json_out(['error' => 'Correo o clave incorrectos.'], 401);
    }

    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE qbox_admins SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($pass, PASSWORD_DEFAULT), $row['id']]);
    }
    $pdo->prepare('UPDATE qbox_admins SET last_login_at = NOW() WHERE id = ?')->execute([$row['id']]);
    $pdo->prepare('DELETE FROM qbox_login_attempts WHERE ip = ?')->execute([$ip]);

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$row['id'];
    $_SESSION['admin_email'] = $row['email'];
    unset($_SESSION['csrf']);
    json_out(['ok' => true, 'email' => $row['email'], 'csrf' => csrf_token()]);
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    json_out(['ok' => true]);
}

if ($action === 'password') {
    $admin = require_admin();
    $current = (string)($body['current'] ?? '');
    $next = (string)($body['next'] ?? '');
    if (strlen($next) < 10) json_out(['error' => 'La nueva clave debe tener al menos 10 caracteres.'], 400);
    $st = $pdo->prepare('SELECT password_hash FROM qbox_admins WHERE id = ?');
    $st->execute([$admin['id']]);
    $hash = (string)$st->fetchColumn();
    if (!password_verify($current, $hash)) json_out(['error' => 'La clave actual no es correcta.'], 400);
    $pdo->prepare('UPDATE qbox_admins SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($next, PASSWORD_DEFAULT), $admin['id']]);
    json_out(['ok' => true]);
}

json_out(['error' => 'Acción inválida'], 400);
