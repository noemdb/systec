<?php
if (!defined('SYSTEC_IMPORT_CONTEXT')) {
    http_response_code(403);
    exit('Acceso no permitido');
}

if (!isset($db)) {
    $db = db();
}
$processed = 0;
$inserted = 0;
$updated = 0;
$unchanged = 0;
$errors = 0;
$errorDetails = array();
$statusCounts = array();
$i = 1;

if (isset($inventoryImportRows) && is_iterable($inventoryImportRows)) {
    $rowsToProcess = $inventoryImportRows;
} else {
    $rowsToProcess = (function () use ($handle) {
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            yield $row;
        }
    })();
}
?>

<table class="table table-sm">
    <thead>
        <tr>
            <th>N</th>
            <th>Gr</th>
            <th>SG</th>
            <th>Sec</th>
            <th>Ident.</th>
            <th>Descripción</th>
            <th>Marca</th>
            <th>Modelo</th>
            <th>Serial</th>
            <th>Material</th>
            <th>Color</th>
            <th>Estado</th>
            <th>Resultado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rowsToProcess as $row) : ?>
            <?php
            if ($row === array(null) || count(array_filter($row, function ($value) { return trim((string) $value) !== ''; })) === 0) {
                continue;
            }

            $row = array_pad($row, 11, '');
            $result = 'Procesado';
            $resultClass = 'text-bg-success';

            try {
                $result = $db->createOrUpdate($row);
                $processed++;
                $status = trim((string) $row[10]);
                if (!isset($statusCounts[$status])) {
                    $statusCounts[$status] = 0;
                }
                $statusCounts[$status]++;
                if ($result === 'inserted') {
                    $inserted++;
                    $result = 'Nuevo';
                } elseif ($result === 'updated') {
                    $updated++;
                    $result = 'Estado actualizado';
                } else {
                    $unchanged++;
                    $result = 'Sin cambios';
                }
            } catch (Exception $e) {
                error_log('Error actualizando inventario: ' . $e->getMessage());
                $errors++;
                $errorDetails[] = array(
                    'row' => $i,
                    'code' => implode('-', array_map('trim', array_slice($row, 0, 4))),
                    'description' => trim((string) $row[4]),
                    'message' => $e->getMessage(),
                );
                $result = 'Error';
                $resultClass = 'text-bg-danger';
            }
            ?>
            <tr>
                <td><?= e($i++) ?></td>
                <?php foreach ($row as $item) : ?>
                    <td><?= e($item) ?></td>
                <?php endforeach; ?>
                <td><span class="badge <?= e($resultClass) ?>"><?= e($result) ?></span></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<section class="card border-primary-subtle mt-3" aria-labelledby="inventory-import-summary">
    <div class="card-body">
        <h3 id="inventory-import-summary" class="h5">Resumen detallado de la importación</h3>
        <dl class="row mb-2">
            <dt class="col-sm-8">Bienes procesados correctamente</dt>
            <dd class="col-sm-4"><?= e($processed) ?></dd>
            <dt class="col-sm-8">Bienes nuevos incorporados</dt>
            <dd class="col-sm-4"><?= e($inserted) ?></dd>
            <dt class="col-sm-8">Bienes existentes cuyo estado cambió</dt>
            <dd class="col-sm-4"><?= e($updated) ?></dd>
            <dt class="col-sm-8">Bienes existentes sin cambios de estado</dt>
            <dd class="col-sm-4"><?= e($unchanged) ?></dd>
            <dt class="col-sm-8">Filas con error</dt>
            <dd class="col-sm-4"><?= e($errors) ?></dd>
            <dt class="col-sm-8">Total de filas consideradas</dt>
            <dd class="col-sm-4"><?= e($processed + $errors) ?></dd>
        </dl>
        <?php if ($statusCounts) : ?>
            <h4 class="h6">Bienes procesados por estado final</h4>
            <ul class="mb-2">
                <?php foreach ($statusCounts as $status => $quantity) : ?>
                    <li><?= e($status) ?>: <?= e($quantity) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($errorDetails) : ?>
            <details class="mt-3">
                <summary class="fw-semibold text-danger">
                    Ver detalle de las <?= e(count($errorDetails)) ?> filas con error
                </summary>
                <div class="table-responsive mt-2">
                    <table class="table table-sm table-striped table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Fila</th>
                                <th scope="col">Código del bien</th>
                                <th scope="col">Descripción</th>
                                <th scope="col">Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($errorDetails as $errorDetail) : ?>
                                <tr>
                                    <td><?= e($errorDetail['row']) ?></td>
                                    <td><?= e($errorDetail['code'] !== '' ? $errorDetail['code'] : 'Sin código') ?></td>
                                    <td><?= e($errorDetail['description'] !== '' ? $errorDetail['description'] : 'Sin descripción') ?></td>
                                    <td class="text-break"><?= e($errorDetail['message']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
        <p class="small text-muted mb-0">
            En bienes existentes solo se actualiza el estado. El detalle anterior identifica cada fila rechazada y el motivo.
        </p>
    </div>
</section>
