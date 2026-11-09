<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';

require_role(array('admin', 'operador', 'tecnico'));
verify_csrf();

$db = db();
$table = 'maintenances';
$id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : false;
$status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';

if ($id === false || $id === null || $status === '') {
    http_response_code(400);
    exit('Datos incompletos.');
}

if (!array_key_exists($status, $db->getListMaintenanceStatus())) {
    http_response_code(422);
    exit('Estado de mantenimiento inválido.');
}

$maintenance = $db->getFirstForId($table, $id);
if (!$maintenance) {
    http_response_code(404);
    exit('Mantenimiento no encontrado.');
}

if (!maintenance_transition_allowed((string) $maintenance['status'], $status)) {
    http_response_code(422);
    exit('La transición de estado no está permitida.');
}

try {
    $data = array('status' => $status);

    if ($status === 'finalizado') {
        $date = trim((string) $maintenance['date']);
        if ($date !== '' && strtotime($date) !== false) {
            $data['time_taken'] = $db->restarFechasEnHoras(new DateTime(), new DateTime($date));
        }
    }

    $db->update($table, $id, $data);

    header('Location: ' . url('maintenances.php?id=' . $id));
    exit();
} catch (Exception $e) {
    error_log('Error cambiando estado del mantenimiento: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo cambiar el estado del mantenimiento.');
}
