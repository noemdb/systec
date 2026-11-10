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
$preservedStatus = 0;
$preservedStatusDetails = array();
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
            <th>Estado de origen</th>
            <th>Estado en SysTec</th>
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
            $localStatus = '';

            try {
                $result = $db->createOrUpdate($row, !empty($preserveExistingStatus));
                $processed++;
                $ident = str_pad(trim((string) $row[3]), 4, '0', STR_PAD_LEFT);
                $property = $db->getFirstForConditions('properties', array('ident' => $ident));
                $localStatus = $property ? trim((string) $property['status']) : '';
                $status = $localStatus;
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
                } elseif ($result === 'preserved') {
                    $preservedStatus++;
                    $preservedStatusDetails[] = array(
                        'code' => trim((string) $row[0])
                            . '-' . str_pad(trim((string) $row[1]), 4, '0', STR_PAD_LEFT)
                            . '-' . str_pad(trim((string) $row[2]), 3, '0', STR_PAD_LEFT)
                            . '-' . str_pad(trim((string) $row[3]), 4, '0', STR_PAD_LEFT),
                        'description' => trim((string) $row[4]),
                        'local_status' => $localStatus,
                        'legacy_status' => trim((string) $row[10]),
                    );
                    $result = 'Estado SysTec conservado';
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
                <td><?= e($localStatus) ?></td>
                <td><span class="badge <?= e($resultClass) ?>"><?= e($result) ?></span></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<section class="card border-primary-subtle mt-3" aria-labelledby="inventory-import-summary">
    <div class="card-body">
        <h3 id="inventory-import-summary" class="h5">Resumen detallado de la importación</h3>
        <?php if (!empty($preserveExistingStatus)) : ?>
            <div class="alert alert-info" role="status">
                En <?= e($preservedStatus) ?> bienes existentes, el estado de PostgreSQL difería y no se aplicó;
                SysTec conservó su estado. Los bienes nuevos sí recibieron el estado de legacy.
            </div>
        <?php endif; ?>
        <dl class="row mb-2">
            <dt class="col-sm-8">Bienes procesados correctamente</dt>
            <dd class="col-sm-4"><?= e($processed) ?></dd>
            <dt class="col-sm-8">Bienes nuevos incorporados</dt>
            <dd class="col-sm-4"><?= e($inserted) ?></dd>
            <dt class="col-sm-8">Bienes existentes cuyo estado cambió</dt>
            <dd class="col-sm-4"><?= e($updated) ?></dd>
            <dt class="col-sm-8">Bienes existentes sin cambios de estado</dt>
            <dd class="col-sm-4"><?= e($unchanged) ?></dd>
            <?php if (!empty($preserveExistingStatus)) : ?>
                <dt class="col-sm-8">Bienes existentes con estado legacy diferente (se conservó SysTec)</dt>
                <dd class="col-sm-2"><?= e($preservedStatus) ?></dd>
                <dd class="col-sm-2">
                    <?php if ($preservedStatusDetails) : ?>
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal" data-bs-target="#preserved-status-details">
                            <i class="bi bi-list-ul me-1" aria-hidden="true"></i>Ver bienes
                        </button>
                    <?php else : ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </dd>
            <?php endif; ?>
            <dt class="col-sm-8">Filas con error</dt>
            <dd class="col-sm-4"><?= e($errors) ?></dd>
            <dt class="col-sm-8">Total de filas consideradas</dt>
            <dd class="col-sm-4"><?= e($processed + $errors) ?></dd>
        </dl>
        <?php if ($preservedStatusDetails) : ?>
            <div class="modal fade" id="preserved-status-details" tabindex="-1"
                 aria-labelledby="preserved-status-details-title" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content text-start">
                        <div class="modal-header">
                            <h4 class="modal-title fs-5" id="preserved-status-details-title">
                                Bienes con estado de SysTec conservado
                            </h4>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted">
                                PostgreSQL reportó un estado diferente para estos bienes. SysTec conservó su estado actual.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-bordered align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Código completo</th>
                                            <th scope="col">Descripción</th>
                                            <th scope="col">Estado SysTec</th>
                                            <th scope="col">Estado legacy</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($preservedStatusDetails as $detail) : ?>
                                            <tr>
                                                <td class="text-nowrap"><?= e($detail['code']) ?></td>
                                                <td><?= e($detail['description'] !== '' ? $detail['description'] : 'Sin descripción') ?></td>
                                                <td><?= e($detail['local_status'] !== '' ? $detail['local_status'] : 'Sin estado') ?></td>
                                                <td><?= e($detail['legacy_status'] !== '' ? $detail['legacy_status'] : 'Sin estado') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
            En importaciones CSV/XLS los bienes existentes actualizan su estado; desde PostgreSQL, SysTec conserva el estado local.
            El detalle de errores identifica las filas rechazadas y el motivo.
        </p>
    </div>
</section>
