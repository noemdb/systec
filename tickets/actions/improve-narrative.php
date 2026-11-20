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
    $respond(403, array('error' => 'Tu rol no tiene permiso para mejorar tickets.'));
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

$original = array();
$limits = array(
    'title' => 255,
    'description' => 5000,
    'observation' => 5000,
);
foreach ($limits as $field => $limit) {
    if (!isset($_POST[$field]) || !is_string($_POST[$field])) {
        $respond(422, array('error' => 'Faltan textos válidos para mejorar.'));
    }
    if (mb_strlen($_POST[$field], 'UTF-8') > $limit) {
        $respond(422, array('error' => 'Uno de los textos supera la longitud permitida.'));
    }
    $original[$field] = $_POST[$field];
}

if (!array_filter($original, static function (string $text): bool {
    return trim($text) !== '';
})) {
    $respond(422, array('error' => 'Completa al menos un campo antes de solicitar una propuesta.'));
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $apiKey = getenv('API_KEY_NVIDIA_BUILD');
    $improved = systec_nvidia_improve_ticket_texts(
        $original,
        $apiKey === false ? '' : $apiKey
    );
} catch (Throwable $e) {
    error_log('No se pudo mejorar la narrativa del ticket: ' . $e->getMessage());
    $respond(502, array('error' => $e->getMessage()));
}

$respond(200, $improved);
