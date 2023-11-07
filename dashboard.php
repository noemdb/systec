<!DOCTYPE html>
<html>
<head>
    <title>SysLogin - Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
        <h2>Dashboard</h2>
        <p>Bienvenido, <?php echo $_SESSION['username']; ?>!</p>
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
        <p><a href="logout.php">Cerrar sesión</a></p>
    </div>
</body>
</html>
