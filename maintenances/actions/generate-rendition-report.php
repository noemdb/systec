<?php

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/inventory_import.php';
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
    $respond(403, array('error' => 'Tu rol no tiene permiso para generar informes de rendición.'));
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

if ($dateFrom === '' || $dateTo === '') {
    $respond(422, array('error' => 'Debes seleccionar un rango de fechas válido (Fecha desde y Fecha hasta).'));
}
if ($dateFrom > $dateTo) {
    $respond(422, array('error' => 'La fecha inicial no puede ser posterior a la fecha final.'));
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

$forceRegenerate = isset($_POST['force_regenerate']) && (string) $_POST['force_regenerate'] === '1';

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

    $existing = $repo->findByScopeHash($periodData['scope_hash']);
    if ($existing !== null) {
        if ((string) $existing['status'] === 'aprobado') {
            $respond(200, array(
                'already_approved' => true,
                'loaded_existing_draft' => false,
                'fallback' => false,
                'report_id' => (int) $existing['id'],
                'report_code' => (string) $existing['report_code'],
                'status' => (string) $existing['status'],
                'date_from' => (string) $existing['date_from'],
                'date_to' => (string) $existing['date_to'],
                'scope_hash' => (string) $existing['scope_hash'],
                'scope_summary' => (string) $existing['scope_summary'],
                'filters' => $existing['filters'],
                'parameters' => $existing['parameters'],
                'stats' => $existing['stats'] !== array() ? $existing['stats'] : $periodData['stats'],
                'maintenance_ids' => $existing['maintenance_ids'],
                'ticket_ids' => $existing['ticket_ids'],
                'content_markdown' => (string) $existing['content_markdown'],
                'existing_report' => $existing,
            ));
        }

        if (!$forceRegenerate) {
            $respond(200, array(
                'loaded_existing_draft' => true,
                'fallback' => false,
                'report_id' => (int) $existing['id'],
                'report_code' => (string) $existing['report_code'],
                'status' => (string) $existing['status'],
                'date_from' => (string) $existing['date_from'],
                'date_to' => (string) $existing['date_to'],
                'scope_hash' => (string) $existing['scope_hash'],
                'scope_summary' => (string) $existing['scope_summary'],
                'filters' => $existing['filters'],
                'parameters' => $existing['parameters'],
                'stats' => $periodData['stats'],
                'maintenance_ids' => $periodData['maintenance_ids'],
                'ticket_ids' => $periodData['ticket_ids'],
                'content_markdown' => (string) $existing['content_markdown'],
            ));
        }
    }

    if ((int) ($periodData['stats']['total_maintenances'] ?? 0) === 0) {
        $respond(422, array(
            'error' => 'No se encontraron acciones de mantenimiento en el rango de fechas y filtros seleccionados.',
        ));
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $aiMaintenances = array();
    foreach (array_slice($periodData['maintenances'], 0, 40) as $mRow) {
        $aiMaintenances[] = array(
            'id' => (int) $mRow['id'],
            'date' => (string) ($mRow['date'] ?? ''),
            'code' => (string) ($mRow['resolved_code'] ?? $mRow['code'] ?? ''),
            'property_description' => mb_substr((string) ($mRow['property_description'] ?? ''), 0, 120, 'UTF-8'),
            'type' => (string) ($mRow['type'] ?? ''),
            'status' => (string) ($mRow['status'] ?? ''),
            'technician' => (string) ($mRow['technician'] ?? ''),
            'time_taken' => (string) ($mRow['time_taken'] ?? ''),
            'description' => mb_substr((string) ($mRow['description'] ?? ''), 0, 350, 'UTF-8'),
            'failure_reason' => mb_substr((string) ($mRow['failure_reason'] ?? ''), 0, 250, 'UTF-8'),
            'notes' => mb_substr((string) ($mRow['notes'] ?? ''), 0, 250, 'UTF-8'),
        );
    }

    $aiTickets = array();
    foreach (array_slice($periodData['tickets'], 0, 25) as $tRow) {
        $aiTickets[] = array(
            'id' => (int) $tRow['id'],
            'created_at' => (string) ($tRow['created_at'] ?? ''),
            'title' => (string) ($tRow['title'] ?? ''),
            'status' => (string) ($tRow['status_name'] ?? $tRow['status_code'] ?? ''),
            'description' => mb_substr((string) ($tRow['description'] ?? ''), 0, 250, 'UTF-8'),
            'observation' => mb_substr((string) ($tRow['observation'] ?? ''), 0, 200, 'UTF-8'),
        );
    }

    $dateFromLabel = date('d/m/Y', strtotime($periodData['date_from']));
    $dateToLabel = date('d/m/Y', strtotime($periodData['date_to']));
    $reportContext = array(
        'period_label' => $dateFromLabel . ' al ' . $dateToLabel,
        'scope_summary' => $periodData['scope_summary'],
        'issuing_unit' => $parameters['issuing_unit'],
        'recipient_name' => $parameters['recipient_name'],
        'prepared_by' => $parameters['prepared_by'],
        'custom_notes' => $parameters['custom_notes'],
        'stats' => $periodData['stats'],
        'maintenances' => $aiMaintenances,
        'tickets' => $aiTickets,
    );

    $apiKey = getenv('API_KEY_NVIDIA_BUILD');
    $aiSections = systec_generate_rendition_report_sections_with_fallback(
        $reportContext,
        $apiKey === false ? '' : $apiKey
    );

    $reportCode = $existing !== null
        ? (string) $existing['report_code']
        : $repo->generateNextReportCode(substr($periodData['date_to'], 0, 4));

    $markdown = $repo->renderReportMarkdown(
        $periodData,
        $parameters,
        $aiSections,
        $reportCode,
        'borrador'
    );

    $respond(200, array(
        'loaded_existing_draft' => false,
        'fallback' => !empty($aiSections['fallback']),
        'report_id' => $existing !== null ? (int) $existing['id'] : null,
        'report_code' => $reportCode,
        'status' => 'borrador',
        'date_from' => $periodData['date_from'],
        'date_to' => $periodData['date_to'],
        'scope_hash' => $periodData['scope_hash'],
        'scope_summary' => $periodData['scope_summary'],
        'filters' => $periodData['filters'],
        'parameters' => $parameters,
        'stats' => $periodData['stats'],
        'maintenance_ids' => $periodData['maintenance_ids'],
        'ticket_ids' => $periodData['ticket_ids'],
        'content_markdown' => $markdown,
    ));
} catch (Throwable $e) {
    error_log('Error al generar Informe de Rendición: ' . $e->getMessage());
    $respond(500, array('error' => $e->getMessage()));
}
