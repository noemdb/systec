<?php
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../include/auth.php';
require_personal_scope();

$db = db();
$properties = $db->index('properties', array(), '*', 'id ASC');

$title = 'Nuevo ticket';
ob_start();
?>
<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Nuevo ticket</h1>
            <p class="text-muted mb-0">Describe la incidencia asociada a un bien.</p>
        </div>
        <a href="<?= e(url('personal/index.php')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a mis tickets
        </a>
    </div>

    <div class="row">
        <div class="col-12 col-xl-8">
            <form method="post" action="<?= e(url('personal/actions/register.php')) ?>" class="card shadow-sm">
                <?= csrf_field() ?>
                <div class="card-body">
                    <h2 class="h5 mb-3">Datos de la incidencia</h2>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="property_id" class="form-label fw-semibold">Bien</label>
                            <select class="form-select" id="property_id" name="property_id" required>
                                <option value="">Seleccione un bien</option>
                                <?php foreach ($properties as $property) : ?>
                                    <option value="<?= e((string) $property['id']) ?>"><?= e((string) $property['ident']) ?> - <?= e((string) $property['description']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="title" class="form-label fw-semibold">Título</label>
                            <input type="text" class="form-control" id="title" name="title" maxlength="255" required>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label fw-semibold">Descripción</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                        </div>
                        <div class="col-12">
                            <label for="observation" class="form-label fw-semibold">Observación <span class="text-muted fw-normal">(opcional)</span></label>
                            <textarea class="form-control" id="observation" name="observation" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex flex-column-reverse flex-sm-row justify-content-end gap-2">
                    <a href="<?= e(url('personal/index.php')) ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Registrar ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$main = ob_get_clean();
include __DIR__ . '/../include/layout.php';
