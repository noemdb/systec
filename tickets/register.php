<?php
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../include/auth.php';
require_role(array('personal', 'admin', 'operador', 'tecnico'));

$db = db();
$properties = $db->index('properties', array(), '*', 'id ASC');

$title = 'Nuevo ticket';
ob_start();
?>
<div class="container-sm py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1">Registrar ticket</h1>
            <p class="text-muted mb-0">Describe la incidencia asociada a un bien.</p>
        </div>
        <a href="<?= e(url('tickets.php')) ?>" class="btn btn-outline-secondary btn-sm">Volver</a>
    </div>

    <form method="post" action="<?= e(url('tickets/actions/register.php')) ?>" class="card shadow-sm border-0">
        <?= csrf_field() ?>
        <div class="card-body row g-3">
            <div class="col-12">
                <label for="property_id" class="form-label">Bien</label>
                <select class="form-select" id="property_id" name="property_id" required>
                    <option value="">Seleccione un bien</option>
                    <?php foreach ($properties as $property) : ?>
                        <option value="<?= e((string) $property['id']) ?>">
                            <?= e((string) $property['ident']) ?> - <?= e((string) $property['description']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <label for="title" class="form-label">Título</label>
                <input type="text" class="form-control" id="title" name="title" maxlength="255" required>
            </div>

            <div class="col-12">
                <label for="description" class="form-label">Descripción</label>
                <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
            </div>

            <div class="col-12">
                <label for="observation" class="form-label">Observación</label>
                <textarea class="form-control" id="observation" name="observation" rows="3"></textarea>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= e(url('tickets.php')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar ticket</button>
        </div>
    </form>
</div>
<?php
$main = ob_get_clean();
include __DIR__ . '/../include/layout.php';
