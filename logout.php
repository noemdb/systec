<?php
// Inicia la sesión
session_start();

// Destruye todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la sesión completamente, se puede descomentar la siguiente línea
// session_destroy();

// Redirige al usuario a la página de inicio de sesión
header("Location: index.php");
exit();
?>
