<?php
/**
 * Propone mejoras de redacción para los textos de mantenimiento con NVIDIA Build.
 *
 * Uso:
 *   php bin/improve-maintenance-narrative.php [--id=ID] [--property-id=ID] [--limit=N] [--execute]
 *
 * La vista previa también consulta la API, pero no modifica la base. Solo --execute guarda los textos.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo CLI\n");
}

$usage = "Uso: php bin/improve-maintenance-narrative.php [--id=ID] [--property-id=ID] [--limit=N] [--execute]\n"
    . "Sin --execute consulta NVIDIA Build y muestra propuestas sin cambiar la base.\n"
    . "Se envían únicamente descripción, motivo de falla y observaciones; --execute guarda las propuestas.\n";
$arguments = array_slice($argv, 1);
if (in_array('--help', $arguments, true) || in_array('-h', $arguments, true)) {
    echo $usage;
    exit(0);
}

$options = array(
    'execute' => false,
    'id' => null,
    'property_id' => null,
    'limit' => null,
);
foreach ($arguments as $argument) {
    if ($argument === '--execute') {
        $options['execute'] = true;
        continue;
    }

    $matched = false;
    foreach (array('id', 'property-id', 'limit') as $optionName) {
        $prefix = '--' . $optionName . '=';
        if (strpos($argument, $prefix) !== 0) {
            continue;
        }

        $value = substr($argument, strlen($prefix));
        $minimum = 1;
        $parsed = filter_var($value, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => $minimum),
        ));
        if ($parsed === false) {
            fwrite(STDERR, 'Valor inválido para ' . $prefix . " Se requiere un entero positivo.\n");
            exit(2);
        }

        $key = str_replace('-', '_', $optionName);
        $options[$key] = $parsed;
        $matched = true;
        break;
    }

    if (!$matched) {
        fwrite(STDERR, "Argumento inválido.\n" . $usage);
        exit(2);
    }
}

require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../include/nvidia_maintenance_narrative.php';

$db = null;
$transactionStarted = false;

try {
    $apiKey = getenv('API_KEY_NVIDIA_BUILD');
    $apiKey = $apiKey === false ? '' : $apiKey;

    $db = db();
    $sql = "SELECT id, description, failure_reason, notes
            FROM maintenances
            WHERE (TRIM(COALESCE(description, '')) <> ''
               OR TRIM(COALESCE(failure_reason, '')) <> ''
               OR TRIM(COALESCE(notes, '')) <> '')";
    $parameters = array();

    if ($options['id'] !== null) {
        $sql .= ' AND id = :id';
        $parameters[':id'] = array($options['id'], SQLITE3_INTEGER);
    }
    if ($options['property_id'] !== null) {
        $sql .= ' AND property_id = :property_id';
        $parameters[':property_id'] = array($options['property_id'], SQLITE3_INTEGER);
    }

    $sql .= ' ORDER BY id ASC';
    if ($options['limit'] !== null) {
        $sql .= ' LIMIT :limit';
        $parameters[':limit'] = array($options['limit'], SQLITE3_INTEGER);
    }

    $statement = $db->prepare($sql);
    if (!$statement) {
        throw new RuntimeException('No se pudieron consultar los mantenimientos: ' . $db->lastErrorMsg());
    }
    foreach ($parameters as $name => $parameter) {
        $statement->bindValue($name, $parameter[0], $parameter[1]);
    }

    $result = $statement->execute();
    if (!$result) {
        throw new RuntimeException('Falló la consulta de mantenimientos: ' . $db->lastErrorMsg());
    }

    $records = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $records[] = $row;
    }
    $result->finalize();
    $statement->close();

    if ($records === array()) {
        echo "No hay textos de mantenimiento que cumplan los filtros.\n";
        $db->close();
        exit(0);
    }

    echo 'Mantenimientos a revisar: ' . count($records) . "\n";
    echo "Solo se enviarán a NVIDIA Build los campos narrativos seleccionados.\n";

    $fieldNames = array('description', 'failure_reason', 'notes');
    $proposals = array();
    foreach ($records as $index => $record) {
        $original = array();
        foreach ($fieldNames as $fieldName) {
            $original[$fieldName] = (string) ($record[$fieldName] ?? '');
        }

        fwrite(STDERR, 'NVIDIA: ' . ($index + 1) . '/' . count($records)
            . ' (mantenimiento #' . (int) $record['id'] . ")\n");
        $improved = systec_nvidia_improve_maintenance_texts($original, $apiKey);
        $changes = array();
        foreach ($fieldNames as $fieldName) {
            if ($improved[$fieldName] !== $original[$fieldName]) {
                $changes[$fieldName] = $improved[$fieldName];
            }
        }

        if ($changes !== array()) {
            $proposals[] = array(
                'id' => (int) $record['id'],
                'source' => array(
                    'description' => $record['description'],
                    'failure_reason' => $record['failure_reason'],
                    'notes' => $record['notes'],
                ),
                'original' => $original,
                'improved' => $improved,
                'changes' => $changes,
            );
            echo json_encode(array(
                'mantenimiento' => (int) $record['id'],
                'antes' => $original,
                'propuesta' => $improved,
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
        }
    }

    echo 'Textos con cambios propuestos: ' . count($proposals) . "\n";

    if (!$options['execute']) {
        echo "Vista previa: la base no fue modificada. Usa --execute para volver a generar y guardar las propuestas.\n";
        $db->close();
        exit(0);
    }

    if ($proposals === array()) {
        echo "No hay cambios que guardar.\n";
        $db->close();
        exit(0);
    }

    if (!$db->exec('BEGIN IMMEDIATE')) {
        throw new RuntimeException('No se pudo iniciar la transacción: ' . $db->lastErrorMsg());
    }
    $transactionStarted = true;
    $updatedCount = 0;

    foreach ($proposals as $proposal) {
        $sets = array();
        foreach (array_keys($proposal['changes']) as $fieldName) {
            $sets[] = $fieldName . ' = :new_' . $fieldName;
        }

        $update = $db->prepare(
            'UPDATE maintenances SET ' . implode(', ', $sets) . '
             WHERE id = :id
               AND description IS :old_description
               AND failure_reason IS :old_failure_reason
               AND notes IS :old_notes'
        );
        if (!$update) {
            throw new RuntimeException('No se pudo preparar la actualización: ' . $db->lastErrorMsg());
        }

        $update->bindValue(':id', $proposal['id'], SQLITE3_INTEGER);
        foreach ($fieldNames as $fieldName) {
            $originalValue = $proposal['source'][$fieldName];
            $oldValue = ':old_' . $fieldName;
            $update->bindValue(
                $oldValue,
                $originalValue,
                $originalValue === null ? SQLITE3_NULL : SQLITE3_TEXT
            );
        }
        foreach ($proposal['changes'] as $fieldName => $newValue) {
            $update->bindValue(':new_' . $fieldName, $newValue, SQLITE3_TEXT);
        }

        $updateResult = $update->execute();
        if (!$updateResult) {
            throw new RuntimeException('No se pudo actualizar mantenimiento #' . $proposal['id'] . ': ' . $db->lastErrorMsg());
        }
        $updateResult->finalize();
        $update->close();

        if ($db->changes() !== 1) {
            throw new RuntimeException(
                'El mantenimiento #' . $proposal['id'] . ' cambió durante el proceso; se revertirá la transacción.'
            );
        }
        $updatedCount++;
    }

    if (!$db->exec('COMMIT')) {
        throw new RuntimeException('No se pudo confirmar los cambios: ' . $db->lastErrorMsg());
    }
    $transactionStarted = false;
    echo 'Mantenimientos actualizados: ' . $updatedCount . "\n";
    $db->close();
} catch (Throwable $e) {
    $rollbackSucceeded = true;
    if ($transactionStarted && $db instanceof DB) {
        $rollbackSucceeded = $db->exec('ROLLBACK');
    }
    if ($db instanceof DB) {
        $db->close();
    }
    $message = 'Error: ' . $e->getMessage();
    if (!$transactionStarted || $rollbackSucceeded) {
        $message .= ' No se guardaron cambios.';
    } else {
        $message .= ' No se pudo confirmar la reversión; verifica la base de datos.';
    }
    fwrite(STDERR, $message . "\n");
    exit(1);
}
