<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/inventory_import.php';
require_login();

$title = 'Dashboard';
$db = db();

$properties = $db->index('properties', array(), '*', 'id ASC');
$completedMaintenanceCount = $db->countRows('maintenances', array('status' => 'finalizado'));
$canViewTickets = auth_has_role(array('admin', 'operador', 'tecnico'));
$ticketCount = $canViewTickets
    ? $db->countRows('tickets', array(array('deleted_at', 'IS', null)))
    : 0;
$maintenanceCountsByProperty = $db->maintenanceCountsByProperty();
$propertyCount = count($properties);
$goodPropertyCount = 0;
$attentionPropertyCount = 0;

foreach ($properties as $property) {
    $status = mb_strtoupper(trim((string) $property['status']));
    if ($status === 'BUENO') {
        $goodPropertyCount++;
    }
    if ($status === 'MALO' || $status === 'MUY MALO') {
        $attentionPropertyCount++;
    }
}

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
$legacySyncAvailable = false;
$legacySyncPendingCount = 0;
$legacySyncSourceCount = 0;
$legacySyncCheckedAt = date('d/m/Y H:i');
$localPropertiesByIdent = array();
$legacyAdscriptionsByIdent = array();

foreach ($properties as $property) {
    $ident = str_pad(trim((string) $property['ident']), 4, '0', STR_PAD_LEFT);
    if (!isset($localPropertiesByIdent[$ident])) {
        $localPropertiesByIdent[$ident] = $property;
    }
}

try {
    $localPropertyIdentifiers = array_map(function ($property) {
        return trim((string) $property['ident']);
    }, $properties);
    $legacySnapshot = legacy_postgres_inventory_snapshot($localPropertyIdentifiers);
    $legacyRows = $legacySnapshot['rows'];
    $legacyAdscriptionsByIdent = $legacySnapshot['adscriptions'];
    $legacySyncSourceCount = count($legacyRows);
    foreach ($legacyRows as $row) {
        $ident = str_pad(trim((string) $row[3]), 4, '0', STR_PAD_LEFT);
        if (!isset($localPropertiesByIdent[$ident])) {
            $legacySyncPendingCount++;
        }
    }
    $legacySyncAvailable = true;
} catch (Throwable $e) {
    error_log('Dashboard no pudo verificar la sincronización con PostgreSQL legacy: ' . $e->getMessage());
}

$adscriptionsByPropertyId = array();
$adscriptionOptions = array();
foreach ($properties as $property) {
    $propertyId = (int) $property['id'];
    $adscription = property_adscription_assignment($property, $legacyAdscriptionsByIdent);
    $adscriptionsByPropertyId[$propertyId] = $adscription;
    $adscriptionOptions[$adscription['key']] = $adscription['label'];
}
asort($adscriptionOptions, SORT_NATURAL | SORT_FLAG_CASE);

$legacySyncCardClass = $legacySyncAvailable
    ? ($legacySyncPendingCount === 0 ? 'success' : 'warning')
    : 'secondary';
$legacySyncIcon = $legacySyncAvailable
    ? ($legacySyncPendingCount === 0 ? 'bi-check-circle' : 'bi-exclamation-circle')
    : 'bi-question-circle';

ob_start(); ?>

    <div class="container-fluid">

        <h2 class="">Dashboard</h2>
        <p class="">Bienvenido!</p>

        <section class="row g-3 mb-4" aria-label="Indicadores del inventario y tickets">
            <div class="col-12 col-sm-6 col-xl">
                <button type="button"
                        class="card h-100 w-100 border-start border-4 border-primary shadow-sm text-start text-body js-inventory-indicator"
                        data-filter-status="" aria-controls="myTable" aria-pressed="false" title="Mostrar todos los bienes">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-primary-subtle text-primary p-3" aria-hidden="true">
                            <i class="bi bi-box-seam fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-muted mb-1">Bienes registrados</p>
                            <p class="h3 mb-0"><?= e($propertyCount) ?></p>
                        </div>
                    </div>
                </button>
            </div>
            <div class="col-12 col-sm-6 col-xl">
                <button type="button"
                        class="card h-100 w-100 border-start border-4 border-success shadow-sm text-start text-body js-inventory-indicator"
                        data-filter-status="BUENO" aria-controls="myTable" aria-pressed="false" title="Filtrar bienes en buen estado">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-success-subtle text-success p-3" aria-hidden="true">
                            <i class="bi bi-check-circle fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-muted mb-1">En buen estado</p>
                            <p class="h3 mb-0"><?= e($goodPropertyCount) ?></p>
                        </div>
                    </div>
                </button>
            </div>
            <div class="col-12 col-sm-6 col-xl">
                <button type="button"
                        class="card h-100 w-100 border-start border-4 border-danger shadow-sm text-start text-body js-inventory-indicator"
                        data-filter-status="__attention__" aria-controls="myTable" aria-pressed="false" title="Filtrar bienes que requieren atención">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-danger-subtle text-danger p-3" aria-hidden="true">
                            <i class="bi bi-exclamation-triangle fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-muted mb-1">Requieren atención</p>
                            <p class="h3 mb-0"><?= e($attentionPropertyCount) ?></p>
                            <p class="small text-muted mb-0">Malo o muy malo</p>
                        </div>
                    </div>
                </button>
            </div>
            <div class="col-12 col-sm-6 col-xl">
                <article class="card h-100 border-start border-4 border-info shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-info-subtle text-info p-3" aria-hidden="true">
                            <i class="bi bi-wrench-adjustable-circle fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-muted mb-1">Mantenimientos realizados</p>
                            <p class="h3 mb-0"><?= e($completedMaintenanceCount) ?></p>
                            <p class="small text-muted mb-0">Finalizados</p>
                        </div>
                    </div>
                </article>
            </div>
            <?php if ($canViewTickets) : ?>
                <div class="col-12 col-sm-6 col-xl">
                    <article class="card h-100 border-start border-4 border-primary shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="rounded-circle bg-primary-subtle text-primary p-3" aria-hidden="true">
                                <i class="bi bi-ticket-detailed fs-4"></i>
                            </span>
                            <div>
                                <p class="small text-muted mb-1">Tickets registrados</p>
                                <p class="h3 mb-1"><?= e($ticketCount) ?></p>
                                <a class="small fw-semibold" href="<?= e(url('tickets.php')) ?>">
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
                        <span class="rounded-circle bg-<?= e($legacySyncCardClass) ?>-subtle text-<?= e($legacySyncCardClass) ?> p-3" aria-hidden="true">
                            <i class="bi <?= e($legacySyncIcon) ?> fs-4"></i>
                        </span>
                        <div class="flex-grow-1">
                            <p class="small text-muted mb-1">Sincronización con legacy</p>
                            <?php if (!$legacySyncAvailable) : ?>
                                <p class="h5 mb-1">No disponible</p>
                                <p class="small text-muted mb-2">
                                    No se pudo consultar PostgreSQL. Último intento: <?= e($legacySyncCheckedAt) ?>.
                                    <a href="<?= e(url('dashboard.php')) ?>">Volver a comprobar</a>.
                                </p>
                            <?php elseif ($legacySyncPendingCount === 0) : ?>
                                <p class="h5 mb-1">Sin faltantes por identificador</p>
                                <p class="small text-muted mb-2">
                                    Los <?= e($legacySyncSourceCount) ?> bienes del origen ya existen en SysTec.
                                    Esta comprobación solo compara identificadores; los estados locales se conservan.
                                    Verificado: <?= e($legacySyncCheckedAt) ?>.
                                </p>
                            <?php else : ?>
                                <p class="h5 mb-1">Faltan bienes en SysTec</p>
                                <p class="small text-muted mb-2">
                                    <?= e($legacySyncPendingCount) ?> identificadores del origen aún no están registrados.
                                    Verificado: <?= e($legacySyncCheckedAt) ?>.
                                </p>
                            <?php endif; ?>
                            <?php if ($legacySyncAvailable && $legacySyncPendingCount > 0 && !$canImportInventory) : ?>
                                <p class="small text-muted mb-0">Solicita a un administrador u operador que importe los bienes pendientes.</p>
                            <?php endif; ?>
                            <?php if ($canImportInventory) : ?>
                                <a class="small fw-semibold" href="<?= e(url('updates.php')) ?>">
                                    Importar bienes <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section class="card mb-4 shadow-sm text-start" aria-labelledby="inventory-filters-title">
            <div class="card-body">
                <h3 class="h6 mb-3" id="inventory-filters-title">Filtrar inventario</h3>
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold" for="filterCategory">Categoría</label>
                        <select class="form-select" id="filterCategory">
                            <option value="">Todas las categorías</option>
                            <option value="informatica">Informática</option>
                            <option value="redes">Redes y telecomunicaciones</option>
                            <option value="otros">Otros bienes</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold" for="filterStatus">Estado</label>
                        <select class="form-select" id="filterStatus">
                            <option value="">Todos los estados</option>
                            <option value="__attention__">Requieren atención</option>
                            <option value="BUENO">Bueno</option>
                            <option value="REGULAR">Regular</option>
                            <option value="MALO">Malo</option>
                            <option value="MUY MALO">Muy malo</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold" for="filterAdscription">Adscripción / unidad operativa</label>
                        <select class="form-select" id="filterAdscription">
                            <option value="">Todas las unidades</option>
                            <?php foreach ($adscriptionOptions as $key => $label) : ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <button type="button" class="btn btn-outline-secondary" id="resetInventoryFilters">
                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Limpiar filtros
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <div class="card" id="inventoryTableSection">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-2">
                    <h3 class="h5 mb-0">Lista de bienes registrados</h3>
                    <p class="small text-body-secondary mb-0" id="inventoryResultCount" aria-live="polite"></p>
                </div>
                <div id="activeInventoryFilters" class="d-flex flex-wrap gap-2 mb-2"
                     aria-label="Filtros activos"></div>

                <?php include __DIR__ . '/dashboard/table/index.php'; ?>

            </div>
        </div>

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

    <div class="modal fade" id="registerMaintenanceModal" tabindex="-1"
         aria-labelledby="registerMaintenanceModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content text-start">
                <form class="maintenance-modal-form" action="<?= e(url('maintenances/register.php')) ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="property_id" id="maintenancePropertyId"
                           value="<?= e($maintenanceFormPropertyId) ?>">
                    <input type="hidden" name="return_to" value="dashboard">
                    <input type="hidden" name="return_category" value="">
                    <input type="hidden" name="return_status" value="">
                    <input type="hidden" name="return_adscription" value="">
                    <input type="hidden" name="return_search" value="">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title fs-5" id="registerMaintenanceModalTitle">Registrar mantenimiento</h3>
                            <p class="small text-muted mb-0">Bien: <span id="maintenancePropertyCode"><?= e($maintenanceFormPropertyCode) ?></span></p>
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

    <script type="text/javascript" nonce="<?= e(systec_csp_nonce()) ?>">
        const search = <?= js(isset($_GET['search']) ? $_GET['search'] : '') ?>;
        const dashboardParams = new URLSearchParams(window.location.search);
        const categoryFilter = document.getElementById('filterCategory');
        const statusFilter = document.getElementById('filterStatus');
        const adscriptionFilter = document.getElementById('filterAdscription');
        categoryFilter.value = dashboardParams.get('category') ?? '';
        statusFilter.value = dashboardParams.get('status') ?? '';
        adscriptionFilter.value = dashboardParams.get('adscription') ?? '';

        const table = new DataTable('#myTable', {
            search: {
                search: dashboardParams.get('search') ?? search ?? ''
            },
            order: [4, 'asc'],
            language: {
                search: 'Buscar bienes: _INPUT_',
                searchPlaceholder: 'Código, descripción, modelo, color, serial...',
                emptyTable: 'No hay bienes registrados.',
                zeroRecords: 'Ningún bien coincide con los filtros aplicados.'
            }
        });

        const inventoryIndicators = document.querySelectorAll('.js-inventory-indicator');
        const activeFiltersContainer = document.getElementById('activeInventoryFilters');
        const resultCount = document.getElementById('inventoryResultCount');
        const totalPropertyCount = <?= js($propertyCount) ?>;

        const updateFilterFeedback = () => {
            const activeFilters = [];
            if (categoryFilter.value) {
                activeFilters.push({
                    key: 'category',
                    label: `Categoría: ${categoryFilter.selectedOptions[0].textContent}`
                });
            }
            if (statusFilter.value) {
                activeFilters.push({
                    key: 'status',
                    label: `Estado: ${statusFilter.selectedOptions[0].textContent}`
                });
            }
            if (adscriptionFilter.value) {
                activeFilters.push({
                    key: 'adscription',
                    label: `Adscripción: ${adscriptionFilter.selectedOptions[0].textContent}`
                });
            }
            if (table.search().trim()) {
                activeFilters.push({
                    key: 'search',
                    label: `Búsqueda: ${table.search().trim()}`
                });
            }

            activeFiltersContainer.replaceChildren();
            activeFilters.forEach((filter) => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'btn btn-sm btn-outline-secondary rounded-pill';
                chip.dataset.filterKey = filter.key;
                chip.setAttribute('aria-label', `Quitar filtro ${filter.label}`);
                chip.textContent = `${filter.label} ×`;
                activeFiltersContainer.appendChild(chip);
            });

            const filteredCount = table.rows({search: 'applied'}).count();
            resultCount.textContent = `Mostrando ${filteredCount} de ${totalPropertyCount} bienes`;
        };

        activeFiltersContainer.addEventListener('click', (event) => {
            const chip = event.target.closest('button[data-filter-key]');
            if (!chip) return;

            if (chip.dataset.filterKey === 'category') categoryFilter.value = '';
            if (chip.dataset.filterKey === 'status') statusFilter.value = '';
            if (chip.dataset.filterKey === 'adscription') adscriptionFilter.value = '';
            if (chip.dataset.filterKey === 'search') table.search('');

            table.draw();
            syncInventoryUrl();
            syncIndicatorState();
        });

        const syncInventoryUrl = () => {
            const url = new URL(window.location.href);
            const filters = {
                category: categoryFilter.value,
                status: statusFilter.value,
                adscription: adscriptionFilter.value,
                search: table.search().trim()
            };

            Object.entries(filters).forEach(([key, value]) => {
                if (value) {
                    url.searchParams.set(key, value);
                } else {
                    url.searchParams.delete(key);
                }
            });

            history.replaceState(null, '', url.pathname + url.search + url.hash);
        };

        const syncIndicatorState = () => {
            inventoryIndicators.forEach((indicator) => {
                const status = indicator.dataset.filterStatus;
                const isActive = status === ''
                    ? !categoryFilter.value && !statusFilter.value && !adscriptionFilter.value && !table.search().trim()
                    : statusFilter.value === status;
                indicator.setAttribute('aria-pressed', String(isActive));
            });
        };

        table.on('draw', updateFilterFeedback);

        $.fn.dataTable.ext.search.push((settings, data, dataIndex) => {
            if (settings.nTable.id !== 'myTable') return true;

            const row = table.row(dataIndex).node();
            if (!row) return true;

            const categoryMatches = !categoryFilter.value || row.dataset.category === categoryFilter.value;
            const adscriptionMatches = !adscriptionFilter.value
                || row.dataset.adscription === adscriptionFilter.value;
            const rowStatus = row.dataset.status;
            const statusMatches = !statusFilter.value
                || (statusFilter.value === '__attention__'
                    ? rowStatus === 'MALO' || rowStatus === 'MUY MALO'
                    : rowStatus === statusFilter.value);

            return categoryMatches && statusMatches && adscriptionMatches;
        });

        [categoryFilter, statusFilter, adscriptionFilter].forEach((filter) => {
            filter.addEventListener('change', () => {
                table.draw();
                syncInventoryUrl();
                syncIndicatorState();
            });
        });

        inventoryIndicators.forEach((indicator) => {
            indicator.addEventListener('click', () => {
                const status = indicator.dataset.filterStatus;
                if (status === '') {
                    categoryFilter.value = '';
                    statusFilter.value = '';
                    adscriptionFilter.value = '';
                    table.search('');
                } else {
                    statusFilter.value = (statusFilter.value === status) ? '' : status;
                }

                table.draw();
                syncInventoryUrl();
                syncIndicatorState();
                document.getElementById('inventoryTableSection').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });
        });

        document.getElementById('resetInventoryFilters').addEventListener('click', () => {
            categoryFilter.value = '';
            statusFilter.value = '';
            adscriptionFilter.value = '';
            table.search('');
            table.draw();
            syncInventoryUrl();
            syncIndicatorState();
        });

        document.getElementById('registerMaintenanceModal').addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (button) {
                document.getElementById('maintenancePropertyId').value = button.dataset.propertyId;
                document.getElementById('maintenancePropertyCode').textContent = button.dataset.propertyCode;
            }
        });

        const maintenanceForm = document.querySelector('.maintenance-modal-form');
        maintenanceForm.addEventListener('submit', () => {
            const params = new URLSearchParams(window.location.search);
            ['category', 'status', 'adscription', 'search'].forEach((key) => {
                maintenanceForm.elements[`return_${key}`].value = params.get(key) ?? '';
            });
        });

        if (<?= js(($maintenanceFeedback['type'] ?? '') === 'danger') ?>) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('registerMaintenanceModal')).show();
        }

        const historyModal = document.getElementById('propertyHistoryModal');
        if (historyModal) {
            const historyContent = document.getElementById('propertyHistoryContent');
            const printHistoryButton = document.getElementById('printPropertyHistory');
            let historyRequest = null;
            let historyTrigger = null;

            historyModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                historyTrigger = button;
                document.getElementById('historyPropertyCode').textContent = button.dataset.propertyCode;
                printHistoryButton.disabled = true;
                historyContent.setAttribute('aria-busy', 'true');
                historyContent.innerHTML = '<div class="d-flex align-items-center gap-2 text-body-secondary" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Cargando historial...</div>';

                if (historyRequest) historyRequest.abort();
                historyRequest = new AbortController();
                const request = historyRequest;

                fetch(button.dataset.historyUrl, {
                    credentials: 'same-origin',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    signal: request.signal
                })
                    .then((response) => {
                        if (response.redirected) {
                            throw new Error('La sesión expiró o la solicitud fue redirigida.');
                        }
                        if (!response.ok) {
                            throw new Error(`Error HTTP ${response.status}`);
                        }
                        return response.text();
                    })
                    .then((html) => {
                        if (!request.signal.aborted) {
                            historyContent.innerHTML = html;
                            printHistoryButton.disabled = false;
                        }
                    })
                    .catch((error) => {
                        if (error.name !== 'AbortError') {
                            console.error('No se pudo cargar el historial del bien:', error);
                            historyContent.innerHTML = '<div class="alert alert-danger mb-0" role="alert">No se pudo cargar el historial. Cierra el diálogo e inténtalo de nuevo.</div>';
                        }
                    })
                    .finally(() => {
                        if (!request.signal.aborted) {
                            historyContent.setAttribute('aria-busy', 'false');
                        }
                    });
            });

            historyModal.addEventListener('hide.bs.modal', () => {
                if (historyRequest) historyRequest.abort();
                if (historyModal.contains(document.activeElement)) {
                    document.activeElement.blur();
                }
            });

            historyModal.addEventListener('hidden.bs.modal', () => {
                if (historyRequest) historyRequest.abort();
                historyRequest = null;
                historyContent.innerHTML = '<p class="text-body-secondary mb-0">Selecciona un bien para consultar su historial.</p>';
                historyContent.setAttribute('aria-busy', 'false');
                printHistoryButton.disabled = true;
                if (historyTrigger && historyTrigger.isConnected) {
                    historyTrigger.focus();
                }
                historyTrigger = null;
            });

            printHistoryButton.addEventListener('click', () => {
                const printWindow = window.open('', '_blank');
                if (!printWindow) {
                    window.alert('El navegador bloqueó la ventana de impresión. Permite ventanas emergentes e inténtalo de nuevo.');
                    return;
                }
                printWindow.opener = null;

                const reportTitle = `Historial de mantenimiento - ${document.getElementById('historyPropertyCode').textContent}`;
                const letterhead = historyContent.querySelector('#historyLetterhead');
                const reportContent = historyContent.cloneNode(true);
                reportContent.querySelector('#historyLetterhead')?.remove();

                const printDocument = printWindow.document;
                printDocument.title = reportTitle;
                const charset = printDocument.createElement('meta');
                charset.setAttribute('charset', 'utf-8');
                printDocument.head.appendChild(charset);
                const viewport = printDocument.createElement('meta');
                viewport.name = 'viewport';
                viewport.content = 'width=device-width, initial-scale=1';
                printDocument.head.appendChild(viewport);

                const printStyle = printDocument.createElement('style');
                printStyle.nonce = <?= js(systec_csp_nonce()) ?>;
                printStyle.textContent = `
                    body { color: #212529; font: 12px Arial, sans-serif; margin: 20px; }
                    h1, h2, h3, h4, h5 { margin: 0 0 8px; }
                    #historyLetterhead { display: block !important; color: #000 !important; margin: 0 0 16px; }
                    #historyLetterhead, #historyLetterhead * { visibility: visible !important; }
                    #historyLetterhead h5 { font-size: .9375rem; }
                    #historyLetterhead .small { font-size: .75rem; }
                    #historyLetterhead dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 16px; margin: 8px 0 0; }
                    #historyLetterhead dt { color: #495057; font-size: 10px; text-transform: uppercase; }
                    #historyLetterhead dd { margin: 0; }
                    table { border-collapse: collapse; width: 100%; }
                    th, td { border: 1px solid #adb5bd; padding: 6px; text-align: left; vertical-align: top; }
                    th { background: #e9ecef; }
                    .badge { border: 1px solid #adb5bd; border-radius: 12px; display: inline-block; padding: 2px 6px; }
                    .list-group { list-style: none; padding: 0; }
                    .list-group-item { border-bottom: 1px solid #dee2e6; padding: 4px 0; }
                    @page { margin: 15mm; }
                    @media print { body { margin: 0; } }
                `;
                printDocument.head.appendChild(printStyle);
                if (letterhead) printDocument.body.appendChild(letterhead.cloneNode(true));
                printDocument.body.appendChild(reportContent);
                printWindow.addEventListener('load', () => {
                    printWindow.focus();
                    printWindow.print();
                }, {once: true});
            });
        }

        const statusModal = document.getElementById('updatePropertyStatusModal');
        if (statusModal) {
            statusModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                document.getElementById('statusPropertyId').value = button.dataset.propertyId;
                document.getElementById('statusPropertyCode').textContent = button.dataset.propertyCode;
                document.getElementById('propertyStatus').value = button.dataset.propertyStatus;
            });
        }

        table.on('search', () => {
            syncInventoryUrl();
            syncIndicatorState();
        });

        syncIndicatorState();
        updateFilterFeedback();
    </script>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
