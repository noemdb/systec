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
$conditions = array();
if (auth_has_role(array('personal'))) {
    $conditions['created_by'] = (int) $user['id'];
}

$tickets = $db->index('tickets', $conditions, '*', 'updated_at DESC');
$statuses = $db->getTicketStatuses();
$summaryCounts = array(
    'total' => count($tickets),
    'in_progress' => 0,
    'delayed' => 0,
    'waiting' => 0,
    'closed' => 0,
);
$properties = array();
$ticketCreators = array();
$ticketHistories = array();
$statusClasses = array(
    'iniciado' => 'primary',
    'en_progreso' => 'info',
    'con_retraso' => 'warning',
    'en_espera' => 'secondary',
    'finalizado' => 'success',
);
foreach ($tickets as $ticket) {
    $property = $db->getFirstForId('properties', (int) $ticket['property_id']);
    $properties[(int) $ticket['id']] = $property ?: array('description' => 'Bien eliminado');
    $creator = $db->getFirstForId('users', (int) $ticket['created_by']);
    $ticketCreators[(int) $ticket['id']] = $creator ?: array('username' => 'Usuario');
    $ticketHistories[(int) $ticket['id']] = $db->ticketHistoryWithActors((int) $ticket['id']);

    $statusCode = isset($statuses[(int) $ticket['status_id']]['code'])
        ? (string) $statuses[(int) $ticket['status_id']]['code']
        : '';
    switch ($statusCode) {
        case 'en_progreso':
            $summaryCounts['in_progress']++;
            break;
        case 'con_retraso':
            $summaryCounts['delayed']++;
            break;
        case 'en_espera':
            $summaryCounts['waiting']++;
            break;
        case 'finalizado':
            $summaryCounts['closed']++;
            break;
    }
}

$title = 'Tickets';
ob_start();
?>
<div class="container-fluid">
    <section class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3"
             aria-labelledby="tickets-page-title">
        <div>
            <h1 class="h3 mb-1" id="tickets-page-title">Tickets</h1>
            <p class="text-body-secondary mb-0">Registro y seguimiento de incidencias por bien.</p>
        </div>
        <a href="<?= e(url('tickets/register.php')) ?>" class="btn btn-primary align-self-md-start flex-shrink-0">
            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Nuevo ticket
        </a>
    </section>

    <section class="row g-3 mb-4" aria-label="Indicadores de tickets">
        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-primary shadow-sm text-start">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-primary-subtle text-primary p-2" aria-hidden="true">
                        <i class="bi bi-ticket-detailed fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">Total</p>
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
                        <p class="small text-muted mb-1">En progreso</p>
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
                        <p class="small text-muted mb-1">Con retraso</p>
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
                        <p class="small text-muted mb-1">En espera</p>
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
                        <p class="small text-muted mb-1">Finalizados</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['closed']) ?></p>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <?php if ($tickets === array()) : ?>
        <section class="card shadow-sm border-0 text-center" aria-label="Sin tickets">
            <div class="card-body py-4">
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
                <span class="badge text-bg-light border text-body-secondary"><?= e((string) count($tickets)) ?> registros</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive tickets-table-responsive">
                    <table class="table table-hover align-middle mb-0">
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
                            $property = $properties[(int) $ticket['id']];
                            $creator = $ticketCreators[(int) $ticket['id']];
                            $status = $statuses[(int) $ticket['status_id']] ?? array('name' => 'Sin estado');
                            $statusCode = isset($status['code']) ? (string) $status['code'] : '';
                            $statusClass = isset($statusClasses[$statusCode]) ? $statusClasses[$statusCode] : 'light';
                            $historyDetails = array();
                            foreach ($ticketHistories[(int) $ticket['id']] as $historyEntry) {
                                $historyDetails[] = array(
                                    'created_at' => (string) $historyEntry['created_at'],
                                    'action_type' => (string) $historyEntry['action_type'],
                                    'description' => (string) $historyEntry['description'],
                                    'observation' => (string) ($historyEntry['observation'] ?? ''),
                                    'actor_username' => (string) ($historyEntry['actor_username'] ?? ''),
                                );
                            }
                            $ticketDetails = array(
                                'id' => (int) $ticket['id'],
                                'title' => (string) $ticket['title'],
                                'property' => (string) ($property['description'] ?? 'Bien desconocido'),
                                'property_code' => (string) ($property['ident'] ?? 'Sin código'),
                                'property_status' => (string) ($property['status'] ?? 'No disponible'),
                                'property_category' => property_category($property),
                                'status' => (string) ($status['name'] ?? 'Sin estado'),
                                'status_id' => (int) $ticket['status_id'],
                                'status_class' => $statusClass,
                                'created_by_name' => (string) ($creator['username'] ?? 'Usuario'),
                                'description' => (string) $ticket['description'],
                                'observation' => (string) ($ticket['observation'] ?? ''),
                                'created_at' => (string) ($ticket['created_at'] ?? ''),
                                'updated_at' => (string) ($ticket['updated_at'] ?? ''),
                                'closed_at' => (string) ($ticket['closed_at'] ?? ''),
                                'created_by' => (int) $ticket['created_by'],
                                'assigned_to' => (string) ($ticket['assigned_to'] ?? ''),
                                'history' => $historyDetails,
                            );
                            ?>
                            <tr>
                                <td><span class="fw-semibold">#<?= e((string) $ticket['id']) ?></span></td>
                                <td>
                                    <span class="fw-semibold"><?= e((string) ($property['description'] ?? 'Bien desconocido')) ?></span>
                                    <div class="small text-body-secondary">Código: <?= e((string) ($property['ident'] ?? '—')) ?></div>
                                </td>
                                <td><?= e((string) $ticket['title']) ?></td>
                                <td>
                                    <span class="badge text-bg-<?= e($statusClass) ?>">
                                        <?= e((string) ($status['name'] ?? 'Sin estado')) ?>
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
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/include/support_ticket_details_modal.php'; ?>
<?php include __DIR__ . '/include/support_ticket_followup_modal.php'; ?>
<?php include __DIR__ . '/include/support_ticket_status_modal.php'; ?>
<?php
$main = ob_get_clean();
include __DIR__ . '/include/layout.php';
