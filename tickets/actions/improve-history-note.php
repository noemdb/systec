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

if (!auth_has_role(array('admin', 'operador', 'tecnico'))) {
    $respond(403, array('error' => 'Tu rol no tiene permiso para mejorar el seguimiento.'));
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

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $apiKey = getenv('API_KEY_NVIDIA_BUILD');
    $improved = systec_nvidia_improve_ticket_history_note(
        $note,
        $apiKey === false ? '' : $apiKey
    );
} catch (Throwable $e) {
    error_log('No se pudo mejorar la nota de seguimiento: ' . $e->getMessage());
    $respond(502, array('error' => $e->getMessage()));
}

$respond(200, array('note' => $improved['note']));
