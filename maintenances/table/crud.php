<?php
// Catálogos usados por los filtros y por los badges.
$list_estado = $db->getListMaintenanceStatus();
$list_tipo = $db->getListMaintenanceType();
$list_tecnico = $db->getListMaintenanceTechnician();
$canEditMaintenance = auth_has_role(array('admin', 'operador', 'tecnico'));
?>

<!-- Filtros de la tabla -->
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-2">
    <h2 class="h6 mb-0" id="maintenanceFiltersTitle">
        <i class="bi bi-funnel text-body-secondary me-1" aria-hidden="true"></i>Filtrar
    </h2>
    <button type="button" id="resetFiltros" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Limpiar filtros
    </button>
</div>

<section aria-labelledby="maintenanceFiltersTitle">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-sm-6 col-xl">
            <label for="filterCategory" class="form-label fw-semibold small mb-0">Categoría del bien</label>
            <select id="filterCategory" class="form-select form-select-sm">
                <option value="">Todas las categorías</option>
                <option value="informatica">Informática</option>
                <option value="redes">Redes y telecomunicaciones</option>
                <option value="otros">Otros bienes</option>
            </select>
        </div>

        <div class="col-12 col-sm-6 col-xl">
            <label for="filterAdscription" class="form-label fw-semibold small mb-0">Unidad de adscripción</label>
            <select id="filterAdscription" class="form-select form-select-sm">
                <option value="">Todas las unidades</option>
                <?php foreach ($adscriptionOptions as $key => $label) : ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($adscriptionSourceWarning !== '') : ?>
                <span class="form-text text-warning-emphasis"><?= e($adscriptionSourceWarning) ?></span>
            <?php endif; ?>
        </div>

        <div class="col-12 col-sm-6 col-xl">
            <label for="filterEstado" class="form-label fw-semibold small mb-0">Estado</label>
            <select id="filterEstado" class="form-select form-select-sm js-col-filter" data-filter="status">
                <option value="">Todos</option>
                <?php foreach ($list_estado as $key => $value) : ?>
                    <option value="<?= e($key) ?>"><?= e($value) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-sm-6 col-xl">
            <label for="filterTipo" class="form-label fw-semibold small mb-0">Tipo</label>
            <select id="filterTipo" class="form-select form-select-sm js-col-filter" data-filter="type">
                <option value="">Todos</option>
                <?php foreach ($list_tipo as $key => $value) : ?>
                    <option value="<?= e($key) ?>"><?= e($value) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-sm-6 col-xl">
            <label for="filterTecnico" class="form-label fw-semibold small mb-0">Técnico</label>
            <select id="filterTecnico" class="form-select form-select-sm js-col-filter" data-filter="technician">
                <option value="">Todos</option>
                <?php foreach ($list_tecnico as $key => $value) : ?>
                    <option value="<?= e($key) ?>"><?= e($value) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row g-2 align-items-end mt-1">
        <div class="col-12 col-sm-6 col-md-3 col-xl-2">
            <label for="filterDateFrom" class="form-label fw-semibold small mb-0">Fecha desde</label>
            <input class="form-control form-control-sm" type="date" id="filterDateFrom">
        </div>

        <div class="col-12 col-sm-6 col-md-3 col-xl-2">
            <label for="filterDateTo" class="form-label fw-semibold small mb-0">Fecha hasta</label>
            <input class="form-control form-control-sm" type="date" id="filterDateTo">
        </div>

        <div class="col-6 col-md-3 col-xl-2">
            <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox" id="soloVencidos">
                <label class="form-check-label small" for="soloVencidos">
                    Solo vencidos
                    <i class="bi bi-exclamation-triangle-fill text-danger" aria-hidden="true"></i>
                </label>
            </div>
        </div>

        <div class="col-6 col-md-3 col-xl-2">
            <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox" id="proximos30">
                <label class="form-check-label small" for="proximos30"
                       title="Mantenimientos cuyo próximo mantenimiento vence en los próximos 30 días">
                    Próximos 30 días
                </label>
            </div>
        </div>
    </div>
</section>

<div class="border-top my-3"></div>

<?php if ($canEditMaintenance) : ?>
    <div id="maintenanceBulkToolbar" class="flex-wrap align-items-center gap-2 border rounded bg-body-tertiary px-2 py-1 mb-2"
         role="status" aria-live="polite" hidden>
        <span class="small fw-semibold">
            <span id="maintenanceBulkCount">0</span>
            <span id="maintenanceBulkCountLabel">seleccionados</span>
        </span>
        <div class="d-flex flex-wrap gap-2 ms-auto">
            <button type="button" class="btn btn-outline-warning btn-sm" id="bulkSendReview"
                    title="Envía a revisión los seleccionados que estén iniciados o finalizados">
                <i class="bi bi-search me-1" aria-hidden="true"></i>Enviar a revisión
            </button>
            <button type="button" class="btn btn-outline-success btn-sm" id="bulkFinalize"
                    title="Finaliza los seleccionados que estén iniciados o en revisión">
                <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Finalizar
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="bulkClear">
                <i class="bi bi-x-lg me-1" aria-hidden="true"></i>Limpiar
            </button>
        </div>
    </div>

    <form id="bulkMaintenanceForm" class="d-none"
          action="<?= e(url('maintenances/actions/bulk-status.php')) ?>" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="status" id="bulkMaintenanceStatus">
        <input type="hidden" name="return_query" id="bulkMaintenanceReturnQuery">
        <div id="bulkMaintenanceIds"></div>
    </form>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-striped table-sm table-hover align-middle mb-0" id="myTable">
    <caption class="visually-hidden">Mantenimientos registrados con datos del bien y sus acciones</caption>
    <thead class="table-light">
        <tr>
            <?php if ($canEditMaintenance) : ?>
                <th scope="col" class="maintenance-select-cell" data-orderable="false">
                    <input class="form-check-input" type="checkbox" id="bulkSelectAll"
                           aria-label="Seleccionar mantenimientos de esta página">
                </th>
            <?php endif; ?>
            <th scope="col">Código</th>
            <th scope="col" class="d-none d-sm-table-cell">Tipo</th>
            <th scope="col">Descripción</th>
            <th scope="col">Fecha</th>
            <th scope="col" class="d-none d-lg-table-cell">Horas</th>
            <th scope="col" class="d-none d-md-table-cell">Técnico</th>
            <th scope="col">Estado</th>
            <th scope="col">Próx. mant.</th>
            <th scope="col" class="d-none d-xl-table-cell">Modelo</th>
            <th scope="col" class="d-none d-xl-table-cell">Color</th>
            <th scope="col">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($maintenances as $maintenance) : ?>
            <?php
            $dateRaw = trim((string) $maintenance['date']);
            $dateTimestamp = $dateRaw !== '' ? strtotime($dateRaw) : false;
            $dateLabel = $dateTimestamp !== false ? date('d-m-Y H:i', $dateTimestamp) : '—';

            $nextRaw = trim((string) $maintenance['next_maintenance_date']);
            $nextTimestamp = $nextRaw !== '' ? strtotime($nextRaw) : false;
            $nextLabel = $nextTimestamp !== false ? date('d-m-Y', $nextTimestamp) : '—';

            $hoursLabel = trim((string) $maintenance['time_taken']) !== ''
                ? (string) $maintenance['time_taken']
                : '—';

            $propertyDetails = $propertiesById[(int) $maintenance['property_id']] ?? array();
            $propertyModel = trim((string) ($propertyDetails['model'] ?? ''));
            $propertyColor = trim((string) ($propertyDetails['color'] ?? ''));
            $propertyAdscription = $adscriptionsByPropertyId[(int) $maintenance['property_id']]
                ?? array('key' => '__unassigned__');
            $statusKey = mb_strtolower(trim((string) $maintenance['status']));
            $vencido = ($statusKey !== 'finalizado' && $nextTimestamp !== false && $nextTimestamp < time());
            $dateIso = $dateTimestamp !== false ? date('Y-m-d', $dateTimestamp) : '';
            $nextIso = $nextTimestamp !== false ? date('Y-m-d', $nextTimestamp) : '';
            $next30 = $nextTimestamp !== false
                && $nextTimestamp >= strtotime('today')
                && $nextTimestamp <= strtotime('+30 days');

            $detalle = htmlspecialchars(
                json_encode(array(
                    'id' => (int) $maintenance['id'],
                    'property_id' => (int) $maintenance['property_id'],
                    'code' => $maintenance['code'],
                    'type' => texto_tipo((string) $maintenance['type']),
                    'type_badge' => badge_clase_tipo((string) $maintenance['type']),
                    'description' => (string) $maintenance['description'],
                    'date' => $dateLabel,
                    'time_taken' => $hoursLabel,
                    'technician' => (string) $maintenance['technician'],
                    'status' => texto_mantenimiento((string) $maintenance['status']),
                    'status_badge' => badge_clase_mantenimiento((string) $maintenance['status']),
                    'next_maintenance_date' => $nextLabel,
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
            ?>
            <tr class="<?= ($maintenance['id'] == $id) ? 'table-secondary' : '' ?> <?= $vencido ? 'js-vencido' : '' ?>"
                data-category="<?= e($propertyCategories[(int) $maintenance['property_id']] ?? '') ?>"
                data-adscription="<?= e($propertyAdscription['key']) ?>"
                data-status="<?= e($statusKey) ?>"
                data-type="<?= e((string) $maintenance['type']) ?>"
                data-technician="<?= e((string) $maintenance['technician']) ?>"
                data-date="<?= e($dateIso) ?>"
                data-next="<?= e($nextIso) ?>"
                data-next30="<?= $next30 ? '1' : '0' ?>"
                data-vencido="<?= $vencido ? '1' : '0' ?>">
                <?php if ($canEditMaintenance) : ?>
                    <td class="maintenance-select-cell">
                        <input class="form-check-input js-bulk-select" type="checkbox"
                               value="<?= e((string) $maintenance['id']) ?>"
                               aria-label="Seleccionar mantenimiento <?= e($maintenance['code']) ?>">
                    </td>
                <?php endif; ?>
                <td class="text-nowrap fw-semibold"><?= e($maintenance['code']) ?></td>
                <td class="d-none d-sm-table-cell"><?= sat_badge(badge_clase_tipo((string) $maintenance['type']), texto_tipo((string) $maintenance['type'])) ?></td>
                <td class="maintenance-description-cell"><?= e($maintenance['description']) ?></td>
                <td class="text-nowrap" data-order="<?= e($dateRaw) ?>"><?= e($dateLabel) ?></td>
                <td class="text-end d-none d-lg-table-cell"><?= e($hoursLabel) ?></td>
                <td class="d-none d-md-table-cell"><?= e($maintenance['technician']) ?></td>
                <td><?= sat_badge(badge_clase_mantenimiento((string) $maintenance['status']), texto_mantenimiento((string) $maintenance['status'])) ?></td>
                <td class="text-nowrap" data-order="<?= e($nextRaw) ?>">
                    <?php if ($vencido) : ?>
                        <span class="text-danger fw-semibold"><?= e($nextLabel) ?></span>
                        <i class="bi bi-exclamation-triangle-fill text-danger" role="img"
                           aria-label="Mantenimiento vencido" data-bs-toggle="tooltip"
                           data-bs-title="Mantenimiento vencido"></i>
                    <?php else : ?>
                        <?= e($nextLabel) ?>
                    <?php endif; ?>
                </td>
                <td class="text-break d-none d-xl-table-cell">
                    <?= $propertyModel !== '' ? e($propertyModel) : '<span class="text-body-secondary">No registrado</span>' ?>
                </td>
                <td class="d-none d-xl-table-cell">
                    <?= $propertyColor !== '' ? e($propertyColor) : '<span class="text-body-secondary">No registrado</span>' ?>
                </td>
                <td class="text-nowrap">
                    <div class="btn-group btn-group-sm" role="group"
                         aria-label="Acciones del mantenimiento <?= e($maintenance['code']) ?>">
                        <button type="button" class="btn btn-outline-secondary js-detail"
                                data-detail="<?= $detalle ?>"
                                aria-label="Ver detalle del mantenimiento" title="Ver detalle">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>

                        <a class="btn btn-outline-secondary"
                           href="<?= e(url('historials.php?property_id=' . (int) $maintenance['property_id'])) ?>"
                           target="_blank" rel="noopener"
                           aria-label="Ver historial del bien <?= e($maintenance['code']) ?>"
                           title="Ver historial del bien">
                            <i class="bi bi-clock-history" aria-hidden="true"></i>
                        </a>

                        <?php if ($canEditMaintenance) : ?>
                            <button type="button" class="btn btn-outline-primary js-edit-maintenance"
                                    data-maintenance="<?= $editData ?>"
                                    data-bs-toggle="offcanvas" data-bs-target="#createMaintenanceDrawer"
                                    aria-controls="createMaintenanceDrawer"
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
    <div class="modal fade" id="maintenanceTransitionModal" tabindex="-1"
         aria-labelledby="maintenanceTransitionModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-start">
                <form action="<?= e(url('maintenances/actions/status.php')) ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="transitionMaintenanceId">
                    <input type="hidden" name="status" id="transitionMaintenanceStatus">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="maintenanceTransitionModalTitle">
                            <i class="bi bi-arrow-repeat text-primary me-2" aria-hidden="true"></i><span id="transitionMaintenanceTitleLabel">Confirmar cambio de estado</span>
                        </h2>
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

    <div class="modal fade" id="bulkTransitionModal" tabindex="-1"
         aria-labelledby="bulkTransitionModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-start">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="bulkTransitionModalTitle">
                        <i class="bi bi-collection text-primary me-2" aria-hidden="true"></i>Confirmar acción masiva
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p id="bulkTransitionMessage" class="mb-1"></p>
                    <p class="small text-body-secondary mb-0">
                        Solo se aplicarán los registros seleccionados que permitan esa transición; los demás se omitirán.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="bulkMaintenanceForm" class="btn" id="bulkTransitionConfirm">
                        <i class="bi me-1" id="bulkTransitionConfirmIcon" aria-hidden="true"></i>
                        <span id="bulkTransitionConfirmLabel">Confirmar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal compartido de detalle -->
<div class="modal fade" id="detalleModal" tabindex="-1"
     aria-labelledby="detalleModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content text-start">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="detalleModalTitle">
                        <i class="bi bi-clipboard-check text-primary me-2" aria-hidden="true"></i>Detalle del mantenimiento
                    </h2>
                    <p class="small text-body-secondary mb-0">
                        Registro <span class="fw-semibold" id="detalleId"></span> ·
                        Código <span class="fw-semibold" id="detalleCode"></span>
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-7">
                        <section class="mb-3">
                            <h3 class="h6 text-body-secondary text-uppercase small mb-1">Razón del mantenimiento</h3>
                            <p class="mb-0 text-break" id="detalleReason"></p>
                        </section>
                        <section class="mb-3">
                            <h3 class="h6 text-body-secondary text-uppercase small mb-1">Descripción</h3>
                            <p class="mb-0 text-break" id="detalleDescription"></p>
                        </section>
                        <section>
                            <h3 class="h6 text-body-secondary text-uppercase small mb-1">Notas</h3>
                            <p class="mb-0 text-break" id="detalleNotes"></p>
                        </section>
                    </div>
                    <div class="col-12 col-lg-5">
                        <div class="border rounded-3 bg-body-tertiary h-100 p-3">
                            <h3 class="h6 text-body-secondary text-uppercase small mb-2">Datos del registro</h3>
                            <dl class="row g-2 small mb-0">
                                <dt class="col-6 fw-normal text-body-secondary">Tipo</dt>
                                <dd class="col-6 mb-0">
                                    <span class="badge rounded-pill text-bg-secondary" id="detalleType"></span>
                                </dd>
                                <dt class="col-6 fw-normal text-body-secondary">Estado</dt>
                                <dd class="col-6 mb-0">
                                    <span class="badge rounded-pill text-bg-secondary" id="detalleStatus"></span>
                                </dd>
                                <dt class="col-6 fw-normal text-body-secondary">Fecha</dt>
                                <dd class="col-6 mb-0" id="detalleDate"></dd>
                                <dt class="col-6 fw-normal text-body-secondary">Tiempo empleado</dt>
                                <dd class="col-6 mb-0" id="detalleTimeTaken"></dd>
                                <dt class="col-6 fw-normal text-body-secondary">Técnico</dt>
                                <dd class="col-6 mb-0" id="detalleTechnician"></dd>
                                <dt class="col-6 fw-normal text-body-secondary">Siguiente</dt>
                                <dd class="col-6 mb-0" id="detalleNextDate"></dd>
                                <dt class="col-6 fw-normal text-body-secondary">ID del bien</dt>
                                <dd class="col-6 mb-0" id="detallePropertyId"></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
