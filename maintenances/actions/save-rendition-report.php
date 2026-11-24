<?php

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/inventory_import.php';

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
    $respond(403, array('error' => 'Tu rol no tiene permiso para guardar o aprobar informes de rendición.'));
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

$db = db();
$repo = $db->renditionReports();

$dateFrom = isset($_POST['date_from']) && is_string($_POST['date_from'])
    ? $repo->normalizeIsoDate($_POST['date_from'])
    : '';
$dateTo = isset($_POST['date_to']) && is_string($_POST['date_to'])
    ? $repo->normalizeIsoDate($_POST['date_to'])
    : '';

if ($dateFrom === '' || $dateTo === '' || $dateFrom > $dateTo) {
    $respond(422, array('error' => 'El rango de fechas del informe es inválido.'));
}

$contentMarkdown = isset($_POST['content_markdown']) && is_string($_POST['content_markdown'])
    ? trim($_POST['content_markdown'])
    : '';
if ($contentMarkdown === '') {
    $respond(422, array('error' => 'El contenido del Informe de Rendición no puede estar vacío.'));
}

$targetStatus = isset($_POST['status']) && $_POST['status'] === 'aprobado'
    ? 'aprobado'
    : 'borrador';

if ($targetStatus === 'aprobado' && (!isset($_POST['confirm_approval']) || (string) $_POST['confirm_approval'] !== '1')) {
    $respond(422, array('error' => 'Debes confirmar la revisión y aprobación antes de cerrar definitivamente el informe.'));
}

$useActiveFilters = !isset($_POST['use_active_filters']) || (string) $_POST['use_active_filters'] === '1';
$rawFilters = $useActiveFilters ? array(
    'category' => isset($_POST['category']) ? (string) $_POST['category'] : '',
    'adscription' => isset($_POST['adscription']) ? (string) $_POST['adscription'] : '',
    'adscription_label' => isset($_POST['adscription_label']) ? (string) $_POST['adscription_label'] : '',
    'status' => isset($_POST['status']) ? (string) $_POST['status'] : '',
    'type' => isset($_POST['type']) ? (string) $_POST['type'] : '',
    'technician' => isset($_POST['technician']) ? (string) $_POST['technician'] : '',
) : array();

$currentUser = auth_user();
$actorUserId = is_array($currentUser) && isset($currentUser['id']) ? (int) $currentUser['id'] : 0;
$defaultPreparedBy = is_array($currentUser) && isset($currentUser['username'])
    ? (string) $currentUser['username']
    : 'Equipo Técnico';

$parameters = $repo->normalizeParameters(array(
    'title' => $_POST['title'] ?? null,
    'issuing_unit' => $_POST['issuing_unit'] ?? null,
    'recipient_name' => $_POST['recipient_name'] ?? null,
    'prepared_by' => $_POST['prepared_by'] ?? null,
    'include_tickets' => $_POST['include_tickets'] ?? '1',
    'custom_notes' => $_POST['custom_notes'] ?? '',
), $defaultPreparedBy);

try {
    $localCache = legacy_postgres_read_local_cache();
    $legacyAdscriptionsByIdent = isset($localCache['adscriptions']) && is_array($localCache['adscriptions'])
        ? $localCache['adscriptions']
        : array();

    $periodData = $repo->collectPeriodData(
        $dateFrom,
        $dateTo,
        $rawFilters,
        (bool) $parameters['include_tickets'],
        $legacyAdscriptionsByIdent
    );

    if ((int) ($periodData['stats']['total_maintenances'] ?? 0) === 0) {
        $respond(422, array('error' => 'No hay mantenimientos en el período seleccionado para respaldar este informe.'));
    }

    $saved = $repo->saveReport(array(
        'id' => $_POST['report_id'] ?? null,
        'date_from' => $periodData['date_from'],
        'date_to' => $periodData['date_to'],
        'filters' => $periodData['filters'],
        'parameters' => $parameters,
        'stats' => $periodData['stats'],
        'maintenance_ids' => $periodData['maintenance_ids'],
        'ticket_ids' => $periodData['ticket_ids'],
        'content_markdown' => $contentMarkdown,
        'ai_generated' => isset($_POST['ai_generated']) && (string) $_POST['ai_generated'] === '1',
        'status' => $targetStatus,
    ), $actorUserId);

    $respond(200, array(
        'message' => $targetStatus === 'aprobado'
            ? 'Informe de Rendición ' . $saved['report_code'] . ' aprobado y guardado correctamente.'
            : 'Borrador ' . $saved['report_code'] . ' guardado correctamente.',
        'report' => $saved,
        'total_reports' => $repo->countReports(),
    ));
} catch (InvalidArgumentException $e) {
    $respond(422, array('error' => $e->getMessage()));
} catch (RuntimeException $e) {
    $respond(409, array('error' => $e->getMessage()));
} catch (Throwable $e) {
    error_log('Error al guardar Informe de Rendición: ' . $e->getMessage());
    $respond(500, array('error' => 'No se pudo guardar el Informe de Rendición.'));
}
