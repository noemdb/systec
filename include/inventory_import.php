<?php

require_once __DIR__ . '/csv_import.php';

function execute_inventory_import($db, string $filename, string $extension, callable $processor): string
{
    $extension = strtolower($extension);

    if ($extension === 'csv') {
        return execute_csv_import($db, $filename, ';', function ($handle) use ($processor) {
            $rows = (function () use ($handle) {
                while (($row = fgetcsv($handle, 0, ';')) !== false) {
                    if ($row !== array(null)) {
                        yield $row;
                    }
                }
            })();

            $processor($rows);
        });
    }

    if ($extension !== 'xls') {
        throw new CsvImportException('Formato no admitido. Selecciona un archivo CSV o el reporte XLS del sistema.');
    }

    if (!is_file($filename) || !is_readable($filename)) {
        throw new CsvImportException('No se pudo leer el reporte XLS.');
    }

    $size = filesize($filename);
    if ($size === false || $size > SYSTEC_IMPORT_MAX_BYTES) {
        throw new CsvImportException('El archivo supera el límite de 5 MB.');
    }

    $rows = inventory_report_rows($filename);
    if (!$rows) {
        throw new CsvImportException('El reporte no contiene bienes para importar.');
    }

    return execute_inventory_rows_import($db, $rows, $processor);
}

function execute_legacy_postgres_inventory_import($db, callable $processor): string
{
    $rows = legacy_postgres_inventory_rows();
    // La lectura PostgreSQL termina antes de iniciar la escritura en la SQLite local.
    return execute_inventory_rows_import($db, $rows, $processor);
}

function legacy_postgres_inventory_rows(): array
{
    $host = inventory_environment_value('SYSTEC_LEGACY_PG_HOST', 'localhost');
    $port = inventory_environment_value('SYSTEC_LEGACY_PG_PORT', '5432');
    $database = inventory_environment_value('SYSTEC_LEGACY_PG_DATABASE', 'DATOS');
    $username = inventory_environment_value('SYSTEC_LEGACY_PG_USER');
    $password = getenv('SYSTEC_LEGACY_PG_PASSWORD');

    if ($username === '' || $password === false || $password === '') {
        throw new CsvImportException('La conexión de lectura del inventario no está configurada en el servidor.');
    }

    if (!preg_match('/^[A-Za-z0-9._:-]+$/', $host)
        || !preg_match('/^[0-9]{1,5}$/', $port)
        || (int) $port < 1
        || (int) $port > 65535
        || !preg_match('/^[A-Za-z0-9_.-]+$/', $database)
        || !preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
        throw new CsvImportException('La configuración de conexión del inventario no es válida.');
    }

    $pdo = null;
    try {
        $pdo = new PDO(
            'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $database,
            $username,
            $password,
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            )
        );
        $pdo->beginTransaction();
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
        $pdo->exec("SET LOCAL statement_timeout = '8000ms'");
        if ($pdo->query('SHOW transaction_read_only')->fetchColumn() !== 'on') {
            throw new CsvImportException('PostgreSQL no confirmó el modo de solo lectura; se canceló la importación.');
        }

        $companyCodes = $pdo->query('SELECT campo001 FROM public.sia000 ORDER BY campo001')->fetchAll(PDO::FETCH_COLUMN);
        if (count($companyCodes) !== 1 || (string) $companyCodes[0] !== '38') {
            throw new CsvImportException('El formato de códigos de la empresa de origen no coincide con el validado.');
        }

        $statement = $pdo->query(
            "SELECT
                b.cod_bien_mue,
                substr(b.cod_clasificacion, 1, 1) AS grupo,
                substr(b.cod_clasificacion, 3, 4) AS subgrupo,
                substr(b.cod_clasificacion, 8, 3) AS seccion,
                b.num_bien AS ident,
                b.denominacion,
                b.caracteristicas,
                b.marca,
                b.modelo,
                b.serial1,
                b.material,
                b.color,
                e.edo_bien AS estado
            FROM public.bien015 b
            LEFT JOIN public.bien004 e ON e.codigo = b.edo_conservacion
            WHERE b.desincorporado = 'N'
            ORDER BY b.cod_bien_mue
            LIMIT " . (SYSTEC_IMPORT_MAX_ROWS + 1)
        );
        $sourceRows = $statement->fetchAll();

        if (count($sourceRows) > SYSTEC_IMPORT_MAX_ROWS) {
            throw new CsvImportException('La fuente PostgreSQL supera el límite de 5000 bienes.');
        }
        if (!$sourceRows) {
            throw new CsvImportException('La fuente PostgreSQL no contiene bienes no desincorporados para importar.');
        }

        $rows = array();
        $seenIdentifiers = array();
        foreach ($sourceRows as $sourceRow) {
            $group = trim((string) $sourceRow['grupo']);
            $subgroup = trim((string) $sourceRow['subgrupo']);
            $section = trim((string) $sourceRow['seccion']);
            $ident = trim((string) $sourceRow['ident']);
            $sourceCode = trim((string) $sourceRow['cod_bien_mue']);
            $expectedCode = $group . '-' . $subgroup . '-' . $section . '-' . $ident;

            if (!preg_match('/^[0-9]$/', $group)
                || !preg_match('/^[0-9]{4}$/', $subgroup)
                || !preg_match('/^[0-9]{3}$/', $section)
                || !preg_match('/^[0-9]{4}$/', $ident)
                || $sourceCode !== $expectedCode) {
                throw new CsvImportException('La fuente contiene un código de bien que no se puede normalizar con seguridad.');
            }

            if (isset($seenIdentifiers[$ident])) {
                throw new CsvImportException('La fuente contiene identificadores repetidos; se canceló la importación para evitar actualizar el bien equivocado.');
            }
            $seenIdentifiers[$ident] = true;

            $status = inventory_normalize_status((string) $sourceRow['estado']);
            $description = trim((string) $sourceRow['denominacion']);
            $description = inventory_append_description(
                $description,
                'CARACTERÍSTICAS',
                (string) $sourceRow['caracteristicas']
            );
            $description = inventory_append_description($description, 'MATERIAL', (string) $sourceRow['material']);
            $description = inventory_append_description($description, 'COLOR', (string) $sourceRow['color']);

            $rows[] = array(
                $group,
                $subgroup,
                $section,
                $ident,
                $description,
                trim((string) $sourceRow['marca']),
                trim((string) $sourceRow['modelo']),
                trim((string) $sourceRow['serial1']),
                trim((string) $sourceRow['material']),
                trim((string) $sourceRow['color']),
                $status,
            );
        }

        $pdo->commit();
        return $rows;
    } catch (CsvImportException $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error de lectura de inventario PostgreSQL: ' . $e->getMessage());
        throw new CsvImportException('No fue posible consultar el inventario PostgreSQL en modo de solo lectura.');
    }
}

function inventory_report_rows(string $filename): array
{
    if (!class_exists('DOMDocument')) {
        throw new RuntimeException('La extensión PHP DOM es necesaria para leer el reporte XLS.');
    }

    $html = file_get_contents($filename);
    if ($html === false) {
        throw new CsvImportException('No se pudo leer el contenido del reporte XLS.');
    }

    $document = new DOMDocument();
    $previousLibxmlSetting = libxml_use_internal_errors(true);

    try {
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlSetting);
    }

    if (!$loaded) {
        throw new CsvImportException('No se pudo interpretar el reporte XLS. Usa el archivo descargado del sistema de bienes.');
    }

    $requiredHeaders = array(
        'CODIGO',
        'DENOMINACION DEL BIEN',
        'MARCA',
        'MODELO',
        'SERIAL',
        'MATERIAL',
        'COLOR',
        'ESTADO',
    );
    $selectedRows = array();
    $headerIndexes = null;
    $headerRowIndex = null;

    foreach ($document->getElementsByTagName('table') as $table) {
        $tableRows = array();
        foreach ($table->getElementsByTagName('tr') as $tableRow) {
            $tableRows[] = $tableRow;
        }

        foreach ($tableRows as $rowIndex => $tableRow) {
            $cells = inventory_report_cells($tableRow);
            $normalizedHeaders = array_map('inventory_report_normalize_header', $cells);
            if (count(array_intersect($requiredHeaders, $normalizedHeaders)) === count($requiredHeaders)) {
                $headerIndexes = array_flip($normalizedHeaders);
                $headerRowIndex = $rowIndex;
                $selectedRows = $tableRows;
                break 2;
            }
        }
    }

    if ($headerIndexes === null || $headerRowIndex === null) {
        throw new CsvImportException('El XLS no contiene las columnas esperadas del reporte de bienes muebles.');
    }

    $rows = array();
    foreach ($selectedRows as $rowIndex => $tableRow) {
        if ($rowIndex <= $headerRowIndex) {
            continue;
        }

        $cells = inventory_report_cells($tableRow);
        if (count($cells) < count($headerIndexes)) {
            continue;
        }

        $code = trim(inventory_report_value($cells, $headerIndexes, 'CODIGO'));
        if ($code === '') {
            continue;
        }

        if (inventory_report_normalize_header($code) === 'CODIGO') {
            continue;
        }

        if (!preg_match('/^([0-9]+)-([0-9]+)-([0-9]+)-([0-9]+)$/', $code, $parts)) {
            throw new CsvImportException('Código de bien inválido en la fila ' . ($rowIndex + 1) . ': ' . $code);
        }

        $description = trim(inventory_report_value($cells, $headerIndexes, 'DENOMINACION DEL BIEN'));
        $brand = trim(inventory_report_value($cells, $headerIndexes, 'MARCA'));
        $model = trim(inventory_report_value($cells, $headerIndexes, 'MODELO'));
        $serial = trim(inventory_report_value($cells, $headerIndexes, 'SERIAL'));
        $material = trim(inventory_report_value($cells, $headerIndexes, 'MATERIAL'));
        $color = trim(inventory_report_value($cells, $headerIndexes, 'COLOR'));
        $status = inventory_normalize_status(inventory_report_value($cells, $headerIndexes, 'ESTADO'));

        $description = inventory_append_description($description, 'MATERIAL', $material);
        $description = inventory_append_description($description, 'COLOR', $color);

        $rows[] = array(
            $parts[1],
            $parts[2],
            $parts[3],
            $parts[4],
            $description,
            $brand,
            $model,
            $serial,
            $material,
            $color,
            $status,
        );

        if (count($rows) > SYSTEC_IMPORT_MAX_ROWS) {
            throw new CsvImportException('El archivo supera el límite de 5000 filas.');
        }
    }

    return $rows;
}

function inventory_report_cells(DOMElement $row): array
{
    $cells = array();
    foreach ($row->childNodes as $child) {
        if ($child instanceof DOMElement && in_array(strtolower($child->tagName), array('td', 'th'), true)) {
            $value = str_replace("\xC2\xA0", ' ', $child->textContent);
            $cells[] = trim((string) preg_replace('/\s+/u', ' ', $value));
        }
    }

    return $cells;
}

function inventory_report_normalize_header(string $header): string
{
    $header = str_replace("\xC2\xA0", ' ', $header);
    return strtoupper(trim((string) preg_replace('/\s+/u', ' ', $header)));
}

function inventory_report_value(array $cells, array $indexes, string $header): string
{
    $index = $indexes[$header];
    return isset($cells[$index]) ? $cells[$index] : '';
}

function inventory_normalize_status(string $status): string
{
    $status = inventory_report_normalize_header($status);
    if ($status === 'MUY BUENO') {
        $status = 'BUENO';
    }

    if (!in_array($status, array('BUENO', 'REGULAR', 'MALO', 'MUY MALO'), true)) {
        throw new CsvImportException('La fuente contiene un estado de bien no reconocido: ' . $status);
    }

    return $status;
}

function inventory_append_description(string $description, string $label, string $value): string
{
    $value = trim($value);
    if ($value === '' || preg_match('/\b' . preg_quote($label, '/') . '\s*:/iu', $description)) {
        return $description;
    }

    $description = rtrim($description, " \t\n\r\0\x0B,");
    return $description . ($description !== '' ? ', ' : '') . $label . ': ' . $value;
}

function inventory_environment_value(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false ? $default : trim($value);
}
