<?php
if (!defined('SYSTEC_IMPORT_CONTEXT')) {
    http_response_code(403);
    exit('Acceso no permitido');
}

$db = db();
$inserted = 0;
$skipped = 0;
$errors = 0;
$i = 1;
?>

<div class="alert alert-info">
    <strong>Resultado:</strong>
    <span id="importInserted"><?= e($inserted) ?></span> insertados,
    <span id="importSkipped"><?= e($skipped) ?></span> omitidos,
    <span id="importErrors"><?= e($errors) ?></span> con error.
</div>

<table class="table table-sm">
    <thead>
        <tr>
            <th>N</th>
            <th>Código</th>
            <th>Tipo</th>
            <th>Descripción</th>
            <th>Técnico</th>
            <th>Razón</th>
            <th>Fecha</th>
            <th>Notas</th>
            <th>Resultado</th>
        </tr>
    </thead>
    <tbody>
        <?php while (($row = fgetcsv($handle, 0, ',')) !== false) : ?>
            <?php
            if ($row === array(null) || count(array_filter($row, function ($value) { return trim((string) $value) !== ''; })) === 0) {
                continue;
            }

            $row = array_pad($row, 9, '');
            $ident = str_pad(trim(substr((string) $row[0], -4)), 4, '0', STR_PAD_LEFT);
            $result = 'Omitido: bien no encontrado';
            $resultClass = 'text-bg-warning';

            try {
                $property = $db->getFirstForConditions('properties', array('ident' => $ident));

                if ($property) {
                    $timestamp = strtotime((string) $row[6]);
                    if ($timestamp === false) {
                        $result = 'Error: fecha inválida';
                        $resultClass = 'text-bg-danger';
                        $errors++;
                    } else {
                        $data = array(
                            'property_id' => $property['id'],
                            'code' => (string) $row[0],
                            'type' => strtolower(trim((string) $row[1])),
                            'description' => (string) $row[2],
                            'technician' => (string) $row[3],
                            'failure_reason' => (string) $row[5],
                            'date' => date('Y-m-d H:i:s', $timestamp),
                            'notes' => (string) $row[7],
                            'status' => 'finalizado',
                            'time_taken' => null,
                        );
                        $db->create('maintenances', $data);
                        $inserted++;
                        $result = 'Insertado';
                        $resultClass = 'text-bg-success';
                    }
                } else {
                    $skipped++;
                }
            } catch (Exception $e) {
                error_log('Error importando mantenimiento: ' . $e->getMessage());
                $errors++;
                $result = 'Error al insertar';
                $resultClass = 'text-bg-danger';
            }
            ?>
            <tr>
                <td><?= e($i++) ?></td>
                <td><?= e($row[0]) ?></td>
                <td><?= e($row[1]) ?></td>
                <td><?= e($row[2]) ?></td>
                <td><?= e($row[3]) ?></td>
                <td><?= e($row[5]) ?></td>
                <td><?= e($row[6]) ?></td>
                <td><?= e($row[7]) ?></td>
                <td><span class="badge <?= e($resultClass) ?>"><?= e($result) ?></span></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<script>
    document.getElementById('importInserted').textContent = <?= js($inserted) ?>;
    document.getElementById('importSkipped').textContent = <?= js($skipped) ?>;
    document.getElementById('importErrors').textContent = <?= js($errors) ?>;
</script>
