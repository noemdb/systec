<?php

if (!defined('SYSTEC_IMPORT_MAX_ROWS')) {
    define('SYSTEC_IMPORT_MAX_ROWS', 5000);
}

if (!defined('SYSTEC_IMPORT_MAX_BYTES')) {
    define('SYSTEC_IMPORT_MAX_BYTES', 5242880);
}

class CsvImportException extends RuntimeException
{
}

function execute_csv_import($db, string $filename, string $delimiter, callable $processor): string
{
    if (!is_file($filename) || !is_readable($filename)) {
        throw new CsvImportException('No se pudo leer el archivo de importación.');
    }

    $handle = fopen($filename, 'r');
    if (!$handle) {
        throw new CsvImportException('No se pudo abrir el archivo de importación.');
    }

    try {
        $stat = fstat($handle);
        if (!$stat || $stat['size'] > SYSTEC_IMPORT_MAX_BYTES) {
            throw new CsvImportException('El archivo supera el límite de 5 MB.');
        }

        $rows = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === array(null) || count(array_filter($row, function ($value) {
                return trim((string) $value) !== '';
            })) === 0) {
                continue;
            }

            $rows++;
            if ($rows > SYSTEC_IMPORT_MAX_ROWS) {
                throw new CsvImportException('El archivo supera el límite de 5000 filas.');
            }
        }

        if (rewind($handle) === false) {
            throw new RuntimeException('No se pudo volver al inicio del archivo de importación.');
        }

        return execute_import_transaction($db, function () use ($processor, $handle) {
            $processor($handle);
        });
    } finally {
        fclose($handle);
    }
}

function execute_inventory_rows_import($db, array $rows, callable $processor): string
{
    if (count($rows) > SYSTEC_IMPORT_MAX_ROWS) {
        throw new CsvImportException('El origen supera el límite de 5000 bienes.');
    }

    return execute_import_transaction($db, function () use ($processor, $rows) {
        $processor($rows);
    });
}

function execute_import_transaction($db, callable $processor): string
{
    if (!$db->exec('BEGIN IMMEDIATE')) {
        throw new RuntimeException('No se pudo iniciar la transacción de importación: ' . $db->lastErrorMsg());
    }

    $outputLevel = ob_get_level();
    ob_start();

    try {
        $processor();
        $report = ob_get_clean();

        if (!$db->exec('COMMIT')) {
            throw new RuntimeException('No se pudo confirmar la transacción de importación: ' . $db->lastErrorMsg());
        }

        return $report;
    } catch (Throwable $e) {
        while (ob_get_level() > $outputLevel) {
            ob_end_clean();
        }
        $db->exec('ROLLBACK');
        throw $e;
    }
}
