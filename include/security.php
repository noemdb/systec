<?php
/**
 * Endurecimiento base: errores, logs y cabeceras de seguridad.
 * Debe incluirse antes de cualquier salida.
 */

require_once __DIR__ . '/config.php';

if (!is_dir(SYSTEC_STORAGE . '/logs')) {
    @mkdir(SYSTEC_STORAGE . '/logs', 0770, true);
}

$debug = getenv('SYSTEC_DEBUG') === '1';

ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', SYSTEC_STORAGE . '/logs/php-error.log');

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}
