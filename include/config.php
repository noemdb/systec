<?php
/**
 * Configuración base de SysTec GMB.
 *
 * Los datos viven fuera del árbol público (storage/). La ruta puede
 * sobreescribirse con la variable de entorno SYSTEC_STORAGE.
 */

if (!defined('SYSTEC_ROOT')) {
    define('SYSTEC_ROOT', dirname(__DIR__));
}

if (!function_exists('systec_load_env_file')) {
    function systec_load_env_file(string $filename): void
    {
        if (!is_file($filename) || !is_readable($filename)) {
            return;
        }

        $allowedNames = array(
            'SYSTEC_STORAGE',
            'SYSTEC_DB',
            'SYSTEC_DEBUG',
            'SYSTEC_LEGACY_PG_HOST',
            'SYSTEC_LEGACY_PG_PORT',
            'SYSTEC_LEGACY_PG_DATABASE',
            'SYSTEC_LEGACY_PG_USER',
            'SYSTEC_LEGACY_PG_PASSWORD',
            'API_KEY_NVIDIA_BUILD',
        );

        $lines = file($filename, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (!in_array($name, $allowedNames, true) || getenv($name) !== false) {
                continue;
            }

            if (strlen($value) >= 2
                && (($value[0] === '"' && substr($value, -1) === '"')
                    || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

systec_load_env_file(SYSTEC_ROOT . DIRECTORY_SEPARATOR . '.env');

if (!defined('SYSTEC_STORAGE')) {
    $storage = getenv('SYSTEC_STORAGE');
    define('SYSTEC_STORAGE', $storage ? rtrim($storage, DIRECTORY_SEPARATOR) : SYSTEC_ROOT . DIRECTORY_SEPARATOR . 'storage');
}

if (!defined('SYSTEC_DB')) {
    $dbPath = getenv('SYSTEC_DB');
    define('SYSTEC_DB', $dbPath ?: SYSTEC_STORAGE . DIRECTORY_SEPARATOR . 'db.db');
}

if (!defined('SYSTEC_BASE_PATH')) {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(SYSTEC_ROOT);
    $basePath = '';

    if ($docRoot && $appRoot && strpos($appRoot, $docRoot) === 0) {
        $basePath = substr($appRoot, strlen($docRoot));
    }

    define('SYSTEC_BASE_PATH', rtrim(str_replace('\\', '/', $basePath), '/'));
}

/** Construye una URL absoluta a partir de la raíz de la aplicación. */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return ($path === '') ? (SYSTEC_BASE_PATH ?: '/') : SYSTEC_BASE_PATH . '/' . $path;
}
