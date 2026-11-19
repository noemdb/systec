<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/inventory_import.php';
require_login();

$title = 'Dashboard';
$db = db();

$canViewTickets = auth_has_role(array('admin', 'operador', 'tecnico'));
$canViewAssignments = auth_has_role(array('admin', 'operador', 'tecnico'));
$propertyFields = 'id,grupo,subgrupo,seccion,ident,adscription,description,model,color,serial,status';
if ($canViewAssignments) {
    $propertyFields .= ',responsible_name,responsible_status,assigned_name,assigned_status';
}
$properties = $db->index('properties', array(), $propertyFields, 'id ASC');
$completedMaintenanceCount = $db->countRows('maintenances', array('status' => 'finalizado'));
$ticketCount = $canViewTickets
    ? $db->countRows('tickets', array(array('deleted_at', 'IS', null)))
    : 0;
$maintenanceCountsByProperty = $db->maintenanceCountsByProperty();
$ticketCountsByProperty = $db->ticketCountsByProperty();

$statusSummary = $db->properties()->summarizeStatuses($properties);
$propertyCount = $statusSummary['total'];
$goodPropertyCount = $statusSummary['good'];
$attentionPropertyCount = $statusSummary['attention'];

$canImportInventory = auth_has_role(array('admin', 'operador'));
$maintenanceFeedback = isset($_SESSION['dashboard_maintenance_feedback'])
    && is_array($_SESSION['dashboard_maintenance_feedback'])
        ? $_SESSION['dashboard_maintenance_feedback']
        : null;
unset($_SESSION['dashboard_maintenance_feedback']);
$maintenanceFeedbackType = isset($maintenanceFeedback['type']) && is_string($maintenanceFeedback['type'])
    ? $maintenanceFeedback['type']
    : 'info';
if (!in_array($maintenanceFeedbackType, array('success', 'info', 'warning', 'danger'), true)) {
    $maintenanceFeedbackType = 'info';
}
$notifications = $maintenanceFeedback !== null
    ? array(toast_notification(
        $maintenanceFeedbackType,
        isset($maintenanceFeedback['message']) && is_string($maintenanceFeedback['message'])
            ? $maintenanceFeedback['message']
            : ''
    ))
    : array();
$maintenanceFormValues = isset($maintenanceFeedback['values']) && is_array($maintenanceFeedback['values'])
    ? $maintenanceFeedback['values']
    : array();
$maintenanceFormPropertyId = isset($maintenanceFormValues['property_id'])
    ? (int) $maintenanceFormValues['property_id']
    : '';
$maintenanceFormPropertyCode = '';
foreach ($properties as $property) {
    if ((int) $property['id'] === $maintenanceFormPropertyId) {
        $maintenanceFormPropertyCode = property_code($property);
        break;
    }
}
// PERF-01: Las adscripciones y asignaciones se leen del cache local en lugar de
// consultar PostgreSQL en cada GET.
$localCache = legacy_postgres_read_local_cache();
$legacyAdscriptionsByIdent = $localCache['adscriptions'];
$legacyAssignmentsByIdent  = $localCache['assignments'];
$legacySyncAvailable       = $localCache['found'];
$legacySyncCheckedAt       = $localCache['cached_at'] !== null
    ? date('d/m/Y H:i', $localCache['cached_at'])
    : '—';

if ($canViewAssignments && $legacySyncAvailable && $legacyAssignmentsByIdent) {
    foreach ($properties as $index => $property) {
        $ident = str_pad(trim((string) $property['ident']), 4, '0', STR_PAD_LEFT);
        if (isset($legacyAssignmentsByIdent[$ident])) {
            $properties[$index] = array_merge($property, $legacyAssignmentsByIdent[$ident]);
        }
    }
}

$filterMetadata = $db->properties()->buildFilterMetadata(
    $properties,
    $legacyAdscriptionsByIdent,
    $canViewAssignments
);
$adscriptionsByPropertyId = $filterMetadata['adscriptions_by_property_id'];
$adscriptionOptions = $filterMetadata['adscription_options'];
$assignmentFilterOptions = $filterMetadata['assignment_filter_options'];
$assignmentFilterKeysByPropertyId = $filterMetadata['assignment_filter_keys_by_property_id'];

$legacySyncCardClass = $legacySyncAvailable ? 'success' : 'secondary';
$legacySyncIcon      = $legacySyncAvailable ? 'bi-clock-history' : 'bi-question-circle';
$legacySyncStale     = $localCache['stale'] ?? false;

ob_start(); ?>

    <div class="container-fluid text-start">

        <section class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-3"
                 aria-labelledby="dashboard-page-title">
            <div>
                <h1 class="h3 mb-1" id="dashboard-page-title">Dashboard</h1>
                <p class="text-body-secondary mb-0">Resumen general del inventario, condición física de bienes y mantenimientos.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                <a href="<?= e(url('maintenances.php')) ?>" class="btn btn-outline-primary">
                    <i class="bi bi-tools me-1" aria-hidden="true"></i>Mantenimientos
                </a>
                <a href="<?= e(url('report.php')) ?>" class="btn btn-outline-danger">
                    <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Bienes dañados
                </a>
            </div>
        </section>

        <section class="row g-3 mb-4" aria-label="Indicadores del inventario y tickets">
            <div class="col-12 col-sm-6 col-xl">
                <button type="button"
                        class="card h-100 w-100 border-start border-4 border-primary shadow-sm text-start text-body js-inventory-indicator"
                        data-filter-status="" aria-controls="myTable" aria-pressed="false" title="Mostrar todos los bienes">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-primary-subtle text-primary p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-box-seam fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-body-secondary mb-1">Bienes registrados</p>
                            <p class="h3 mb-0"><?= e((string) $propertyCount) ?></p>
                        </div>
                    </div>
                </button>
            </div>
            <div class="col-12 col-sm-6 col-xl">
                <button type="button"
                        class="card h-100 w-100 border-start border-4 border-success shadow-sm text-start text-body js-inventory-indicator"
                        data-filter-status="BUENO" aria-controls="myTable" aria-pressed="false" title="Filtrar bienes en buen estado">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-success-subtle text-success p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-check-circle fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-body-secondary mb-1">En buen estado</p>
                            <p class="h3 mb-0"><?= e((string) $goodPropertyCount) ?></p>
                        </div>
                    </div>
                </button>
            </div>
            <div class="col-12 col-sm-6 col-xl">
                <button type="button"
                        class="card h-100 w-100 border-start border-4 border-danger shadow-sm text-start text-body js-inventory-indicator"
                        data-filter-status="__attention__" aria-controls="myTable" aria-pressed="false" title="Filtrar bienes que requieren atención">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-danger-subtle text-danger p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-exclamation-triangle fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-body-secondary mb-1">Requieren atención</p>
                            <p class="h3 mb-0"><?= e((string) $attentionPropertyCount) ?></p>
                            <p class="small text-body-secondary mb-0">Malo o muy malo</p>
                        </div>
                    </div>
                </button>
            </div>
            <div class="col-12 col-sm-6 col-xl">
                <article class="card h-100 border-start border-4 border-info shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-info-subtle text-info p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-wrench-adjustable-circle fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-body-secondary mb-1">Mantenimientos realizados</p>
                            <p class="h3 mb-0"><?= e((string) $completedMaintenanceCount) ?></p>
                            <p class="small text-body-secondary mb-0">Finalizados</p>
                        </div>
                    </div>
                </article>
            </div>
            <?php if ($canViewTickets) : ?>
                <div class="col-12 col-sm-6 col-xl">
                    <article class="card h-100 border-start border-4 border-primary shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="rounded-circle bg-primary-subtle text-primary p-2 flex-shrink-0" aria-hidden="true">
                                <i class="bi bi-ticket-detailed fs-4"></i>
                            </span>
                            <div>
                                <p class="small text-body-secondary mb-1">Tickets registrados</p>
                                <p class="h3 mb-1"><?= e((string) $ticketCount) ?></p>
                                <a class="small fw-semibold text-decoration-none" href="<?= e(url('tickets.php')) ?>">
                                    Consultar tickets <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endif; ?>
            <div class="col-12 col-sm-6 col-xl">
                <article class="card h-100 border-start border-4 border-<?= e($legacySyncCardClass) ?> shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-<?= e($legacySyncCardClass) ?>-subtle text-<?= e($legacySyncCardClass) ?> p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi <?= e($legacySyncIcon) ?> fs-4"></i>
                        </span>
                        <div class="flex-grow-1">
                            <p class="small text-body-secondary mb-1">Inventario institucional</p>
                            <?php if (!$legacySyncAvailable) : ?>
                                <p class="h6 mb-1">Sin caché local</p>
                                <p class="small text-body-secondary mb-1">
                                    Pendiente de importar o sincronizar.
                                </p>
                            <?php else : ?>
                                <p class="h5 mb-1">
                                    <?= e((string) count($legacyAdscriptionsByIdent)) ?> adscripciones<?= $legacySyncStale ? ' <span class="badge text-bg-warning ms-1">Desactualizado</span>' : '' ?>
                                </p>
                                <p class="small text-body-secondary mb-1">
                                    <?= $legacySyncCheckedAt !== '—' ? 'Sync: ' . e($legacySyncCheckedAt) : 'Datos locales disponibles' ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($canImportInventory) : ?>
                                <a class="small fw-semibold text-decoration-none" href="<?= e(url('updates.php')) ?>">
                                    Importar / actualizar <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <?php include __DIR__ . '/dashboard/filter_bar.php'; ?>

        <section class="card shadow-sm border-0" id="inventoryTableSection" aria-labelledby="inventory-table-title">
            <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <h2 class="h5 mb-0" id="inventory-table-title">
                        <i class="bi bi-box-seam text-primary me-2" aria-hidden="true"></i>Lista de bienes registrados
                    </h2>
                    <p class="small text-body-secondary mb-0" id="inventoryResultCount" role="status" aria-live="polite"></p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            id="toggleTableDensity"
                            aria-pressed="true"
                            aria-controls="myTable"
                            title="Alternar entre vista compacta y vista con todas las columnas">
                        <i class="bi bi-layout-three-columns me-1" aria-hidden="true"></i>
                        <span id="toggleTableDensityLabel">Mostrar todas las columnas</span>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="activeInventoryFilters" class="d-flex flex-wrap gap-2 mb-2"
                     aria-label="Filtros activos"></div>

                <?php include __DIR__ . '/dashboard/table/index.php'; ?>

            </div>
        </section>

    </div>

    <style nonce="<?= e(systec_csp_nonce()) ?>">
        .js-inventory-indicator {
            appearance: none;
            font: inherit;
            cursor: pointer;
            transition: box-shadow .15s ease, transform .15s ease;
        }

        .js-inventory-indicator:hover {
            transform: translateY(-1px);
        }

        .js-inventory-indicator:focus-visible,
        .js-inventory-indicator[aria-pressed="true"] {
            outline: 0;
            box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .2), var(--bs-box-shadow-sm);
        }

        @media (prefers-reduced-motion: reduce) {
            .js-inventory-indicator {
                transition: none;
            }

            .js-inventory-indicator:hover {
                transform: none;
            }
        }

        #myTable.is-compact-view .col-extended-detail {
            display: none;
        }

        #myTable:not(.is-compact-view) .compact-only-detail {
            display: none;
        }

        .maintenance-modal-form {
            display: flex;
            flex: 1 1 auto;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }

        .maintenance-modal-form .modal-body {
            min-height: 0;
            overflow-y: auto;
        }
    </style>

    <?php include __DIR__ . '/include/dashboard_register_maintenance_modal.php'; ?>

    <script nonce="<?= e(systec_csp_nonce()) ?>">
        window.SystecDashboardConfig = {
            search: <?= js(isset($_GET['search']) ? $_GET['search'] : '') ?>,
            totalPropertyCount: <?= js($propertyCount) ?>,
            openMaintenanceModalOnLoad: <?= js(($maintenanceFeedback['type'] ?? '') === 'danger') ?>,
            cspNonce: <?= js(systec_csp_nonce()) ?>
        };
    </script>
    <script nonce="<?= e(systec_csp_nonce()) ?>" src="<?= e(url('assets/js/dashboard.js')) ?>"></script>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
