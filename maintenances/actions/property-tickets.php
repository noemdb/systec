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

if (!auth_has_role(array('admin', 'operador', 'tecnico'))) {
    $respond(403, array('error' => 'Tu rol no tiene permiso para consultar tickets.'));
}

$propertyId = isset($_GET['property_id']) && is_string($_GET['property_id'])
    ? filter_var($_GET['property_id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)))
    : false;
if ($propertyId === false) {
    $respond(422, array('error' => 'Identificador de bien inválido.'));
}

try {
    $tickets = db()->tickets()->maintenanceTicketsForProperty((int) $propertyId);
    foreach ($tickets as &$ticket) {
        $ticket['property_category'] = property_category(array(
            'grupo' => (string) ($ticket['property_grupo'] ?? ''),
            'subgrupo' => (string) ($ticket['property_subgrupo'] ?? ''),
            'seccion' => (string) ($ticket['property_seccion'] ?? ''),
        ));
    }
    unset($ticket);
    $respond(200, array('tickets' => $tickets));
} catch (Throwable $e) {
    error_log('No se pudieron consultar los tickets del bien: ' . $e->getMessage());
    $respond(500, array('error' => 'No se pudieron cargar los tickets del bien.'));
}
