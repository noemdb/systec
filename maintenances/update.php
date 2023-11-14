<?php
$base = $_SERVER['DOCUMENT_ROOT'];
include($base.'/conn.php');
$db = new DB($base.'/db.db');

// Procesar el formulario de registro
if (isset($_POST['update'])) {
    $id = (array_key_exists('id',$_POST)) ? $_POST['id'] : null;
    $property_id = (array_key_exists('property_id',$_POST)) ? $_POST['property_id'] : null;
    $type = (array_key_exists('type',$_POST)) ? $_POST['type'] : null;
    $description = (array_key_exists('description',$_POST)) ? $_POST['description'] : null;
    $date = (array_key_exists('date',$_POST)) ? $_POST['date'] : null;
    $technician = (array_key_exists('technician',$_POST)) ? $_POST['technician'] : null;
    $next_maintenance_date = (array_key_exists('next_maintenance_date',$_POST)) ? $_POST['next_maintenance_date'] : null;
    $failure_reason = (array_key_exists('failure_reason',$_POST)) ? $_POST['failure_reason'] : null;
    $notes = (array_key_exists('notes',$_POST)) ? $_POST['notes'] : null;
    $status = (array_key_exists('status',$_POST)) ? $_POST['status'] : null;

    // Validación de los datos del formulario
    $errors = [];
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

    try {
        $data = array(
            'property_id' => $property_id,
            'type' => $type,
            'description' => $description,
            'date' => $date,
            'technician' => $technician,
            'next_maintenance_date' => $next_maintenance_date,
            'failure_reason' => $failure_reason,
            'notes' => $notes,
            'status' => $status,
        );
        $where = "id = ".$id;
        $result = $db->update('maintenances', $data, $where); //var_dump($result) ;die();
        header("Location: ../maintenances.php"); 
        exit();
    } catch (PDOException $e) {
        echo "Error al registrar el usuario: " . $e->getMessage();
    }
}
