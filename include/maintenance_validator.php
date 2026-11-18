<?php
/**
 * P2-05 · Validación centralizada de mantenimientos por contexto:
 * - create: alta de mantenimiento
 * - update: edición de mantenimiento
 * - transition: cambio de estado de mantenimiento
 */

require_once __DIR__ . '/../conn.php';

function validate_maintenance_create(array $input, ?DB $database = null): ?string
{
    $db = $database ?: db();

    $propertyId = isset($input['property_id'])
        ? filter_var($input['property_id'], FILTER_VALIDATE_INT)
        : false;
    $type = isset($input['type']) ? trim((string) $input['type']) : '';
    $technician = isset($input['technician']) ? trim((string) $input['technician']) : '';
    $date = isset($input['date']) ? trim((string) $input['date']) : '';
    $nextDate = isset($input['next_maintenance_date']) ? trim((string) $input['next_maintenance_date']) : '';
    $failureReason = isset($input['failure_reason']) ? trim((string) $input['failure_reason']) : '';
    $description = isset($input['description']) ? trim((string) $input['description']) : '';

    if ($propertyId === false || $propertyId === null || $propertyId < 1) {
        return 'Selecciona un bien válido.';
    }
    if ($type === '' || !array_key_exists($type, $db->getListMaintenanceType())) {
        return 'Selecciona un tipo de mantenimiento válido.';
    }
    if ($technician === '' || !array_key_exists($technician, $db->getListMaintenanceTechnician())) {
        return 'Selecciona un técnico válido.';
    }
    if ($date === '') {
        return 'La fecha de inicio es obligatoria.';
    }
    if (strtotime($date) === false) {
        return 'La fecha de inicio no es válida.';
    }
    if ($nextDate !== '' && strtotime($nextDate) === false) {
        return 'La fecha del próximo mantenimiento no es válida.';
    }
    if ($failureReason === '') {
        return 'La razón del mantenimiento es obligatoria.';
    }
    if ($description === '') {
        return 'La descripción es obligatoria.';
    }

    return null;
}

function validate_maintenance_update(array $input, ?DB $database = null, ?array $existing = null): ?string
{
    $db = $database ?: db();

    $id = isset($input['id'])
        ? filter_var($input['id'], FILTER_VALIDATE_INT)
        : false;
    if ($id === false || $id === null || $id < 1) {
        return 'El identificador del mantenimiento es obligatorio.';
    }

    $baseError = validate_maintenance_create($input, $db);
    if ($baseError !== null) {
        return $baseError;
    }

    $status = isset($input['status']) ? trim((string) $input['status']) : '';
    if ($status === '' || !array_key_exists($status, $db->getListMaintenanceStatus())) {
        return 'El estado del mantenimiento no es válido.';
    }

    if ($existing !== null) {
        $currentStatus = isset($existing['status']) ? (string) $existing['status'] : '';
        if (!maintenance_transition_allowed($currentStatus, $status)) {
            return 'La transición de estado no está permitida.';
        }
    }

    return null;
}

function validate_maintenance_transition(
    $id,
    string $targetStatus,
    ?DB $database = null,
    ?array $existing = null
): ?string {
    $db = $database ?: db();

    $validId = filter_var($id, FILTER_VALIDATE_INT);
    $status = trim($targetStatus);

    if ($validId === false || $validId === null || $validId < 1 || $status === '') {
        return 'Datos incompletos.';
    }

    if (!array_key_exists($status, $db->getListMaintenanceStatus())) {
        return 'Estado de mantenimiento inválido.';
    }

    if ($existing !== null) {
        $currentStatus = isset($existing['status']) ? (string) $existing['status'] : '';
        if (!maintenance_transition_allowed($currentStatus, $status)) {
            return 'La transición de estado no está permitida.';
        }
    }

    return null;
}
