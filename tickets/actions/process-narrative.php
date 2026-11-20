<?php

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/nvidia_maintenance_narrative.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

$respond = static function (int $status, array $payload): void {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit();
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $respond(405, array('error' => 'Método no permitido.'));
}

if (!auth_check()) {
    $respond(401, array('error' => 'La sesión expiró. Inicia sesión e inténtalo de nuevo.'));
}

if (!auth_has_role(array('admin', 'operador', 'tecnico'))) {
    $respond(403, array('error' => 'Tu rol no tiene permiso para procesar relatos de tickets.'));
}

$csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
    ? $_POST['csrf_token']
    : '';
$expectedToken = isset($_SESSION['csrf']) && is_string($_SESSION['csrf'])
    ? $_SESSION['csrf']
    : '';
if ($expectedToken === '' || $csrfToken === '' || !hash_equals($expectedToken, $csrfToken)) {
    $respond(419, array('error' => 'La sesión expiró o el token de seguridad no es válido. Recarga la página.'));
}

$narrative = isset($_POST['narrative']) && is_string($_POST['narrative'])
    ? trim($_POST['narrative'])
    : '';
if ($narrative === '') {
    $respond(422, array('error' => 'Escribe el relato de la incidencia antes de procesarlo.'));
}
if (mb_strlen($narrative, 'UTF-8') > 10000) {
    $respond(422, array('error' => 'El relato supera la longitud permitida de 10000 caracteres.'));
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $apiKey = getenv('API_KEY_NVIDIA_BUILD');
    $ticket = systec_nvidia_process_ticket_narrative(
        $narrative,
        $apiKey === false ? '' : $apiKey
    );
} catch (Throwable $e) {
    error_log('No se pudo procesar el relato del ticket: ' . $e->getMessage());
    $respond(502, array('error' => $e->getMessage()));
}

$respond(200, $ticket);
