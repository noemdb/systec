<?php
/**
 * Detecta mantenimientos duplicados según los datos mostrados en el reporte.
 *
 * Uso:
 *   php bin/deduplicate-maintenances.php [--execute]
 *
 * Sin --execute solo muestra los duplicados. Al ejecutar, conserva el registro
 * con el ID menor de cada grupo y elimina los demás dentro de una transacción.
 * Duración, código importado, causa de falla y próxima fecha no distinguen eventos;
 * las observaciones sí se preservan como parte de la identidad del registro.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo CLI\n");
}

$arguments = array_slice($argv, 1);
if (in_array('--help', $arguments, true) || in_array('-h', $arguments, true)) {
    echo "Uso: php bin/deduplicate-maintenances.php [--execute]\n";
    echo "Sin --execute solo muestra los duplicados. Con --execute elimina los registros sobrantes.\n";
    exit(0);
}

if (array_diff($arguments, array('--execute')) !== array()) {
    fwrite(STDERR, "Argumento inválido.\n");
    fwrite(STDERR, "Uso: php bin/deduplicate-maintenances.php [--execute]\n");
    exit(2);
}

require_once __DIR__ . '/../conn.php';

$columns = array(
    'property_id',
    'type',
    'description',
    'date(date)',
    'technician',
    'notes',
    'status',
);
$groupBy = implode(', ', $columns);
$findDuplicates = function (DB $db) use ($groupBy) {
    $result = $db->query(
        'SELECT property_id, MIN(id) AS kept_id, COUNT(*) AS group_count, GROUP_CONCAT(id) AS ids
         FROM maintenances
         GROUP BY ' . $groupBy . '
         HAVING COUNT(*) > 1
         ORDER BY property_id ASC, kept_id ASC'
    );

    if (!$result) {
        throw new RuntimeException('No se pudieron consultar los duplicados: ' . $db->lastErrorMsg());
    }

    $groups = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $groups[] = $row;
    }
    $result->finalize();

    return $groups;
};

$db = null;
$transactionStarted = false;

try {
    $db = db();
    $execute = in_array('--execute', $arguments, true);

    if ($execute) {
        if (!$db->exec('BEGIN IMMEDIATE')) {
            throw new RuntimeException('No se pudo iniciar la transacción: ' . $db->lastErrorMsg());
        }
        $transactionStarted = true;
    }

    $groups = $findDuplicates($db);
    $duplicateRows = 0;
    foreach ($groups as $group) {
        $duplicateRows += (int) $group['group_count'] - 1;
        $ids = array_map('intval', explode(',', (string) $group['ids']));
        sort($ids, SORT_NUMERIC);
        $keptId = (int) $group['kept_id'];
        $removedIds = array_values(array_filter($ids, function ($id) use ($keptId) {
            return $id !== $keptId;
        }));
        $propertyId = $group['property_id'] === null ? 'sin asignar' : (string) $group['property_id'];

        echo 'Bien ' . $propertyId . ': conservar mantenimiento #' . $keptId
            . '; duplicados #' . implode(', #', $removedIds) . "\n";
    }

    echo 'Grupos duplicados: ' . count($groups) . "\n";
    echo 'Registros sobrantes: ' . $duplicateRows . "\n";

    if ($execute) {
        if ($duplicateRows > 0) {
            $deleteSql = 'DELETE FROM maintenances
                          WHERE id NOT IN (
                              SELECT MIN(id)
                              FROM maintenances
                              GROUP BY ' . $groupBy . '
                          )';
            if (!$db->exec($deleteSql)) {
                throw new RuntimeException('No se pudieron eliminar los duplicados: ' . $db->lastErrorMsg());
            }

            $deletedRows = $db->changes();
            if ($deletedRows !== $duplicateRows) {
                throw new RuntimeException(
                    'El número de registros eliminados no coincide con la vista previa; se revertirá la operación.'
                );
            }
        } else {
            $deletedRows = 0;
        }

        if (!$db->exec('COMMIT')) {
            throw new RuntimeException('No se pudo confirmar la operación: ' . $db->lastErrorMsg());
        }
        $transactionStarted = false;
        echo 'Registros eliminados: ' . $deletedRows . "\n";
    } else {
        echo "Vista previa: la base no fue modificada. Usa --execute para eliminar los registros sobrantes.\n";
    }

    $db->close();
} catch (Throwable $e) {
    if ($transactionStarted && $db instanceof DB) {
        $db->exec('ROLLBACK');
    }
    if ($db instanceof DB) {
        $db->close();
    }
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
