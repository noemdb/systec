<?php
require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/inventory_import.php';
require_personal_scope();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

try {
    $db = db();
    $adscriptionsOnly = isset($_GET['mode']) && $_GET['mode'] === 'adscriptions';
    $properties = $db->index(
        'properties',
        array(),
        'id, ident, grupo, subgrupo, seccion, description, status',
        'id ASC'
    );
    $identifiers = array_map(function ($property) {
        return trim((string) $property['ident']);
    }, $properties);
    $snapshot = legacy_postgres_cached_property_adscriptions($identifiers);
    $adscriptions = $snapshot['adscriptions'];
    $options = array();
    $directions = array();
    $adscriptionOptions = array();

    foreach ($properties as $property) {
        $ident = str_pad(trim((string) $property['ident']), 4, '0', STR_PAD_LEFT);
        $assignment = $adscriptions[$ident] ?? array(
            'key' => '__unassigned__',
            'label' => 'Sin adscripción',
            'direction_key' => '__unassigned_direction__',
            'direction_label' => 'Sin dirección asignada',
        );
        if (!$adscriptionsOnly) {
            $options[] = array(
                'id' => (int) $property['id'],
                'code' => property_code($property),
                'description' => trim((string) ($property['description'] ?? '')) ?: 'Sin descripción',
                'status' => property_status_label((string) ($property['status'] ?? '')),
                'category' => property_category($property),
                'direction_key' => (string) $assignment['direction_key'],
                'direction_label' => (string) $assignment['direction_label'],
            );
            $directions[(string) $assignment['direction_key']] = (string) $assignment['direction_label'];
        }
        $adscriptionOptions[(string) $assignment['key']] = (string) $assignment['label'];
    }

    uasort($directions, 'strnatcasecmp');
    uasort($adscriptionOptions, 'strnatcasecmp');
    echo json_encode(array(
        'properties' => $adscriptionsOnly ? array() : $options,
        'directions' => $adscriptionsOnly ? array() : $directions,
        'adscriptions' => $adscriptionOptions,
        'stale' => (bool) $snapshot['stale'],
        'cached_at' => date(DATE_ATOM, (int) $snapshot['cached_at']),
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('No se pudieron cargar bienes para el ticket personal: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(array('error' => 'No fue posible cargar el inventario y sus direcciones. Intenta de nuevo.'));
}
