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
    $currentStatus = mb_strtoupper(trim((string) $property['status']));
    $targetStatus = mb_strtoupper($status);
    $search = str_pad(trim((string) $property['ident']), 4, '0', STR_PAD_LEFT);
    $user = auth_user();
    $username = is_array($user) ? ($user['username'] ?? 'usuario') : 'usuario';

    if ($currentStatus !== $targetStatus) {
        $db->update('properties', $propertyId, array('status' => $targetStatus));
        error_log("Usuario '{$username}' actualizó el estado del bien #{$propertyId} ({$search}) de '{$currentStatus}' a '{$targetStatus}'.");
        $_SESSION['dashboard_maintenance_feedback'] = array(
            'type' => 'success',
            'message' => 'Se actualizó el estado del bien ' . $search . ' a ' . ($validStatuses[$status] ?? $targetStatus) . '.',
        );
    } else {
        $_SESSION['dashboard_maintenance_feedback'] = array(
            'type' => 'info',
            'message' => 'El bien ' . $search . ' ya se encontraba en estado ' . ($validStatuses[$status] ?? $targetStatus) . '.',
        );
    }

    header('Location: ' . dashboard_return_url($_POST, $search));
    exit();
} catch (Exception $e) {
    error_log('Error actualizando estado del bien: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo actualizar el estado del bien.');
}
