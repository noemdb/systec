<div class="offcanvas offcanvas-end maintenance-create-drawer text-start" tabindex="-1"
     id="createMaintenanceDrawer" aria-labelledby="createMaintenanceDrawerTitle">
    <div class="offcanvas-header border-bottom">
        <div>
            <h2 class="offcanvas-title h5 mb-1" id="createMaintenanceDrawerTitle">
                <i class="bi bi-plus-circle text-primary me-2" id="createMaintenanceDrawerTitleIcon" aria-hidden="true"></i>
                <span id="createMaintenanceDrawerTitleLabel">Registrar mantenimiento</span>
            </h2>
            <p class="small text-body-secondary mb-0" id="createMaintenanceDrawerDescription">
                Completa los datos para crear un registro.
            </p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <form id="createMaintenanceForm" class="maintenance-create-drawer-form"
          action="<?= e(url('maintenances/register.php')) ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="createMaintenanceId" value="">
        <div class="offcanvas-body">
            <?php $maintenanceFieldPrefix = 'create'; ?>
            <?php include __DIR__ . '/fields.php'; ?>
            <div class="mt-3 d-none" id="createMaintenanceStatusField">
                <label for="create_status" class="form-label fw-semibold small">
                    Estado <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <select class="form-select" name="status" id="create_status" disabled required>
                    <?php foreach ($db->getListMaintenanceStatus() as $key => $value) : ?>
                        <option value="<?= e($key) ?>"><?= e($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="border-top p-3 d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="offcanvas">
                Cancelar
            </button>
            <button type="submit" id="createMaintenanceSubmit" name="register" value="Registrar"
                    class="btn btn-primary flex-fill">
                <i class="bi bi-check-lg me-1" id="createMaintenanceSubmitIcon" aria-hidden="true"></i>
                <span id="createMaintenanceSubmitLabel">Registrar</span>
            </button>
        </div>
    </form>
</div>
