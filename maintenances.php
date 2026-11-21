<?php

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/inventory_import.php';
require_login();
require_non_personal_area();

$title = 'Mantenimiento';
$maintenanceNotice = isset($_SESSION['maintenance_notice']) && is_string($_SESSION['maintenance_notice'])
    ? trim($_SESSION['maintenance_notice'])
    : '';
$maintenanceNoticeType = isset($_SESSION['maintenance_notice_type']) && is_string($_SESSION['maintenance_notice_type'])
    ? $_SESSION['maintenance_notice_type']
    : 'success';
if (!in_array($maintenanceNoticeType, array('success', 'info', 'warning', 'danger'), true)) {
    $maintenanceNoticeType = 'success';
}
unset($_SESSION['maintenance_notice'], $_SESSION['maintenance_notice_type']);
$notifications = $maintenanceNotice !== ''
    ? array(toast_notification($maintenanceNoticeType, $maintenanceNotice))
    : array();

$db = db();
$maintenances = $db->index('maintenances', array(), '*', 'id DESC');
$canEditMaintenance = auth_has_role(array('admin', 'operador', 'tecnico'));
$canViewTickets = auth_can_view_tickets();
$canViewAssignments = auth_has_role(array('admin', 'operador', 'tecnico'));
$ticketCount = $canViewTickets
    ? $db->countRows('tickets', array(array('deleted_at', 'IS', null)))
    : 0;
$ticketCountsByProperty = $canViewTickets ? $db->ticketCountsByProperty() : array();

$propertyFields = 'id,grupo,subgrupo,seccion,ident,adscription,description,model,color,status';
if ($canViewAssignments) {
    $propertyFields .= ',responsible_name,responsible_status,assigned_name,assigned_status';
}
$propertiesForSelect = $db->index('properties', array(), $propertyFields, 'id ASC');

// PERF-01: Leer adscripciones y asignaciones desde el caché local sin bloquear el GET con PostgreSQL.
$localCache = legacy_postgres_read_local_cache();
$legacyAdscriptionsByIdent = $localCache['adscriptions'];
$legacyAssignmentsByIdent = $localCache['assignments'];
$legacySyncAvailable = !empty($localCache['found']);
$legacySyncStale = !empty($localCache['stale']);

if ($canViewAssignments && $legacySyncAvailable && $legacyAssignmentsByIdent) {
    foreach ($propertiesForSelect as $index => $propertyRow) {
        $ident = str_pad(trim((string) $propertyRow['ident']), 4, '0', STR_PAD_LEFT);
        if (isset($legacyAssignmentsByIdent[$ident])) {
            $propertiesForSelect[$index] = array_merge($propertyRow, $legacyAssignmentsByIdent[$ident]);
        }
    }
}

$filterMetadata = $db->properties()->buildFilterMetadata(
    $propertiesForSelect,
    $legacyAdscriptionsByIdent,
    $canViewAssignments
);
$propertyCategories = $filterMetadata['property_categories'];
$propertiesById = $filterMetadata['properties_by_id'];
$assignmentFilterOptions = $filterMetadata['assignment_filter_options'];
$assignmentFilterKeysByPropertyId = $filterMetadata['assignment_filter_keys_by_property_id'];
$adscriptionsByPropertyId = $filterMetadata['adscriptions_by_property_id'];
$adscriptionOptions = $filterMetadata['adscription_options'];
$localAdscriptionCount = $filterMetadata['local_adscription_count'];

$adscriptionSourceWarning = '';
if (!$legacySyncAvailable) {
    $adscriptionSourceWarning = $localAdscriptionCount > 0
        ? 'Sin caché institucional reciente; se usan las adscripciones locales guardadas.'
        : 'Sin caché institucional ni adscripciones locales; sincroniza el inventario para ver unidades.';
} elseif ($legacySyncStale) {
    $adscriptionSourceWarning = 'Caché institucional desactualizado (>24 h); se muestran los últimos datos guardados.';
}

$maintenanceCounts = $db->maintenances()->summarizeCounts($maintenances);

$id = array_key_exists('id', $_GET) ? $_GET['id'] : null;
$property_id = array_key_exists('property_id', $_GET) ? $_GET['property_id'] : null;
$modeCreate = array_key_exists('modeCreate', $_GET) ? $_GET['modeCreate'] : null;
$modeEdit = array_key_exists('modeEdit', $_GET) ? $_GET['modeEdit'] : null;
$modeIndex = (empty($modeCreate) && empty($modeEdit)) ? 'true' : null;
$property = ($property_id !== null && $property_id !== '') ? $db->getFirstForId('properties', $property_id) : null;
$itemArrSeleted = ($id !== null && $id !== '') ? ($db->getFirstForId('maintenances', $id) ?: array()) : array();

ob_start(); ?>

    <div class="container-fluid text-start">
        <section class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-3"
                 aria-labelledby="maintenance-page-title">
            <div>
                <h1 class="h3 mb-1" id="maintenance-page-title">Mantenimientos</h1>
                <p class="text-body-secondary mb-0">Consulta, registra y da seguimiento a los mantenimientos de los bienes.</p>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2 flex-shrink-0">
                <button type="button" class="btn btn-outline-primary" id="printMaintenanceHistories"
                        <?= $maintenanceCounts['total'] === 0 ? 'disabled' : '' ?>
                        title="Imprime un historial por bien según los filtros aplicados">
                    <i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir historiales
                </button>
                <button type="button" class="btn btn-outline-secondary" id="exportMaintenanceCsv"
                        <?= $maintenanceCounts['total'] === 0 ? 'disabled' : '' ?>
                        title="Descarga en CSV los mantenimientos filtrados">
                    <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Exportar CSV
                </button>
                <?php if ($canEditMaintenance) : ?>
                    <button type="button" class="btn btn-primary"
                            data-bs-toggle="offcanvas" data-bs-target="#createMaintenanceDrawer"
                            aria-controls="createMaintenanceDrawer">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Registrar mantenimiento
                    </button>
                <?php endif; ?>
            </div>
        </section>

        <?php
        $bulkUpdated = isset($_GET['bulk_updated']) ? max(0, (int) $_GET['bulk_updated']) : null;
        $bulkSkipped = isset($_GET['bulk_skipped']) ? max(0, (int) $_GET['bulk_skipped']) : 0;
        ?>
        <?php if ($bulkUpdated !== null) : ?>
            <div class="alert alert-<?= $bulkUpdated > 0 ? 'success' : 'warning' ?> d-flex align-items-start gap-2"
                 role="status">
                <i class="bi <?= $bulkUpdated > 0 ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> mt-1"
                   aria-hidden="true"></i>
                <div>
                    Se actualizaron <strong><?= e((string) $bulkUpdated) ?></strong>
                    <?= $bulkUpdated === 1 ? 'mantenimiento' : 'mantenimientos' ?>.
                    <?php if ($bulkSkipped > 0) : ?>
                        Se omitieron <strong><?= e((string) $bulkSkipped) ?></strong>
                        <?= $bulkSkipped === 1 ? 'registro' : 'registros' ?> que no permitían esa transición.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <section class="row g-3 mb-4" aria-label="Resumen de mantenimientos y tickets">
            <?php
            $indicators = array(
                array('total', 'Total', $maintenanceCounts['total'], 'primary', 'bi-tools', 'Quitar todos los filtros'),
                array('iniciado', 'Iniciados', $maintenanceCounts['iniciado'], 'info', 'bi-play-circle', 'Filtrar mantenimientos iniciados'),
                array('revision', 'En revisión', $maintenanceCounts['revision'], 'warning', 'bi-search', 'Filtrar mantenimientos en revisión'),
                array('finalizado', 'Finalizados', $maintenanceCounts['finalizado'], 'success', 'bi-check-circle', 'Filtrar mantenimientos finalizados'),
                array('vencido', 'Vencidos', $maintenanceCounts['vencido'], 'danger', 'bi-exclamation-triangle', 'Mostrar solo mantenimientos vencidos'),
            );
            ?>
            <?php foreach ($indicators as $indicator) : ?>
                <div class="col-6 col-md">
                    <button type="button"
                            class="card h-100 w-100 border-start border-4 border-<?= e($indicator[3]) ?> shadow-sm text-start text-body js-kpi-filter"
                            data-kpi="<?= e($indicator[0]) ?>"
                            aria-controls="myTable" aria-pressed="false"
                            title="<?= e($indicator[5]) ?>">
                        <div class="card-body d-flex align-items-center gap-2">
                            <span class="rounded-circle bg-<?= e($indicator[3]) ?>-subtle text-<?= e($indicator[3]) ?> p-2"
                                  aria-hidden="true">
                                <i class="bi <?= e($indicator[4]) ?> fs-4"></i>
                            </span>
                            <div class="flex-grow-1">
                                <p class="small text-body-secondary mb-0"><?= e($indicator[1]) ?></p>
                                <p class="h3 mb-0" data-kpi-count="<?= e($indicator[0]) ?>"><?= e((string) $indicator[2]) ?></p>
                            </div>
                        </div>
                    </button>
                </div>
            <?php endforeach; ?>
            <?php if ($canViewTickets) : ?>
                <div class="col-6 col-md">
                    <article class="card h-100 border-start border-4 border-primary shadow-sm position-relative maintenance-link-card">
                        <div class="card-body d-flex align-items-center gap-2">
                            <span class="rounded-circle bg-primary-subtle text-primary p-2" aria-hidden="true">
                                <i class="bi bi-ticket-detailed fs-4"></i>
                            </span>
                            <div class="flex-grow-1">
                                <p class="small text-body-secondary mb-0">Tickets activos</p>
                                <p class="h3 mb-0"><?= e((string) $ticketCount) ?></p>
                                <a href="<?= e(url('tickets.php?status=abiertos')) ?>"
                                   class="stretched-link"
                                   aria-label="Consultar los <?= e((string) $ticketCount) ?> tickets activos"></a>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endif; ?>
        </section>

        <?php include __DIR__ . '/maintenances/form/edit_card.php'; ?>

        <?php if ($maintenanceCounts['total'] === 0 && empty($modeEdit)) : ?>
            <section class="card shadow-sm border-0 text-center" aria-label="Sin mantenimientos registrados">
                <div class="card-body py-5" role="status" aria-live="polite">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary p-3 mb-3"
                          aria-hidden="true">
                        <i class="bi bi-tools fs-3"></i>
                    </span>
                    <h2 class="h5 mb-1">Aún no hay mantenimientos registrados</h2>
                    <p class="text-body-secondary mb-3">
                        Registra la primera intervención preventiva, correctiva, predictiva o evaluativa de un bien.
                    </p>
                    <?php if ($canEditMaintenance) : ?>
                        <button type="button" class="btn btn-primary"
                                data-bs-toggle="offcanvas" data-bs-target="#createMaintenanceDrawer"
                                aria-controls="createMaintenanceDrawer">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Registrar primer mantenimiento
                        </button>
                    <?php endif; ?>
                </div>
            </section>
        <?php else : ?>
            <section class="card shadow-sm" aria-labelledby="maintenance-list-title">
                <div class="card-header d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                    <h2 class="h5 mb-0" id="maintenance-list-title">
                        <i class="bi bi-list-task text-primary me-2" aria-hidden="true"></i>Registro de mantenimientos
                    </h2>
                    <span class="badge text-bg-light border text-body-secondary" id="maintenanceResultCount"
                          role="status" aria-live="polite">
                        <?= e((string) $maintenanceCounts['total']) ?> registros
                    </span>
                </div>
                <div class="card-body">
                    <?php include __DIR__ . '/maintenances/table/crud.php'; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <?php if ($canEditMaintenance) : ?>
        <?php include __DIR__ . '/maintenances/form/create_drawer.php'; ?>
    <?php endif; ?>

    <style nonce="<?= e(systec_csp_nonce()) ?>">
        .maintenance-create-drawer {
            --bs-offcanvas-width: min(34rem, 100vw);
        }

        .js-kpi-filter,
        .maintenance-link-card {
            transition: box-shadow .15s ease, transform .15s ease;
        }

        .js-kpi-filter {
            appearance: none;
            font: inherit;
            cursor: pointer;
        }

        .js-kpi-filter:hover,
        .maintenance-link-card:hover {
            transform: translateY(-1px);
        }

        .js-kpi-filter:focus-visible,
        .js-kpi-filter[aria-pressed="true"],
        .maintenance-link-card:focus-within {
            outline: 0;
            box-shadow: 0 0 0 .2rem rgba(var(--bs-emphasis-color-rgb, 33, 37, 41), .3),
                        var(--bs-box-shadow-sm, 0 .125rem .25rem rgba(0, 0, 0, .075));
        }

        @media (prefers-reduced-motion: reduce) {
            .js-kpi-filter,
            .maintenance-link-card {
                transition: none;
            }

            .js-kpi-filter:hover,
            .maintenance-link-card:hover {
                transform: none;
            }
        }

        .maintenance-create-drawer-form {
            display: flex;
            flex: 1 1 auto;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }

        .maintenance-create-drawer-form .offcanvas-body {
            min-height: 0;
            overflow-y: auto;
        }

        .maintenance-create-drawer-form .card-body {
            padding: 0;
        }

        .maintenance-description-cell {
            min-width: 16rem;
            overflow-wrap: anywhere;
        }

        @media (min-width: 992px) {
            .maintenance-edit-form .card-body:first-of-type {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0 1rem;
                align-items: start;
            }

            .maintenance-edit-form .property-picker {
                grid-column: 1 / -1;
            }
        }
    </style>

    <script nonce="<?= e(systec_csp_nonce()) ?>" src="<?= e(url('assets/js/property-picker.js')) ?>"></script>
    <script nonce="<?= e(systec_csp_nonce()) ?>">
        window.SystecMaintenancesConfig = {
            search: <?= js(isset($_GET['search']) ? $_GET['search'] : '') ?>,
            totalMaintenanceCount: <?= js($maintenanceCounts['total']) ?>,
            canViewTickets: <?= js($canViewTickets) ?>,
            improveMaintenanceNarrativeUrl: <?= js(url('maintenances/actions/improve-narrative.php')) ?>,
            propertyTicketsUrl: <?= js(url('maintenances/actions/property-tickets.php')) ?>,
            registerMaintenanceUrl: <?= js(url('maintenances/register.php')) ?>,
            updateMaintenanceUrl: <?= js(url('maintenances/update.php')) ?>,
            printUrl: <?= js(url('maintenances/print.php')) ?>
        };
    </script>
    <script nonce="<?= e(systec_csp_nonce()) ?>" src="<?= e(url('assets/js/maintenances.js')) ?>"></script>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
