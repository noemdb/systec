<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/inventory_import.php';

require_role(array('admin', 'operador'));

$title = 'Sincronizar asignaciones';
$db = db();
$notice = isset($_SESSION['property_assignment_sync_notice'])
    && is_array($_SESSION['property_assignment_sync_notice'])
    ? $_SESSION['property_assignment_sync_notice']
    : null;
unset($_SESSION['property_assignment_sync_notice']);
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!isset($_POST['confirm_sync']) || $_POST['confirm_sync'] !== '1') {
        $errorMessage = 'Confirma que deseas sincronizar las asignaciones desde PostgreSQL legacy.';
    } else {
        try {
            $properties = $db->index(
                'properties',
                array(),
                'id,ident,responsible_name,responsible_status,assigned_name,assigned_status',
                'id ASC'
            );
            $identifiers = array_map(function ($property) {
                return trim((string) $property['ident']);
            }, $properties);
            $snapshot = legacy_postgres_inventory_snapshot($identifiers, false, true);

            if (!isset($snapshot['assignments']) || !is_array($snapshot['assignments'])) {
                throw new RuntimeException('La consulta legacy no devolvió asignaciones válidas.');
            }

            $sourceCount = count($snapshot['assignments']);
            $updatedCount = $db->syncPropertyAssignments($properties, $snapshot['assignments']);
            $_SESSION['property_assignment_sync_notice'] = array(
                'source_count' => $sourceCount,
                'updated_count' => $updatedCount,
            );

            header('Location: ' . url('sync-assignments.php'), true, 303);
            exit;
        } catch (Throwable $e) {
            error_log('No se pudieron sincronizar las asignaciones desde legacy: ' . $e->getMessage());
            $errorMessage = 'No se pudo completar la sincronización. Los datos locales existentes se conservaron.';
        }
    }
}

ob_start(); ?>
<div class="container-fluid text-start">
    <section class="mb-3" aria-labelledby="page-title">
        <h1 class="h3 mb-1" id="page-title">Sincronizar asignaciones de bienes</h1>
        <p class="text-body-secondary mb-0">
            Actualiza desde PostgreSQL legacy los nombres y estados de responsables y usuarios asignados a cada bien.
        </p>
    </section>

    <?php if ($notice !== null) : ?>
        <div class="alert alert-success" role="status" aria-live="polite">
            <i class="bi bi-check-circle me-1" aria-hidden="true"></i>
            Sincronización completada: legacy devolvió asignaciones para
            <?= e((string) ($notice['source_count'] ?? 0)) ?> bienes;
            <?= e((string) ($notice['updated_count'] ?? 0)) ?> registros cambiaron en SysTec.
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== '') : ?>
        <div class="alert alert-danger" role="alert"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm" aria-labelledby="sync-card-title">
        <div class="card-header">
            <h2 class="h5 mb-0" id="sync-card-title">
                <i class="bi bi-arrow-repeat text-primary me-2" aria-hidden="true"></i>Sincronización manual
            </h2>
        </div>
        <div class="card-body">
            <p class="mb-3">
                La consulta a PostgreSQL es estrictamente de solo lectura. SysTec guardará localmente únicamente
                el nombre y estado de cada asignación; no sincroniza cédulas, cuentas de acceso ni roles.
            </p>
            <form method="post" action="<?= e(url('sync-assignments.php')) ?>">
                <?= csrf_field() ?>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirmAssignmentSync"
                           name="confirm_sync" value="1" required>
                    <label class="form-check-label" for="confirmAssignmentSync">
                        Confirmo actualizar las asignaciones locales con los datos disponibles en legacy.
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Sincronizar desde legacy
                </button>
            </form>
        </div>
    </section>
</div>
<?php
$main = ob_get_clean();
include __DIR__ . '/include/layout.php';
