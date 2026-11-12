<?php
$canRegisterMaintenance = auth_has_role(array('admin', 'operador', 'tecnico'));
$canPropertyStatus = auth_has_role(array('admin', 'operador'));
?>
<div class="container-fluid text-start">
    <div class="table-responsive">
    <table class="table table-striped table-sm table-hover" id="myTable">
        <thead>
            <tr class="table-secondary">
                <th scope="col">N</th>
                <th scope="col">Gr</th>
                <th scope="col">SG</th>
                <th scope="col">Sec</th>
                <th scope="col">Ident.</th>
                <th scope="col">Adscripción / unidad operativa</th>
                <th scope="col">Descripción</th>
                <th scope="col">Modelo</th>
                <th scope="col">Color</th>
                <th scope="col">Serial</th>
                <th scope="col">Estado</th>
                <th scope="col">Mant.</th>
                <th scope="col" class="text-center">Acciones</th>
            </tr>
        </thead>
        <caption class="visually-hidden">Inventario de bienes registrados en SysTec</caption>
        <tbody>
            <?php foreach ($properties as $property) : ?>
                <?php
                $group = trim((string) $property['grupo']);
                $subgroup = str_pad(trim((string) $property['subgrupo']), 4, '0', STR_PAD_LEFT);
                $section = str_pad(trim((string) $property['seccion']), 3, '0', STR_PAD_LEFT);
                $propertyCategory = property_category($property);
                $propertyCode = property_code($property);
                $propertyModel = trim((string) ($property['model'] ?? ''));
                $propertyColor = trim((string) ($property['color'] ?? ''));
                $propertyAdscription = $adscriptionsByPropertyId[(int) $property['id']]
                    ?? array('key' => '__unassigned__', 'label' => 'Sin adscripción');
                ?>
                <tr data-category="<?= e($propertyCategory) ?>"
                    data-adscription="<?= e($propertyAdscription['key']) ?>"
                    data-status="<?= e(mb_strtoupper(trim((string) $property['status']))) ?>">
                    <td><?= e($property['id']) ?></td>
                    <td><?= e($property['grupo']) ?></td>
                    <td><?= e($subgroup) ?></td>
                    <td><?= e($section) ?></td>
                    <td><?= e(str_pad((string) $property['ident'], 4, '0', STR_PAD_LEFT)) ?></td>
                    <td><?= e($propertyAdscription['label']) ?></td>
                    <td><?= e($property['description']) ?></td>
                    <td class="text-break">
                        <?= $propertyModel !== '' ? e($propertyModel) : '<span class="text-body-secondary">No registrado</span>' ?>
                    </td>
                    <td>
                        <?= $propertyColor !== '' ? e($propertyColor) : '<span class="text-body-secondary">No registrado</span>' ?>
                    </td>
                    <td><?= e($property['serial']) ?></td>
                    <td><?= sat_badge(badge_clase_bien((string) $property['status']), property_status_label((string) $property['status'])) ?></td>
                    <td><?= e($maintenanceCountsByProperty[(int) $property['id']] ?? 0) ?></td>
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
                <form action="<?= e(url('dashboard/actions/status.php')) ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="property_id" id="statusPropertyId">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title fs-5" id="updatePropertyStatusModalTitle">Actualizar estado del bien</h2>
                            <p class="small text-body-secondary mb-0">Código: <span id="statusPropertyCode"></span></p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label fw-semibold" for="propertyStatus">Nuevo estado</label>
                        <select class="form-select" name="status" id="propertyStatus" required>
                            <option value="">Seleccione un estado</option>
                            <?php foreach ($db->getListPropertyStatus() as $key => $label) : ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
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
