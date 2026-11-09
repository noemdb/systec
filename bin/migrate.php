<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI');
}

require_once __DIR__ . '/../conn.php';

$backupArgument = null;
foreach (array_slice($argv, 1) as $argument) {
    if (strpos($argument, '--backup=') === 0) {
        $backupArgument = substr($argument, strlen('--backup='));
    }
}

if ($backupArgument === null || $backupArgument === '') {
    fwrite(STDERR, "Uso: php bin/migrate.php --backup=RUTA_A_BACKUP_VERIFICADO\n");
    exit(1);
}

$backupPath = realpath($backupArgument);
if ($backupPath === false || !is_file($backupPath) || !is_readable($backupPath)) {
    fwrite(STDERR, "El backup indicado no existe o no se puede leer.\n");
    exit(1);
}

try {
    $backup = new SQLite3($backupPath, SQLITE3_OPEN_READONLY);
    $integrity = $backup->querySingle('PRAGMA integrity_check');
    if ($integrity !== 'ok') {
        throw new RuntimeException('El backup no supera PRAGMA integrity_check.');
    }

    $db = db();
    foreach (array('properties', 'maintenances', 'users') as $table) {
        $backupCount = (int) $backup->querySingle('SELECT COUNT(*) FROM ' . $table);
        $currentCount = (int) $db->querySingle('SELECT COUNT(*) FROM ' . $table);
        if ($backupCount !== $currentCount) {
            throw new RuntimeException('Los conteos del backup no coinciden con la base actual.');
        }
    }
    $backup->close();

    if (!$db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        version TEXT PRIMARY KEY,
        applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )')) {
        throw new RuntimeException('No se pudo crear schema_migrations: ' . $db->lastErrorMsg());
    }

    $migrationFiles = glob(__DIR__ . '/../migrations/*.sql');
    if ($migrationFiles === false) {
        throw new RuntimeException('No se pudo listar el directorio de migraciones.');
    }
    sort($migrationFiles, SORT_NATURAL);

    $appliedCount = 0;
    foreach ($migrationFiles as $migrationFile) {
        $version = basename($migrationFile);
        if (!preg_match('/^\d{3}_[a-z0-9_]+\.sql$/', $version)) {
            throw new RuntimeException('Nombre de migración inválido: ' . $version);
        }

        $check = $db->prepare('SELECT 1 FROM schema_migrations WHERE version = :version');
        if (!$check) {
            throw new RuntimeException('No se pudo consultar schema_migrations: ' . $db->lastErrorMsg());
        }
        $check->bindValue(':version', $version, SQLITE3_TEXT);
        $result = $check->execute();
        $alreadyApplied = $result && $result->fetchArray(SQLITE3_NUM) !== false;
        if ($result) {
            $result->finalize();
        }
        $check->close();

        if ($alreadyApplied) {
            continue;
        }

        $sql = file_get_contents($migrationFile);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('La migración está vacía o no se pudo leer: ' . $version);
        }

        if (!$db->exec('BEGIN IMMEDIATE')) {
            throw new RuntimeException('No se pudo iniciar la migración: ' . $db->lastErrorMsg());
        }

        try {
            if (!$db->exec($sql)) {
                throw new RuntimeException('Falló la migración ' . $version . ': ' . $db->lastErrorMsg());
            }

            $insert = $db->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
            if (!$insert) {
                throw new RuntimeException('No se pudo registrar la migración: ' . $db->lastErrorMsg());
            }
            $insert->bindValue(':version', $version, SQLITE3_TEXT);
            if (!$insert->execute()) {
                throw new RuntimeException('No se pudo registrar la migración: ' . $db->lastErrorMsg());
            }

            if (!$db->exec('COMMIT')) {
                throw new RuntimeException('No se pudo confirmar la migración: ' . $db->lastErrorMsg());
            }
        } catch (Throwable $e) {
            $db->exec('ROLLBACK');
            throw $e;
        }

        $appliedCount++;
        echo "Aplicada: {$version}\n";
    }

    if ($appliedCount === 0) {
        echo "No hay migraciones pendientes.\n";
    }
} catch (Throwable $e) {
    if (isset($backup) && $backup instanceof SQLite3) {
        $backup->close();
    }
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
