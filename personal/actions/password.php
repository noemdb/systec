<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';

require_login();
verify_csrf();

$user = auth_user();
$userId = isset($user['id']) ? (int) $user['id'] : 0;

$currentPassword = isset($_POST['current_password']) ? (string) $_POST['current_password'] : '';
$newPassword = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';
$confirmPassword = isset($_POST['confirm_password']) ? (string) $_POST['confirm_password'] : '';

$result = auth_change_password($userId, $currentPassword, $newPassword, $confirmPassword);

$_SESSION['auth_password_feedback'] = array(
    'type' => $result['ok'] ? 'success' : 'danger',
    'message' => $result['message'],
);

$defaultReturn = auth_has_role(array('personal')) ? url('personal/index.php') : url('dashboard.php');
$returnTo = isset($_POST['return_to']) && is_string($_POST['return_to']) ? trim($_POST['return_to']) : '';

if ($returnTo !== '' && $returnTo[0] === '/' && substr($returnTo, 0, 2) !== '//' && strpos($returnTo, "\n") === false) {
    if (auth_has_role(array('personal')) && strpos($returnTo, '/personal') !== 0) {
        $returnTo = $defaultReturn;
    }
} else {
    $returnTo = $defaultReturn;
}

header('Location: ' . $returnTo);
exit();
