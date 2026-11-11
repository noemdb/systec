<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';

if (auth_check() && auth_has_role(array('personal'))) {
    header('Location: ' . url('personal/index.php'));
    exit();
}

require_role(array('admin', 'operador'));
verify_csrf();

$ticketId = isset($_POST['ticket_id']) ? filter_var($_POST['ticket_id'], FILTER_VALIDATE_INT) : false;
if ($ticketId === false || $ticketId === null || $ticketId < 1) {
    http_response_code(422);
    exit('Identificador de ticket inválido.');
}

$user = auth_user();
try {
    if (!db()->softDeleteTicket((int) $ticketId, (int) $user['id'], date('Y-m-d H:i:s'))) {
        http_response_code(404);
        exit('Ticket no encontrado o ya eliminado.');
    }

    $_SESSION['ticket_notice'] = 'Ticket eliminado. Su historial se conserva.';
    header('Location: ' . url('tickets.php'));
    exit();
} catch (Throwable $e) {
    error_log('Error eliminando ticket: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo eliminar el ticket.');
}
