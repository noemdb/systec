<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';

require_role(array('admin', 'operador'));
verify_csrf();

$propertyId = isset($_POST['property_id']) ? filter_var($_POST['property_id'], FILTER_VALIDATE_INT) : false;
$status = isset($_POST['status']) ? mb_strtolower(trim((string) $_POST['status'])) : '';

if ($propertyId === false || $propertyId === null) {
    http_response_code(400);
    exit('Bien inválido');
}

$db = db();
$validStatuses = $db->getListPropertyStatus();
if (!array_key_exists($status, $validStatuses)) {
    http_response_code(400);
    exit('Estado inválido');
}

$property = $db->getFirstForId('properties', $propertyId);

if (!$property) {
    http_response_code(404);
    exit('Bien no encontrado');
}

try {
    $db->update('properties', $propertyId, array('status' => mb_strtoupper($status)));

    $search = str_pad(trim((string) $property['ident']), 4, '0', STR_PAD_LEFT);
    header('Location: ' . url('dashboard.php?search=' . urlencode($search)));
    exit();
} catch (Exception $e) {
    error_log('Error actualizando estado del bien: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo actualizar el estado del bien.');
}
