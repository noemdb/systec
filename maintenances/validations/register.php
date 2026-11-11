<?php

$validationError = null;

if ($property_id === false || $property_id === null || $property_id < 1) {
    $validationError = 'Selecciona un bien válido.';
} elseif ($type === '' || !array_key_exists($type, $db->getListMaintenanceType())) {
    $validationError = 'Selecciona un tipo de mantenimiento válido.';
} elseif ($technician === '' || !array_key_exists($technician, $db->getListMaintenanceTechnician())) {
    $validationError = 'Selecciona un técnico válido.';
} elseif ($date === '') {
    $validationError = 'La fecha de inicio es obligatoria.';
} elseif ($failure_reason === '') {
    $validationError = 'La razón del mantenimiento es obligatoria.';
} elseif ($description === '') {
    $validationError = 'La descripción es obligatoria.';
}