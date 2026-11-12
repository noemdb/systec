<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';

require_role(array('admin', 'operador', 'tecnico'));
verify_csrf();

$db = db();
$table = 'maintenances';
$status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';

if (!in_array($status, array('revision', 'finalizado'), true)
    || !array_key_exists($status, $db->getListMaintenanceStatus())) {
    http_response_code(422);
    exit('Estado de mantenimiento inválido.');
}

$rawIds = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : array();
$ids = array();
foreach (array_slice($rawIds, 0, 500) as $rawId) {
    $id = filter_var($rawId, FILTER_VALIDATE_INT);
    if ($id !== false && $id !== null) {
        $ids[$id] = $id;
    }
}

if (!$ids) {
    http_response_code(400);
    exit('No se recibieron mantenimientos para actualizar.');
}

$allowedReturnParams = array(
    'category', 'adscription', 'search', 'estado', 'tipo',
    'tecnico', 'desde', 'hasta', 'proximo', 'vencido',
);
$returnParams = array();
if (isset($_POST['return_query']) && is_string($_POST['return_query'])) {
    $parsed = array();
    parse_str(ltrim($_POST['return_query'], '?'), $parsed);
    foreach ($allowedReturnParams as $key) {
        if (isset($parsed[$key]) && is_string($parsed[$key])) {
            $value = trim($parsed[$key]);
            if ($value !== '' && strlen($value) <= 200) {
                $returnParams[$key] = $value;
            }
        }
    }
}

$updated = 0;
$skipped = 0;

foreach ($ids as $id) {
    $maintenance = $db->getFirstForId($table, $id);
    if (!$maintenance || !maintenance_transition_allowed((string) $maintenance['status'], $status)) {
        $skipped++;
        continue;
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
        $updated++;
    } catch (Exception $e) {
        error_log('Error actualizando mantenimiento ' . $id . ' en lote: ' . $e->getMessage());
        $skipped++;
    }
}

$returnParams['bulk_updated'] = $updated;
$returnParams['bulk_skipped'] = $skipped;

header('Location: ' . url('maintenances.php?' . http_build_query($returnParams)));
exit();
