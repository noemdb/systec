<?php

if (empty($id)) {
    die("Error: El identificador es obligatorio. ir atrás para corregir.");
}

if (empty($property_id)) {
    die("Error: El código es obligatorio. ir atrás para corregir.");
}
if (empty($type) || !array_key_exists($type, $db->getListMaintenanceType())) {
    die("Error: El tipo es obligatorio. ir atrás para corregir.");
}
if (empty($technician) || !array_key_exists($technician, $db->getListMaintenanceTechnician())) {
    die("Error: El técnico es obligatorio. ir atrás para corregir.");
}
if (empty($date)) {
    die("Error: la fecha es obligatoria. ir atrás para corregir.");
}
if (empty($failure_reason)) {
    die("Error: La Razón del mantenimiento es obligatoria. ir atrás para corregir.");
}
if (empty($description)) {
    die("Error: La descripción es obligatorio. ir atrás para corregir.");
}