<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/inventory_import.php';

if (auth_check() && auth_has_role(array('personal'))) {
    $redirect = 'personal/index.php';
    if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
        $redirect = 'personal/detail.php?id=' . (int) $_GET['id'];
    }
    header('Location: ' . url($redirect));
    exit();
}

require_role(array('admin', 'operador', 'tecnico', 'consulta'));

$db = db();
$canManageTickets = auth_can_manage_all_tickets();

// PERF-02: Una única consulta obtiene los estados activos y sus conteos agregados.
$dashboardSummary = $db->tickets()->supportTicketDashboardSummary(null);
$statuses = $dashboardSummary['statuses'];
$summaryCounts = $dashboardSummary['counts'];

$matchingCount = $summaryCounts['total'];

// PERF-02: Segunda y última consulta con JOIN a properties, users, ticket_status,
// historial JSON y mantenimientos asociados.
$tickets = $matchingCount > 0
    ? $db->supportTickets($matchingCount, 0, null)
    : array();

$statusLabelsByCode = array();
foreach ($statuses as $statusRow) {
    $statusLabelsByCode[(string) $statusRow['code']] = (string) $statusRow['name'];
}

$requestedStatus = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$activeStatusFilter = $requestedStatus === 'abiertos' || isset($statusLabelsByCode[$requestedStatus])
    ? $requestedStatus
    : '';
$activeStatusFilterLabel = $activeStatusFilter === 'abiertos'
    ? 'Abiertos'
    : ($statusLabelsByCode[$activeStatusFilter] ?? '');
$requestedAssigned = isset($_GET['assigned']) ? trim((string) $_GET['assigned']) : '';
$requestedAdscription = isset($_GET['adscription']) ? trim((string) $_GET['adscription']) : '';

// Mantener las asignaciones y adscripciones consistentes con Dashboard.
$assignmentCache = legacy_postgres_read_local_cache();
$legacyAssignmentsByIdent = isset($assignmentCache['assignments']) && is_array($assignmentCache['assignments'])
    ? $assignmentCache['assignments']
    : array();
$legacyAdscriptionsByIdent = isset($assignmentCache['adscriptions']) && is_array($assignmentCache['adscriptions'])
    ? $assignmentCache['adscriptions']
    : array();
$institutionalAdscriptionsByLabel = property_adscription_institutional_index($legacyAdscriptionsByIdent);
$assignedFilterOptions = array();
$adscriptionFilterOptions = array();
foreach ($tickets as &$ticketRow) {
    $assignmentIdent = str_pad(trim((string) ($ticketRow['property_ident'] ?? '')), 4, '0', STR_PAD_LEFT);
    $propertyAssignment = $legacyAssignmentsByIdent[$assignmentIdent] ?? null;
    if (!empty($assignmentCache['found']) && is_array($propertyAssignment)) {
        foreach (array('assigned_name', 'assigned_status') as $assignmentField) {
            if (array_key_exists($assignmentField, $propertyAssignment)) {
                $ticketRow['property_' . $assignmentField] = (string) $propertyAssignment[$assignmentField];
            }
        }
    }

    $assignedName = trim((string) ($ticketRow['property_assigned_name'] ?? ''));
    $assignedStatus = trim((string) ($ticketRow['property_assigned_status'] ?? ''));
    $assignedFilterKey = property_assignment_filter_key($assignedName, $assignedStatus);
    $assignedFilterLabel = property_assignment_filter_label($assignedName, $assignedStatus);
    $ticketRow['property_assigned_key'] = $assignedFilterKey;
    $ticketRow['property_assigned_filter_label'] = $assignedFilterLabel;
    $assignedFilterOptions[$assignedFilterKey] = $assignedFilterLabel;

    $propertyAdscription = property_adscription_assignment(
        array(
            'ident' => $ticketRow['property_ident'] ?? '',
            'adscription' => $ticketRow['property_adscription'] ?? '',
        ),
        $legacyAdscriptionsByIdent,
        $institutionalAdscriptionsByLabel
    );
    $ticketRow['property_adscription_key'] = $propertyAdscription['key'];
    $ticketRow['property_adscription_label'] = $propertyAdscription['label'];
    $adscriptionFilterOptions[$propertyAdscription['key']] = $propertyAdscription['label'];
}
unset($ticketRow);
asort($assignedFilterOptions, SORT_NATURAL | SORT_FLAG_CASE);
asort($adscriptionFilterOptions, SORT_NATURAL | SORT_FLAG_CASE);

$activeAssignedFilter = isset($assignedFilterOptions[$requestedAssigned]) ? $requestedAssigned : '';
$activeAdscriptionFilter = isset($adscriptionFilterOptions[$requestedAdscription]) ? $requestedAdscription : '';
$displayedTicketCount = count($tickets);
$initialTicketSearch = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$openRegisterDrawerOnLoad = $canManageTickets && isset($_GET['action']) && $_GET['action'] === 'new';

$statusClasses = array(
    'iniciado' => 'primary',
    'en_progreso' => 'info',
    'con_retraso' => 'warning',
    'en_espera' => 'secondary',
    'finalizado' => 'success',
);

$title = 'Tickets';
$ticketNotice = isset($_SESSION['ticket_notice']) && is_string($_SESSION['ticket_notice'])
    ? trim($_SESSION['ticket_notice'])
    : '';
$ticketNoticeType = isset($_SESSION['ticket_notice_type']) && is_string($_SESSION['ticket_notice_type'])
    ? $_SESSION['ticket_notice_type']
    : 'success';
if (!in_array($ticketNoticeType, array('success', 'info', 'warning', 'danger'), true)) {
    $ticketNoticeType = 'success';
}
unset($_SESSION['ticket_notice'], $_SESSION['ticket_notice_type']);
$notifications = $ticketNotice !== ''
    ? array(toast_notification($ticketNoticeType, $ticketNotice))
    : array();
ob_start();
?>
<div class="container-fluid text-start">
    <section class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3"
             aria-labelledby="tickets-page-title">
        <div>
            <h1 class="h3 mb-1" id="tickets-page-title">Tickets</h1>
            <p class="text-body-secondary mb-0">Registro y seguimiento de incidencias por bien.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-self-md-start flex-shrink-0">
            <button type="button" class="btn btn-outline-secondary" id="exportTicketsCsv"
                    <?= $displayedTicketCount === 0 ? 'disabled' : '' ?>
                    title="Descarga en CSV los tickets visibles según los filtros aplicados">
                <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Exportar CSV
            </button>
            <?php if ($canManageTickets) : ?>
                <button type="button" class="btn btn-primary"
                        data-bs-toggle="offcanvas" data-bs-target="#ticketRegisterDrawer"
                        aria-controls="ticketRegisterDrawer">
                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Nuevo ticket
                </button>
            <?php endif; ?>
        </div>
    </section>

    <?php
    $kpiCards = array(
        array('key' => '', 'label' => 'Total', 'count' => $summaryCounts['total'], 'color' => 'primary', 'icon' => 'bi-ticket-detailed', 'href' => url('tickets.php')),
        array('key' => 'en_progreso', 'label' => 'En progreso', 'count' => $summaryCounts['in_progress'], 'color' => 'info', 'icon' => 'bi-hourglass-split', 'href' => url('tickets.php?status=en_progreso')),
        array('key' => 'con_retraso', 'label' => 'Con retraso', 'count' => $summaryCounts['delayed'], 'color' => 'warning', 'icon' => 'bi-exclamation-triangle', 'href' => url('tickets.php?status=con_retraso')),
        array('key' => 'en_espera', 'label' => 'En espera', 'count' => $summaryCounts['waiting'], 'color' => 'secondary', 'icon' => 'bi-pause-circle', 'href' => url('tickets.php?status=en_espera')),
        array('key' => 'finalizado', 'label' => 'Finalizados', 'count' => $summaryCounts['closed'], 'color' => 'success', 'icon' => 'bi-check-circle', 'href' => url('tickets.php?status=finalizado')),
    );
    ?>
    <section class="row g-3 mb-3" aria-label="Indicadores de tickets">
        <?php foreach ($kpiCards as $kpi) :
            $isActiveKpi = $activeStatusFilter === $kpi['key'];
            ?>
            <div class="col-6 col-md-4 col-xl">
                <article class="card h-100 border-start border-4 border-<?= e($kpi['color']) ?> shadow-sm text-start position-relative ticket-kpi-card<?= $isActiveKpi ? ' is-active' : '' ?>">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-<?= e($kpi['color']) ?>-subtle text-<?= e($kpi['color']) ?> p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi <?= e($kpi['icon']) ?> fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-body-secondary mb-1"><?= e($kpi['label']) ?></p>
                            <p class="h3 mb-0"><?= e((string) $kpi['count']) ?></p>
                            <a href="<?= e($kpi['href']) ?>"
                               class="stretched-link"
                               data-ticket-status-link
                               <?= $isActiveKpi ? 'aria-current="true"' : '' ?>
                               aria-label="Filtrar tickets: <?= e($kpi['label']) ?> (<?= e((string) $kpi['count']) ?>)"></a>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </section>

    <nav class="d-flex flex-wrap align-items-center gap-2 mb-3" id="ticketQuickStatusFilters"
         aria-label="Filtro rápido por estado de ticket">
        <a href="<?= e(url('tickets.php')) ?>"
           class="btn btn-sm <?= $activeStatusFilter === '' ? 'btn-primary' : 'btn-outline-secondary' ?>"
           data-ticket-status-link>
            Todos
        </a>
        <a href="<?= e(url('tickets.php?status=abiertos')) ?>"
           class="btn btn-sm <?= $activeStatusFilter === 'abiertos' ? 'btn-primary' : 'btn-outline-secondary' ?>"
           data-ticket-status-link>
            Abiertos
        </a>
        <?php foreach ($statuses as $statusItem) :
            $sCode = (string) $statusItem['code'];
            $sActive = $activeStatusFilter === $sCode;
            ?>
            <a href="<?= e(url('tickets.php?status=' . rawurlencode($sCode))) ?>"
               class="btn btn-sm <?= $sActive ? 'btn-primary' : 'btn-outline-secondary' ?>"
               data-ticket-status-link>
                <?= e((string) $statusItem['name']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="card shadow-sm border-0 mb-3" aria-labelledby="ticket-filters-title">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <h2 class="h6 mb-0" id="ticket-filters-title">
                    <i class="bi bi-funnel text-body-secondary me-1" aria-hidden="true"></i>Filtrar
                </h2>
                <a href="<?= e(url('tickets.php')) ?>" class="btn btn-outline-secondary btn-sm" id="resetTicketFilters">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Limpiar filtros
                </a>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="ticketFilterAssigned" class="form-label fw-semibold small mb-0">Usuario asignado</label>
                    <select id="ticketFilterAssigned" class="form-select form-select-sm">
                        <option value="">Todos los usuarios asignados</option>
                        <?php foreach ($assignedFilterOptions as $filterKey => $filterLabel) : ?>
                            <option value="<?= e($filterKey) ?>" <?= $activeAssignedFilter === $filterKey ? 'selected' : '' ?>>
                                <?= e($filterLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label for="ticketFilterAdscription" class="form-label fw-semibold small mb-0">Adscripción / unidad operativa</label>
                    <select id="ticketFilterAdscription" class="form-select form-select-sm">
                        <option value="">Todas las unidades</option>
                        <?php foreach ($adscriptionFilterOptions as $filterKey => $filterLabel) : ?>
                            <option value="<?= e($filterKey) ?>" <?= $activeAdscriptionFilter === $filterKey ? 'selected' : '' ?>>
                                <?= e($filterLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label for="ticketFilterStatus" class="form-label fw-semibold small mb-0">Estado</label>
                    <select id="ticketFilterStatus" class="form-select form-select-sm">
                        <option value="">Todos los estados</option>
                        <option value="abiertos" <?= $activeStatusFilter === 'abiertos' ? 'selected' : '' ?>>Abiertos</option>
                        <?php foreach ($statuses as $statusItem) : ?>
                            <option value="<?= e((string) $statusItem['code']) ?>" <?= $activeStatusFilter === (string) $statusItem['code'] ? 'selected' : '' ?>>
                                <?= e((string) $statusItem['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-2" id="ticketActiveFilters" aria-label="Filtros activos"></div>
        </div>
    </section>

    <?php if ($tickets === array()) : ?>
        <section class="card shadow-sm border-0 text-center" aria-label="Sin tickets">
            <div class="card-body py-4" role="status" aria-live="polite">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary p-3 mb-3"
                      aria-hidden="true">
                    <i class="bi bi-inbox fs-3"></i>
                </span>
                <h2 class="h5 mb-1">
                    <?= $activeStatusFilter !== '' ? 'No hay tickets con el filtro seleccionado' : 'No hay tickets registrados' ?>
                </h2>
                <p class="text-body-secondary mb-0">
                    <?= $activeStatusFilter !== ''
                        ? 'No se encontraron tickets en estado «' . e($activeStatusFilterLabel) . '».'
                        : 'Los tickets disponibles aparecerán aquí.' ?>
                </p>
                <?php if ($activeStatusFilter !== '') : ?>
                    <div class="mt-3">
                        <a href="<?= e(url('tickets.php')) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Ver todos los tickets
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php else : ?>
        <section class="card shadow-sm border-0 text-start" aria-labelledby="tickets-list-title">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <h2 class="h5 mb-0" id="tickets-list-title">
                        <i class="bi bi-ticket-detailed text-primary me-2" aria-hidden="true"></i>Registro de tickets
                    </h2>
                </div>
                <span class="badge text-bg-light border text-body-secondary" id="ticketsResultCount"
                      role="status" aria-live="polite"><?= e((string) $displayedTicketCount) ?> registros</span>
            </div>
            <div class="card-body">
                <div class="table-responsive tickets-table-responsive">
                    <table class="table table-hover align-middle mb-0" id="ticketsTable">
                        <caption class="visually-hidden">Tickets registrados</caption>
                        <thead class="table-light">
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Bien</th>
                            <th scope="col">Título</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Asignado al bien</th>
                            <th scope="col">Actualización</th>
                            <th scope="col" class="text-end" data-orderable="false">Acción</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tickets as $ticket) :
                            $propertyRow = array(
                                'grupo' => (string) ($ticket['property_grupo'] ?? ''),
                                'subgrupo' => (string) ($ticket['property_subgrupo'] ?? ''),
                                'seccion' => (string) ($ticket['property_seccion'] ?? ''),
                                'ident' => (string) ($ticket['property_ident'] ?? ''),
                                'description' => (string) ($ticket['property_description'] ?? 'Bien eliminado'),
                                'status' => (string) ($ticket['property_status'] ?? ''),
                            );
                            $statusCode = (string) ($ticket['status_code'] ?? '');
                            $statusName = (string) ($ticket['status_name'] ?? 'Sin estado');
                            $statusClass = isset($statusClasses[$statusCode]) ? $statusClasses[$statusCode] : 'light';
                            $assignedName = trim((string) ($ticket['property_assigned_name'] ?? ''));
                            $assignedStatusLabel = assignment_status_label((string) ($ticket['property_assigned_status'] ?? ''));
                            $assignedUserLabel = $assignedName !== ''
                                ? $assignedName
                                : ($assignedStatusLabel === 'Sin asignar' || $assignedStatusLabel === 'Sin ficha'
                                    ? $assignedStatusLabel
                                    : 'Nombre no registrado');
                            $identRaw = trim($propertyRow['ident']);
                            $identPadded = $identRaw !== '' ? str_pad($identRaw, 4, '0', STR_PAD_LEFT) : '';
                            $ticketMaintenanceDetails = array();
                            foreach (isset($ticket['maintenances']) && is_array($ticket['maintenances']) ? $ticket['maintenances'] : array() as $maintenanceRow) {
                                $maintenanceDateRaw = trim((string) ($maintenanceRow['date'] ?? ''));
                                $maintenanceDateTimestamp = $maintenanceDateRaw !== '' ? strtotime($maintenanceDateRaw) : false;
                                $maintenanceNextRaw = trim((string) ($maintenanceRow['next_maintenance_date'] ?? ''));
                                $maintenanceNextTimestamp = $maintenanceNextRaw !== '' ? strtotime($maintenanceNextRaw) : false;
                                $maintenanceTime = trim((string) ($maintenanceRow['time_taken'] ?? ''));
                                $ticketMaintenanceDetails[] = array(
                                    'id' => (int) ($maintenanceRow['id'] ?? 0),
                                    'code' => trim((string) ($maintenanceRow['code'] ?? '')) !== ''
                                        ? (string) $maintenanceRow['code']
                                        : 'Sin código',
                                    'type' => texto_tipo((string) ($maintenanceRow['type'] ?? '')) ?: 'No disponible',
                                    'type_badge' => badge_clase_tipo((string) ($maintenanceRow['type'] ?? '')),
                                    'status' => texto_mantenimiento((string) ($maintenanceRow['status'] ?? '')) ?: 'Sin estado',
                                    'status_badge' => badge_clase_mantenimiento((string) ($maintenanceRow['status'] ?? '')),
                                    'date' => $maintenanceDateTimestamp !== false
                                        ? date('d-m-Y H:i', $maintenanceDateTimestamp)
                                        : ($maintenanceDateRaw !== '' ? $maintenanceDateRaw : 'No disponible'),
                                    'technician' => trim((string) ($maintenanceRow['technician'] ?? '')) !== ''
                                        ? (string) $maintenanceRow['technician']
                                        : 'No disponible',
                                    'time_taken' => $maintenanceTime !== '' ? $maintenanceTime . ' h' : 'No disponible',
                                    'next_maintenance_date' => $maintenanceNextTimestamp !== false
                                        ? date('d-m-Y', $maintenanceNextTimestamp)
                                        : ($maintenanceNextRaw !== '' ? $maintenanceNextRaw : 'No disponible'),
                                    'failure_reason' => (string) ($maintenanceRow['failure_reason'] ?? ''),
                                    'description' => (string) ($maintenanceRow['description'] ?? ''),
                                    'notes' => (string) ($maintenanceRow['notes'] ?? ''),
                                );
                            }
                            $ticketDetails = array(
                                'id' => (int) $ticket['id'],
                                'title' => (string) $ticket['title'],
                                'property' => $propertyRow['description'] !== '' ? $propertyRow['description'] : 'Bien desconocido',
                                'property_code' => $propertyRow['ident'] !== '' ? $propertyRow['ident'] : 'Sin código',
                                'property_search_code' => $identPadded,
                                'property_status' => $propertyRow['status'] !== '' ? $propertyRow['status'] : 'No disponible',
                                'property_category' => property_category($propertyRow),
                                'status' => $statusName,
                                'status_id' => (int) $ticket['status_id'],
                                'status_class' => $statusClass,
                                'created_by_name' => (string) ($ticket['creator_username'] ?? '') !== '' ? (string) $ticket['creator_username'] : 'Usuario',
                                'description' => (string) $ticket['description'],
                                'observation' => (string) ($ticket['observation'] ?? ''),
                                'created_at' => (string) ($ticket['created_at'] ?? ''),
                                'updated_at' => (string) ($ticket['updated_at'] ?? ''),
                                'closed_at' => (string) ($ticket['closed_at'] ?? ''),
                                'created_by' => (int) $ticket['created_by'],
                                'assigned_to' => (string) ($ticket['assigned_to'] ?? ''),
                                'property_assigned_name' => $assignedName,
                                'property_assigned_label' => $assignedUserLabel,
                                'property_assigned_status_label' => $assignedStatusLabel,
                                'history' => isset($ticket['history']) && is_array($ticket['history']) ? $ticket['history'] : array(),
                                'maintenances' => $ticketMaintenanceDetails,
                            );
                            ?>
                            <tr data-export-id="<?= e((string) $ticket['id']) ?>"
                                data-filter-assigned="<?= e((string) ($ticket['property_assigned_key'] ?? '')) ?>"
                                data-filter-adscription="<?= e((string) ($ticket['property_adscription_key'] ?? '')) ?>"
                                data-filter-status="<?= e($statusCode) ?>"
                                data-terminal="<?= (int) ($ticket['status_is_terminal'] ?? 0) === 1 ? '1' : '0' ?>"
                                data-export-code="<?= e($identPadded) ?>"
                                data-export-property="<?= e($ticketDetails['property']) ?>"
                                data-export-title="<?= e((string) $ticket['title']) ?>"
                                data-export-status="<?= e($statusName) ?>"
                                data-export-creator="<?= e($ticketDetails['created_by_name']) ?>"
                                data-export-assigned="<?= e($assignedUserLabel) ?>"
                                data-export-updated="<?= e((string) $ticket['updated_at']) ?>">
                                <td>
                                    <a href="<?= e(url('tickets/detail.php?id=' . (int) $ticket['id'])) ?>"
                                       class="fw-semibold text-decoration-none"
                                       title="Abrir vista completa del ticket #<?= e((string) $ticket['id']) ?>">
                                        #<?= e((string) $ticket['id']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= e($ticketDetails['property']) ?></span>
                                    <div class="small text-body-secondary">
                                        Código:
                                        <?php if ($identPadded !== '') : ?>
                                            <a href="<?= e(url('dashboard.php?search=' . rawurlencode($identPadded))) ?>"
                                               class="text-decoration-none"
                                               title="Consultar bien <?= e($identPadded) ?> en el Dashboard">
                                                <?= e($identPadded) ?>
                                            </a>
                                        <?php else : ?>
                                            —
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= e((string) $ticket['title']) ?></td>
                                <td>
                                    <span class="badge text-bg-<?= e($statusClass) ?>">
                                        <?= e($statusName) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="d-inline-flex align-items-center gap-1<?= $assignedName === '' ? ' text-body-secondary' : '' ?>">
                                            <i class="bi bi-person-check" aria-hidden="true"></i><span class="text-break"><?= e($assignedUserLabel) ?></span>
                                        </span>
                                        <?php if ($assignedName !== '') : ?>
                                            <small class="text-body-secondary"><?= e($assignedStatusLabel) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-nowrap"><?= e((string) $ticket['updated_at']) ?></td>
                                <td class="text-end text-nowrap">
                                    <?php
                                    $ticketActionMenuId = 'ticketActionsMenu' . (int) $ticket['id'];
                                    $ticketActionToggleId = 'ticketActionsToggle' . (int) $ticket['id'];
                                    ?>
                                    <div class="dropdown ticket-actions-dropdown">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary dropdown-toggle text-nowrap"
                                                id="<?= e($ticketActionToggleId) ?>"
                                                aria-label="Mostrar acciones del ticket #<?= e((string) $ticket['id']) ?>"
                                                title="Mostrar acciones del ticket #<?= e((string) $ticket['id']) ?>"
                                                aria-haspopup="menu"
                                                aria-controls="<?= e($ticketActionMenuId) ?>"
                                                aria-expanded="false"
                                                data-bs-toggle="dropdown">
                                            <!-- <i class="bi bi-three-dots me-1" aria-hidden="true"></i> -->
                                            Acciones
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end ticket-actions-menu"
                                            id="<?= e($ticketActionMenuId) ?>"
                                            aria-labelledby="<?= e($ticketActionToggleId) ?>"
                                            role="menu">
                                            <li role="none">
                                                <button type="button" class="dropdown-item d-flex align-items-center gap-2" role="menuitem"
                                                        aria-label="Ver detalle del ticket #<?= e((string) $ticket['id']) ?>"
                                                        data-bs-toggle="offcanvas" data-bs-target="#supportTicketDetailsDrawer"
                                                        aria-controls="supportTicketDetailsDrawer"
                                                        data-ticket="<?= e(js($ticketDetails)) ?>">
                                                    <i class="bi bi-eye text-primary" aria-hidden="true"></i>Ver detalle
                                                </button>
                                            </li>
                                            <?php if ($identPadded !== '') : ?>
                                                <li role="none">
                                                    <a class="dropdown-item d-flex align-items-center gap-2" role="menuitem"
                                                       href="<?= e(url('maintenances.php?search=' . rawurlencode($identPadded))) ?>"
                                                       aria-label="Ver mantenimientos del bien <?= e($identPadded) ?> del ticket #<?= e((string) $ticket['id']) ?>"
                                                       title="Ver mantenimientos del bien <?= e($identPadded) ?>">
                                                        <i class="bi bi-tools text-body-secondary" aria-hidden="true"></i>Mantenimientos del bien
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($canManageTickets || auth_has_role(array('admin', 'operador'))) : ?>
                                                <li role="none"><hr class="dropdown-divider"></li>
                                            <?php endif; ?>
                                            <?php if ($canManageTickets) : ?>
                                                <li role="none">
                                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2" role="menuitem"
                                                            aria-label="Añadir seguimiento al ticket #<?= e((string) $ticket['id']) ?>"
                                                            data-bs-toggle="modal" data-bs-target="#supportTicketFollowupModal"
                                                            data-ticket="<?= e(js($ticketDetails)) ?>">
                                                        <i class="bi bi-chat-left-text text-body-secondary" aria-hidden="true"></i>Seguimiento
                                                    </button>
                                                </li>
                                                <li role="none">
                                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2" role="menuitem"
                                                            aria-label="Cambiar estado del ticket #<?= e((string) $ticket['id']) ?>"
                                                            data-bs-toggle="modal" data-bs-target="#supportTicketStatusModal"
                                                            data-ticket="<?= e(js($ticketDetails)) ?>">
                                                        <i class="bi bi-arrow-repeat text-success" aria-hidden="true"></i>Estado
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                            <?php if (auth_has_role(array('admin', 'operador'))) : ?>
                                                <li role="none"><hr class="dropdown-divider"></li>
                                                <li role="none">
                                                    <button type="button" class="dropdown-item d-flex align-items-center gap-2 text-danger" role="menuitem"
                                                            aria-label="Eliminar ticket #<?= e((string) $ticket['id']) ?>"
                                                            data-bs-toggle="modal" data-bs-target="#deleteTicketModal"
                                                            data-ticket-id="<?= e((string) $ticket['id']) ?>"
                                                            data-ticket-title="<?= e((string) $ticket['title']) ?>">
                                                        <i class="bi bi-trash" aria-hidden="true"></i>Eliminar
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <script nonce="<?= e(systec_csp_nonce()) ?>">
            (function () {
                const totalDisplayed = <?= js($displayedTicketCount) ?>;
                const resultCountBadge = document.getElementById('ticketsResultCount');
                const exportCsvButton = document.getElementById('exportTicketsCsv');
                const assignedFilter = document.getElementById('ticketFilterAssigned');
                const adscriptionFilter = document.getElementById('ticketFilterAdscription');
                const statusFilter = document.getElementById('ticketFilterStatus');
                const resetFiltersButton = document.getElementById('resetTicketFilters');
                const activeFiltersContainer = document.getElementById('ticketActiveFilters');
                const ticketFilterControls = [
                    { control: assignedFilter, param: 'assigned', label: 'Usuario asignado' },
                    { control: adscriptionFilter, param: 'adscription', label: 'Adscripción / unidad operativa' },
                    { control: statusFilter, param: 'status', label: 'Estado' }
                ];

                const ticketsTable = new window.Systec.TablePaginator('#ticketsTable', {
                    pageLength: 10,
                    search: {
                        search: <?= js($initialTicketSearch) ?>
                    },
                    filterPredicate: (row) => {
                        const assignedMatches = !assignedFilter.value
                            || row.dataset.filterAssigned === assignedFilter.value;
                        const adscriptionMatches = !adscriptionFilter.value
                            || row.dataset.filterAdscription === adscriptionFilter.value;
                        const statusMatches = !statusFilter.value
                            || (statusFilter.value === 'abiertos'
                                ? row.dataset.terminal !== '1'
                                : row.dataset.filterStatus === statusFilter.value);
                        return assignedMatches && adscriptionMatches && statusMatches;
                    },
                    language: {
                        search: 'Buscar tickets:',
                        searchPlaceholder: 'ID, bien, código, título, estado, asignado…',
                        emptyTable: 'No hay tickets registrados.',
                        zeroRecords: 'Ningún ticket coincide con los filtros aplicados.',
                        unitLabel: 'tickets'
                    }
                });

                const renderTicketActiveFilters = () => {
                    activeFiltersContainer.replaceChildren();
                    ticketFilterControls.forEach(({ control, label }) => {
                        if (!control.value) {
                            return;
                        }
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'btn btn-sm btn-outline-secondary rounded-pill';
                        button.textContent = `${label}: ${control.selectedOptions[0].textContent.trim()} ×`;
                        button.setAttribute('aria-label', `Quitar filtro ${label}`);
                        button.addEventListener('click', () => {
                            control.value = '';
                            ticketsTable.draw();
                            syncTicketFilterUrl();
                        });
                        activeFiltersContainer.appendChild(button);
                    });
                };

                const syncTicketStatusControls = () => {
                    const currentStatus = statusFilter.value;
                    document.querySelectorAll('#ticketQuickStatusFilters [data-ticket-status-link]').forEach((link) => {
                        const linkStatus = new URL(link.href, window.location.href).searchParams.get('status') || '';
                        const isActive = linkStatus === currentStatus;
                        link.classList.toggle('btn-primary', isActive);
                        link.classList.toggle('btn-outline-secondary', !isActive);
                        if (isActive) {
                            link.setAttribute('aria-current', 'page');
                        } else {
                            link.removeAttribute('aria-current');
                        }
                    });
                    document.querySelectorAll('.ticket-kpi-card').forEach((card) => {
                        const link = card.querySelector('[data-ticket-status-link]');
                        if (!link) {
                            return;
                        }
                        const linkStatus = new URL(link.href, window.location.href).searchParams.get('status') || '';
                        const isActive = linkStatus === currentStatus;
                        card.classList.toggle('is-active', isActive);
                        if (isActive) {
                            link.setAttribute('aria-current', 'page');
                        } else {
                            link.removeAttribute('aria-current');
                        }
                    });
                };

                const syncTicketFilterUrl = () => {
                    const url = new URL(window.location.href);
                    ticketFilterControls.forEach(({ control, param }) => {
                        if (control.value) {
                            url.searchParams.set(param, control.value);
                        } else {
                            url.searchParams.delete(param);
                        }
                    });
                    if (ticketsTable.search().trim()) {
                        url.searchParams.set('search', ticketsTable.search().trim());
                    } else {
                        url.searchParams.delete('search');
                    }
                    window.history.replaceState({}, '', url);
                    syncTicketStatusControls();
                    renderTicketActiveFilters();
                };

                ticketFilterControls.forEach(({ control }) => {
                    control.addEventListener('change', () => {
                        ticketsTable.draw();
                        syncTicketFilterUrl();
                    });
                });

                resetFiltersButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    ticketFilterControls.forEach(({ control }) => {
                        control.value = '';
                    });
                    ticketsTable.search('');
                    ticketsTable.draw();
                    syncTicketFilterUrl();
                });

                ticketsTable.searchInput.addEventListener('input', syncTicketFilterUrl);

                document.addEventListener('click', (event) => {
                    const link = event.target.closest('[data-ticket-status-link]');
                    if (!link) {
                        return;
                    }
                    event.preventDefault();
                    const targetStatus = new URL(link.href, window.location.href).searchParams.get('status') || '';
                    statusFilter.value = targetStatus;
                    ticketsTable.draw();
                    syncTicketFilterUrl();
                    const scrollTarget = document.getElementById('tickets-list-title')
                        || document.getElementById('ticket-filters-title');
                    if (scrollTarget) {
                        scrollTarget.scrollIntoView({
                            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                            block: 'start'
                        });
                    }
                });

                syncTicketFilterUrl();

                // Los menús se mueven al body mientras están abiertos para que
                // el overflow de .table-responsive no recorte las acciones.
                document.querySelectorAll('.ticket-actions-dropdown').forEach((wrapper) => {
                    const toggle = wrapper.querySelector('[data-bs-toggle="dropdown"]');
                    const menu = wrapper.querySelector('.ticket-actions-menu');
                    if (!toggle || !menu || !window.bootstrap || !window.bootstrap.Dropdown) {
                        return;
                    }

                    const dropdown = window.bootstrap.Dropdown.getOrCreateInstance(toggle, {
                        boundary: 'viewport'
                    });

                    wrapper.addEventListener('show.bs.dropdown', () => {
                        document.body.appendChild(menu);
                    });

                    wrapper.addEventListener('shown.bs.dropdown', () => {
                        const firstItem = menu.querySelector('.dropdown-item:not(:disabled)');
                        if (firstItem) {
                            firstItem.focus();
                        }
                    });

                    wrapper.addEventListener('hide.bs.dropdown', () => {
                        if (menu.parentNode !== wrapper) {
                            wrapper.appendChild(menu);
                        }
                    });

                    window.addEventListener('keydown', (event) => {
                        if (!menu.contains(event.target)) {
                            return;
                        }

                        const items = Array.from(menu.querySelectorAll('.dropdown-item:not(:disabled)'));
                        const currentIndex = items.indexOf(document.activeElement);
                        let nextIndex = null;

                        if (event.key === 'ArrowDown') {
                            nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
                        } else if (event.key === 'ArrowUp') {
                            nextIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
                        } else if (event.key === 'Home') {
                            nextIndex = 0;
                        } else if (event.key === 'End') {
                            nextIndex = items.length - 1;
                        } else if (event.key === 'Escape') {
                            event.preventDefault();
                            event.stopPropagation();
                            dropdown.hide();
                            toggle.focus();
                            return;
                        } else if (event.key === 'Tab') {
                            const atFirstItem = currentIndex === 0;
                            const atLastItem = currentIndex === items.length - 1;
                            if ((event.shiftKey && atFirstItem) || (!event.shiftKey && atLastItem)) {
                                event.preventDefault();
                                event.stopPropagation();
                                dropdown.hide();

                                if (event.shiftKey) {
                                    toggle.focus();
                                    return;
                                }

                                const focusable = Array.from(document.querySelectorAll(
                                    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                                )).filter((element) => !menu.contains(element) && element.offsetParent !== null);
                                const toggleIndex = focusable.indexOf(toggle);
                                const nextControl = toggleIndex >= 0 ? focusable[toggleIndex + 1] : null;
                                (nextControl || toggle).focus();
                            }
                            return;
                        }

                        if (nextIndex !== null && items[nextIndex]) {
                            event.preventDefault();
                            event.stopPropagation();
                            items[nextIndex].focus();
                        }
                    }, true);
                });

                const updateTicketFeedback = () => {
                    const filteredCount = ticketsTable.rows({ search: 'applied' }).count();
                    const hasActiveFilters = ticketsTable.search().trim()
                        || assignedFilter.value
                        || adscriptionFilter.value
                        || statusFilter.value;
                    if (resultCountBadge) {
                        resultCountBadge.textContent = hasActiveFilters
                            ? `Mostrando ${filteredCount} de ${totalDisplayed} registros`
                            : `${totalDisplayed} registros`;
                    }
                    if (exportCsvButton) {
                        exportCsvButton.disabled = filteredCount === 0;
                    }
                };

                ticketsTable.on('draw', updateTicketFeedback);
                updateTicketFeedback();

                if (exportCsvButton) {
                    const csvEscape = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
                    exportCsvButton.addEventListener('click', () => {
                        const columns = [
                            { header: 'ID', key: 'exportId' },
                            { header: 'Código bien', key: 'exportCode' },
                            { header: 'Bien', key: 'exportProperty' },
                            { header: 'Título', key: 'exportTitle' },
                            { header: 'Estado', key: 'exportStatus' },
                            { header: 'Reportado por', key: 'exportCreator' },
                            { header: 'Asignado al bien', key: 'exportAssigned' },
                            { header: 'Actualización', key: 'exportUpdated' }
                        ];
                        const lines = [columns.map((col) => csvEscape(col.header)).join(';')];
                        ticketsTable.rows({ search: 'applied' }).nodes().toArray().forEach((row) => {
                            const values = columns.map((col) => (row.dataset[col.key] || '').replace(/\s+/g, ' ').trim());
                            lines.push(values.map(csvEscape).join(';'));
                        });

                        const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                        const downloadUrl = URL.createObjectURL(blob);
                        const today = new Date();
                        const dateStamp = [
                            today.getFullYear(),
                            String(today.getMonth() + 1).padStart(2, '0'),
                            String(today.getDate()).padStart(2, '0')
                        ].join('-');
                        const link = document.createElement('a');
                        link.href = downloadUrl;
                        link.download = `tickets-${dateStamp}.csv`;
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                        URL.revokeObjectURL(downloadUrl);
                    });
                }
            })();
        </script>
    <?php endif; ?>
</div>
<style nonce="<?= e(systec_csp_nonce()) ?>">
    .ticket-actions-dropdown > .btn {
        min-height: 2.4rem;
    }

    .ticket-actions-menu {
        min-width: min(14rem, calc(100vw - 1rem));
        max-width: calc(100vw - 1rem);
    }

    .ticket-actions-menu .dropdown-item {
        min-height: 2.4rem;
    }

    .ticket-actions-menu .dropdown-divider {
        margin-inline: .75rem;
    }

    .ticket-kpi-card {
        transition: box-shadow .15s ease, transform .15s ease;
    }

    .ticket-kpi-card:hover {
        transform: translateY(-1px);
    }

    .ticket-kpi-card:focus-within,
    .ticket-kpi-card.is-active {
        box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .2), var(--bs-box-shadow-sm);
    }

    @media (prefers-reduced-motion: reduce) {
        .ticket-kpi-card {
            transition: none;
        }

        .ticket-kpi-card:hover {
            transform: none;
        }
    }
</style>
<?php include __DIR__ . '/include/support_ticket_details_modal.php'; ?>
<?php if ($canManageTickets) : ?>
    <?php include __DIR__ . '/include/support_ticket_followup_modal.php'; ?>
    <?php include __DIR__ . '/include/support_ticket_status_modal.php'; ?>
    <?php
    $ticketRegisterOptions = array(
        'presentation' => 'drawer',
        'properties_url' => 'tickets/actions/properties.php',
        'form_action' => 'tickets/actions/register.php',
        'improve_narrative_url' => 'tickets/actions/improve-narrative.php',
        'selected_property_id' => '',
        'error' => '',
        'values' => array(),
    );
    include __DIR__ . '/include/ticket_register_modal.php';
    unset($ticketRegisterOptions);
    ?>
    <?php if ($openRegisterDrawerOnLoad) : ?>
        <script nonce="<?= e(systec_csp_nonce()) ?>">
            document.addEventListener('DOMContentLoaded', function () {
                const drawerEl = document.getElementById('ticketRegisterDrawer');
                if (drawerEl && window.bootstrap && window.bootstrap.Offcanvas) {
                    window.bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
                }
            });
        </script>
    <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/include/support_ticket_delete_modal.php'; ?>
<?php
$main = ob_get_clean();
include __DIR__ . '/include/layout.php';
