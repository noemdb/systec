<?php
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../include/auth.php';
require_personal_scope();

$followupSaved = !empty($_SESSION['personal_ticket_followup_saved']);
unset($_SESSION['personal_ticket_followup_saved']);
$createdTicket = isset($_SESSION['personal_ticket_created']) && is_array($_SESSION['personal_ticket_created'])
    ? $_SESSION['personal_ticket_created']
    : null;
unset($_SESSION['personal_ticket_created']);
$createdTicketId = $createdTicket !== null ? (int) ($createdTicket['id'] ?? 0) : 0;

$notifications = array();
if ($followupSaved) {
    $notifications[] = toast_notification('success', 'El seguimiento se registró correctamente.');
}
if ($createdTicketId > 0) {
    $notifications[] = toast_notification('success', 'Ticket #' . $createdTicketId . ' registrado correctamente.');
}

$db = db();
$user = auth_user();
$statuses = $db->getTicketStatuses();
$statusCodes = array();
$statusNamesByCode = array();
foreach ($statuses as $status) {
    $code = (string) $status['code'];
    $statusCodes[$code] = true;
    $statusNamesByCode[$code] = (string) $status['name'];
}
$queryString = function (string $key): string {
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
};
$rawStatus = $queryString('status');
$rawDateFrom = $queryString('date_from');
$rawDateTo = $queryString('date_to');
$rawCategory = $queryString('category');
$adscriptionFilter = $queryString('adscription');
$rawSort = $queryString('sort');
$rawDir = strtolower($queryString('dir'));
$allowedSorts = array('id', 'property', 'status', 'updated_at');
$sort = in_array($rawSort, $allowedSorts, true) ? $rawSort : 'updated_at';
$dir = $rawDir === 'asc' ? 'asc' : 'desc';

$filterError = '';
$categoryOptions = array(
    'informatica' => 'Informática',
    'redes' => 'Redes y telecomunicaciones',
    'otros' => 'Otros bienes',
);
$categoryFilter = isset($categoryOptions[$rawCategory]) ? $rawCategory : '';
$isValidDate = function ($value) {
    if ($value === '') {
        return true;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
};
$dateFrom = $isValidDate($rawDateFrom) ? $rawDateFrom : '';
$dateTo = $isValidDate($rawDateTo) ? $rawDateTo : '';
if ($dateFrom !== $rawDateFrom || $dateTo !== $rawDateTo) {
    $filterError = 'Una fecha no es válida; revisa el intervalo seleccionado.';
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $filterError = 'La fecha inicial no puede ser posterior a la fecha final.';
    $dateFrom = '';
    $dateTo = '';
}
$searchTerm = trim(mb_substr($queryString('q'), 0, 100, 'UTF-8'));
$statusFilter = isset($statusCodes[$rawStatus]) ? $rawStatus : '';
$filters = array(
    'q' => $searchTerm,
    'status' => $statusFilter,
    'category' => $categoryFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'adscription' => $adscriptionFilter,
);
$ticketFilters = array(
    'search' => $searchTerm,
    'status' => $statusFilter,
    'category' => $categoryFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'sort' => $sort,
    'dir' => $dir,
);
$hasActiveFilters = count(array_filter($filters, function ($value) {
    return $value !== '';
})) > 0;
$adscriptionLabel = '';
if ($adscriptionFilter !== '') {
    require_once __DIR__ . '/../include/inventory_import.php';
    try {
        $propertiesForAdscription = $db->index(
            'properties',
            array(),
            'id, ident',
            'id ASC'
        );
        // PERF: Leer primero del caché local para evitar bloquear el GET con PostgreSQL.
        $localCache = legacy_postgres_read_local_cache();
        if ($localCache['found'] && !empty($localCache['adscriptions'])) {
            $adscriptionsMap = $localCache['adscriptions'];
        } else {
            $identifiers = array_map(function ($property) {
                return trim((string) $property['ident']);
            }, $propertiesForAdscription);
            $snapshot = legacy_postgres_cached_property_adscriptions($identifiers);
            $adscriptionsMap = $snapshot['adscriptions'];
        }
        $matchingPropertyIds = array();
        $knownAdscription = false;
        foreach ($propertiesForAdscription as $property) {
            $ident = str_pad(trim((string) $property['ident']), 4, '0', STR_PAD_LEFT);
            $assignment = $adscriptionsMap[$ident] ?? array('key' => '__unassigned__', 'label' => 'Sin adscripción');
            if ((string) $assignment['key'] === $adscriptionFilter) {
                $knownAdscription = true;
                $adscriptionLabel = (string) ($assignment['label'] ?? 'Unidad seleccionada');
                $matchingPropertyIds[] = (int) $property['id'];
            }
        }
        $ticketFilters['adscription_property_ids'] = $matchingPropertyIds;
        if (!$knownAdscription) {
            $filterError = 'La adscripción seleccionada ya no está disponible; no se aplicó ningún filtro.';
        }
    } catch (Throwable $e) {
        error_log('No se pudo resolver el filtro de adscripción personal: ' . $e->getMessage());
        $ticketFilters['adscription_property_ids'] = array();
        $filterError = 'No se pudo verificar la adscripción. No se muestran resultados hasta que la fuente esté disponible.';
    }
}

$summaryCounts = array(
    'total' => 0,
    'initiated' => 0,
    'in_progress' => 0,
    'delayed' => 0,
    'waiting' => 0,
    'closed' => 0,
);
$summaryKeyByStatus = array(
    'iniciado' => 'initiated',
    'en_progreso' => 'in_progress',
    'con_retraso' => 'delayed',
    'en_espera' => 'waiting',
    'finalizado' => 'closed',
);
foreach ($db->personalTicketStatusCounts((int) $user['id'], $ticketFilters) as $row) {
    $count = (int) $row['ticket_count'];
    $summaryCounts['total'] += $count;
    $key = $summaryKeyByStatus[(string) ($row['code'] ?? '')] ?? null;
    if ($key !== null) {
        $summaryCounts[$key] += $count;
    }
}
$pageSize = 25;
$matchingCount = $db->personalTicketCount((int) $user['id'], $ticketFilters);
$totalPages = max(1, (int) ceil($matchingCount / $pageSize));
$page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : 1;
$page = $page === false || $page === null ? 1 : max(1, min((int) $page, $totalPages));
$tickets = $db->personalTickets((int) $user['id'], $ticketFilters, $pageSize, ($page - 1) * $pageSize);
$statusClasses = array(
    'iniciado' => 'primary',
    'en_progreso' => 'info',
    'con_retraso' => 'warning',
    'en_espera' => 'secondary',
    'finalizado' => 'success',
);
$inactiveTicketCutoff = strtotime('-7 days');

$formState = isset($_SESSION['personal_ticket_form']) && is_array($_SESSION['personal_ticket_form'])
    ? $_SESSION['personal_ticket_form']
    : array();
unset($_SESSION['personal_ticket_form']);
$formValues = isset($formState['values']) && is_array($formState['values']) ? $formState['values'] : array();
$formError = isset($formState['error']) ? (string) $formState['error'] : '';
$selectedPropertyId = isset($formValues['property_id']) ? (string) $formValues['property_id'] : '';

$buildUrl = function (array $overrides = array()) use ($filters, $sort, $dir): string {
    $query = array_filter($filters, function ($value) {
        return $value !== '';
    });
    if ($sort !== 'updated_at' || $dir !== 'desc') {
        $query['sort'] = $sort;
        $query['dir'] = $dir;
    }
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    return url('personal/index.php' . ($query !== array() ? '?' . http_build_query($query) : ''));
};
$pageUrl = function (int $targetPage) use ($buildUrl): string {
    return $buildUrl(array('page' => $targetPage));
};
$statusUrl = function (string $status) use ($buildUrl): string {
    return $buildUrl(array('status' => $status, 'page' => null));
};
$sortUrl = function (string $column) use ($buildUrl, $sort, $dir): string {
    $nextDir = ($sort === $column && $dir === 'desc') ? 'asc' : 'desc';
    return $buildUrl(array('sort' => $column, 'dir' => $nextDir, 'page' => null));
};
$sortIcon = function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return 'bi-arrow-down-up text-body-tertiary';
    }
    return $dir === 'asc' ? 'bi-arrow-up text-primary' : 'bi-arrow-down text-primary';
};

$activeFilterChips = array();
if ($searchTerm !== '') {
    $activeFilterChips[] = array('label' => 'Búsqueda: ' . $searchTerm, 'url' => $buildUrl(array('q' => null, 'page' => null)));
}
if ($categoryFilter !== '') {
    $activeFilterChips[] = array('label' => 'Categoría: ' . $categoryOptions[$categoryFilter], 'url' => $buildUrl(array('category' => null, 'page' => null)));
}
if ($statusFilter !== '') {
    $activeFilterChips[] = array('label' => 'Estado: ' . ($statusNamesByCode[$statusFilter] ?? $statusFilter), 'url' => $buildUrl(array('status' => null, 'page' => null)));
}
if ($adscriptionFilter !== '') {
    $activeFilterChips[] = array('label' => 'Adscripción: ' . ($adscriptionLabel !== '' ? $adscriptionLabel : 'Unidad seleccionada'), 'url' => $buildUrl(array('adscription' => null, 'page' => null)));
}
if ($dateFrom !== '') {
    $activeFilterChips[] = array('label' => 'Desde: ' . $dateFrom, 'url' => $buildUrl(array('date_from' => null, 'page' => null)));
}
if ($dateTo !== '') {
    $activeFilterChips[] = array('label' => 'Hasta: ' . $dateTo, 'url' => $buildUrl(array('date_to' => null, 'page' => null)));
}

$kpiCards = array(
    array('code' => '', 'label' => 'Total', 'count' => $summaryCounts['total'], 'color' => 'primary', 'icon' => 'bi-ticket-detailed', 'aria' => 'Mostrar todos los tickets'),
    array('code' => 'iniciado', 'label' => 'Iniciados', 'count' => $summaryCounts['initiated'], 'color' => 'primary', 'icon' => 'bi-play-circle', 'aria' => 'Filtrar tickets iniciados'),
    array('code' => 'en_progreso', 'label' => 'En progreso', 'count' => $summaryCounts['in_progress'], 'color' => 'info', 'icon' => 'bi-hourglass-split', 'aria' => 'Filtrar tickets en progreso'),
    array('code' => 'con_retraso', 'label' => 'Con retraso', 'count' => $summaryCounts['delayed'], 'color' => 'warning', 'icon' => 'bi-exclamation-triangle', 'aria' => 'Filtrar tickets con retraso'),
    array('code' => 'en_espera', 'label' => 'En espera', 'count' => $summaryCounts['waiting'], 'color' => 'secondary', 'icon' => 'bi-pause-circle', 'aria' => 'Filtrar tickets en espera'),
    array('code' => 'finalizado', 'label' => 'Finalizados', 'count' => $summaryCounts['closed'], 'color' => 'success', 'icon' => 'bi-check-circle', 'aria' => 'Filtrar tickets finalizados'),
);

$title = 'Tickets personales';
ob_start();
?>
<div class="container-fluid text-start">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Mi área de tickets</h1>
            <p class="text-body-secondary mb-0">Seguimiento y registro de incidencias del personal.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-self-md-start flex-shrink-0">
            <button type="button" class="btn btn-outline-secondary" id="exportPersonalTicketsCsv"
                    <?= $tickets === array() ? 'disabled' : '' ?>
                    title="Descarga en CSV los tickets visibles en esta página">
                <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Exportar CSV
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#ticketRegisterDrawer" aria-controls="ticketRegisterDrawer">
                <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> Nuevo ticket
            </button>
        </div>
    </div>

    <section class="row g-3 mb-4" aria-label="Indicadores del área personal">
        <?php foreach ($kpiCards as $kpi) :
            $isActiveKpi = $statusFilter === $kpi['code'];
            ?>
            <div class="col-6 col-md-4 col-xl">
                <a href="<?= e($statusUrl($kpi['code'])) ?>"
                   class="card h-100 border-start border-4 border-<?= e($kpi['color']) ?> shadow-sm text-start text-body text-decoration-none personal-ticket-kpi-card<?= $isActiveKpi ? ' is-active' : '' ?>"
                   aria-label="<?= e($kpi['aria']) ?>"
                   <?= $isActiveKpi ? 'aria-current="page"' : '' ?>>
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-<?= e($kpi['color']) ?>-subtle text-<?= e($kpi['color']) ?> p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi <?= e($kpi['icon']) ?> fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-body-secondary mb-1"><?= e($kpi['label']) ?></p>
                            <p class="h3 mb-0"><?= e((string) $kpi['count']) ?></p>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="card shadow-sm text-start" aria-labelledby="my-tickets-title">
        <div class="card-header d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
            <h2 class="h5 mb-0" id="my-tickets-title">
                <i class="bi bi-list-task text-primary me-2" aria-hidden="true"></i>Mis tickets
            </h2>
            <span class="small text-body-secondary" aria-live="polite">
                <?= e((string) $matchingCount) ?> de <?= e((string) $summaryCounts['total']) ?> tickets
            </span>
        </div>

        <form method="get" action="<?= e(url('personal/index.php')) ?>" class="card-body border-bottom personal-ticket-filters" aria-label="Filtros de tickets">
            <?php if ($sort !== 'updated_at' || $dir !== 'desc') : ?>
                <input type="hidden" name="sort" value="<?= e($sort) ?>">
                <input type="hidden" name="dir" value="<?= e($dir) ?>">
            <?php endif; ?>
            <div class="row g-2 align-items-start">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold personal-ticket-filter-label" for="personalTicketSearch">Buscar</label>
                    <input class="form-control form-control-sm" id="personalTicketSearch" name="q" type="search"
                           value="<?= e($searchTerm) ?>" maxlength="100" placeholder="Título, código o bien">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold personal-ticket-filter-label" for="filterCategory">Categoría</label>
                    <select class="form-select form-select-sm" id="filterCategory" name="category">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categoryOptions as $categoryCode => $categoryLabel) : ?>
                            <option value="<?= e($categoryCode) ?>" <?= $categoryFilter === $categoryCode ? 'selected' : '' ?>>
                                <?= e($categoryLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold personal-ticket-filter-label" for="filterStatus">Estado</label>
                    <select class="form-select form-select-sm" id="filterStatus" name="status">
                        <option value="">Todos los estados</option>
                        <?php foreach ($statuses as $status) : ?>
                            <option value="<?= e((string) $status['code']) ?>" <?= $statusFilter === (string) $status['code'] ? 'selected' : '' ?>>
                                <?= e((string) $status['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold personal-ticket-filter-label" for="filterAdscription">Adscripción / unidad operativa</label>
                    <select class="form-select form-select-sm" id="filterAdscription" name="adscription"
                            data-options-url="<?= e(url('personal/actions/properties.php')) ?>"
                            <?= $adscriptionFilter === '' ? 'disabled' : '' ?>>
                        <option value="">Todas las unidades</option>
                        <?php if ($adscriptionFilter !== '') : ?>
                            <option value="<?= e($adscriptionFilter) ?>" selected>
                                <?= e($adscriptionLabel !== '' ? $adscriptionLabel : 'Unidad seleccionada') ?>
                            </option>
                        <?php endif; ?>
                    </select>
                    <div class="form-text" id="filterAdscriptionStatus" role="status" aria-live="polite">
                        <?= $adscriptionFilter === '' ? 'Cargando unidades…' : '' ?>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small fw-semibold personal-ticket-filter-label" for="personalTicketDateFrom">Desde</label>
                    <input class="form-control form-control-sm" id="personalTicketDateFrom" name="date_from"
                           type="date" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small fw-semibold personal-ticket-filter-label" for="personalTicketDateTo">Hasta</label>
                    <input class="form-control form-control-sm" id="personalTicketDateTo" name="date_to"
                           type="date" value="<?= e($dateTo) ?>">
                </div>
                <div class="col-12 d-flex flex-wrap align-items-center gap-2">
                    <button class="btn btn-sm btn-primary" type="submit">
                        <i class="bi bi-search me-1" aria-hidden="true"></i>Aplicar filtros
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" id="resetInventoryFilters"
                            data-reset-url="<?= e(url('personal/index.php')) ?>">
                        <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Limpiar filtros
                    </button>
                    <?php if ($filterError !== '') : ?>
                        <span class="small text-danger" role="alert"><?= e($filterError) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <?php if ($activeFilterChips !== array()) : ?>
            <div class="px-3 py-2 border-bottom bg-body-tertiary d-flex flex-wrap align-items-center gap-2" aria-label="Filtros activos">
                <span class="small text-body-secondary">Filtros activos:</span>
                <?php foreach ($activeFilterChips as $chip) : ?>
                    <a href="<?= e($chip['url']) ?>"
                       class="badge rounded-pill text-bg-light border text-body text-decoration-none d-inline-flex align-items-center gap-1 py-1 px-2"
                       title="Quitar filtro">
                        <span><?= e($chip['label']) ?></span>
                        <i class="bi bi-x" aria-hidden="true"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($tickets === array()) : ?>
            <div class="card-body py-4">
                <div class="text-center py-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary p-3 mb-3" aria-hidden="true">
                        <i class="bi bi-inbox fs-3"></i>
                    </span>
                    <?php if ($hasActiveFilters) : ?>
                        <h3 class="h6 mb-1">No hay tickets con estos filtros</h3>
                        <p class="text-body-secondary mb-3">Prueba con otros criterios o limpia la búsqueda.</p>
                        <a class="btn btn-outline-secondary" href="<?= e(url('personal/index.php')) ?>">Limpiar filtros</a>
                    <?php else : ?>
                        <h3 class="h6 mb-1">Aún no tienes tickets</h3>
                        <p class="text-body-secondary mb-3">Cuando registres una incidencia, podrás seguir su estado desde aquí.</p>
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="offcanvas" data-bs-target="#ticketRegisterDrawer" aria-controls="ticketRegisterDrawer">
                            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Registrar incidencia
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else : ?>
            <div class="card-body p-0">
                <div class="table-responsive personal-ticket-table-responsive">
                    <table class="table table-hover align-middle mb-0" id="personalTicketsTable">
                        <caption class="visually-hidden">Listado de tickets personales registrados</caption>
                        <thead class="table-light">
                        <tr>
                            <th scope="col">
                                <a href="<?= e($sortUrl('id')) ?>" class="text-decoration-none text-body d-inline-flex align-items-center gap-1">
                                    Ticket <i class="bi <?= e($sortIcon('id')) ?>" aria-hidden="true"></i>
                                </a>
                            </th>
                            <th scope="col">
                                <a href="<?= e($sortUrl('property')) ?>" class="text-decoration-none text-body d-inline-flex align-items-center gap-1">
                                    Bien <i class="bi <?= e($sortIcon('property')) ?>" aria-hidden="true"></i>
                                </a>
                            </th>
                            <th scope="col">Incidencia</th>
                            <th scope="col">
                                <a href="<?= e($sortUrl('status')) ?>" class="text-decoration-none text-body d-inline-flex align-items-center gap-1">
                                    Estado <i class="bi <?= e($sortIcon('status')) ?>" aria-hidden="true"></i>
                                </a>
                            </th>
                            <th scope="col">
                                <a href="<?= e($sortUrl('updated_at')) ?>" class="text-decoration-none text-body d-inline-flex align-items-center gap-1">
                                    Actualizado <i class="bi <?= e($sortIcon('updated_at')) ?>" aria-hidden="true"></i>
                                </a>
                            </th>
                            <th scope="col" class="text-end">Acción</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tickets as $ticket) :
                            $property = array(
                                'grupo' => (string) ($ticket['property_grupo'] ?? ''),
                                'subgrupo' => (string) ($ticket['property_subgrupo'] ?? ''),
                                'seccion' => (string) ($ticket['property_seccion'] ?? ''),
                                'ident' => (string) ($ticket['property_ident'] ?? ''),
                                'description' => (string) ($ticket['property_description'] ?? 'Bien desconocido'),
                                'status' => (string) ($ticket['property_status'] ?? ''),
                            );
                            $propertyCode = property_code($property);
                            $statusCode = (string) ($ticket['status_code'] ?? '');
                            $statusName = (string) ($ticket['status_name'] ?? 'Sin estado');
                            $statusClass = isset($statusClasses[$statusCode]) ? $statusClasses[$statusCode] : 'light';
                            $updatedTimestamp = strtotime((string) $ticket['updated_at']);
                            $updatedLabel = $updatedTimestamp !== false
                                ? date('d/m/Y H:i', $updatedTimestamp)
                                : (string) $ticket['updated_at'];
                            $isInactive = $statusCode !== 'finalizado'
                                && $updatedTimestamp !== false
                                && $updatedTimestamp <= $inactiveTicketCutoff;
                            $isNewlyCreated = $createdTicketId > 0 && (int) $ticket['id'] === $createdTicketId;
                            $rowClass = $isNewlyCreated
                                ? 'table-success'
                                : ($statusCode === 'con_retraso' || $isInactive ? 'table-warning' : '');
                            $assignedTech = trim((string) ($ticket['assigned_username'] ?? ''));
                            $isFinalized = $statusCode === 'finalizado';
                            $followupTicket = array(
                                'id' => (int) $ticket['id'],
                                'title' => (string) $ticket['title'],
                            );
                            ?>
                            <tr class="<?= e($rowClass) ?>"
                                data-export-id="#<?= e((string) $ticket['id']) ?>"
                                data-export-code="<?= e($propertyCode) ?>"
                                data-export-property="<?= e($property['description']) ?>"
                                data-export-title="<?= e((string) $ticket['title']) ?>"
                                data-export-status="<?= e($statusName) ?>"
                                data-export-tech="<?= e($assignedTech !== '' ? $assignedTech : 'Sin asignar') ?>"
                                data-export-updated="<?= e($updatedLabel) ?>">
                                <td><span class="fw-semibold text-nowrap">#<?= e((string) $ticket['id']) ?></span></td>
                                <td>
                                    <span class="fw-semibold"><?= e($property['description']) ?></span>
                                    <div class="small text-body-secondary"><?= e($propertyCode) ?></div>
                                </td>
                                <td>
                                    <div><?= e((string) $ticket['title']) ?></div>
                                    <?php if ($assignedTech !== '') : ?>
                                        <div class="small text-body-secondary">
                                            <i class="bi bi-person-badge me-1" aria-hidden="true"></i>Técnico: <?= e($assignedTech) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?= e($statusClass) ?>"><?= e($statusName) ?></span>
                                    <?php if ($isInactive) : ?>
                                        <span class="badge text-bg-warning ms-1" title="Sin cambios durante 7 días o más">
                                            <i class="bi bi-clock-history me-1" aria-hidden="true"></i>Sin actividad
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($updatedTimestamp !== false) : ?>
                                        <time datetime="<?= e(date(DATE_ATOM, $updatedTimestamp)) ?>"
                                              data-relative-time
                                              title="<?= e($updatedLabel) ?>">
                                            <?= e($updatedLabel) ?>
                                        </time>
                                    <?php else : ?>
                                        <?= e((string) $ticket['updated_at']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group"
                                         aria-label="Acciones para el ticket #<?= e((string) $ticket['id']) ?>">
                                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap"
                                                aria-label="Ver detalle del ticket #<?= e((string) $ticket['id']) ?>"
                                                data-bs-toggle="offcanvas" data-bs-target="#personalTicketDetailsDrawer"
                                                aria-controls="personalTicketDetailsDrawer"
                                                data-ticket-id="<?= e((string) $ticket['id']) ?>">
                                            <i class="bi bi-eye me-1" aria-hidden="true"></i>Detalle
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap"
                                                aria-label="Añadir seguimiento al ticket #<?= e((string) $ticket['id']) ?>"
                                                <?= $isFinalized ? 'disabled title="No se puede añadir seguimiento a un ticket finalizado"' : 'data-bs-toggle="offcanvas" data-bs-target="#personalTicketFollowupDrawer" aria-controls="personalTicketFollowupDrawer" data-ticket="' . e(js($followupTicket)) . '"' ?>>
                                            <i class="bi bi-chat-left-text me-1" aria-hidden="true"></i>Seguimiento
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 p-3 border-top">
                    <span class="small text-body-secondary">
                        Mostrando <?= e((string) (($page - 1) * $pageSize + 1)) ?>–<?= e((string) (($page - 1) * $pageSize + count($tickets))) ?>
                        de <?= e((string) $matchingCount) ?>
                    </span>
                    <nav aria-label="Paginación de tickets">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= e($pageUrl(max(1, $page - 1))) ?>"
                                   <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Anterior</a>
                            </li>
                            <li class="page-item disabled">
                                <span class="page-link">Página <?= e((string) $page) ?> de <?= e((string) $totalPages) ?></span>
                            </li>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= e($pageUrl(min($totalPages, $page + 1))) ?>"
                                   <?= $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Siguiente</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include __DIR__ . '/../include/personal_ticket_details_modal.php'; ?>
<?php include __DIR__ . '/../include/personal_ticket_followup_modal.php'; ?>

<?php
$ticketRegisterOptions = array(
    'presentation' => 'drawer',
    'properties_url' => 'personal/actions/properties.php',
    'form_action' => 'personal/actions/register.php',
    'process_narrative_url' => 'personal/actions/process-narrative.php',
    'improve_narrative_url' => 'personal/actions/improve-narrative.php',
    'selected_property_id' => $selectedPropertyId,
    'error' => $formError,
    'values' => $formValues,
);
include __DIR__ . '/../include/ticket_register_modal.php';
unset($ticketRegisterOptions);
?>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    const filterAdscription = document.getElementById('filterAdscription');
    const filterAdscriptionStatus = document.getElementById('filterAdscriptionStatus');
    const adscriptionOptionsUrl = new URL(filterAdscription.dataset.optionsUrl, window.location.href);
    adscriptionOptionsUrl.searchParams.set('mode', 'adscriptions');
    fetch(adscriptionOptionsUrl, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
    }).then(async (response) => {
        let data;
        try {
            data = await response.json();
        } catch (error) {
            throw new Error('Respuesta inválida al cargar las adscripciones.');
        }
        if (!response.ok) throw new Error(data.error || 'No se pudieron cargar las adscripciones.');

        const selectedAdscription = new URLSearchParams(window.location.search).get('adscription') || '';
        filterAdscription.replaceChildren(new Option('Todas las unidades', ''));
        Object.entries(data.adscriptions).forEach(([key, label]) => {
            filterAdscription.add(new Option(label, key, false, key === selectedAdscription));
        });
        if (selectedAdscription && !data.adscriptions[selectedAdscription]) {
            filterAdscription.add(new Option('Unidad seleccionada (no disponible)', selectedAdscription, true, true));
        }
        filterAdscription.disabled = false;
        filterAdscriptionStatus.className = data.stale ? 'form-text text-warning-emphasis' : 'form-text text-body-secondary';
        filterAdscriptionStatus.textContent = data.stale
            ? `Adscripciones guardadas al ${new Intl.DateTimeFormat('es-VE', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(data.cached_at))}; la fuente no responde.`
            : `Unidades cargadas: ${new Intl.DateTimeFormat('es-VE', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(data.cached_at))}.`;
    }).catch((error) => {
        filterAdscriptionStatus.className = 'form-text text-danger';
        filterAdscriptionStatus.textContent = `${error.message} No se aplicará un filtro parcial.`;
        filterAdscription.disabled = !filterAdscription.value;
    });

    document.getElementById('resetInventoryFilters').addEventListener('click', (event) => {
        window.location.assign(event.currentTarget.dataset.resetUrl);
    });

    const exportPersonalCsvBtn = document.getElementById('exportPersonalTicketsCsv');
    if (exportPersonalCsvBtn) {
        const csvEscape = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
        exportPersonalCsvBtn.addEventListener('click', () => {
            const rows = Array.from(document.querySelectorAll('#personalTicketsTable tbody tr'));
            if (rows.length === 0) return;
            const columns = [
                { header: 'Ticket', key: 'exportId' },
                { header: 'Código bien', key: 'exportCode' },
                { header: 'Bien', key: 'exportProperty' },
                { header: 'Incidencia', key: 'exportTitle' },
                { header: 'Estado', key: 'exportStatus' },
                { header: 'Técnico asignado', key: 'exportTech' },
                { header: 'Actualizado', key: 'exportUpdated' }
            ];
            const lines = [columns.map((col) => csvEscape(col.header)).join(';')];
            rows.forEach((row) => {
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
            link.download = `mis-tickets-${dateStamp}.csv`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(downloadUrl);
        });
    }

    if (typeof Intl.RelativeTimeFormat === 'function') {
        const relativeTime = new Intl.RelativeTimeFormat('es-VE', { numeric: 'auto' });
        const units = [
            ['year', 31536000],
            ['month', 2592000],
            ['day', 86400],
            ['hour', 3600],
            ['minute', 60],
            ['second', 1]
        ];
        document.querySelectorAll('time[data-relative-time]').forEach((time) => {
            const timestamp = new Date(time.dateTime).getTime();
            if (Number.isNaN(timestamp)) return;
            const difference = Math.round((timestamp - Date.now()) / 1000);
            const [unit, secondsPerUnit] = units.find(([, seconds]) => Math.abs(difference) >= seconds) || units[units.length - 1];
            time.textContent = relativeTime.format(Math.round(difference / secondsPerUnit), unit);
        });
    }
</script>
<?php
$main = ob_get_clean();
include __DIR__ . '/../include/layout.php';
