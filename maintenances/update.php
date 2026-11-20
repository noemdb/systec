<?php
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../include/auth.php';

require_role(array('admin', 'operador', 'tecnico'));
verify_csrf();

$db = db();
$table = 'maintenances';

if (isset($_POST['update'])) {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : false;
    $property_id = isset($_POST['property_id']) ? filter_var($_POST['property_id'], FILTER_VALIDATE_INT) : false;
    $ticket_id = array_key_exists('ticket_id', $_POST) && $_POST['ticket_id'] !== ''
        ? filter_var($_POST['ticket_id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))
        : null;
    $type = isset($_POST['type']) ? trim((string) $_POST['type']) : '';
    $description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
    $date = isset($_POST['date']) ? trim((string) $_POST['date']) : '';
    $technician = isset($_POST['technician']) ? trim((string) $_POST['technician']) : '';
    $next_maintenance_date = isset($_POST['next_maintenance_date']) ? trim((string) $_POST['next_maintenance_date']) : '';
    $failure_reason = isset($_POST['failure_reason']) ? trim((string) $_POST['failure_reason']) : '';
    $notes = isset($_POST['notes']) ? trim((string) $_POST['notes']) : '';
    $status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';

    $existing = $db->getFirstForId($table, $id);
    if (!$existing && $id !== false && $id !== null && $id > 0) {
        http_response_code(404);
        exit('Mantenimiento no encontrado.');
    }

    include_once __DIR__ . '/validations/update.php';
    if ($validationError !== null) {
        http_response_code(422);
        exit($validationError);
    }

    $property = $db->getFirstForId('properties', $property_id);
    if (!$property) {
        http_response_code(422);
        exit('El bien seleccionado no existe.');
    }

    if ($ticket_id === false) {
        http_response_code(422);
        exit('El ticket seleccionado no es válido.');
    }
    if ($ticket_id !== null) {
        $ticket = $db->getFirstForConditions(
            'tickets',
            array(
                array('id', '=', (int) $ticket_id),
                array('property_id', '=', (int) $property_id),
                array('deleted_at', 'IS', null),
            )
        );
        if (!$ticket) {
            http_response_code(422);
            exit('El ticket seleccionado no existe o no está asociado al bien elegido.');
        }
    } elseif (!array_key_exists('ticket_id', $_POST)) {
        $ticket_id = isset($existing['ticket_id']) ? (int) $existing['ticket_id'] : null;
    }

    try {
        $code = $db->getCodeId($property_id);
        $data = array(
            'code' => $code,
            'property_id' => $property_id,
            'ticket_id' => $ticket_id,
            'type' => $type,
            'description' => $description,
            'date' => $date,
            'technician' => $technician,
            'next_maintenance_date' => $next_maintenance_date !== '' ? $next_maintenance_date : null,
            'failure_reason' => $failure_reason,
            'notes' => $notes,
            'status' => $status,
        );
        $db->update($table, $id, $data);

        $_SESSION['maintenance_notice'] = 'Mantenimiento actualizado correctamente.';
        header('Location: ' . url('maintenances.php?id=' . $id));
        exit();
    } catch (Exception $e) {
        error_log('Error actualizando mantenimiento: ' . $e->getMessage());
        http_response_code(500);
        exit('No se pudo actualizar el mantenimiento.');
    }
}

http_response_code(400);
exit('Solicitud inválida.');
