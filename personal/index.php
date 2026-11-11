<?php
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../include/auth.php';
require_personal_scope();

$followupSaved = !empty($_SESSION['personal_ticket_followup_saved']);
unset($_SESSION['personal_ticket_followup_saved']);
$notifications = $followupSaved
    ? array(toast_notification('success', 'El seguimiento se registró correctamente.'))
    : array();

$db = db();
$user = auth_user();
$statuses = $db->getTicketStatuses();
$statusCodes = array();
foreach ($statuses as $status) {
    $statusCodes[(string) $status['code']] = true;
}
$queryString = function (string $key): string {
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
};
$rawStatus = $queryString('status');
$rawDateFrom = $queryString('date_from');
$rawDateTo = $queryString('date_to');
$rawCategory = $queryString('category');
$adscriptionFilter = $queryString('adscription');
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
    'search' => $searchTerm,
    'status' => $statusFilter,
    'category' => $categoryFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'adscription' => $adscriptionFilter,
);
$ticketFilters = $filters;
unset($ticketFilters['adscription']);
$hasActiveFilters = count(array_filter($filters, function ($value) {
    return $value !== '';
})) > 0;
if ($adscriptionFilter !== '') {
    require_once __DIR__ . '/../include/inventory_import.php';
    try {
        $propertiesForAdscription = $db->index(
            'properties',
            array(),
            'id, ident',
            'id ASC'
        );
        $identifiers = array_map(function ($property) {
            return trim((string) $property['ident']);
        }, $propertiesForAdscription);
        $snapshot = legacy_postgres_cached_property_adscriptions($identifiers);
        $matchingPropertyIds = array();
        $knownAdscription = false;
        foreach ($propertiesForAdscription as $property) {
            $ident = str_pad(trim((string) $property['ident']), 4, '0', STR_PAD_LEFT);
            $assignment = $snapshot['adscriptions'][$ident] ?? array('key' => '__unassigned__');
            if ((string) $assignment['key'] === $adscriptionFilter) {
                $knownAdscription = true;
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
    'in_progress' => 0,
    'delayed' => 0,
    'waiting' => 0,
    'closed' => 0,
);
$summaryKeyByStatus = array(
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
$pageUrl = function (int $targetPage) use ($filters): string {
    $query = array_filter($filters, function ($value) {
        return $value !== '';
    });
    $query['page'] = $targetPage;
    return url('personal/index.php?' . http_build_query($query));
};
$statusUrl = function (string $status) use ($filters): string {
    $query = array_filter($filters, function ($value) {
        return $value !== '';
    });
    if ($status === '') {
        unset($query['status']);
    } else {
        $query['status'] = $status;
    }
    return url('personal/index.php?' . http_build_query($query));
};

$title = 'Tickets personales';
ob_start();
?>
<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Mi área de tickets</h1>
            <p class="text-muted mb-0">Seguimiento y registro de incidencias del personal.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ticketRegisterModal">
            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> Nuevo ticket
        </button>
    </div>

    <section class="row g-3 mb-4" aria-label="Indicadores del área personal">
        <div class="col-6 col-md-4 col-xl">
            <a href="<?= e($statusUrl('')) ?>" class="card h-100 border-start border-4 border-primary shadow-sm text-start text-body text-decoration-none"
               aria-label="Mostrar todos los tickets">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-primary-subtle text-primary p-2" aria-hidden="true">
                        <i class="bi bi-ticket-detailed fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">Total</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['total']) ?></p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <a href="<?= e($statusUrl('en_progreso')) ?>" class="card h-100 border-start border-4 border-info shadow-sm text-start text-body text-decoration-none"
               aria-label="Filtrar tickets en progreso">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-info-subtle text-info p-2" aria-hidden="true">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">En progreso</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['in_progress']) ?></p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <a href="<?= e($statusUrl('con_retraso')) ?>" class="card h-100 border-start border-4 border-warning shadow-sm text-start text-body text-decoration-none"
               aria-label="Filtrar tickets con retraso">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-warning-subtle text-warning p-2" aria-hidden="true">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">Con retraso</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['delayed']) ?></p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <a href="<?= e($statusUrl('en_espera')) ?>" class="card h-100 border-start border-4 border-secondary shadow-sm text-start text-body text-decoration-none"
               aria-label="Filtrar tickets en espera">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-secondary-subtle text-secondary p-2" aria-hidden="true">
                        <i class="bi bi-pause-circle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">En espera</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['waiting']) ?></p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <a href="<?= e($statusUrl('finalizado')) ?>" class="card h-100 border-start border-4 border-success shadow-sm text-start text-body text-decoration-none"
               aria-label="Filtrar tickets finalizados">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-success-subtle text-success p-2" aria-hidden="true">
                        <i class="bi bi-check-circle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">Finalizados</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['closed']) ?></p>
                    </div>
                </div>
            </a>
        </div>
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
                            <option value="<?= e($adscriptionFilter) ?>" selected>Unidad seleccionada</option>
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
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ticketRegisterModal">
                            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Registrar incidencia
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else : ?>
            <div class="card-body p-0">
                <div class="table-responsive personal-ticket-table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th scope="col">Ticket</th>
                            <th scope="col">Bien</th>
                            <th scope="col">Incidencia</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Actualizado</th>
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
                            $statusCode = (string) ($ticket['status_code'] ?? '');
                            $statusClass = isset($statusClasses[$statusCode]) ? $statusClasses[$statusCode] : 'light';
                            $updatedTimestamp = strtotime((string) $ticket['updated_at']);
                            $isInactive = $statusCode !== 'finalizado'
                                && $updatedTimestamp !== false
                                && $updatedTimestamp <= $inactiveTicketCutoff;
                            $rowClass = $statusCode === 'con_retraso' || $isInactive ? 'table-warning' : '';
                            $followupTicket = array(
                                'id' => (int) $ticket['id'],
                                'title' => (string) $ticket['title'],
                            );
                            ?>
                            <tr class="<?= e($rowClass) ?>">
                                <td><span class="fw-semibold">#<?= e((string) $ticket['id']) ?></span></td>
                                <td>
                                    <span class="fw-semibold"><?= e($property['description']) ?></span>
                                    <div class="small text-body-secondary"><?= e(property_code($property)) ?></div>
                                </td>
                                <td><?= e((string) $ticket['title']) ?></td>
                                <td>
                                    <span class="badge text-bg-<?= e($statusClass) ?>"><?= e((string) ($ticket['status_name'] ?? 'Sin estado')) ?></span>
                                    <?php if ($isInactive) : ?>
                                        <span class="badge text-bg-warning ms-1" title="Sin cambios durante 7 días o más">
                                            <i class="bi bi-clock-history me-1" aria-hidden="true"></i>Sin actividad
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($updatedTimestamp !== false) : ?>
                                        <time datetime="<?= e(date(DATE_ATOM, $updatedTimestamp)) ?>"
                                              data-relative-time
                                              title="<?= e(date('d/m/Y H:i', $updatedTimestamp)) ?>">
                                            <?= e(date('d/m/Y H:i', $updatedTimestamp)) ?>
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
                                                data-bs-toggle="modal" data-bs-target="#personalTicketDetailsModal"
                                                data-ticket-id="<?= e((string) $ticket['id']) ?>">
                                            <i class="bi bi-eye me-1" aria-hidden="true"></i>Detalle
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap"
                                                aria-label="Añadir seguimiento al ticket #<?= e((string) $ticket['id']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#personalTicketFollowupModal"
                                                data-ticket="<?= e(js($followupTicket)) ?>">
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
    'properties_url' => 'personal/actions/properties.php',
    'form_action' => 'personal/actions/register.php',
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
