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
    $respond(403, array('error' => 'Tu rol no tiene permiso para editar mantenimientos.'));
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

$maintenanceId = null;
if (isset($_POST['id']) && $_POST['id'] !== '') {
    $maintenanceId = is_string($_POST['id'])
        ? filter_var($_POST['id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))
        : false;
    if ($maintenanceId === false) {
        $respond(422, array('error' => 'Identificador de mantenimiento inválido.'));
    }
}

$original = array();
foreach (array('description', 'failure_reason', 'notes') as $field) {
    if (!isset($_POST[$field]) || !is_string($_POST[$field])) {
        $respond(422, array('error' => 'Faltan textos narrativos válidos para mejorar.'));
    }
    $original[$field] = $_POST[$field];
}

if (!array_filter($original, static function (string $text): bool {
    return trim($text) !== '';
})) {
    $respond(422, array('error' => 'Completa al menos un campo narrativo antes de solicitar una propuesta.'));
}

try {
    if ($maintenanceId !== null && !db()->getFirstForId('maintenances', $maintenanceId)) {
        $respond(404, array('error' => 'No se encontró el mantenimiento seleccionado.'));
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $apiKey = getenv('API_KEY_NVIDIA_BUILD');
    $improved = systec_nvidia_improve_maintenance_texts(
        $original,
        $apiKey === false ? '' : $apiKey
    );
} catch (Throwable $e) {
    error_log('No se pudo mejorar la narrativa del mantenimiento: ' . $e->getMessage());
    $respond(502, array('error' => $e->getMessage()));
}

echo json_encode(
    $improved,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
);
