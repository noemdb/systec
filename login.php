<?php
// Establecer la conexión con la base de datos
$host = 'nombre_del_servidor';
$dbname = 'nombre_de_la_base_de_datos';
$username = 'nombre_de_usuario';
$password = 'contraseña';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}

// Procesar el formulario de inicio de sesión
if (isset($_POST['login'])) {
    $username_or_email = $_POST['username_or_email'];
    $password = $_POST['password'];

    // Buscar al usuario por nombre de usuario o correo electrónico
    $sql = "SELECT * FROM users WHERE username = ? OR email = ?";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username_or_email, $username_or_email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Inicio de sesión exitoso
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];

            header("Location: dashboard.php");
            exit();
        } else {
            echo "Nombre de usuario o contraseña incorrectos.";
        }
    } catch (PDOException $e) {
        echo "Error al iniciar sesión: " . $e->getMessage();
    }
}
?>
