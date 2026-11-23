<?php
function property_picker_json_response(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');

    try {
        $db = db();
        $adscriptionsOnly = isset($_GET['mode']) && $_GET['mode'] === 'adscriptions';
        $properties = $db->index(
            'properties',
            array(),
            'id, ident, grupo, subgrupo, seccion, description, status, assigned_name, assigned_status',
            'id ASC'
        );
        $identifiers = array_map(function ($property) {
            return trim((string) $property['ident']);
        }, $properties);
        $snapshot = legacy_postgres_cached_property_adscriptions($identifiers);
        $adscriptions = $snapshot['adscriptions'];
        $localCache = !$adscriptionsOnly ? legacy_postgres_read_local_cache() : array('assignments' => array());
        $legacyAssignments = isset($localCache['assignments']) && is_array($localCache['assignments'])
            ? $localCache['assignments']
            : array();
        $options = array();
        $directions = array();
        $assignedUsers = array();
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
                $assignedName = trim((string) ($property['assigned_name'] ?? ''));
                $assignedStatus = trim((string) ($property['assigned_status'] ?? ''));
                if (isset($legacyAssignments[$ident]) && is_array($legacyAssignments[$ident])) {
                    $assignedName = trim((string) ($legacyAssignments[$ident]['assigned_name'] ?? $assignedName));
                    $assignedStatus = trim((string) ($legacyAssignments[$ident]['assigned_status'] ?? $assignedStatus));
                }
                $assignedKey = property_assignment_filter_key($assignedName, $assignedStatus);
                $assignedLabel = property_assignment_filter_label($assignedName, $assignedStatus);
                $options[] = array(
                    'id' => (int) $property['id'],
                    'code' => property_code($property),
                    'description' => trim((string) ($property['description'] ?? '')) ?: 'Sin descripción',
                    'status' => property_status_label((string) ($property['status'] ?? '')),
                    'category' => property_category($property),
                    'direction_key' => (string) $assignment['direction_key'],
                    'direction_label' => (string) $assignment['direction_label'],
                    'assigned_key' => $assignedKey,
                    'assigned_label' => $assignedLabel,
                );
                $directions[(string) $assignment['direction_key']] = (string) $assignment['direction_label'];
                $assignedUsers[$assignedKey] = $assignedLabel;
            }
            $adscriptionOptions[(string) $assignment['key']] = (string) $assignment['label'];
        }

        uasort($directions, 'strnatcasecmp');
        asort($assignedUsers, SORT_NATURAL | SORT_FLAG_CASE);
        uasort($adscriptionOptions, 'strnatcasecmp');
        echo json_encode(array(
            'properties' => $adscriptionsOnly ? array() : $options,
            'directions' => $adscriptionsOnly ? array() : $directions,
            'assigned_users' => $adscriptionsOnly ? array() : $assignedUsers,
            'adscriptions' => $adscriptionOptions,
            'stale' => (bool) $snapshot['stale'],
            'cached_at' => date(DATE_ATOM, (int) $snapshot['cached_at']),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        error_log('No se pudieron cargar bienes para registro de ticket: ' . $e->getMessage());
        http_response_code(503);
        echo json_encode(array('error' => 'No fue posible cargar el inventario y sus direcciones. Intenta de nuevo.'));
    }
}
