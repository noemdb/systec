<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';

if (auth_check() && auth_has_role(array('personal'))) {
    $ticketId = isset($_POST['ticket_id']) ? filter_var($_POST['ticket_id'], FILTER_VALIDATE_INT) : false;
    $redirect = 'personal/index.php';
    if ($ticketId !== false && $ticketId !== null) {
        $redirect = 'personal/detail.php?id=' . (int) $ticketId;
    }
    header('Location: ' . url($redirect));
    exit();
}

require_role(array('admin', 'operador', 'tecnico'));
verify_csrf();

$db = db();
$ticket_id = isset($_POST['ticket_id']) ? filter_var($_POST['ticket_id'], FILTER_VALIDATE_INT) : false;
$status_id = isset($_POST['status_id']) ? filter_var($_POST['status_id'], FILTER_VALIDATE_INT) : false;

if ($ticket_id === false || $ticket_id === null || $status_id === false || $status_id === null) {
    http_response_code(422);
    exit('Parámetros del ticket inválidos.');
}

$ticket = $db->getFirstForId('tickets', $ticket_id);
if (!$ticket) {
    http_response_code(404);
    exit('Ticket no encontrado.');
}

$status = $db->getFirstForId('ticket_status', $status_id);
if (!$status) {
    http_response_code(422);
    exit('Estado no válido.');
}

$user = auth_user();
$now = date('Y-m-d H:i:s');
$closedAt = $status['code'] === 'finalizado' ? $now : null;

try {
    $db->update('tickets', $ticket_id, array(
        'status_id' => (int) $status_id,
        'updated_at' => $now,
        'closed_at' => $closedAt,
    ));

    $db->create('ticket_histories', array(
        'ticket_id' => (int) $ticket_id,
        'actor_user_id' => (int) $user['id'],
        'action_type' => 'status_change',
        'description' => 'Cambio de estado a ' . $status['name'] . '.',
        'observation' => null,
        'from_status_id' => (int) $ticket['status_id'],
        'to_status_id' => (int) $status_id,
        'created_at' => $now,
    ));

    header('Location: ' . url('tickets/detail.php?id=' . (int) $ticket_id));
    exit();
} catch (Throwable $e) {
    error_log('Error actualizando ticket: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo actualizar el estado del ticket.');
}
