<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';
require_personal_scope();
verify_csrf();

$db = db();
$user = auth_user();

$property_id = isset($_POST['property_id']) ? filter_var($_POST['property_id'], FILTER_VALIDATE_INT) : false;
$title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
$description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
$observation = isset($_POST['observation']) ? trim((string) $_POST['observation']) : '';

if ($property_id === false || $property_id === null || $title === '' || $description === '') {
    http_response_code(422);
    exit('Los datos del ticket son incompletos.');
}

$property = $db->getFirstForId('properties', $property_id);
if (!$property) {
    http_response_code(422);
    exit('El bien seleccionado no existe.');
}

$initialStatus = $db->getTicketStatusByCode('iniciado');
if (!$initialStatus) {
    http_response_code(500);
    exit('No existe el estado inicial del ticket.');
}

try {
    $ticketId = $db->create('tickets', array(
        'property_id' => $property_id,
        'status_id' => (int) $initialStatus['id'],
        'title' => $title,
        'description' => $description,
        'observation' => $observation !== '' ? $observation : null,
        'created_by' => (int) $user['id'],
        'assigned_to' => null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'closed_at' => null,
    ));

    $db->create('ticket_histories', array(
        'ticket_id' => (int) $ticketId,
        'actor_user_id' => (int) $user['id'],
        'action_type' => 'created',
        'description' => 'Ticket registrado por el usuario.',
        'observation' => $observation !== '' ? $observation : null,
        'from_status_id' => null,
        'to_status_id' => (int) $initialStatus['id'],
        'created_at' => date('Y-m-d H:i:s'),
    ));

    header('Location: ' . url('personal/detail.php?id=' . (int) $ticketId));
    exit();
} catch (Throwable $e) {
    error_log('Error creando ticket personal: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo registrar el ticket.');
}
