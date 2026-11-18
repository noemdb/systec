<?php
/**
 * Autenticación, autorización y CSRF.
 *
 * Roles: admin, operador, tecnico, consulta, personal.
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

function auth_can_manage_all_tickets(): bool
{
    return auth_has_role(array('admin', 'operador', 'tecnico'));
}

function auth_can_access_ticket_for_user(int $ticketCreatedBy): bool
{
    $user = auth_user();
    if ($user === null) {
        return false;
    }

    if (auth_can_manage_all_tickets()) {
        return true;
    }

    return auth_has_role(array('personal')) && (int) $user['id'] === $ticketCreatedBy;
}

function auth_is_personal_area(): bool
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    return strpos($path, '/personal/') !== false || $path === '/personal' || $path === '/personal/index.php';
}

function require_personal_scope(): void
{
    require_login();

    $user = auth_user();
    if (!is_array($user) || ($user['role'] ?? '') !== 'personal') {
        header('Location: ' . url('login.php'));
        exit();
    }

    if (!auth_is_personal_area()) {
        header('Location: ' . url('personal/index.php'));
        exit();
    }
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

    $user = auth_user();
    if (is_array($user) && isset($user['role']) && $user['role'] === 'personal') {
        return url('personal/index.php');
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

function auth_throttle_directory(string $override = ''): string
{
    if ($override !== '') {
        if (!is_dir($override)) {
            @mkdir($override, 0750, true);
        }
        return $override;
    }
    $dir = SYSTEC_STORAGE . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'throttles';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir;
}

function auth_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return '127.0.0.1';
    }
    return $ip;
}

/**
 * Comprueba si un usuario o IP está bloqueado por exceso de intentos fallidos.
 *
 * @param string $username Nombre de usuario a comprobar.
 * @param string $ip Dirección IP (por defecto la del cliente actual).
 * @param int|null $now Timestamp actual (útil para pruebas).
 * @param string $storageDir Directorio de almacenamiento de throttles (opcional).
 * @return array Estado del bloqueo: ['blocked' => bool, 'retry_after' => int, 'retry_minutes' => int, 'reason' => string, 'remaining' => int]
 */
function auth_throttle_check(string $username, string $ip = '', ?int $now = null, string $storageDir = ''): array
{
    $ip = $ip !== '' ? $ip : auth_client_ip();
    $username = mb_strtolower(trim($username));
    $now = $now !== null ? $now : time();
    $windowSeconds = 900; // 15 minutos
    $maxUserAttempts = 5;
    $maxIpAttempts = 15;

    $userKey = 'user_' . hash('sha256', $username);
    $ipKey = 'ip_' . hash('sha256', $ip);
    $dir = auth_throttle_directory($storageDir);

    if ($username !== '') {
        $checks = array(
            array($userKey, $maxUserAttempts, 'cuenta de usuario'),
            array($ipKey, $maxIpAttempts, 'dirección IP'),
        );
    } else {
        $checks = array(
            array($ipKey, $maxIpAttempts, 'dirección IP'),
        );
    }

    foreach ($checks as $check) {
        list($key, $max, $type) = $check;
        $file = $dir . DIRECTORY_SEPARATOR . $key . '.json';
        if (!is_file($file)) {
            continue;
        }

        $content = @file_get_contents($file);
        $data = $content ? json_decode($content, true) : null;
        if (!is_array($data) || empty($data['attempts'])) {
            continue;
        }

        $validAttempts = array_values(array_filter((array) $data['attempts'], function ($ts) use ($now, $windowSeconds) {
            return is_numeric($ts) && ($now - (int) $ts) < $windowSeconds;
        }));

        if (count($validAttempts) >= $max) {
            $oldestInWindow = min($validAttempts);
            $retryAfter = max(1, $windowSeconds - ($now - $oldestInWindow));
            return array(
                'blocked' => true,
                'retry_after' => $retryAfter,
                'retry_minutes' => (int) ceil($retryAfter / 60),
                'reason' => $type,
                'remaining' => 0,
            );
        }
    }

    $userRemaining = $maxUserAttempts;
    if ($username !== '') {
        $userFile = $dir . DIRECTORY_SEPARATOR . $userKey . '.json';
        if (is_file($userFile)) {
            $content = @file_get_contents($userFile);
            $data = $content ? json_decode($content, true) : null;
            if (is_array($data) && !empty($data['attempts'])) {
                $validAttempts = array_filter((array) $data['attempts'], function ($ts) use ($now, $windowSeconds) {
                    return is_numeric($ts) && ($now - (int) $ts) < $windowSeconds;
                });
                $userRemaining = max(0, $maxUserAttempts - count($validAttempts));
            }
        }
    }

    return array(
        'blocked' => false,
        'retry_after' => 0,
        'retry_minutes' => 0,
        'reason' => '',
        'remaining' => $userRemaining,
    );
}

/**
 * Registra un intento fallido de inicio de sesión para el usuario e IP.
 */
function auth_throttle_record_failure(string $username, string $ip = '', ?int $now = null, string $storageDir = ''): void
{
    $ip = $ip !== '' ? $ip : auth_client_ip();
    $username = mb_strtolower(trim($username));
    $now = $now !== null ? $now : time();
    $windowSeconds = 900;

    $dir = auth_throttle_directory($storageDir);
    $keys = array('ip_' . hash('sha256', $ip));
    if ($username !== '') {
        $keys[] = 'user_' . hash('sha256', $username);
    }

    foreach ($keys as $key) {
        $file = $dir . DIRECTORY_SEPARATOR . $key . '.json';
        $attempts = array();
        if (is_file($file)) {
            $content = @file_get_contents($file);
            $data = $content ? json_decode($content, true) : null;
            if (is_array($data) && !empty($data['attempts'])) {
                $attempts = array_filter((array) $data['attempts'], function ($ts) use ($now, $windowSeconds) {
                    return is_numeric($ts) && ($now - (int) $ts) < $windowSeconds;
                });
            }
        }
        $attempts[] = $now;
        $encoded = json_encode(array('attempts' => array_values($attempts)));
        $temp = tempnam($dir, 'throttle_');
        if ($temp !== false && file_put_contents($temp, $encoded, LOCK_EX) !== false) {
            rename($temp, $file);
        } elseif ($temp !== false && is_file($temp)) {
            @unlink($temp);
        }
    }
}

/**
 * Limpia los intentos fallidos tras un inicio de sesión exitoso.
 */
function auth_throttle_clear(string $username, string $ip = '', string $storageDir = ''): void
{
    $username = mb_strtolower(trim($username));
    if ($username === '') {
        return;
    }

    $dir = auth_throttle_directory($storageDir);
    $userFile = $dir . DIRECTORY_SEPARATOR . 'user_' . hash('sha256', $username) . '.json';
    if (is_file($userFile)) {
        @unlink($userFile);
    }
}

/**
 * OPS-02: Cambia la contraseña del propio usuario autenticado tras verificar la actual.
 */
function auth_change_password(
    int $userId,
    string $currentPassword,
    string $newPassword,
    string $confirmPassword,
    ?DB $database = null
): array {
    $dbInstance = $database ?: db();
    if ($userId < 1) {
        return array('ok' => false, 'message' => 'Usuario inválido.');
    }
    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        return array('ok' => false, 'message' => 'Completa todos los campos de contraseña.');
    }
    if (strlen($newPassword) < 6) {
        return array('ok' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres.');
    }
    if ($newPassword !== $confirmPassword) {
        return array('ok' => false, 'message' => 'La confirmación de la nueva contraseña no coincide.');
    }

    $user = $dbInstance->getFirstForId('users', $userId);
    if (!$user || (int) ($user['active'] ?? 0) !== 1) {
        return array('ok' => false, 'message' => 'La cuenta de usuario no está disponible.');
    }
    if (!password_verify($currentPassword, (string) $user['password_hash'])) {
        return array('ok' => false, 'message' => 'La contraseña actual no es correcta.');
    }
    if ($currentPassword === $newPassword) {
        return array('ok' => false, 'message' => 'La nueva contraseña debe ser diferente a la actual.');
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $dbInstance->update('users', $userId, array('password_hash' => $newHash));

    return array('ok' => true, 'message' => 'Tu contraseña se actualizó correctamente.');
}

/**
 * OPS-02: Alta o edición administrativa de cuentas de usuario.
 */
function auth_admin_save_user(array $input, int $actorUserId, ?DB $database = null): array
{
    $dbInstance = $database ?: db();
    $userId = isset($input['user_id']) && $input['user_id'] !== ''
        ? filter_var($input['user_id'], FILTER_VALIDATE_INT)
        : null;
    if ($userId === false) {
        return array('ok' => false, 'message' => 'Identificador de usuario inválido.');
    }

    $username = isset($input['username']) ? trim((string) $input['username']) : '';
    $role = isset($input['role']) ? trim((string) $input['role']) : '';
    $password = isset($input['password']) ? (string) $input['password'] : '';
    $active = isset($input['active']) && (string) $input['active'] === '0' ? 0 : 1;
    $roles = $dbInstance->getListRoles();

    if (!array_key_exists($role, $roles)) {
        return array('ok' => false, 'message' => 'Selecciona un rol válido.');
    }

    if ($userId === null) {
        if ($username === '' || !preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $username)) {
            return array(
                'ok' => false,
                'message' => 'El usuario debe tener entre 3 y 64 caracteres alfanuméricos, punto, guion o guion bajo.',
            );
        }
        if (strlen($password) < 6) {
            return array('ok' => false, 'message' => 'La contraseña inicial debe tener al menos 6 caracteres.');
        }
        $existing = $dbInstance->getFirstForConditions('users', array('username' => $username));
        if ($existing) {
            return array('ok' => false, 'message' => 'Ya existe un usuario con ese nombre.');
        }

        $newId = $dbInstance->create('users', array(
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'active' => $active,
        ));

        return array(
            'ok' => true,
            'id' => (int) $newId,
            'message' => 'Usuario «' . $username . '» creado correctamente.',
        );
    }

    $existing = $dbInstance->getFirstForId('users', $userId);
    if (!$existing) {
        return array('ok' => false, 'message' => 'El usuario seleccionado no existe.');
    }

    if ((int) $existing['id'] === $actorUserId) {
        if ($active !== 1) {
            return array('ok' => false, 'message' => 'No puedes desactivar tu propia cuenta en sesión.');
        }
        if ($role !== 'admin') {
            return array('ok' => false, 'message' => 'No puedes retirar el rol de administrador de tu propia cuenta.');
        }
    }

    $updates = array(
        'role' => $role,
        'active' => $active,
    );
    if ($password !== '') {
        if (strlen($password) < 6) {
            return array('ok' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres.');
        }
        $updates['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $dbInstance->update('users', (int) $existing['id'], $updates);

    return array(
        'ok' => true,
        'id' => (int) $existing['id'],
        'message' => 'Usuario «' . $existing['username'] . '» actualizado correctamente.',
    );
}
