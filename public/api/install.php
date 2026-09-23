<?php
// install.php — instalación única: crea las tablas, carga la config inicial
// (desde seed.json, generado en el build a partir de src/data/config.json)
// y crea el primer usuario admin. Se bloquea sola si ya hay un admin creado.
// Después de usarla, BORRALA del servidor.

declare(strict_types=1);
require __DIR__ . '/_lib.php';

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');

function page(string $body): void
{
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Instalación qBox</title><style>body{font-family:system-ui,sans-serif;background:#F3EFE8;color:#1E2022;display:flex;justify-content:center;padding:48px 16px}'
        . '.b{background:#fff;border-radius:16px;padding:32px;max-width:420px;width:100%;box-shadow:0 8px 32px rgba(0,0,0,.08)}h1{font-size:20px;margin:0 0 16px}'
        . 'label{display:block;font-size:13px;font-weight:600;margin:14px 0 6px}input{width:100%;box-sizing:border-box;padding:10px 12px;border:1.5px solid #ddd;border-radius:10px;font-size:15px}'
        . 'button{margin-top:20px;width:100%;padding:12px;border:0;border-radius:10px;background:#1E2022;color:#fff;font-size:15px;font-weight:600;cursor:pointer}'
        . '.err{color:#B4452F}.ok{color:#2E7D32}p{line-height:1.5;font-size:14px}</style></head><body><div class="b">' . $body . '</div></body></html>';
    exit;
}

$pdo = db();

// ¿Ya instalado?
if (tables_exist() && (int)$pdo->query('SELECT COUNT(*) FROM qbox_admins')->fetchColumn() > 0) {
    page('<h1>Ya está instalado</h1><p>Existe al menos un usuario admin. Por seguridad esta página está bloqueada.</p>'
        . '<p><strong>Borrá <code>api/install.php</code> del servidor.</strong></p><p><a href="/admin">Ir al panel →</a></p>');
}

$error = '';
if (method() === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass  = (string)($_POST['pass'] ?? '');
    $pass2 = (string)($_POST['pass2'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Correo inválido.';
    elseif (strlen($pass) < 10) $error = 'La clave debe tener al menos 10 caracteres.';
    elseif ($pass !== $pass2) $error = 'Las claves no coinciden.';

    if (!$error) {
        // 1) Tablas
        $sql = file_get_contents(__DIR__ . '/_schema.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', (string)$sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            $pdo->exec($stmt);
        }

        // 2) Config inicial (solo secciones que todavía no existen)
        $seeded = 0;
        $seedFile = __DIR__ . '/seed.json';
        if (is_file($seedFile)) {
            $seed = json_decode((string)file_get_contents($seedFile), true);
            if (is_array($seed)) {
                $ins = $pdo->prepare('INSERT IGNORE INTO qbox_config (section, value, updated_by) VALUES (?, ?, ?)');
                foreach (QBOX_SECTIONS as $s) {
                    if (array_key_exists($s, $seed)) {
                        $ins->execute([$s, json_encode($seed[$s], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'install']);
                        $seeded += $ins->rowCount();
                    }
                }
            }
        }

        // 3) Admin
        $pdo->prepare('INSERT INTO qbox_admins (email, password_hash) VALUES (?, ?)')
            ->execute([$email, password_hash($pass, PASSWORD_DEFAULT)]);

        page('<h1 class="ok">✓ Instalación completa</h1>'
            . '<p>Tablas creadas, ' . $seeded . ' secciones de contenido cargadas y usuario <strong>' . htmlspecialchars($email) . '</strong> creado.</p>'
            . '<p class="err"><strong>Ahora borrá <code>api/install.php</code> del servidor</strong> (Administrador de archivos de cPanel).</p>'
            . '<p><a href="/admin">Ir al panel →</a></p>');
    }
}

page('<h1>Instalación del panel qBox</h1>'
    . '<p>La conexión a la base de datos funciona. Creá el usuario administrador:</p>'
    . ($error ? '<p class="err">' . htmlspecialchars($error) . '</p>' : '')
    . '<form method="post"><label>Correo</label><input type="email" name="email" required value="' . htmlspecialchars((string)($_POST['email'] ?? '')) . '">'
    . '<label>Clave (mín. 10 caracteres)</label><input type="password" name="pass" required minlength="10">'
    . '<label>Repetir clave</label><input type="password" name="pass2" required minlength="10">'
    . '<button type="submit">Instalar</button></form>');
