<?php

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/nvidia_maintenance_narrative.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

$respond = static function (int $status, array $payload): void {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit();
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $respond(405, array('error' => 'Método no permitido.'));
}

if (!auth_check()) {
    $respond(401, array('error' => 'La sesión expiró. Inicia sesión e inténtalo de nuevo.'));
}

if (!auth_has_role(array('personal'))) {
    $respond(403, array('error' => 'Tu rol no tiene permiso para mejorar el seguimiento personal.'));
}

$csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
    ? $_POST['csrf_token']
    : '';
$expectedToken = isset($_SESSION['csrf']) && is_string($_SESSION['csrf'])
    ? $_SESSION['csrf']
    : '';
if ($expectedToken === '' || $csrfToken === '' || !hash_equals($expectedToken, $csrfToken)) {
    $respond(419, array('error' => 'La sesión expiró o el token de seguridad no es válido. Recarga la página.'));
}

$ticketId = isset($_POST['ticket_id']) && is_string($_POST['ticket_id'])
    ? filter_var($_POST['ticket_id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))
    : false;
$note = isset($_POST['note']) && is_string($_POST['note']) ? trim($_POST['note']) : '';
if ($ticketId === false) {
    $respond(422, array('error' => 'Identificador de ticket inválido.'));
}
if ($note === '') {
    $respond(422, array('error' => 'Escribe una nota de seguimiento antes de solicitar una propuesta.'));
}
if (mb_strlen($note, 'UTF-8') > 10000) {
    $respond(422, array('error' => 'La nota supera la longitud permitida de 10000 caracteres.'));
}

$db = db();
$ticket = $db->getFirstForConditions(
    'tickets',
    array(array('id', '=', (int) $ticketId), array('deleted_at', 'IS', null))
);
if (!$ticket) {
    $respond(404, array('error' => 'Ticket no encontrado.'));
}
if (!auth_can_access_ticket_for_user((int) $ticket['created_by'])) {
    $respond(403, array('error' => 'No tiene permiso para seguir este ticket.'));
}

$ticketStatus = $db->getFirstForId('ticket_status', (int) $ticket['status_id']);
if ($ticketStatus && (string) ($ticketStatus['code'] ?? '') === 'finalizado') {
    $respond(409, array('error' => 'No se puede añadir seguimiento a un ticket finalizado.'));
}

$property = !empty($ticket['property_id'])
    ? $db->getFirstForId('properties', (int) $ticket['property_id'])
    : null;
$contextParts = array();
if (trim((string) ($ticket['title'] ?? '')) !== '') {
    $contextParts[] = 'Incidencia: ' . trim((string) $ticket['title']);
}
if ($property && trim((string) ($property['description'] ?? '')) !== '') {
    $contextParts[] = 'Bien: ' . trim((string) $property['description']);
}
if ($ticketStatus && trim((string) ($ticketStatus['name'] ?? '')) !== '') {
    $contextParts[] = 'Estado actual: ' . trim((string) $ticketStatus['name']);
}
$ticketContext = implode(' | ', $contextParts);

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$apiKey = getenv('API_KEY_NVIDIA_BUILD');
$improved = systec_improve_ticket_history_note_with_fallback(
    $note,
    $apiKey === false ? '' : $apiKey,
    $ticketContext
);

$respond(200, $improved);
