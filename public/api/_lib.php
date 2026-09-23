<?php
// _lib.php — utilidades compartidas del backend PHP de qBox (cPanel + MySQL).
// No se accede directo: .htaccess bloquea los archivos que empiezan con "_".
// Compatible con PHP 7.4+.

declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

const QBOX_SECTIONS = ['content', 'cotizador', 'clients', 'seo', 'media'];
const QBOX_MEDIA_CATS = ['modulos', 'stands', 'cocheras-galerias', 'ampliaciones', 'locales'];

/**
 * Carga las credenciales. Busca primero FUERA de public_html (recomendado):
 *   /home/USUARIO/qbox-env.php
 * y si no existe, api/_env.php (también protegido por .htaccess).
 */
function qbox_env(): array
{
    static $env = null;
    if ($env !== null) return $env;
    $candidates = [
        dirname(__DIR__, 2) . '/qbox-env.php',
        __DIR__ . '/_env.php',
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            $env = require $file;
            if (is_array($env)) return $env;
        }
    }
    json_out(['error' => 'Falta el archivo de configuración (qbox-env.php). Ver README-CPANEL.'], 500);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $e = qbox_env();
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $e['DB_HOST'] ?? 'localhost', (int)($e['DB_PORT'] ?? 3306), $e['DB_NAME'] ?? '');
    try {
        $pdo = new PDO($dsn, $e['DB_USER'] ?? '', $e['DB_PASS'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $ex) {
        error_log('qbox db: ' . $ex->getMessage());
        json_out(['error' => 'No se pudo conectar a la base de datos'], 500);
    }
    return $pdo;
}

function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function json_body(int $maxBytes = 262144): array
{
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($raw === false || $raw === '') return [];
    if (strlen($raw) > $maxBytes) json_out(['error' => 'Solicitud demasiado grande'], 413);
    $data = json_decode($raw, true);
    if (!is_array($data)) json_out(['error' => 'JSON inválido'], 400);
    return $data;
}

/** Recorta texto UTF-8 sin depender de la extensión mbstring. */
function cut(string $s, int $max): string
{
    if (function_exists('mb_substr')) return mb_substr($s, 0, $max, 'UTF-8');
    if (preg_match('/^.{0,' . $max . '}/us', $s, $m)) return $m[0];
    return substr($s, 0, $max);
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/* ───────────── Sesión / auth ───────────── */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('qbox_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    // Expira tras 8 h de inactividad.
    $now = time();
    if (isset($_SESSION['last']) && $now - $_SESSION['last'] > 8 * 3600) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last'] = $now;
}

function current_admin(): ?array
{
    start_session();
    return isset($_SESSION['admin_id'])
        ? ['id' => $_SESSION['admin_id'], 'email' => $_SESSION['admin_email']]
        : null;
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

/** Exige sesión de admin válida + token CSRF en el header X-CSRF-Token. */
function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) json_out(['error' => 'Sesión expirada. Volvé a ingresar.'], 401);
    $sent = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrf_token(), $sent)) json_out(['error' => 'Token inválido. Recargá la página.'], 403);
    return $admin;
}

function tables_exist(): bool
{
    try {
        db()->query('SELECT 1 FROM qbox_admins LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
