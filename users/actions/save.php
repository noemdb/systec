<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';

require_role(array('admin'));
verify_csrf();

$currentUser = auth_user();
$actorId = isset($currentUser['id']) ? (int) $currentUser['id'] : 0;

try {
    $result = auth_admin_save_user($_POST, $actorId);
    $_SESSION['users_feedback'] = array(
        'type' => $result['ok'] ? 'success' : 'danger',
        'message' => $result['message'],
    );
} catch (Throwable $e) {
    error_log('Error administrando usuario: ' . $e->getMessage());
    $_SESSION['users_feedback'] = array(
        'type' => 'danger',
        'message' => 'No fue posible guardar los cambios del usuario.',
    );
}

header('Location: ' . url('users.php'));
exit();
