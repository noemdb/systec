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

require_role(array('admin', 'operador', 'tecnico'));

$db = db();
$user = auth_user();
$createdByFilter = auth_has_role(array('personal')) ? (int) $user['id'] : null;

// PERF-02: Una única consulta obtiene los estados activos y sus conteos agregados.
$dashboardSummary = $db->tickets()->supportTicketDashboardSummary($createdByFilter);
$statuses = $dashboardSummary['statuses'];
$summaryCounts = $dashboardSummary['counts'];

$matchingCount = $summaryCounts['total'];

// PERF-02: Segunda y última consulta con JOIN a properties, users, ticket_status e historial JSON.
$tickets = $matchingCount > 0
    ? $db->supportTickets($matchingCount, 0, $createdByFilter)
    : array();

$statusClasses = array(
    'iniciado' => 'primary',
    'en_progreso' => 'info',
    'con_retraso' => 'warning',
    'en_espera' => 'secondary',
    'finalizado' => 'success',
);

$title = 'Tickets';
$ticketNotice = isset($_SESSION['ticket_notice']) && is_string($_SESSION['ticket_notice'])
    ? $_SESSION['ticket_notice']
    : '';
unset($_SESSION['ticket_notice']);
$notifications = $ticketNotice !== ''
    ? array(toast_notification('success', $ticketNotice))
    : array();
ob_start();
?>
<div class="container-fluid">
    <section class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3"
             aria-labelledby="tickets-page-title">
        <div>
            <h1 class="h3 mb-1" id="tickets-page-title">Tickets</h1>
            <p class="text-body-secondary mb-0">Registro y seguimiento de incidencias por bien.</p>
        </div>
        <button type="button" class="btn btn-primary align-self-md-start flex-shrink-0"
                data-bs-toggle="modal" data-bs-target="#ticketRegisterModal">
            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Nuevo ticket
        </button>
    </section>

    <section class="row g-3 mb-4" aria-label="Indicadores de tickets">
        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-primary shadow-sm text-start">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-primary-subtle text-primary p-2" aria-hidden="true">
                        <i class="bi bi-ticket-detailed fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Total</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['total']) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-info shadow-sm text-start">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-info-subtle text-info p-2" aria-hidden="true">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">En progreso</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['in_progress']) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-warning shadow-sm text-start">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-warning-subtle text-warning p-2" aria-hidden="true">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Con retraso</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['delayed']) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-secondary shadow-sm text-start">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-secondary-subtle text-secondary p-2" aria-hidden="true">
                        <i class="bi bi-pause-circle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">En espera</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['waiting']) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-success shadow-sm text-start">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-success-subtle text-success p-2" aria-hidden="true">
                        <i class="bi bi-check-circle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Finalizados</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['closed']) ?></p>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <?php if ($tickets === array()) : ?>
        <section class="card shadow-sm border-0 text-center" aria-label="Sin tickets">
            <div class="card-body py-4" role="status" aria-live="polite">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary p-3 mb-3"
                      aria-hidden="true">
                    <i class="bi bi-inbox fs-3"></i>
                </span>
                <h2 class="h5 mb-1">No hay tickets registrados</h2>
                <p class="text-body-secondary mb-0">Los tickets disponibles aparecerán aquí.</p>
            </div>
        </section>
    <?php else : ?>
        <section class="card shadow-sm border-0 text-start" aria-labelledby="tickets-list-title">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <h2 class="h5 mb-0" id="tickets-list-title">
                    <i class="bi bi-ticket-detailed text-primary me-2" aria-hidden="true"></i>Registro de tickets
                </h2>
                <span class="badge text-bg-light border text-body-secondary" aria-live="polite"><?= e((string) $matchingCount) ?> registros</span>
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
                            ?>
                            <tr>
                                <td><span class="fw-semibold">#<?= e((string) $ticket['id']) ?></span></td>
                                <td>
                                    <span class="fw-semibold"><?= e($ticketDetails['property']) ?></span>
                                    <div class="small text-body-secondary">Código: <?= e($propertyRow['ident'] !== '' ? $propertyRow['ident'] : '—') ?></div>
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
            new window.Systec.TablePaginator('#ticketsTable', {
                pageLength: 10,
                language: {
                    search: 'Buscar tickets:',
                    searchPlaceholder: 'ID, bien, código, título, estado…',
                    emptyTable: 'No hay tickets registrados.',
                    zeroRecords: 'Ningún ticket coincide con la búsqueda.',
                    unitLabel: 'tickets'
                }
            });
        </script>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/include/support_ticket_details_modal.php'; ?>
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
<?php include __DIR__ . '/include/support_ticket_delete_modal.php'; ?>
<?php
$main = ob_get_clean();
include __DIR__ . '/include/layout.php';
