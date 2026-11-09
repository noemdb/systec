<?php
// Catálogos usados por los filtros y por los badges.
$list_estado = $db->getListMaintenanceStatus();
$list_tipo = $db->getListMaintenanceType();
$list_tecnico = $db->getListMaintenanceTechnician();
$canEditMaintenance = auth_has_role(array('admin', 'operador', 'tecnico'));
?>

<!-- Barra de filtros (search por columna de DataTables) -->
<div class="row g-2 pb-3 align-items-end">
    <div class="col-6 col-md-2">
        <label for="filterEstado" class="form-label small mb-0">Estado</label>
        <select id="filterEstado" class="form-select form-select-sm js-col-filter" data-column="7">
            <option value="">Todos</option>
            <?php foreach ($list_estado as $value) : ?>
                <option value="<?= e($value) ?>"><?= e($value) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-6 col-md-2">
        <label for="filterTipo" class="form-label small mb-0">Tipo</label>
        <select id="filterTipo" class="form-select form-select-sm js-col-filter" data-column="2">
            <option value="">Todos</option>
            <?php foreach ($list_tipo as $value) : ?>
                <option value="<?= e($value) ?>"><?= e($value) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-6 col-md-2">
        <label for="filterTecnico" class="form-label small mb-0">Técnico</label>
        <select id="filterTecnico" class="form-select form-select-sm js-col-filter" data-column="6">
            <option value="">Todos</option>
            <?php foreach ($list_tecnico as $value) : ?>
                <option value="<?= e($value) ?>"><?= e($value) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-6 col-md-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="soloVencidos">
            <label class="form-check-label small" for="soloVencidos">
                Solo vencidos <i class="bi bi-exclamation-triangle-fill text-danger" aria-hidden="true"></i>
            </label>
        </div>
    </div>

    <div class="col-12 col-md-3 text-md-end">
        <button type="button" id="resetFiltros" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-circle" aria-hidden="true"></i> Limpiar filtros
        </button>
    </div>
</div>

<table class="table table-striped table-sm table-hover" id="myTable">
    <thead>
        <tr>
            <th>N</th>
            <th>Código</th>
            <th>Tipo</th>
            <th>Descripción</th>
            <th>Fecha</th>
            <th>Horas</th>
            <th>Técnico</th>
            <th>Estado</th>
            <th>Próx. mant.</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; ?>
        <?php foreach ($maintenances as $maintenance) : ?>
            <?php
            $detalle = htmlspecialchars(
                json_encode(array(
                    'code' => $maintenance['code'],
                    'reason' => (string) $maintenance['failure_reason'],
                    'notes' => (string) $maintenance['notes'],
                )),
                ENT_QUOTES,
                'UTF-8'
            );

            $statusKey = mb_strtolower(trim((string) $maintenance['status']));
            $next = trim((string) $maintenance['next_maintenance_date']);
            $vencido = ($statusKey !== 'finalizado' && $next !== '' && strtotime($next) < time());
            ?>
            <tr class="<?= ($maintenance['id'] == $id) ? 'table-secondary' : '' ?> <?= $vencido ? 'js-vencido' : '' ?>"
                data-vencido="<?= $vencido ? '1' : '0' ?>">
                <td><?= e($i++) ?></td>
                <td class="text-nowrap"><?= e($maintenance['code']) ?></td>
                <td><?= sat_badge(badge_clase_tipo((string) $maintenance['type']), texto_tipo((string) $maintenance['type'])) ?></td>
                <td><?= e($maintenance['description']) ?></td>
                <td class="text-nowrap"><?= e($maintenance['date']) ?></td>
                <td><?= e($maintenance['time_taken']) ?></td>
                <td><?= e($maintenance['technician']) ?></td>
                <td><?= sat_badge(badge_clase_mantenimiento((string) $maintenance['status']), texto_mantenimiento((string) $maintenance['status'])) ?></td>
                <td class="text-nowrap">
                    <?= e($maintenance['next_maintenance_date']) ?>
                    <?php if ($vencido) : ?>
                        <i class="bi bi-exclamation-triangle-fill text-danger" data-bs-toggle="tooltip" data-bs-title="Mantenimiento vencido"></i>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex justify-content-evenly align-items-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm mx-1 js-detail"
                                data-detail="<?= $detalle ?>"
                                data-bs-toggle="tooltip" data-bs-title="Ver Razón y Notas">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                            <span class="visually-hidden">Detalle</span>
                        </button>

                        <?php if ($canEditMaintenance) : ?>
                            <a class="btn btn-warning btn-sm mx-1"
                               href="<?= e(url('maintenances.php?modeEdit=true&id=' . (int) $maintenance['id'])) ?>"
                               role="button">Editar</a>

                            <?php if ($statusKey === 'iniciado' || $statusKey === 'finalizado') : ?>
                                <form method="post" action="<?= e(url('maintenances/actions/status.php')) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($maintenance['id']) ?>">
                                    <input type="hidden" name="status" value="revision">
                                    <button type="submit" class="btn btn-info btn-sm mx-1">Revisión</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($statusKey === 'iniciado' || $statusKey === 'revision') : ?>
                                <form method="post" action="<?= e(url('maintenances/actions/status.php')) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($maintenance['id']) ?>">
                                    <input type="hidden" name="status" value="finalizado">
                                    <button type="submit" class="btn btn-success btn-sm mx-1">Finalizar</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Modal compartido de detalle (Razón y Notas) -->
<div class="modal fade" id="detalleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del mantenimiento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-start">
                <p class="mb-1"><strong>Código:</strong> <span id="detalleCode"></span></p>
                <hr class="my-2">
                <p class="mb-1"><strong>Razón del mantenimiento:</strong></p>
                <p id="detalleReason" class="mb-2" style="white-space: pre-line;"></p>
                <hr class="my-2">
                <p class="mb-1"><strong>Notas:</strong></p>
                <p id="detalleNotes" class="mb-2" style="white-space: pre-line;"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
