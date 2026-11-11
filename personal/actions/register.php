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

$returnFormError = function (string $message) use ($property_id, $title, $description, $observation): void {
    $_SESSION['personal_ticket_form'] = array(
        'error' => $message,
        'values' => array(
            'property_id' => $property_id === false || $property_id === null ? '' : (string) $property_id,
            'title' => $title,
            'description' => $description,
            'observation' => $observation,
        ),
    );
    header('Location: ' . url('personal/index.php'), true, 303);
    exit();
};

if ($property_id === false || $property_id === null || $property_id < 1 || $title === '' || $description === '') {
    $returnFormError('Completa el título, la descripción y selecciona un bien.');
}
if (mb_strlen($title, 'UTF-8') > 255
    || mb_strlen($description, 'UTF-8') > 5000
    || mb_strlen($observation, 'UTF-8') > 5000) {
    $returnFormError('Revisa la longitud de los campos: el título admite 255 caracteres y las descripciones hasta 5000.');
}

$property = $db->getFirstForId('properties', $property_id);
if (!$property) {
    $returnFormError('El bien seleccionado ya no existe. Selecciona otro bien.');
}

$initialStatus = $db->getTicketStatusByCode('iniciado');
if (!$initialStatus) {
    $returnFormError('No se puede registrar el ticket porque no está disponible el estado inicial.');
}

$transactionStarted = false;
try {
    if (!$db->exec('BEGIN IMMEDIATE')) {
        throw new RuntimeException('No se pudo iniciar la transacción: ' . $db->lastErrorMsg());
    }
    $transactionStarted = true;
    $createdAt = date('Y-m-d H:i:s');
    $ticketId = $db->create('tickets', array(
        'property_id' => $property_id,
        'status_id' => (int) $initialStatus['id'],
        'title' => $title,
        'description' => $description,
        'observation' => $observation !== '' ? $observation : null,
        'created_by' => (int) $user['id'],
        'assigned_to' => null,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
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
        'created_at' => $createdAt,
    ));

    if (!$db->exec('COMMIT')) {
        throw new RuntimeException('No se pudo confirmar la transacción: ' . $db->lastErrorMsg());
    }
    $transactionStarted = false;
    header('Location: ' . url('personal/detail.php?id=' . (int) $ticketId));
    exit();
} catch (Throwable $e) {
    if ($transactionStarted) {
        $db->exec('ROLLBACK');
    }
    error_log('Error creando ticket personal: ' . $e->getMessage());
    $returnFormError('No se pudo registrar el ticket. Tus datos se conservaron; intenta nuevamente.');
}
