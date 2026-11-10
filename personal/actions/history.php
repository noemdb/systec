<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';
require_personal_scope();

http_response_code(403);
exit('El rol personal solo puede consultar el historial del ticket.');
