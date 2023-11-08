<html lang="en" class="h-100" data-bs-theme="dark">
<?php

// Inicia la sesión
session_start();

// Verifica si el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}


include('conn.php');

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "SELECT * FROM users";
    $stmt = $conn->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

?>

<head>

    <script src="vendor/bootstrap/5.3.2/js/color-modes.js"></script>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <title>GaboSys - Dashboard</title>

    <link rel="stylesheet" href="vendor/bootstrap/5.3.2/css/style.css">
    <link rel="stylesheet" href="vendor/bootstrap/5.3.2/css/bootstrap.min.css">

    <link rel="stylesheet" href="vendor/bootstrap/5.3.2/css/app.css">
    <link rel="stylesheet" href="vendor/bootstrap/5.3.2/css/cover.css">
</head>

<body class="d-flex h-100 text-center text-dark bg-white" style="color:black !important;box-shadow: none !important;text-shadow:none !important">

    <div class="container">
        <header class="mb-auto" >
            <div>
                <h3 class="float-md-start mb-0">GaboSys</h3>
                <nav class="nav nav-masthead justify-content-center float-md-end" >
                    <a class="nav-link fw-bold py-1 px-0 active" aria-current="page" href="index.php" style="color: black !important;">Inicio</a>
                    <a class="nav-link fw-bold py-1 px-0" href="register.html" style="color: black !important;">Registro</a>

                    <?php if (isset($_SESSION['username'])) { ?>
                        <a class="nav-link fw-bold py-1 px-0" href="logout.php" style="color: black !important;">Cerrar sesión</a>
                    <?php } else { ?>
                        <a class="nav-link fw-bold py-1 px-0" href="login.html" style="color: black !important;">Login</a>
                    <?php } ?>
                    
                </nav>
            </div>
        </header>

        <div class="container mt-4" data-bs-theme="light">

            <h2 class="mt-4">Dashboard</h2>
            <p class="">Bienvenido, <?php echo $_SESSION['firstname'] .' '. $_SESSION['lastname']; ?>!</p>

            <div class="card">
                <div class="card-body">                   
                    
                    

                    <h3>Lista de Usuarios Registrados</h3>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Apellido</th>
                                <th>Nombre de usuario</th>
                                <th>Correo electrónico</th>
                                <th>Edad</th>
                                <th>País</th>
                                <th>Perfil</th>
                                <th>Rol</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['firstname']; ?></td>
                                    <td><?php echo $user['lastname']; ?></td>
                                    <td><?php echo $user['username']; ?></td>
                                    <td><?php echo $user['email']; ?></td>
                                    <td><?php echo $user['age']; ?></td>
                                    <td><?php echo $user['country']; ?></td>
                                    <td><?php echo $user['profile']; ?></td>
                                    <td><?php echo $user['rol']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
                        
        </div>

        

        

    </div>
</body>



</html>
