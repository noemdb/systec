<?php
/**
 * Endurecimiento base: errores, logs con niveles/rotación (P2-07) y cabeceras de seguridad.
 * Debe incluirse antes de cualquier salida.
 */

require_once __DIR__ . '/config.php';

$GLOBALS['systecCspNonce'] = base64_encode(random_bytes(18));

function systec_csp_nonce(): string
{
    return $GLOBALS['systecCspNonce'];
}

/**
 * P2-07: Rota un archivo de log cuando alcanza $maxBytes, conservando hasta $maxArchives copias (.1 .. .N).
 */
function systec_rotate_log_file(
    string $logFile,
    int $maxBytes = 5242880,
    int $maxArchives = 5
): bool {
    if ($logFile === '' || $maxBytes < 1 || $maxArchives < 1) {
        return false;
    }

    clearstatcache(true, $logFile);
    if (!is_file($logFile)) {
        return false;
    }

    $size = @filesize($logFile);
    if ($size === false || $size < $maxBytes) {
        return false;
    }

    $oldestArchive = $logFile . '.' . $maxArchives;
    if (is_file($oldestArchive)) {
        @unlink($oldestArchive);
    }

    for ($i = $maxArchives - 1; $i >= 1; $i--) {
        $src = $logFile . '.' . $i;
        $dst = $logFile . '.' . ($i + 1);
        if (is_file($src)) {
            @rename($src, $dst);
        }
    }

    if (!@rename($logFile, $logFile . '.1')) {
        return false;
    }

    @touch($logFile);
    @chmod($logFile, 0640);

    return true;
}

/**
 * P2-07: Escribe una entrada estructurada en el log sin saltos de línea inyectables y aplica rotación.
 */
function systec_log(
    string $level,
    string $message,
    ?string $logFile = null,
    int $maxBytes = 5242880,
    int $maxArchives = 5
): void {
    $allowedLevels = array('INFO', 'WARNING', 'ERROR', 'SECURITY');
    $normalizedLevel = strtoupper(trim($level));
    if (!in_array($normalizedLevel, $allowedLevels, true)) {
        $normalizedLevel = 'INFO';
    }

    $targetFile = $logFile !== null && $logFile !== ''
        ? $logFile
        : SYSTEC_STORAGE . '/logs/php-error.log';

    $dir = dirname($targetFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }

    systec_rotate_log_file($targetFile, $maxBytes, $maxArchives);

    $sanitizedMessage = preg_replace('/[\r\n]+/', ' ', trim($message));
    $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $normalizedLevel, $sanitizedMessage);
    @file_put_contents($targetFile, $line, FILE_APPEND | LOCK_EX);
}

if (!is_dir(SYSTEC_STORAGE . '/logs')) {
    @mkdir(SYSTEC_STORAGE . '/logs', 0770, true);
}

$defaultErrorLog = SYSTEC_STORAGE . '/logs/php-error.log';
systec_rotate_log_file($defaultErrorLog);

$debug = getenv('SYSTEC_DEBUG') === '1';

ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', $defaultErrorLog);

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cache-Control: no-store, private');
    $nonce = systec_csp_nonce();
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . $nonce . "'; style-src 'self' 'nonce-" . $nonce . "'; style-src-attr 'unsafe-inline'; object-src 'none'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}
