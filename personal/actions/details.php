<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';
require_personal_scope();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

$ticketId = isset($_GET['ticket_id']) ? filter_var($_GET['ticket_id'], FILTER_VALIDATE_INT) : false;
if ($ticketId === false || $ticketId === null || $ticketId < 1) {
    http_response_code(422);
    echo json_encode(array('error' => 'Identificador de ticket inválido.'));
    exit();
}

$user = auth_user();
try {
    $ticket = db()->personalTicketDetails((int) $ticketId, (int) $user['id']);
} catch (Throwable $e) {
    error_log('No se pudo cargar el detalle del ticket personal: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(array('error' => 'No fue posible cargar el detalle del ticket.'));
    exit();
}
if (!$ticket) {
    http_response_code(404);
    echo json_encode(array('error' => 'Ticket no encontrado.'));
    exit();
}

echo json_encode(
    $ticket,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
);
