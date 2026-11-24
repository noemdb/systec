<?php

require_once __DIR__ . '/csv_import.php';

/**
 * Lee el último cache local de adscripciones del inventario legacy sin conectarse a PostgreSQL.
 *
 * Devuelve un array con:
 *   - adscriptions: array<string, array{key:string, label:string}>  (vacío si no hay cache)
 *   - assignments:  array<string, array{...}>                       (vacío si no hay cache)
 *   - rows:         array                                           (siempre vacío; solo para compatibilidad)
 *   - cached_at:    int|null  (timestamp UNIX del cache, o null)
 *   - stale:        bool
 *   - found:        bool  (true si se encontró y leyó un archivo de cache)
 *   - unreadable:   bool  (true si el directorio o los archivos de cache no son legibles)
 */
function legacy_postgres_read_local_cache(?int $ttlSeconds = null, ?int $now = null): array
{
    $empty = array(
        'adscriptions' => array(),
        'assignments'  => array(),
        'rows'         => array(),
        'cached_at'    => null,
        'age_seconds'  => null,
        'stale'        => false,
        'found'        => false,
        'unreadable'   => false,
    );

    $cacheDirectory = SYSTEC_STORAGE . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($cacheDirectory)) {
        return $empty;
    }

    if (!is_readable($cacheDirectory)) {
        $empty['unreadable'] = true;
        return $empty;
    }

    // Busca el archivo de cache más reciente (puede haber varios para distintos conjuntos de idents).
    $pattern = $cacheDirectory . DIRECTORY_SEPARATOR . 'inventory-adscriptions-*.json';
    $files   = glob($pattern);
    if (!$files) {
        return $empty;
    }

    // Ordena por mtime descendente y usa el más reciente que se pueda leer.
    usort($files, static function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    $decoded = null;
    foreach ($files as $cacheFile) {
        if (!is_readable($cacheFile)) {
            // Puede haber archivos legibles más antiguos escritos por otra cuenta de servicio.
            $empty['unreadable'] = true;
            continue;
        }

        $contents = @file_get_contents($cacheFile);
        if ($contents === false) {
            $empty['unreadable'] = true;
            continue;
        }

        $candidate = json_decode($contents, true);
        if (!is_array($candidate)) {
            continue;
        }

        $decoded = $candidate;
        break;
    }

    if (!is_array($decoded)) {
        return $empty;
    }

    $cachedAt = isset($decoded['cached_at']) && is_numeric($decoded['cached_at'])
        ? (int) $decoded['cached_at']
        : null;
    $referenceNow = $now !== null ? $now : time();
    $ttl = ($ttlSeconds !== null && $ttlSeconds > 0)
        ? $ttlSeconds
        : (defined('SYSTEC_LEGACY_CACHE_TTL') ? (int) SYSTEC_LEGACY_CACHE_TTL : 86400);
    $ageSeconds = $cachedAt !== null ? max(0, $referenceNow - $cachedAt) : null;
    $isStale = (isset($decoded['stale']) && $decoded['stale'] === true)
        || ($ageSeconds !== null && $ageSeconds > $ttl);

    return array(
        'adscriptions' => isset($decoded['adscriptions']) && is_array($decoded['adscriptions'])
            ? $decoded['adscriptions']
            : array(),
        'assignments'  => isset($decoded['assignments']) && is_array($decoded['assignments'])
            ? $decoded['assignments']
            : array(),
        'rows'         => array(),   // no se usa en la tarjeta; evita cargar datos voluminosos
        'cached_at'    => $cachedAt,
        'age_seconds'  => $ageSeconds,
        'stale'        => $isStale,
        'found'        => true,
    );
}

/**
 * Persiste atómicamente adscripciones y asignaciones en el archivo de caché local.
 */
function legacy_postgres_write_local_cache(
    array $adscriptions,
    array $assignments = array(),
    array $propertyIdentifiers = array(),
    ?int $cachedAt = null
): bool {
    $identifiers = array();
    $sourceIdentifiers = $propertyIdentifiers !== array()
        ? $propertyIdentifiers
        : array_merge(array_keys($adscriptions), array_keys($assignments));
    foreach ($sourceIdentifiers as $identifier) {
        $identifier = trim((string) $identifier);
        if ($identifier !== '') {
            $normalized = str_pad($identifier, 4, '0', STR_PAD_LEFT);
            $identifiers[$normalized] = $normalized;
        }
    }
    $identifiers = array_values($identifiers);
    sort($identifiers, SORT_STRING);
    if (!$identifiers) {
        return false;
    }

    $cacheDirectory = SYSTEC_STORAGE . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0750, true) && !is_dir($cacheDirectory)) {
        error_log('No se pudo crear el directorio de cache de adscripciones: ' . $cacheDirectory);
        return false;
    }

    if (!is_writable($cacheDirectory)) {
        error_log('El directorio de cache de adscripciones no es escribible por la cuenta del servidor: ' . $cacheDirectory);
        return false;
    }

    $cachePath = $cacheDirectory . DIRECTORY_SEPARATOR
        . 'inventory-adscriptions-' . hash('sha256', implode("\n", $identifiers)) . '.json';
    $lock = fopen($cachePath . '.lock', 'c');
    if ($lock === false) {
        error_log('No se pudo abrir el lock del cache de adscripciones.');
        return false;
    }
    // Permite que otra cuenta de servicio (CLI vs. FPM) comparta el lock.
    @chmod($cachePath . '.lock', 0660);

    try {
        if (!flock($lock, LOCK_EX)) {
            return false;
        }

        if (($adscriptions === array() || $assignments === array()) && is_file($cachePath)) {
            $existingRaw = @file_get_contents($cachePath);
            $existingDecoded = $existingRaw !== false ? json_decode($existingRaw, true) : null;
            if (is_array($existingDecoded)) {
                if ($adscriptions === array()
                    && isset($existingDecoded['adscriptions'])
                    && is_array($existingDecoded['adscriptions'])
                    && $existingDecoded['adscriptions'] !== array()) {
                    $adscriptions = $existingDecoded['adscriptions'];
                }
                if ($assignments === array()
                    && isset($existingDecoded['assignments'])
                    && is_array($existingDecoded['assignments'])
                    && $existingDecoded['assignments'] !== array()) {
                    $assignments = $existingDecoded['assignments'];
                }
            }
        }

        $payload = array(
            'rows' => array(),
            'adscriptions' => $adscriptions,
            'assignments' => $assignments,
            'cached_at' => $cachedAt !== null ? $cachedAt : time(),
            'stale' => false,
        );
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($encoded === false) {
            return false;
        }

        $temporaryPath = tempnam($cacheDirectory, 'inventory-adscriptions-');
        if ($temporaryPath === false
            || file_put_contents($temporaryPath, $encoded, LOCK_EX) === false
            || !rename($temporaryPath, $cachePath)) {
            if (isset($temporaryPath) && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
            error_log('No se pudo guardar el cache local de inventario.');
            return false;
        }
        @chmod($cachePath, 0640);

        return true;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function property_assignment_filter_key(string $name, string $status): string
{
    $name = trim($name);
    $status = mb_strtoupper(trim($status), 'UTF-8');

    return hash('sha256', strlen($name) . ':' . $name . $status);
}

function property_assignment_filter_label(string $name, string $status): string
{
    $name = trim($name);
    $statusLabel = assignment_status_label($status, '');

    if ($name !== '' && $statusLabel !== '') {
        return $name . ' · ' . $statusLabel;
    }

    return $name !== '' ? $name : ($statusLabel !== '' ? $statusLabel : 'Pendiente de sincronizar');
}

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
    $snapshot = legacy_postgres_inventory_snapshot(array(), true, true);
    // La lectura PostgreSQL termina antes de iniciar la escritura en la SQLite local.
    $output = execute_inventory_rows_import($db, $snapshot['rows'], $processor);

    try {
        $properties = $db->index(
            'properties',
            array(),
            'id,ident,adscription,responsible_name,responsible_status,assigned_name,assigned_status',
            'id ASC'
        );
        $identifiers = array_map(function ($property) {
            return trim((string) $property['ident']);
        }, $properties);

        if (!empty($snapshot['adscriptions'])) {
            $db->syncPropertyAdscriptions($properties, $snapshot['adscriptions']);
        }
        if (!empty($snapshot['assignments'])) {
            $db->syncPropertyAssignments($properties, $snapshot['assignments']);
        }
        legacy_postgres_write_local_cache(
            $snapshot['adscriptions'] ?? array(),
            $snapshot['assignments'] ?? array(),
            $identifiers
        );
    } catch (Throwable $e) {
        error_log('Importación PostgreSQL completada, pero falló la actualización del caché local: ' . $e->getMessage());
    }

    return $output;
}

function legacy_postgres_inventory_rows(): array
{
    $snapshot = legacy_postgres_inventory_snapshot();
    return $snapshot['rows'];
}

function legacy_postgres_inventory_snapshot(
    array $propertyIdentifiers = array(),
    bool $includeInventoryRows = true,
    bool $includePropertyAssignments = false
): array
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
            'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';connect_timeout=5',
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

        $rows = array();
        if ($includeInventoryRows) {
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
        }

        $effectiveIdentifiers = $propertyIdentifiers;
        foreach ($rows as $row) {
            $effectiveIdentifiers[] = $row[3];
        }
        $adscriptions = legacy_postgres_property_adscriptions($pdo, $effectiveIdentifiers);
        $assignments = array();
        if ($includePropertyAssignments) {
            $assignments = legacy_postgres_property_assignments($pdo, $effectiveIdentifiers);
        }

        $pdo->commit();
        return array(
            'rows' => $rows,
            'adscriptions' => $adscriptions,
            'assignments' => $assignments,
        );
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

function legacy_postgres_cached_property_adscriptions(array $propertyIdentifiers, int $ttlSeconds = 300): array
{
    $identifiers = array();
    foreach ($propertyIdentifiers as $identifier) {
        $identifier = trim((string) $identifier);
        if ($identifier !== '') {
            $normalized = str_pad($identifier, 4, '0', STR_PAD_LEFT);
            $identifiers[$normalized] = $normalized;
        }
    }
    $identifiers = array_values($identifiers);
    sort($identifiers, SORT_STRING);
    if (!$identifiers) {
        return array('adscriptions' => array(), 'cached_at' => time(), 'stale' => false);
    }

    $cacheDirectory = SYSTEC_STORAGE . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0750, true) && !is_dir($cacheDirectory)) {
        error_log('No se pudo crear el directorio de cache de adscripciones.');
        return legacy_postgres_inventory_snapshot($identifiers, false) + array(
            'cached_at' => time(),
            'stale' => false,
        );
    }

    $cachePath = $cacheDirectory . DIRECTORY_SEPARATOR
        . 'inventory-adscriptions-' . hash('sha256', implode("\n", $identifiers)) . '.json';
    $lock = fopen($cachePath . '.lock', 'c');
    if ($lock === false) {
        error_log('No se pudo abrir el lock del cache de adscripciones.');
        return legacy_postgres_inventory_snapshot($identifiers, false) + array(
            'cached_at' => time(),
            'stale' => false,
        );
    }
    @chmod($cachePath . '.lock', 0660);

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('No se pudo bloquear el cache de adscripciones.');
        }
        $cached = null;
        if (is_file($cachePath)) {
            $contents = file_get_contents($cachePath);
            $decoded = $contents === false ? null : json_decode($contents, true);
            if (is_array($decoded)
                && isset($decoded['cached_at'], $decoded['adscriptions'])
                && is_numeric($decoded['cached_at'])
                && is_array($decoded['adscriptions'])) {
                $cached = $decoded;
                if (time() - (int) $cached['cached_at'] <= max(0, $ttlSeconds)) {
                    return array(
                        'adscriptions' => $cached['adscriptions'],
                        'cached_at' => (int) $cached['cached_at'],
                        'stale' => false,
                    );
                }
            }
        }

        // Con FastCGI la respuesta sale antes del refresco; la caché vencida se sirve al instante.
        if ($cached !== null && function_exists('fastcgi_finish_request')) {
            legacy_postgres_schedule_adscription_cache_refresh(
                $cachePath,
                $cacheDirectory,
                $identifiers,
                $ttlSeconds
            );
            return array(
                'adscriptions' => $cached['adscriptions'],
                'cached_at' => (int) $cached['cached_at'],
                'stale' => false,
                'background_refresh' => true,
            );
        }

        try {
            $snapshot = legacy_postgres_inventory_snapshot($identifiers, false);
            if (empty($snapshot['assignments']) && is_array($cached) && !empty($cached['assignments']) && is_array($cached['assignments'])) {
                $snapshot['assignments'] = $cached['assignments'];
            }
            $snapshot['cached_at'] = time();
            $snapshot['stale'] = false;
            $encoded = json_encode(
                $snapshot,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
            if ($encoded === false) {
                throw new RuntimeException('No se pudo codificar el cache de adscripciones.');
            }
            $temporaryPath = tempnam($cacheDirectory, 'inventory-adscriptions-');
            if ($temporaryPath === false
                || file_put_contents($temporaryPath, $encoded, LOCK_EX) === false
                || !rename($temporaryPath, $cachePath)) {
                if (isset($temporaryPath) && is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
                error_log('No se pudo guardar el cache de adscripciones.');
            } else {
                @chmod($cachePath, 0640);
            }
            return $snapshot;
        } catch (Throwable $e) {
            if ($cached !== null) {
                error_log('Se usará un cache de adscripciones vencido: ' . $e->getMessage());
                return array(
                    'adscriptions' => $cached['adscriptions'],
                    'cached_at' => (int) $cached['cached_at'],
                    'stale' => true,
                );
            }
            throw $e;
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function legacy_postgres_schedule_adscription_cache_refresh(
    string $cachePath,
    string $cacheDirectory,
    array $identifiers,
    int $ttlSeconds
): void {
    static $scheduled = array();
    if (isset($scheduled[$cachePath])) {
        return;
    }
    $scheduled[$cachePath] = true;

    register_shutdown_function(static function () use ($cachePath, $cacheDirectory, $identifiers, $ttlSeconds): void {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        legacy_postgres_refresh_adscription_cache($cachePath, $cacheDirectory, $identifiers, $ttlSeconds);
    });
}

function legacy_postgres_refresh_adscription_cache(
    string $cachePath,
    string $cacheDirectory,
    array $identifiers,
    int $ttlSeconds
): void {
    $lock = @fopen($cachePath . '.lock', 'c');
    if ($lock === false) {
        return;
    }
    @chmod($cachePath . '.lock', 0660);

    try {
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            return;
        }

        if (is_file($cachePath)) {
            $contents = file_get_contents($cachePath);
            $decoded = $contents === false ? null : json_decode($contents, true);
            if (is_array($decoded)
                && isset($decoded['cached_at'])
                && is_numeric($decoded['cached_at'])
                && time() - (int) $decoded['cached_at'] <= max(0, $ttlSeconds)) {
                return;
            }
        }

        $snapshot = legacy_postgres_inventory_snapshot($identifiers, false);
        $snapshot['cached_at'] = time();
        $snapshot['stale'] = false;
        $encoded = json_encode(
            $snapshot,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($encoded === false) {
            throw new RuntimeException('No se pudo codificar el cache de adscripciones.');
        }

        $temporaryPath = tempnam($cacheDirectory, 'inventory-adscriptions-');
        if ($temporaryPath === false
            || file_put_contents($temporaryPath, $encoded, LOCK_EX) === false
            || !rename($temporaryPath, $cachePath)) {
            if (isset($temporaryPath) && is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
            throw new RuntimeException('No se pudo guardar el cache de adscripciones.');
        }
        @chmod($cachePath, 0640);
    } catch (Throwable $e) {
        error_log('No se pudo refrescar el cache de adscripciones en segundo plano: ' . $e->getMessage());
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function legacy_postgres_property_adscriptions(PDO $pdo, array $propertyIdentifiers): array
{
    $identifiers = array();
    foreach ($propertyIdentifiers as $identifier) {
        if (is_string($identifier) || is_int($identifier)) {
            $identifier = trim((string) $identifier);
            if ($identifier !== '') {
                $normalizedIdentifier = str_pad($identifier, 4, '0', STR_PAD_LEFT);
                $identifiers[$normalizedIdentifier] = $normalizedIdentifier;
            }
        }
    }

    $adscriptions = array();
    foreach (array_chunk(array_values($identifiers), 500) as $identifierBatch) {
        $parameters = array();
        $placeholders = array();
        foreach ($identifierBatch as $index => $identifier) {
            $placeholder = ':ident' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $identifier;
        }

        $statement = $pdo->prepare(
            'SELECT
                b.num_bien AS ident,
                b.cod_dependencia,
                ref_dependency.denominacion_dep AS dependency_name,
                b.cod_direccion,
                ref_direction.denominacion_dir AS direction_name,
                b.cod_departamento,
                ref_department.denominacion_dep AS department_name
             FROM public.bien015 b
             LEFT JOIN public.bien001 ref_dependency
                ON ref_dependency.cod_dependencia = b.cod_dependencia
             LEFT JOIN public.bien005 ref_direction
                ON ref_direction.cod_dependencia = b.cod_dependencia
                AND ref_direction.cod_direccion = b.cod_direccion
             LEFT JOIN public.bien006 ref_department
                ON ref_department.cod_dependencia = b.cod_dependencia
                AND ref_department.cod_direccion = b.cod_direccion
                AND ref_department.cod_departamento = b.cod_departamento
             WHERE LPAD(TRIM(b.num_bien), 4, \'0\') IN (' . implode(', ', $placeholders) . ')
             ORDER BY b.num_bien'
        );
        foreach ($parameters as $placeholder => $value) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_STR);
        }
        $statement->execute();

        foreach ($statement->fetchAll() as $sourceRow) {
            $ident = trim((string) $sourceRow['ident']);
            $normalizedIdent = str_pad($ident, 4, '0', STR_PAD_LEFT);
            if (isset($adscriptions[$normalizedIdent])) {
                throw new CsvImportException(
                    'La fuente contiene identificadores repetidos; no se puede determinar la adscripción del bien.'
                );
            }
            $adscriptions[$normalizedIdent] = inventory_adscription($sourceRow);
        }
    }

    return $adscriptions;
}

function legacy_postgres_property_assignments(PDO $pdo, array $propertyIdentifiers): array
{
    $identifiers = array();
    foreach ($propertyIdentifiers as $identifier) {
        if (is_string($identifier) || is_int($identifier)) {
            $identifier = trim((string) $identifier);
            if ($identifier !== '') {
                $normalizedIdentifier = str_pad($identifier, 4, '0', STR_PAD_LEFT);
                $identifiers[$normalizedIdentifier] = $normalizedIdentifier;
            }
        }
    }

    $assignments = array();
    foreach (array_chunk(array_values($identifiers), 500) as $identifierBatch) {
        $parameters = array();
        $placeholders = array();
        foreach ($identifierBatch as $index => $identifier) {
            $placeholder = ':ident' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $identifier;
        }

        $statement = $pdo->prepare(
            "SELECT
                b.num_bien AS ident,
                COALESCE(responsible.display_name, '') AS responsible_name,
                CASE
                    WHEN NULLIF(TRIM(b.ced_responsable), '') IS NULL THEN 'SIN ASIGNAR'
                    WHEN responsible.employment_status IS NULL THEN 'SIN FICHA'
                    WHEN responsible.employment_status = '' THEN 'SIN ESTADO'
                    ELSE UPPER(responsible.employment_status)
                END AS responsible_status,
                COALESCE(assigned.display_name, '') AS assigned_name,
                CASE
                    WHEN NULLIF(TRIM(b.ced_responsable_uso), '') IS NULL THEN 'SIN ASIGNAR'
                    WHEN assigned.employment_status IS NULL THEN 'SIN FICHA'
                    WHEN assigned.employment_status = '' THEN 'SIN ESTADO'
                    ELSE UPPER(assigned.employment_status)
                END AS assigned_status
             FROM public.bien015 b
             LEFT JOIN LATERAL (
                SELECT
                    COALESCE(
                        NULLIF(TRIM(CONCAT_WS(
                            ' ',
                            NULLIF(TRIM(t.nombre1), ''),
                            NULLIF(TRIM(t.nombre2), ''),
                            NULLIF(TRIM(t.apellido1), ''),
                            NULLIF(TRIM(t.apellido2), '')
                        )), ''),
                        NULLIF(TRIM(t.nombre), '')
                    ) AS display_name,
                    NULLIF(TRIM(t.status), '') AS employment_status
                FROM public.trabajadores t
                WHERE NULLIF(TRIM(t.cedula), '') = NULLIF(TRIM(b.ced_responsable), '')
                ORDER BY
                    CASE WHEN UPPER(TRIM(COALESCE(t.status, ''))) = 'ACTIVO' THEN 0 ELSE 1 END,
                    t.status,
                    t.cod_empleado
                LIMIT 1
             ) responsible ON TRUE
             LEFT JOIN LATERAL (
                SELECT
                    COALESCE(
                        NULLIF(TRIM(CONCAT_WS(
                            ' ',
                            NULLIF(TRIM(t.nombre1), ''),
                            NULLIF(TRIM(t.nombre2), ''),
                            NULLIF(TRIM(t.apellido1), ''),
                            NULLIF(TRIM(t.apellido2), '')
                        )), ''),
                        NULLIF(TRIM(t.nombre), '')
                    ) AS display_name,
                    NULLIF(TRIM(t.status), '') AS employment_status
                FROM public.trabajadores t
                WHERE NULLIF(TRIM(t.cedula), '') = NULLIF(TRIM(b.ced_responsable_uso), '')
                ORDER BY
                    CASE WHEN UPPER(TRIM(COALESCE(t.status, ''))) = 'ACTIVO' THEN 0 ELSE 1 END,
                    t.status,
                    t.cod_empleado
                LIMIT 1
             ) assigned ON TRUE
             WHERE LPAD(TRIM(b.num_bien), 4, '0') IN (" . implode(', ', $placeholders) . ")
             ORDER BY b.num_bien"
        );
        foreach ($parameters as $placeholder => $value) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_STR);
        }
        $statement->execute();

        foreach ($statement->fetchAll() as $sourceRow) {
            $ident = trim((string) $sourceRow['ident']);
            $normalizedIdent = str_pad($ident, 4, '0', STR_PAD_LEFT);
            if (isset($assignments[$normalizedIdent])) {
                throw new CsvImportException(
                    'La fuente contiene identificadores repetidos; no se puede determinar la asignación del bien.'
                );
            }

            $assignments[$normalizedIdent] = array(
                'responsible_name' => trim((string) $sourceRow['responsible_name']),
                'responsible_status' => trim((string) $sourceRow['responsible_status']),
                'assigned_name' => trim((string) $sourceRow['assigned_name']),
                'assigned_status' => trim((string) $sourceRow['assigned_status']),
            );
        }
    }

    return $assignments;
}

function inventory_adscription(array $sourceRow): array
{
    $dependencyCode = trim((string) ($sourceRow['cod_dependencia'] ?? ''));
    $directionCode = trim((string) ($sourceRow['cod_direccion'] ?? ''));
    $departmentCode = trim((string) ($sourceRow['cod_departamento'] ?? ''));
    $directionLabel = trim((string) ($sourceRow['direction_name'] ?? ''));
    $label = trim((string) ($sourceRow['department_name'] ?? ''));

    if ($label === '') {
        $label = $directionLabel;
    }
    $dependencyLabel = trim((string) ($sourceRow['dependency_name'] ?? ''));
    if ($label === '') {
        $label = $dependencyLabel;
    }

    $key = implode('/', array($dependencyCode, $directionCode, $departmentCode));
    if ($dependencyCode === '' && $directionCode === '' && $departmentCode === '' && $label !== '') {
        $key = 'local:' . hash('sha256', mb_strtolower($label));
    }
    $directionKey = $dependencyCode !== '' && $directionCode !== ''
        ? $dependencyCode . '/' . $directionCode
        : '__unassigned_direction__';

    return array(
        'key' => $key,
        'label' => $label,
        'direction_key' => $directionKey,
        'direction_label' => $directionLabel !== '' ? $directionLabel : 'Sin dirección asignada',
    );
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
