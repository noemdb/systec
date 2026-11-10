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
    $ticketHistories[(int) $ticket['id']] = $db->index(
        'ticket_histories',
        array('ticket_id' => (int) $ticket['id']),
        '*',
        'created_at ASC, id ASC'
    );
}

$title = 'Tickets';
ob_start();
?>
<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Tickets</h1>
            <p class="text-muted mb-0">Registro y seguimiento de incidencias por bien.</p>
        </div>
        <a href="<?= e(url('tickets/register.php')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> Nuevo ticket
        </a>
    </div>

    <?php if ($tickets === array()) : ?>
        <div class="alert alert-light border">No hay tickets registrados para este usuario.</div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Bien</th>
                    <th>Título</th>
                    <th>Estado</th>
                    <th>Actualización</th>
                    <th class="text-end">Acción</th>
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
                        <td>#<?= e((string) $ticket['id']) ?></td>
                        <td>
                            <?= e((string) ($property['description'] ?? 'Bien desconocido')) ?>
                            <div class="small text-muted">Código: <?= e((string) ($property['ident'] ?? '—')) ?></div>
                        </td>
                        <td><?= e((string) $ticket['title']) ?></td>
                        <td>
                            <span class="badge text-bg-<?= e($statusClass) ?>">
                                <?= e((string) $status['name']) ?>
                            </span>
                        </td>
                        <td><?= e((string) $ticket['updated_at']) ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#supportTicketDetailsModal"
                                    data-ticket="<?= e(js($ticketDetails)) ?>">
                                <i class="bi bi-eye me-1" aria-hidden="true"></i>Ver detalle
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/include/support_ticket_details_modal.php'; ?>
<?php
$main = ob_get_clean();
include __DIR__ . '/include/layout.php';
