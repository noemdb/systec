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
$user = auth_user();
$ticket_id = isset($_POST['ticket_id']) ? filter_var($_POST['ticket_id'], FILTER_VALIDATE_INT) : false;
$description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';

if ($ticket_id === false || $ticket_id === null || $description === '') {
    http_response_code(422);
    exit('La nota del seguimiento es obligatoria.');
}

$ticket = $db->getFirstForConditions(
    'tickets',
    array(array('id', '=', (int) $ticket_id), array('deleted_at', 'IS', null))
);
if (!$ticket) {
    http_response_code(404);
    exit('Ticket no encontrado.');
}

if (!auth_can_access_ticket_for_user((int) $ticket['created_by'])) {
    http_response_code(403);
    exit('No tiene permiso para seguir este ticket.');
}

$now = date('Y-m-d H:i:s');
$returnToTickets = isset($_POST['return_to']) && is_string($_POST['return_to']) && $_POST['return_to'] === 'tickets';
$rawReturnQuery = isset($_POST['return_query']) && is_string($_POST['return_query']) ? $_POST['return_query'] : '';
$inTransaction = false;
try {
    if (!$db->exec('BEGIN IMMEDIATE')) {
        throw new RuntimeException('No se pudo iniciar la transacción de seguimiento.');
    }
    $inTransaction = true;

    $db->create('ticket_histories', array(
        'ticket_id' => (int) $ticket_id,
        'actor_user_id' => (int) $user['id'],
        'action_type' => 'comment',
        'description' => $description,
        'observation' => null,
        'from_status_id' => (int) $ticket['status_id'],
        'to_status_id' => (int) $ticket['status_id'],
        'created_at' => $now,
    ));

    $db->update('tickets', $ticket_id, array('updated_at' => $now));

    if (!$db->exec('COMMIT')) {
        throw new RuntimeException('No se pudo confirmar el registro de seguimiento.');
    }
    $inTransaction = false;

    $_SESSION['ticket_notice_type'] = 'success';
    $_SESSION['ticket_notice'] = 'Seguimiento registrado en el ticket #' . (int) $ticket_id . '.';
    $redirectUrl = $returnToTickets
        ? tickets_return_url($rawReturnQuery)
        : url('tickets/detail.php?id=' . (int) $ticket_id);
    header('Location: ' . $redirectUrl);
    exit();
} catch (Throwable $e) {
    if ($inTransaction) {
        $db->exec('ROLLBACK');
    }
    error_log('Error registrando seguimiento: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo registrar el seguimiento.');
}
