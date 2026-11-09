<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';

require_login();
verify_csrf();
auth_logout();

header('Location: ' . url('login.php'));
exit();
