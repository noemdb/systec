<?php
require_once __DIR__ . '/../../include/maintenance_validator.php';

$validationError = validate_maintenance_update(
    array(
        'id' => $id ?? null,
        'property_id' => $property_id ?? null,
        'type' => $type ?? '',
        'technician' => $technician ?? '',
        'date' => $date ?? '',
        'next_maintenance_date' => $next_maintenance_date ?? '',
        'failure_reason' => $failure_reason ?? '',
        'description' => $description ?? '',
        'status' => $status ?? '',
    ),
    isset($db) && $db instanceof DB ? $db : null,
    isset($existing) && is_array($existing) ? $existing : null
);
