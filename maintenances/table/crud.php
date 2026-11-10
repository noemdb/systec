<?php
// Catálogos usados por los filtros y por los badges.
$list_estado = $db->getListMaintenanceStatus();
$list_tipo = $db->getListMaintenanceType();
$list_tecnico = $db->getListMaintenanceTechnician();
$canEditMaintenance = auth_has_role(array('admin', 'operador', 'tecnico'));
?>

<!-- Barra de filtros (search por columna de DataTables) -->
<div class="row g-2 pb-3 align-items-end" aria-label="Filtros de mantenimientos">
    <div class="col-6 col-md-2">
        <label for="filterCategory" class="form-label small mb-0">Categoría del bien</label>
        <select id="filterCategory" class="form-select form-select-sm">
            <option value="">Todas las categorías</option>
            <option value="informatica">Informática</option>
            <option value="redes">Redes y telecomunicaciones</option>
            <option value="otros">Otros bienes</option>
        </select>
    </div>

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

    <div class="col-6 col-md-2">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="soloVencidos">
            <label class="form-check-label small" for="soloVencidos">
                Solo vencidos <i class="bi bi-exclamation-triangle-fill text-danger" aria-hidden="true"></i>
            </label>
        </div>
    </div>

    <div class="col-6 col-md-2 text-md-end">
        <button type="button" id="resetFiltros" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Limpiar filtros
        </button>
    </div>
</div>

<div class="table-responsive">
<table class="table table-striped table-sm table-hover align-middle mb-0" id="myTable">
    <thead>
        <tr>
            <th scope="col">N</th>
            <th scope="col">Código</th>
            <th scope="col">Tipo</th>
            <th scope="col">Descripción</th>
            <th scope="col">Fecha</th>
            <th scope="col">Horas</th>
            <th scope="col">Técnico</th>
            <th scope="col">Estado</th>
            <th scope="col">Próx. mant.</th>
            <th scope="col">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; ?>
        <?php foreach ($maintenances as $maintenance) : ?>
            <?php
            $detalle = htmlspecialchars(
                json_encode(array(
                    'id' => (int) $maintenance['id'],
                    'property_id' => (int) $maintenance['property_id'],
                    'code' => $maintenance['code'],
                    'type' => texto_tipo((string) $maintenance['type']),
                    'description' => (string) $maintenance['description'],
                    'date' => (string) $maintenance['date'],
                    'time_taken' => (string) $maintenance['time_taken'],
                    'technician' => (string) $maintenance['technician'],
                    'status' => texto_mantenimiento((string) $maintenance['status']),
                    'next_maintenance_date' => (string) $maintenance['next_maintenance_date'],
                    'reason' => (string) $maintenance['failure_reason'],
                    'notes' => (string) $maintenance['notes'],
                )),
                ENT_QUOTES,
                'UTF-8'
            );
            $editData = htmlspecialchars(
                json_encode(array(
                    'id' => (int) $maintenance['id'],
                    'property_id' => (int) $maintenance['property_id'],
                    'code' => (string) $maintenance['code'],
                    'type' => (string) $maintenance['type'],
                    'technician' => (string) $maintenance['technician'],
                    'date' => (string) $maintenance['date'],
                    'failure_reason' => (string) $maintenance['failure_reason'],
                    'description' => (string) $maintenance['description'],
                    'notes' => (string) $maintenance['notes'],
                    'next_maintenance_date' => (string) $maintenance['next_maintenance_date'],
                    'status' => (string) $maintenance['status'],
                )),
                ENT_QUOTES,
                'UTF-8'
            );

            $statusKey = mb_strtolower(trim((string) $maintenance['status']));
            $next = trim((string) $maintenance['next_maintenance_date']);
            $nextTimestamp = ($next !== '') ? strtotime($next) : false;
            $vencido = ($statusKey !== 'finalizado' && $nextTimestamp !== false && $nextTimestamp < time());
            ?>
            <tr class="<?= ($maintenance['id'] == $id) ? 'table-secondary' : '' ?> <?= $vencido ? 'js-vencido' : '' ?>"
                data-category="<?= e($propertyCategories[(int) $maintenance['property_id']] ?? '') ?>"
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
                <td class="text-nowrap">
                    <div class="btn-group btn-group-sm" role="group"
                         aria-label="Acciones del mantenimiento <?= e($maintenance['code']) ?>">
                        <button type="button" class="btn btn-outline-secondary js-detail"
                                data-detail="<?= $detalle ?>"
                                aria-label="Ver detalle del mantenimiento" title="Ver detalle">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>

                        <?php if ($canEditMaintenance) : ?>
                            <button type="button" class="btn btn-outline-primary js-edit-maintenance"
                                    data-maintenance="<?= $editData ?>"
                                    data-bs-toggle="modal" data-bs-target="#editMaintenanceModal"
                                    aria-label="Editar mantenimiento" title="Editar mantenimiento">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </button>

                            <?php if ($statusKey === 'iniciado' || $statusKey === 'finalizado') : ?>
                                    <button type="button" class="btn btn-outline-warning js-maintenance-transition"
                                            data-maintenance-id="<?= e($maintenance['id']) ?>"
                                            data-maintenance-code="<?= e($maintenance['code']) ?>"
                                            data-next-status="revision"
                                            data-action-label="Enviar a revisión"
                                            data-bs-toggle="modal" data-bs-target="#maintenanceTransitionModal"
                                            aria-label="Enviar mantenimiento a revisión" title="Enviar a revisión">
                                        <i class="bi bi-search" aria-hidden="true"></i>
                                    </button>
                            <?php endif; ?>

                            <?php if ($statusKey === 'iniciado' || $statusKey === 'revision') : ?>
                                    <button type="button" class="btn btn-outline-success js-maintenance-transition"
                                            data-maintenance-id="<?= e($maintenance['id']) ?>"
                                            data-maintenance-code="<?= e($maintenance['code']) ?>"
                                            data-next-status="finalizado"
                                            data-action-label="Finalizar mantenimiento"
                                            data-bs-toggle="modal" data-bs-target="#maintenanceTransitionModal"
                                            aria-label="Finalizar mantenimiento" title="Finalizar mantenimiento">
                                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                    </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($canEditMaintenance) : ?>
    <div class="modal fade" id="editMaintenanceModal" tabindex="-1"
         aria-labelledby="editMaintenanceModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content text-start">
                <form action="<?= e(url('maintenances/update.php')) ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="editMaintenanceId">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title fs-5" id="editMaintenanceModalTitle">Editar mantenimiento</h2>
                            <p class="small text-body-secondary mb-0">Código: <span id="editMaintenanceCode"></span></p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label for="editMaintenanceProperty" class="form-label">Bien</label>
                                <select class="form-select" name="property_id" id="editMaintenanceProperty" required>
                                    <?php foreach ($db->getListProperties('properties') as $key => $value) : ?>
                                        <option value="<?= e($key) ?>"><?= e($value) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="editMaintenanceType" class="form-label">Tipo</label>
                                <select class="form-select" name="type" id="editMaintenanceType" required>
                                    <?php foreach ($list_tipo as $key => $value) : ?>
                                        <option value="<?= e($key) ?>"><?= e($value) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="editMaintenanceTechnician" class="form-label">Técnico</label>
                                <select class="form-select" name="technician" id="editMaintenanceTechnician" required>
                                    <?php foreach ($list_tecnico as $key => $value) : ?>
                                        <option value="<?= e($key) ?>"><?= e($value) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="editMaintenanceDate" class="form-label">Fecha de inicio</label>
                                <input class="form-control" type="datetime-local" name="date" id="editMaintenanceDate" required>
                            </div>
                            <div class="col-12">
                                <label for="editMaintenanceReason" class="form-label">Razón del mantenimiento</label>
                                <input class="form-control" type="text" name="failure_reason"
                                       id="editMaintenanceReason" required>
                            </div>
                            <div class="col-12">
                                <label for="editMaintenanceDescription" class="form-label">Descripción</label>
                                <textarea class="form-control" name="description" id="editMaintenanceDescription"
                                          rows="3" required></textarea>
                            </div>
                            <div class="col-12">
                                <label for="editMaintenanceNotes" class="form-label">Notas</label>
                                <textarea class="form-control" name="notes" id="editMaintenanceNotes"
                                          rows="3"></textarea>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="editNextMaintenanceDate" class="form-label">Siguiente mantenimiento</label>
                                <input class="form-control" type="date" name="next_maintenance_date"
                                       id="editNextMaintenanceDate">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="editMaintenanceStatus" class="form-label">Estado</label>
                                <select class="form-select" name="status" id="editMaintenanceStatus" required>
                                    <?php foreach ($list_estado as $key => $value) : ?>
                                        <option value="<?= e($key) ?>"><?= e($value) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="update" value="Actualizar" class="btn btn-primary">
                            <i class="bi bi-save me-1" aria-hidden="true"></i>Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="maintenanceTransitionModal" tabindex="-1"
         aria-labelledby="maintenanceTransitionModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-start">
                <form action="<?= e(url('maintenances/actions/status.php')) ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="transitionMaintenanceId">
                    <input type="hidden" name="status" id="transitionMaintenanceStatus">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="maintenanceTransitionModalTitle">Confirmar cambio de estado</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p id="transitionMaintenanceMessage" class="mb-1"></p>
                        <p class="small text-body-secondary mb-0">Mantenimiento: <span id="transitionMaintenanceCode"></span></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn" id="confirmMaintenanceTransition">
                            <i class="bi me-1" id="confirmMaintenanceTransitionIcon" aria-hidden="true"></i>
                            <span id="confirmMaintenanceTransitionLabel">Confirmar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal compartido de detalle (Razón y Notas) -->
<div class="modal fade" id="detalleModal" tabindex="-1"
     aria-labelledby="detalleModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detalleModalTitle">Detalle del mantenimiento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-start">
                <dl class="row g-2 mb-0">
                    <dt class="col-sm-4">N.º de registro</dt>
                    <dd class="col-sm-8" id="detalleId"></dd>
                    <dt class="col-sm-4">Código del bien</dt>
                    <dd class="col-sm-8" id="detalleCode"></dd>
                    <dt class="col-sm-4">ID del bien</dt>
                    <dd class="col-sm-8" id="detallePropertyId"></dd>
                    <dt class="col-sm-4">Tipo</dt>
                    <dd class="col-sm-8" id="detalleType"></dd>
                    <dt class="col-sm-4">Fecha</dt>
                    <dd class="col-sm-8" id="detalleDate"></dd>
                    <dt class="col-sm-4">Tiempo empleado</dt>
                    <dd class="col-sm-8" id="detalleTimeTaken"></dd>
                    <dt class="col-sm-4">Técnico</dt>
                    <dd class="col-sm-8" id="detalleTechnician"></dd>
                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8" id="detalleStatus"></dd>
                    <dt class="col-sm-4">Siguiente mantenimiento</dt>
                    <dd class="col-sm-8" id="detalleNextDate"></dd>
                    <dt class="col-12">Razón del mantenimiento</dt>
                    <dd class="col-12 text-break" id="detalleReason"></dd>
                    <dt class="col-12">Descripción</dt>
                    <dd class="col-12 text-break" id="detalleDescription"></dd>
                    <dt class="col-12">Notas</dt>
                    <dd class="col-12 text-break" id="detalleNotes"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
