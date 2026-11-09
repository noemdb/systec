<?php
/**
 * Router para el servidor embebido de PHP (make dev).
 * Deniega el acceso directo a datos, documentación y código interno.
 */

$path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
$path = rawurldecode((string) $path);
$path = '/' . ltrim($path, '/');

$blockedPrefixes = array('/storage', '/bck', '/docs', '/grafo', '/spec', '/bin', '/include', '/.git');
$blockedFiles = array('/router.php', '/conn.php');

foreach ($blockedPrefixes as $prefix) {
    if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Acceso no permitido';
        return true;
    }
}

foreach ($blockedFiles as $file) {
    if ($path === $file) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Acceso no permitido';
        return true;
    }
}

if (preg_match('/\.(db|sqlite|sqlite3|csv|log|md|env)$/i', $path)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Acceso no permitido';
    return true;
}

$file = __DIR__ . $path;

if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}

if (is_file($file)) {
    return false;
}

if (is_file($file . '.php')) {
    require $file . '.php';
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo 'No encontrado';
return true;
