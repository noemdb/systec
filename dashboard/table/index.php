<?php
$canRegisterMaintenance = auth_has_role(array('admin', 'operador', 'tecnico'));
$canPropertyStatus = auth_has_role(array('admin', 'operador'));
?>
<div class="table-responsive">
    <table class="table table-striped table-sm table-hover align-middle mb-0 is-compact-view" id="myTable">
        <thead>
            <tr class="table-secondary">
                <th scope="col" class="col-extended-detail">N</th>
                <th scope="col" class="col-extended-detail">Gr</th>
                <th scope="col" class="col-extended-detail">SG</th>
                <th scope="col" class="col-extended-detail">Sec</th>
                <th scope="col">Ident.</th>
                <th scope="col">Adscripción / unidad operativa</th>
                <?php if ($canViewAssignments) : ?>
                    <th scope="col" class="col-extended-detail">Responsable</th>
                    <th scope="col">Usuario asignado</th>
                <?php endif; ?>
                <th scope="col">Descripción</th>
                <th scope="col" class="col-extended-detail">Modelo</th>
                <th scope="col" class="col-extended-detail">Color</th>
                <th scope="col" class="col-extended-detail">Serial</th>
                <th scope="col">Estado</th>
                <th scope="col">Mant.</th>
                <?php if ($canViewTickets) : ?>
                    <th scope="col">Tickets</th>
                <?php endif; ?>
                <th scope="col" class="text-center">Acciones</th>
            </tr>
        </thead>
        <caption class="visually-hidden">Inventario de bienes registrados en SysTec</caption>
        <tbody>
            <?php foreach ($properties as $property) : ?>
                <?php
                $propertyId = (int) $property['id'];
                $group = trim((string) $property['grupo']);
                $subgroup = str_pad(trim((string) $property['subgrupo']), 4, '0', STR_PAD_LEFT);
                $section = str_pad(trim((string) $property['seccion']), 4, '0', STR_PAD_LEFT);
                $identPadded = str_pad(trim((string) $property['ident']), 4, '0', STR_PAD_LEFT);
                $propertyCategory = $propertyCategories[$propertyId] ?? property_category($property);
                $propertyCode = property_code($property);
                $propertyModel = trim((string) ($property['model'] ?? ''));
                $propertyColor = trim((string) ($property['color'] ?? ''));
                $propertySerial = trim((string) ($property['serial'] ?? ''));
                $compactMetaParts = array_values(array_filter(array(
                    $propertyModel !== '' ? 'Mod: ' . $propertyModel : '',
                    $propertyColor !== '' ? 'Color: ' . $propertyColor : '',
                    $propertySerial !== '' ? 'S/N: ' . $propertySerial : '',
                )));
                $propertyAdscription = $adscriptionsByPropertyId[$propertyId]
                    ?? array('key' => '__unassigned__', 'label' => 'Sin adscripción');
                $responsibleName = trim((string) ($property['responsible_name'] ?? ''));
                $responsibleStatusLabel = assignment_status_label((string) ($property['responsible_status'] ?? ''));
                $responsibleDisplay = $responsibleName !== ''
                    ? $responsibleName
                    : ($responsibleStatusLabel === 'Sin asignar' || $responsibleStatusLabel === 'Sin ficha'
                        ? $responsibleStatusLabel
                        : 'Nombre no registrado');
                $assignedName = trim((string) ($property['assigned_name'] ?? ''));
                $assignedStatusLabel = assignment_status_label((string) ($property['assigned_status'] ?? ''));
                $assignedDisplay = $assignedName !== ''
                    ? $assignedName
                    : ($assignedStatusLabel === 'Sin asignar' || $assignedStatusLabel === 'Sin ficha'
                        ? $assignedStatusLabel
                        : 'Nombre no registrado');
                $propertyStatusLabel = property_status_label((string) $property['status']);
                $maintenanceCount = (int) ($maintenanceCountsByProperty[$propertyId] ?? 0);
                $propertyTicketCount = (int) ($ticketCountsByProperty[$propertyId] ?? 0);
                ?>
                <tr data-category="<?= e($propertyCategory) ?>"
                    data-adscription="<?= e($propertyAdscription['key']) ?>"
                    data-status="<?= e(mb_strtoupper(trim((string) $property['status']))) ?>"
                    data-export-code="<?= e($propertyCode) ?>"
                    data-export-adscription="<?= e($propertyAdscription['label']) ?>"
                    data-export-description="<?= e((string) $property['description']) ?>"
                    data-export-model="<?= e($propertyModel) ?>"
                    data-export-color="<?= e($propertyColor) ?>"
                    data-export-serial="<?= e($propertySerial) ?>"
                    data-export-status="<?= e($propertyStatusLabel) ?>"
                    data-export-maintenances="<?= e((string) $maintenanceCount) ?>"
                    <?php if ($canViewTickets) : ?>
                        data-export-tickets="<?= e((string) $propertyTicketCount) ?>"
                    <?php endif; ?>
                    <?php if ($canViewAssignments) : ?>
                        data-responsible="<?= e($assignmentFilterKeysByPropertyId[$propertyId]['responsible'] ?? '') ?>"
                        data-assigned="<?= e($assignmentFilterKeysByPropertyId[$propertyId]['assigned'] ?? '') ?>"
                        data-export-responsible="<?= e($responsibleDisplay) ?>"
                        data-export-assigned="<?= e($assignedDisplay) ?>"
                    <?php endif; ?>>
                    <td class="col-extended-detail"><?= e($property['id']) ?></td>
                    <td class="col-extended-detail"><?= e($property['grupo']) ?></td>
                    <td class="col-extended-detail"><?= e($subgroup) ?></td>
                    <td class="col-extended-detail"><?= e($section) ?></td>
                    <td class="text-nowrap">
                        <span class="fw-semibold"><?= e($identPadded) ?></span>
                        <small class="d-block text-body-secondary compact-only-detail"><?= e($group . '-' . $subgroup . '-' . $section) ?></small>
                    </td>
                    <td><?= e($propertyAdscription['label']) ?></td>
                    <?php if ($canViewAssignments) : ?>
                        <td class="text-break col-extended-detail">
                            <span><?= e($responsibleDisplay) ?></span>
                            <?php if ($responsibleName !== '') : ?>
                                <small class="d-block text-body-secondary"><?= e($responsibleStatusLabel) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-break">
                            <span><?= e($assignedDisplay) ?></span>
                            <?php if ($assignedName !== '') : ?>
                                <small class="d-block text-body-secondary"><?= e($assignedStatusLabel) ?></small>
                            <?php endif; ?>
                            <?php if ($responsibleName !== '' && $responsibleName !== $assignedName) : ?>
                                <small class="d-block text-body-secondary compact-only-detail">Resp.: <?= e($responsibleName) ?></small>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td>
                        <span><?= e($property['description']) ?></span>
                        <?php if ($compactMetaParts !== array()) : ?>
                            <small class="d-block text-body-secondary text-break compact-only-detail">
                                <?= e(implode(' · ', $compactMetaParts)) ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td class="text-break col-extended-detail">
                        <?= $propertyModel !== '' ? e($propertyModel) : '<span class="text-body-secondary">No registrado</span>' ?>
                    </td>
                    <td class="col-extended-detail">
                        <?= $propertyColor !== '' ? e($propertyColor) : '<span class="text-body-secondary">No registrado</span>' ?>
                    </td>
                    <td class="col-extended-detail"><?= e($property['serial']) ?></td>
                    <td><?= sat_badge(badge_clase_bien((string) $property['status']), $propertyStatusLabel) ?></td>
                    <td class="text-nowrap">
                        <?php if ($maintenanceCount > 0) : ?>
                            <a href="<?= e(url('maintenances.php?search=' . rawurlencode($identPadded))) ?>"
                               class="badge rounded-pill text-bg-info text-decoration-none"
                               title="Ver mantenimientos del bien <?= e($identPadded) ?>"
                               aria-label="Ver <?= e((string) $maintenanceCount) ?> mantenimientos del bien <?= e($identPadded) ?>">
                                <?= e((string) $maintenanceCount) ?>
                            </a>
                        <?php else : ?>
                            <span class="text-body-secondary">0</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($canViewTickets) : ?>
                        <td class="text-nowrap">
                            <?php if ($propertyTicketCount > 0) : ?>
                                <a href="<?= e(url('tickets.php?search=' . rawurlencode($identPadded))) ?>"
                                   class="badge rounded-pill text-bg-primary text-decoration-none"
                                   title="Ver tickets del bien <?= e($identPadded) ?>"
                                   aria-label="Ver <?= e((string) $propertyTicketCount) ?> tickets del bien <?= e($identPadded) ?>">
                                    <?= e((string) $propertyTicketCount) ?>
                                </a>
                            <?php else : ?>
                                <span class="text-body-secondary">0</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td>
                        <div class="btn-group" role="group" aria-label="Acciones del bien">
                            <?php if ($canRegisterMaintenance) : ?>
                                <button type="button" class="btn btn-primary btn-sm property-action-btn"
                                        data-bs-toggle="modal" data-bs-target="#registerMaintenanceModal"
                                        data-property-id="<?= e($property['id']) ?>"
                                        data-property-code="<?= e($propertyCode) ?>"
                                        aria-label="Registrar mantenimiento" title="Registrar mantenimiento">
                                    <i class="bi bi-tools" aria-hidden="true"></i>
                                </button>
                            <?php endif; ?>

                            <?php if ($canManageTickets) : ?>
                                <button type="button" class="btn btn-outline-primary btn-sm property-action-btn"
                                        data-bs-toggle="offcanvas" data-bs-target="#dashboardTicketDrawer"
                                        aria-controls="dashboardTicketDrawer"
                                        data-property-id="<?= e($property['id']) ?>"
                                        data-property-code="<?= e($propertyCode) ?>"
                                        data-property-description="<?= e($property['description']) ?>"
                                        aria-label="Registrar ticket para este bien" title="Registrar ticket">
                                    <i class="bi bi-ticket-detailed" aria-hidden="true"></i>
                                </button>
                            <?php endif; ?>

                            <button type="button" class="btn btn-secondary btn-sm property-action-btn"
                                    data-bs-toggle="modal" data-bs-target="#propertyHistoryModal"
                                    data-history-url="<?= e(url('historials.php?property_id=' . (int) $property['id'] . '&embed=1')) ?>"
                                    data-property-code="<?= e($propertyCode) ?>"
                                    aria-label="Ver historial del bien" title="Ver historial">
                                <i class="bi bi-clock-history" aria-hidden="true"></i>
                            </button>

                            <?php if ($canPropertyStatus) : ?>
                                <button type="button" class="btn btn-dark btn-sm property-action-btn"
                                        data-bs-toggle="modal" data-bs-target="#updatePropertyStatusModal"
                                        data-property-id="<?= e($property['id']) ?>"
                                        data-property-code="<?= e($propertyCode) ?>"
                                        data-property-status="<?= e(mb_strtolower(trim((string) $property['status']))) ?>"
                                        aria-label="Cambiar estado del bien" title="Cambiar estado del bien">
                                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="propertyHistoryModal" tabindex="-1"
     aria-labelledby="propertyHistoryModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered property-history-dialog">
        <div class="modal-content property-history-content text-start">
            <div class="modal-header py-2">
                <div>
                    <h2 class="modal-title fs-5" id="propertyHistoryModalTitle">Historial de mantenimiento</h2>
                    <p class="small text-body-secondary mb-0">Bien: <span id="historyPropertyCode"></span></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body property-history-body" id="propertyHistoryContent"
                 aria-live="polite" aria-busy="false">
                <p class="text-body-secondary mb-0">Selecciona un bien para consultar su historial.</p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-primary btn-sm" id="printPropertyHistory"
                        disabled>
                    <i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir reporte
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<style nonce="<?= e(systec_csp_nonce()) ?>">
    .property-history-dialog {
        width: 90vw;
        max-width: 90vw;
        height: 90vh;
        height: 90dvh;
        margin: auto;
    }

    .property-history-content {
        height: 100%;
        min-height: 0;
    }

    .property-history-body {
        min-height: 0;
        overflow: auto;
    }

    @media (max-width: 575.98px) {
        .property-history-dialog {
            width: 96vw;
            max-width: 96vw;
            height: 92vh;
            height: 92dvh;
        }
    }
</style>

<?php if ($canPropertyStatus) : ?>
    <div class="modal fade" id="updatePropertyStatusModal" tabindex="-1"
         aria-labelledby="updatePropertyStatusModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-start">
                <form action="<?= e(url('dashboard/actions/status.php')) ?>" method="POST" id="updatePropertyStatusForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="property_id" id="statusPropertyId">
                    <input type="hidden" name="return_category" value="">
                    <input type="hidden" name="return_status" value="">
                    <input type="hidden" name="return_adscription" value="">
                    <input type="hidden" name="return_responsible" value="">
                    <input type="hidden" name="return_assigned" value="">
                    <input type="hidden" name="return_search" value="">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title fs-5" id="updatePropertyStatusModalTitle">Actualizar estado del bien</h2>
                            <p class="small text-body-secondary mb-0">Código: <span id="statusPropertyCode"></span></p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <span class="text-body-secondary small d-block mb-1">Condición actual:</span>
                            <span id="statusCurrentBadge" class="badge bg-secondary">No registrado</span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="propertyStatus">Nueva condición física</label>
                            <select class="form-select" name="status" id="propertyStatus" required>
                                <option value="">Seleccione un estado</option>
                                <?php foreach ($db->getListPropertyStatus() as $key => $label) : ?>
                                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Define si el bien está operativo o requiere atención técnica.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Guardar estado
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
