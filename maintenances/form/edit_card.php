<?php if ($modeEdit === 'true') : ?>
    <section class="card shadow-sm mb-3 maintenance-edit-form" aria-labelledby="maintenance-form-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <h2 class="h5 mb-0" id="maintenance-form-title">
                <i class="bi bi-pencil-square text-primary me-2" aria-hidden="true"></i>Actualizar mantenimiento
            </h2>
            <a class="btn-close" href="<?= e(url('maintenances.php')) ?>"
               aria-label="Cerrar formulario"></a>
        </div>
        <form action="<?= e(url('maintenances/update.php')) ?>" method="post">
            <?= csrf_field() ?>
            <?php include __DIR__ . '/edit.php'; ?>
            <div class="card-footer d-flex flex-column flex-sm-row justify-content-end gap-2">
                <a class="btn btn-outline-secondary" href="<?= e(url('maintenances.php')) ?>">Cancelar</a>
                <button type="submit" name="update" value="Actualizar" class="btn btn-primary">
                    <i class="bi bi-save me-1" aria-hidden="true"></i>Guardar cambios
                </button>
            </div>
        </form>
    </section>
<?php endif; ?>
