<?php
// Establecer la conexión con la base de datos
include('conn.php');
$db = new DB();

// Procesar el formulario de registro
if (isset($_POST['register'])) {
    $code = $_POST['property_id'];
    $type = $_POST['type'];
    $technician = $_POST['technician'];
    $date = $_POST['date'];
    $nextMaintenance = $_POST['next-maintenance'];
    $reason = $_POST['reason'];
    $description = $_POST['description'];
    $notes = $_POST['notes'];

    // Validación de los datos del formulario
    $errors = [];
    if (empty($code)) {
        die("Error: El nombre es obligatorio. ir atrás para corregir.");
    }
    if (empty($type) || !array_key_exists($type, $db->getListMaintenanceType())) {
        die("Error: El apellido es obligatorio. ir atrás para corregir.");
    }
    if (empty($technician) || !array_key_exists($technician, $db->getListMaintenanceTechnician())) {
        die("Error: El apellido es obligatorio. ir atrás para corregir.");
    }
    if (empty($date)) {
        die("Error: El apellido es obligatorio. ir atrás para corregir.");
    }
    if (empty($reason)) {
        die("Error: El apellido es obligatorio. ir atrás para corregir.");
    }
    if (empty($description)) {
        die("Error: El correo electrónico es obligatorio. ir atrás para corregir.");
    }
    if (empty($notes)) {
        die("Error: El correo electrónico es obligatorio. ir atrás para corregir.");
    }

    try {
        $db->create(
            "INSERT INTO maintenances (property_id, type, description, date, technician, next_maintenance_date, failure_reason, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$code, $type, $description, $date, $technician, $nextMaintenance, $reason, $notes, 'pendiente']
        );

        header("Location: dashboard.php");
        exit();
    } catch (PDOException $e) {
        echo "Error al registrar el usuario: " . $e->getMessage();
    }
}
