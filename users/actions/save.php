<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';

require_role(array('admin'));
verify_csrf();

$currentUser = auth_user();
$actorId = isset($currentUser['id']) ? (int) $currentUser['id'] : 0;
$actorUsername = is_array($currentUser) && isset($currentUser['username']) ? (string) $currentUser['username'] : 'admin';
$action = isset($_POST['action']) && is_string($_POST['action']) ? trim($_POST['action']) : 'save';

try {
    $db = db();

    if ($action === 'delete') {
        $targetId = isset($_POST['user_id']) ? filter_var($_POST['user_id'], FILTER_VALIDATE_INT) : false;
        $confirmUsername = isset($_POST['confirm_username']) && is_string($_POST['confirm_username'])
            ? trim($_POST['confirm_username'])
            : '';
        $confirmedRisk = isset($_POST['confirm_risk']) && $_POST['confirm_risk'] === '1';

        $result = auth_admin_delete_user(
            ($targetId !== false && $targetId !== null) ? (int) $targetId : 0,
            $actorId,
            $confirmUsername,
            $confirmedRisk,
            $db
        );
        if ($result['ok']) {
            error_log("Auditoría usuarios: '{$actorUsername}' ELIMINÓ permanentemente al usuario #{$result['id']} ('{$result['username']}', rol={$result['role']}).");
        }
        $_SESSION['users_feedback'] = array(
            'type' => $result['ok'] ? 'success' : 'danger',
            'message' => $result['message'],
        );
    } elseif ($action === 'unlock') {
        $targetId = isset($_POST['user_id']) ? filter_var($_POST['user_id'], FILTER_VALIDATE_INT) : false;
        $target = ($targetId !== false && $targetId !== null) ? $db->getFirstForId('users', (int) $targetId) : null;
        if (!$target) {
            $_SESSION['users_feedback'] = array(
                'type' => 'danger',
                'message' => 'El usuario seleccionado no existe.',
            );
        } else {
            auth_throttle_clear((string) $target['username']);
            error_log("Auditoría usuarios: '{$actorUsername}' desbloqueó intentos fallidos de '{$target['username']}' (#{$target['id']}).");
            $_SESSION['users_feedback'] = array(
                'type' => 'success',
                'message' => 'Se desbloqueó la cuenta «' . $target['username'] . '».',
            );
        }
    } elseif ($action === 'toggle_active') {
        $targetId = isset($_POST['user_id']) ? filter_var($_POST['user_id'], FILTER_VALIDATE_INT) : false;
        $target = ($targetId !== false && $targetId !== null) ? $db->getFirstForId('users', (int) $targetId) : null;
        if (!$target) {
            $_SESSION['users_feedback'] = array(
                'type' => 'danger',
                'message' => 'El usuario seleccionado no existe.',
            );
        } else {
            $nextActive = (int) ($target['active'] ?? 0) === 1 ? '0' : '1';
            $result = auth_admin_save_user(array(
                'user_id' => (string) $target['id'],
                'role' => (string) $target['role'],
                'active' => $nextActive,
                'password' => '',
            ), $actorId, $db);

            if ($result['ok']) {
                $stateLabel = $nextActive === '1' ? 'activó' : 'desactivó';
                error_log("Auditoría usuarios: '{$actorUsername}' {$stateLabel} a '{$target['username']}' (#{$target['id']}).");
                $_SESSION['users_feedback'] = array(
                    'type' => 'success',
                    'message' => 'Cuenta «' . $target['username'] . '» ' . ($nextActive === '1' ? 'activada' : 'desactivada') . ' correctamente.',
                );
            } else {
                $_SESSION['users_feedback'] = array(
                    'type' => 'danger',
                    'message' => $result['message'],
                );
            }
        }
    } else {
        $isNew = empty($_POST['user_id']);
        $passwordChanged = isset($_POST['password']) && (string) $_POST['password'] !== '';
        $result = auth_admin_save_user($_POST, $actorId, $db);
        if ($result['ok']) {
            $targetUsername = trim((string) ($_POST['username'] ?? ''));
            $targetRole = trim((string) ($_POST['role'] ?? ''));
            $targetActive = (isset($_POST['active']) && (string) $_POST['active'] === '0') ? 0 : 1;
            $profileNote = (isset($_POST['first_name']) && trim((string) $_POST['first_name']) !== '')
                ? ' (perfil actualizado)'
                : '';
            $verb = $isNew ? 'creó' : 'actualizó';
            $pwdNote = $passwordChanged ? ' (contraseña actualizada)' : '';
            error_log("Auditoría usuarios: '{$actorUsername}' {$verb} usuario #{$result['id']} ('{$targetUsername}', rol={$targetRole}, active={$targetActive}){$pwdNote}{$profileNote}.");
        }
        $_SESSION['users_feedback'] = array(
            'type' => $result['ok'] ? 'success' : 'danger',
            'message' => $result['message'],
        );
    }
} catch (Throwable $e) {
    error_log('Error administrando usuario: ' . $e->getMessage());
    $_SESSION['users_feedback'] = array(
        'type' => 'danger',
        'message' => 'No fue posible guardar los cambios del usuario.',
    );
}

header('Location: ' . url('users.php'));
exit();
