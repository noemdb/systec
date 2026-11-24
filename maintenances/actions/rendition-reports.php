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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    $respond(405, array('error' => 'Método no permitido.'));
}

if (!auth_check()) {
    $respond(401, array('error' => 'La sesión expiró. Inicia sesión e inténtalo de nuevo.'));
}

if (!auth_has_role(array('admin', 'operador', 'tecnico', 'consulta'))) {
    $respond(403, array('error' => 'Tu rol no tiene permiso para consultar informes de rendición.'));
}

$db = db();
$repo = $db->renditionReports();

try {
    if (isset($_GET['id']) && $_GET['id'] !== '') {
        $reportId = filter_var($_GET['id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        if ($reportId === false) {
            $respond(422, array('error' => 'Identificador de informe inválido.'));
        }
        $report = $repo->findById((int) $reportId);
        if ($report === null) {
            $respond(404, array('error' => 'No se encontró el Informe de Rendición solicitado.'));
        }
        $respond(200, array('report' => $report));
    }

    $reports = $repo->listReports(50);
    $respond(200, array(
        'reports' => $reports,
        'total' => count($reports),
    ));
} catch (Throwable $e) {
    error_log('Error consultando Informes de Rendición: ' . $e->getMessage());
    $respond(500, array('error' => 'No se pudieron cargar los Informes de Rendición.'));
}
