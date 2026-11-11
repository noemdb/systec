<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';
require_personal_scope();
verify_csrf();

$db = db();
$user = auth_user();
$ticketId = isset($_POST['ticket_id']) ? filter_var($_POST['ticket_id'], FILTER_VALIDATE_INT) : false;
$description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';

if ($ticketId === false || $ticketId === null || $description === '') {
    http_response_code(422);
    exit('La nota del seguimiento es obligatoria.');
}

$ticket = $db->getFirstForId('tickets', $ticketId);
if (!$ticket) {
    http_response_code(404);
    exit('Ticket no encontrado.');
}

if (!auth_can_access_ticket_for_user((int) $ticket['created_by'])) {
    http_response_code(403);
    exit('No tiene permiso para seguir este ticket.');
}

$now = date('Y-m-d H:i:s');
$transactionStarted = false;
try {
    if (!$db->exec('BEGIN IMMEDIATE')) {
        throw new RuntimeException('No se pudo iniciar la transacción: ' . $db->lastErrorMsg());
    }
    $transactionStarted = true;

    $db->create('ticket_histories', array(
        'ticket_id' => (int) $ticketId,
        'actor_user_id' => (int) $user['id'],
        'action_type' => 'comment',
        'description' => $description,
        'observation' => null,
        'from_status_id' => (int) $ticket['status_id'],
        'to_status_id' => (int) $ticket['status_id'],
        'created_at' => $now,
    ));

    $db->update('tickets', $ticketId, array('updated_at' => $now));

    if (!$db->exec('COMMIT')) {
        throw new RuntimeException('No se pudo confirmar la transacción: ' . $db->lastErrorMsg());
    }
    $transactionStarted = false;

    $_SESSION['personal_ticket_followup_saved'] = true;
    header('Location: ' . url('personal/index.php'));
    exit();
} catch (Throwable $e) {
    if ($transactionStarted) {
        $db->exec('ROLLBACK');
    }
    error_log('Error registrando seguimiento personal: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo registrar el seguimiento.');
}
