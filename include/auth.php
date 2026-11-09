<?php
/**
 * Autenticación, autorización y CSRF.
 *
 * Roles: admin, operador, tecnico, consulta.
 */

require_once __DIR__ . '/../conn.php';

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_name('systec_session');
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ));

    session_start();
}

function auth_user()
{
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function auth_check(): bool
{
    return auth_user() !== null;
}

function auth_has_role(array $roles): bool
{
    $user = auth_user();

    return $user !== null && in_array($user['role'], $roles, true);
}

function auth_login(string $username, string $password): bool
{
    $username = trim($username);

    if ($username === '' || $password === '') {
        return false;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE username = :username AND active = 1 LIMIT 1');

    if (!$stmt) {
        return false;
    }

    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update = db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $update->bindValue(':hash', $newHash, SQLITE3_TEXT);
        $update->bindValue(':id', (int) $user['id'], SQLITE3_INTEGER);
        $update->execute();
    }

    session_regenerate_id(true);
    $_SESSION['user'] = array(
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
    );
    $_SESSION['csrf'] = bin2hex(random_bytes(32));

    return true;
}

function auth_logout()
{
    $_SESSION = array();

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function require_login()
{
    if (auth_check()) {
        return;
    }

    $_SESSION['intended'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    header('Location: ' . url('login.php'));
    exit();
}

function require_role(array $roles)
{
    require_login();

    if (!auth_has_role($roles)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><link rel="icon" type="image/svg+xml" href="' . e(url('favicon.svg')) . '"><title>Acceso denegado</title></head><body>';
        echo '<h1>Acceso denegado</h1><p>Tu rol no tiene permiso para esta acción.</p>';
        echo '<p><a href="' . e(url('dashboard.php')) . '">Volver al dashboard</a></p></body></html>';
        exit();
    }
}

function auth_intended_url(): string
{
    $next = isset($_SESSION['intended']) ? $_SESSION['intended'] : '';
    unset($_SESSION['intended']);

    if (is_string($next) && $next !== '' && $next[0] === '/' && substr($next, 0, 2) !== '//' && strpos($next, "\n") === false) {
        return $next;
    }

    return url('dashboard.php');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf()
{
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Método no permitido');
    }

    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $expected = isset($_SESSION['csrf']) ? (string) $_SESSION['csrf'] : '';

    if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
        http_response_code(419);
        exit('Token CSRF inválido o expirado. Recarga la página e inténtalo de nuevo.');
    }
}
