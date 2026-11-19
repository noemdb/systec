<div class="modal fade" id="registerMaintenanceModal" tabindex="-1"
     aria-labelledby="registerMaintenanceModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content text-start">
            <form class="maintenance-modal-form" action="<?= e(url('maintenances/register.php')) ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="property_id" id="maintenancePropertyId"
                       value="<?= e((string) $maintenanceFormPropertyId) ?>">
                <input type="hidden" name="return_to" value="dashboard">
                <input type="hidden" name="return_category" value="">
                <input type="hidden" name="return_status" value="">
                <input type="hidden" name="return_adscription" value="">
                <?php if ($canViewAssignments) : ?>
                    <input type="hidden" name="return_responsible" value="">
                    <input type="hidden" name="return_assigned" value="">
                <?php endif; ?>
                <input type="hidden" name="return_search" value="">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="registerMaintenanceModalTitle">Registrar mantenimiento</h2>
                        <p class="small text-body-secondary mb-0">Bien: <span id="maintenancePropertyCode"><?= e($maintenanceFormPropertyCode) ?></span></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="dashboardMaintenanceType">Tipo de mantenimiento</label>
                        <select class="form-select" name="type" id="dashboardMaintenanceType" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($db->getListMaintenanceType() as $key => $value) : ?>
                                <option value="<?= e($key) ?>"<?= ($maintenanceFormValues['type'] ?? '') === (string) $key ? ' selected' : '' ?>><?= e($value) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dashboardMaintenanceTechnician">Técnico</label>
                        <select class="form-select" name="technician" id="dashboardMaintenanceTechnician" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($db->getListMaintenanceTechnician() as $key => $value) : ?>
                                <option value="<?= e($key) ?>"<?= ($maintenanceFormValues['technician'] ?? '') === (string) $key ? ' selected' : '' ?>><?= e($value) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dashboardMaintenanceDate">Fecha de inicio</label>
                        <input class="form-control" type="datetime-local" name="date" id="dashboardMaintenanceDate"
                               value="<?= e($maintenanceFormValues['date'] ?? date('Y-m-d\TH:i')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dashboardMaintenanceReason">Razón del mantenimiento</label>
                        <input class="form-control" type="text" name="failure_reason" id="dashboardMaintenanceReason"
                               value="<?= e($maintenanceFormValues['failure_reason'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dashboardMaintenanceDescription">Descripción</label>
                        <textarea class="form-control" name="description" id="dashboardMaintenanceDescription"
                                  rows="4" required><?= e($maintenanceFormValues['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dashboardMaintenanceNotes">Notas</label>
                        <textarea class="form-control" name="notes" id="dashboardMaintenanceNotes" rows="3"><?= e($maintenanceFormValues['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dashboardNextMaintenanceDate">Siguiente mantenimiento</label>
                        <input class="form-control" type="date" name="next_maintenance_date" id="dashboardNextMaintenanceDate"
                               value="<?= e($maintenanceFormValues['next_maintenance_date'] ?? '') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" name="register" value="Registrar">
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Registrar mantenimiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
