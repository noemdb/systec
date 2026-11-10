<?php
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../include/auth.php';

require_role(array('admin', 'operador', 'tecnico'));
verify_csrf();

$db = db();
$table = 'maintenances';

if (isset($_POST['register'])) {
    $property_id = isset($_POST['property_id']) ? filter_var($_POST['property_id'], FILTER_VALIDATE_INT) : false;
    $type = isset($_POST['type']) ? trim((string) $_POST['type']) : '';
    $description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
    $date = isset($_POST['date']) ? trim((string) $_POST['date']) : '';
    $technician = isset($_POST['technician']) ? trim((string) $_POST['technician']) : '';
    $next_maintenance_date = isset($_POST['next_maintenance_date']) ? trim((string) $_POST['next_maintenance_date']) : '';
    $failure_reason = isset($_POST['failure_reason']) ? trim((string) $_POST['failure_reason']) : '';
    $notes = isset($_POST['notes']) ? trim((string) $_POST['notes']) : '';
    $status = 'iniciado';

    include_once __DIR__ . '/validations/register.php';

    $property = $db->getFirstForId('properties', $property_id);
    if (!$property) {
        http_response_code(422);
        exit('El bien seleccionado no existe.');
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
            'property_id' => $property_id,
            'code' => $code,
            'type' => $type,
            'description' => $description,
            'date' => $date,
            'technician' => $technician,
            'next_maintenance_date' => $next_maintenance_date !== '' ? $next_maintenance_date : null,
            'failure_reason' => $failure_reason,
            'notes' => $notes,
            'status' => $status,
        );
        $db->create($table, $data);

        $returnPage = isset($_POST['return_to']) && $_POST['return_to'] === 'dashboard'
            ? 'dashboard.php'
            : 'maintenances.php';
        header('Location: ' . url($returnPage));
        exit();
    } catch (Exception $e) {
        error_log('Error registrando mantenimiento: ' . $e->getMessage());
        http_response_code(500);
        exit('No se pudo registrar el mantenimiento.');
    }
}

http_response_code(400);
exit('Solicitud inválida.');
