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
    $type = isset($_POST['type']) ? trim((string) $_POST['type']) : '';
    $description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
    $date = isset($_POST['date']) ? trim((string) $_POST['date']) : '';
    $technician = isset($_POST['technician']) ? trim((string) $_POST['technician']) : '';
    $next_maintenance_date = isset($_POST['next_maintenance_date']) ? trim((string) $_POST['next_maintenance_date']) : '';
    $failure_reason = isset($_POST['failure_reason']) ? trim((string) $_POST['failure_reason']) : '';
    $notes = isset($_POST['notes']) ? trim((string) $_POST['notes']) : '';
    $status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';

    include_once __DIR__ . '/validations/update.php';

    $existing = $db->getFirstForId($table, $id);
    if (!$existing) {
        http_response_code(404);
        exit('Mantenimiento no encontrado.');
    }

    $property = $db->getFirstForId('properties', $property_id);
    if (!$property) {
        http_response_code(422);
        exit('El bien seleccionado no existe.');
    }

    if (!array_key_exists($status, $db->getListMaintenanceStatus())) {
        http_response_code(422);
        exit('El estado del mantenimiento no es válido.');
    }

    if (!maintenance_transition_allowed((string) $existing['status'], $status)) {
        http_response_code(422);
        exit('La transición de estado no está permitida.');
    }

    if (strtotime($date) === false) {
        http_response_code(422);
        exit('La fecha de inicio no es válida.');
    }

    if ($next_maintenance_date !== '' && strtotime($next_maintenance_date) === false) {
        http_response_code(422);
        exit('La fecha del próximo mantenimiento no es válida.');
    }

    try {
        $code = $db->getCodeId($property_id);
        $data = array(
            'code' => $code,
            'property_id' => $property_id,
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
