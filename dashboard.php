<html lang="en" class="h-100" data-bs-theme="light">

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

<?php include('head.php'); ?>
    
</head>

<body class="d-flex h-100 text-center">

    <div class="d-flex w-100 h-100 p-3 mx-auto flex-column">

        <header class="mb-auto" >
            <?php include('header.php'); ?> 
        </header>              

        <main class="px-3 w-75">
            <div class="container">

                <h2 class="">Dashboard</h2>
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
        </main>

        <footer class="mt-auto">
            <?php include('footer.php'); ?>
        </footer>

    </div>    

    <?php include('script.php'); ?>

</body>

</html>