<?php
$base = $_SERVER['DOCUMENT_ROOT'];
include($base.'/conn.php');
$db = new DB($base.'/db.db');
$table = "maintenances";

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
    include_once('./validations/register.php');

    try {
        $code = $db->getCodeId($property_id);
        $data = array(
            'code' => $code,
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
        $result = $db->update($table, $id, $data);
        header("Location: ../maintenances.php?id=".$id); 
        exit();
    } catch (PDOException $e) {
        echo "Error al registrar los datos: " . $e->getMessage();
    }
}
