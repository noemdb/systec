<html lang="en" class="h-100" data-bs-theme="light">

<?php

include('conn.php');
$db = new DB();
$result = $db->select("SELECT * FROM users");

?>

<head>

<?php include('head.php'); ?>
    
</head>

<body class="d-flex h-100 text-center">

    <div class="d-flex w-100 h-100 p-3 mx-auto flex-column">

        <header class="mb-auto" >
            <?php include('header.php'); ?> 
        </header>              

        <main class="px-3 w-100">
            <div class="container">

                <h2 class="">Dashboard</h2>
                <p class="">Bienvenido!</p>

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
                                <?php foreach ($result as $user) : ?>
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