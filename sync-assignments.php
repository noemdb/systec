<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/inventory_import.php';

require_role(array('admin', 'operador'));

$title = 'Sincronizar asignaciones';
$db = db();
$currentUser = auth_user();
$actorUsername = is_array($currentUser) && isset($currentUser['username']) ? (string) $currentUser['username'] : 'usuario';
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
            legacy_postgres_write_local_cache(
                isset($snapshot['adscriptions']) && is_array($snapshot['adscriptions']) ? $snapshot['adscriptions'] : array(),
                $snapshot['assignments'],
                $identifiers
            );
            error_log("Auditoría asignaciones: '{$actorUsername}' sincronizó {$sourceCount} asignaciones ({$updatedCount} actualizadas en SQLite).");
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

$coverageRows = $db->fetchAllQuery(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN TRIM(COALESCE(responsible_name, '')) != '' THEN 1 ELSE 0 END), 0) AS with_responsible,
        COALESCE(SUM(CASE WHEN TRIM(COALESCE(assigned_name, '')) != '' THEN 1 ELSE 0 END), 0) AS with_assigned
     FROM properties"
);
$coverage = $coverageRows ? $coverageRows[0] : array('total' => 0, 'with_responsible' => 0, 'with_assigned' => 0);
$totalProperties = (int) ($coverage['total'] ?? 0);
$withResponsible = (int) ($coverage['with_responsible'] ?? 0);
$withAssigned = (int) ($coverage['with_assigned'] ?? 0);

$localCache = legacy_postgres_read_local_cache();
$legacySyncAvailable = !empty($localCache['found']);
$legacySyncStale = !empty($localCache['stale']);
$legacySyncRelativeAge = format_relative_age(
    isset($localCache['age_seconds']) && is_int($localCache['age_seconds']) ? $localCache['age_seconds'] : null
);
$syncColor = !$legacySyncAvailable ? 'secondary' : ($legacySyncStale ? 'warning' : 'success');

ob_start(); ?>
<div class="container-fluid text-start">
    <section class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3"
             aria-labelledby="page-title">
        <div>
            <h1 class="h3 mb-1" id="page-title">Sincronizar asignaciones de bienes</h1>
            <p class="text-body-secondary mb-0">
                Actualiza desde PostgreSQL legacy los nombres y estados de responsables y usuarios asignados a cada bien.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-self-md-start flex-shrink-0">
            <a href="<?= e(url('updates.php')) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-box-arrow-in-down me-1" aria-hidden="true"></i>Importar bienes
            </a>
            <a href="<?= e(url('dashboard.php')) ?>" class="btn btn-outline-primary">
                <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Ir al Dashboard
            </a>
        </div>
    </section>

    <section class="row g-3 mb-4" aria-label="Cobertura actual de asignaciones">
        <div class="col-6 col-md-3">
            <article class="card h-100 border-start border-4 border-primary shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-primary-subtle text-primary p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-box-seam fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Total bienes</p>
                        <p class="h3 mb-0"><?= e((string) $totalProperties) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-6 col-md-3">
            <article class="card h-100 border-start border-4 border-info shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-info-subtle text-info p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-person-badge fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Con responsable</p>
                        <p class="h3 mb-0"><?= e((string) $withResponsible) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-6 col-md-3">
            <article class="card h-100 border-start border-4 border-success shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-success-subtle text-success p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-person-check fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Con usuario asignado</p>
                        <p class="h3 mb-0"><?= e((string) $withAssigned) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-6 col-md-3">
            <article class="card h-100 border-start border-4 border-<?= e($syncColor) ?> shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-<?= e($syncColor) ?>-subtle text-<?= e($syncColor) ?> p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-clock-history fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Última sincronización</p>
                        <p class="h6 mb-0">
                            <?= !$legacySyncAvailable ? 'Sin caché' : ($legacySyncStale ? 'Desactualizado' : 'Sincronizado') ?>
                        </p>
                        <p class="small text-body-secondary mb-0"><?= e($legacySyncRelativeAge) ?></p>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <?php if ($notice !== null) : ?>
        <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-2" role="status" aria-live="polite">
            <div>
                <i class="bi bi-check-circle me-1" aria-hidden="true"></i>
                Sincronización completada: legacy devolvió asignaciones para
                <strong><?= e((string) ($notice['source_count'] ?? 0)) ?></strong> bienes;
                <strong><?= e((string) ($notice['updated_count'] ?? 0)) ?></strong> registros cambiaron en SysTec.
            </div>
            <a href="<?= e(url('dashboard.php')) ?>" class="btn btn-sm btn-success">
                Ver en Dashboard
            </a>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== '') : ?>
        <div class="alert alert-danger" role="alert"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm border-0" aria-labelledby="sync-card-title">
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
            <form method="post" action="<?= e(url('sync-assignments.php')) ?>" id="syncAssignmentsForm">
                <?= csrf_field() ?>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirmAssignmentSync"
                           name="confirm_sync" value="1" required>
                    <label class="form-check-label" for="confirmAssignmentSync">
                        Confirmo actualizar las asignaciones locales con los datos disponibles en legacy.
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" id="syncAssignmentsSubmit">
                    <i class="bi bi-arrow-repeat me-1" id="syncAssignmentsSubmitIcon" aria-hidden="true"></i>
                    <span id="syncAssignmentsSubmitLabel">Sincronizar desde legacy</span>
                </button>
            </form>
        </div>
    </section>
</div>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    (function () {
        const form = document.getElementById('syncAssignmentsForm');
        const submitButton = document.getElementById('syncAssignmentsSubmit');
        const submitIcon = document.getElementById('syncAssignmentsSubmitIcon');
        const submitLabel = document.getElementById('syncAssignmentsSubmitLabel');
        if (form && submitButton) {
            form.addEventListener('submit', () => {
                submitButton.disabled = true;
                if (submitIcon) submitIcon.className = 'spinner-border spinner-border-sm me-2';
                if (submitLabel) submitLabel.textContent = 'Sincronizando…';
            });
        }
    })();
</script>
<?php
$main = ob_get_clean();
include __DIR__ . '/include/layout.php';
