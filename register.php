<?php
// Establecer la conexión con la base de datos
include('conn.php');
$db = new DB();

// Procesar el formulario de registro
if (isset($_POST['register'])) {
    $code = $_POST['code'];
    $state = $_POST['state'];
    $description = $_POST['description'];

    // Validación de los datos del formulario
    $errors = [];
    if (empty($code)) {
        die("Error: El nombre es obligatorio. ir atrás para corregir.");
    }
    if (empty($state)) {
        die("Error: El apellido es obligatorio. ir atrás para corregir.");
    }
    if (empty($description)) {
        die("Error: El correo electrónico es obligatorio. ir atrás para corregir.");
    }

    try {
        $db->create(
            "INSERT INTO goods (code, state, description) VALUES (?, ?, ?)",
            [$code, $state, $description]
        );

        header("Location: dashboard.php");
        exit();
    } catch (PDOException $e) {
        echo "Error al registrar el usuario: " . $e->getMessage();
    }
}
