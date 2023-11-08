<?php
// Establecer la conexión con la base de datos
include('conn.php');

// Procesar el formulario de registro
if (isset($_POST['register'])) {
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $age = $_POST['age'];
    $country = $_POST['country'];
    $profile = $_POST['profile'];
    $rol = $_POST['rol'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];    

    // Validación de los datos del formulario
    $errors = [];
    if (empty($firstname)) {
        die("Error: El nombre es obligatorio. ir atrás para corregir.");
    }
    if (empty($firstname)) {
        die("Error: El apellido es obligatorio. ir atrás para corregir.");
    }
    if (empty($email)) {
        die("Error: El correo electrónico es obligatorio. ir atrás para corregir.");
    }
    if (empty($password)) {
        die("Error: La contraseña es obligatoria. ir atrás para corregir.");
    }
    if (strlen($password) < 6) {
        die("Error: La contraseña debe tener al menos 6 caracteres. ir atrás para corregir.");
    }
    if ($password !== $confirm_password) {
        die("Error: Las contraseñas no coinciden. ir atrás para corregir.");
    }

    // Encriptar la contraseña
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insertar los datos en la base de datos
    $sql = "INSERT INTO users (firstname, lastname, username, email, age, country, profile, rol, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$firstname, $lastname, $username, $email, $age, $country,$profile, $rol, $hashed_password]);

        // session_start();
        // $_SESSION['user_id'] = $user['id'];
        // $_SESSION['firstname'] = $firstname;
        // $_SESSION['lastname'] = $lastname;
        // $_SESSION['username'] = $username;
        // $_SESSION['email'] = $email;

        header("Location: dashboard.php");
        exit();

    } catch (PDOException $e) {
        echo "Error al registrar el usuario: " . $e->getMessage();
    }
}
?>
