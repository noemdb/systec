<?php

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';

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

if (!auth_has_role(array('admin'))) {
    $respond(403, array('error' => 'Solo los administradores pueden eliminar Informes de Rendición.'));
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

$reportId = isset($_POST['report_id'])
    ? filter_var($_POST['report_id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))
    : false;
if ($reportId === false || $reportId === null) {
    $respond(422, array('error' => 'Identificador de informe inválido.'));
}

$db = db();
$repo = $db->renditionReports();

try {
    $existing = $repo->findById((int) $reportId);
    if ($existing === null) {
        $respond(404, array('error' => 'El Informe de Rendición indicado no existe o ya fue eliminado.'));
    }

    $deleted = $repo->deleteReport((int) $reportId);
    if (!$deleted) {
        $respond(500, array('error' => 'No se pudo eliminar el Informe de Rendición.'));
    }

    $respond(200, array(
        'deleted' => true,
        'deleted_id' => (int) $reportId,
        'report_code' => (string) $existing['report_code'],
        'total_reports' => $repo->countReports(),
        'message' => 'Informe de Rendición ' . $existing['report_code'] . ' eliminado correctamente.',
    ));
} catch (Throwable $e) {
    error_log('Error al eliminar Informe de Rendición: ' . $e->getMessage());
    $respond(500, array('error' => $e->getMessage()));
}
