<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';

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

// PERF-02: Segunda y última consulta con JOIN a properties, users, ticket_status e historial JSON.
$tickets = $matchingCount > 0
    ? $db->supportTickets($matchingCount, 0, null)
    : array();

$statusLabelsByCode = array();
foreach ($statuses as $statusRow) {
    $statusLabelsByCode[(string) $statusRow['code']] = (string) $statusRow['name'];
}

$requestedStatus = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$activeStatusFilter = '';
$activeStatusFilterLabel = '';
if ($requestedStatus === 'abiertos') {
    $activeStatusFilter = 'abiertos';
    $activeStatusFilterLabel = 'Abiertos';
    $tickets = array_values(array_filter($tickets, function (array $ticket) use ($statuses): bool {
        $statusId = (int) ($ticket['status_id'] ?? 0);
        return !isset($statuses[$statusId]) || (int) $statuses[$statusId]['is_terminal'] === 0;
    }));
} elseif ($requestedStatus !== '' && isset($statusLabelsByCode[$requestedStatus])) {
    $activeStatusFilter = $requestedStatus;
    $activeStatusFilterLabel = $statusLabelsByCode[$requestedStatus];
    $tickets = array_values(array_filter($tickets, function (array $ticket) use ($requestedStatus): bool {
        return (string) ($ticket['status_code'] ?? '') === $requestedStatus;
    }));
}
$displayedTicketCount = count($tickets);
$initialTicketSearch = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$openRegisterModalOnLoad = $canManageTickets && isset($_GET['action']) && $_GET['action'] === 'new';

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
                        data-bs-toggle="modal" data-bs-target="#ticketRegisterModal">
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
                               <?= $isActiveKpi ? 'aria-current="true"' : '' ?>
                               aria-label="Filtrar tickets: <?= e($kpi['label']) ?> (<?= e((string) $kpi['count']) ?>)"></a>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </section>

    <nav class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Filtro rápido por estado de ticket">
        <a href="<?= e(url('tickets.php')) ?>"
           class="btn btn-sm <?= $activeStatusFilter === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            Todos
        </a>
        <a href="<?= e(url('tickets.php?status=abiertos')) ?>"
           class="btn btn-sm <?= $activeStatusFilter === 'abiertos' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            Abiertos
        </a>
        <?php foreach ($statuses as $statusItem) :
            $sCode = (string) $statusItem['code'];
            $sActive = $activeStatusFilter === $sCode;
            ?>
            <a href="<?= e(url('tickets.php?status=' . rawurlencode($sCode))) ?>"
               class="btn btn-sm <?= $sActive ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= e((string) $statusItem['name']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

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
                    <?php if ($activeStatusFilter !== '') : ?>
                        <a href="<?= e(url('tickets.php')) ?>"
                           class="btn btn-sm btn-outline-secondary rounded-pill"
                           aria-label="Quitar filtro de estado <?= e($activeStatusFilterLabel) ?>">
                            Estado: <?= e($activeStatusFilterLabel) ?> ×
                        </a>
                    <?php endif; ?>
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
                            <th scope="col">Actualización</th>
                            <th scope="col" class="text-end">Acción</th>
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
                            $ticketDetails = array(
                                'id' => (int) $ticket['id'],
                                'title' => (string) $ticket['title'],
                                'property' => $propertyRow['description'] !== '' ? $propertyRow['description'] : 'Bien desconocido',
                                'property_code' => $propertyRow['ident'] !== '' ? $propertyRow['ident'] : 'Sin código',
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
                                'history' => isset($ticket['history']) && is_array($ticket['history']) ? $ticket['history'] : array(),
                            );
                            $identRaw = trim($propertyRow['ident']);
                            $identPadded = $identRaw !== '' ? str_pad($identRaw, 4, '0', STR_PAD_LEFT) : '';
                            ?>
                            <tr data-export-id="<?= e((string) $ticket['id']) ?>"
                                data-export-code="<?= e($identPadded) ?>"
                                data-export-property="<?= e($ticketDetails['property']) ?>"
                                data-export-title="<?= e((string) $ticket['title']) ?>"
                                data-export-status="<?= e($statusName) ?>"
                                data-export-creator="<?= e($ticketDetails['created_by_name']) ?>"
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
                                <td class="text-nowrap"><?= e((string) $ticket['updated_at']) ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap"
                                                aria-label="Ver detalle del ticket #<?= e((string) $ticket['id']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#supportTicketDetailsModal"
                                                data-ticket="<?= e(js($ticketDetails)) ?>">
                                            <i class="bi bi-eye me-1" aria-hidden="true"></i>Ver detalle
                                        </button>
                                        <?php if ($canManageTickets) : ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap"
                                                    aria-label="Añadir seguimiento al ticket #<?= e((string) $ticket['id']) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#supportTicketFollowupModal"
                                                    data-ticket="<?= e(js($ticketDetails)) ?>">
                                                <i class="bi bi-chat-left-text me-1" aria-hidden="true"></i>Seguimiento
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success text-nowrap"
                                                    aria-label="Cambiar estado del ticket #<?= e((string) $ticket['id']) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#supportTicketStatusModal"
                                                    data-ticket="<?= e(js($ticketDetails)) ?>">
                                                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Estado
                                            </button>
                                        <?php endif; ?>
                                        <?php if (auth_has_role(array('admin', 'operador'))) : ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger text-nowrap"
                                                    aria-label="Eliminar ticket #<?= e((string) $ticket['id']) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#deleteTicketModal"
                                                    data-ticket-id="<?= e((string) $ticket['id']) ?>"
                                                    data-ticket-title="<?= e((string) $ticket['title']) ?>">
                                                <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar
                                            </button>
                                        <?php endif; ?>
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
                const ticketsTable = new window.Systec.TablePaginator('#ticketsTable', {
                    pageLength: 10,
                    search: {
                        search: <?= js($initialTicketSearch) ?>
                    },
                    language: {
                        search: 'Buscar tickets:',
                        searchPlaceholder: 'ID, bien, código, título, estado…',
                        emptyTable: 'No hay tickets registrados.',
                        zeroRecords: 'Ningún ticket coincide con la búsqueda.',
                        unitLabel: 'tickets'
                    }
                });

                const updateTicketFeedback = () => {
                    const filteredCount = ticketsTable.rows({ search: 'applied' }).count();
                    if (resultCountBadge) {
                        resultCountBadge.textContent = ticketsTable.search().trim()
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
        'properties_url' => 'tickets/actions/properties.php',
        'form_action' => 'tickets/actions/register.php',
        'selected_property_id' => '',
        'error' => '',
        'values' => array(),
    );
    include __DIR__ . '/include/ticket_register_modal.php';
    unset($ticketRegisterOptions);
    ?>
    <?php if ($openRegisterModalOnLoad) : ?>
        <script nonce="<?= e(systec_csp_nonce()) ?>">
            document.addEventListener('DOMContentLoaded', function () {
                const modalEl = document.getElementById('ticketRegisterModal');
                if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });
        </script>
    <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/include/support_ticket_delete_modal.php'; ?>
<?php
$main = ob_get_clean();
include __DIR__ . '/include/layout.php';
