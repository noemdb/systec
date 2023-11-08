<html lang="en" class="h-100" data-bs-theme="dark">

<?php session_start(); ?>

<head>

    <!-- <script src="vendor/bootstrap/5.3.2/js/color-modes.js"></script> -->

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <title>GaboSys - Inicio</title>

    <link rel="stylesheet" href="vendor/bootstrap/5.3.2/css/style.css">
    <link rel="stylesheet" href="vendor/bootstrap/5.3.2/css/bootstrap.min.css">

    <link rel="stylesheet" href="vendor/bootstrap/5.3.2/css/app.css">
    <link rel="stylesheet" href="vendor/bootstrap/5.3.2/css/cover.css">
</head>

<body class="d-flex h-100 text-center text-dark bg-white" style="color:black !important;box-shadow: none !important;text-shadow:none !important">


    <div class="cover-container d-flex w-100 h-100 p-3 mx-auto flex-column">
        <header class="mb-auto" >
            <div>
                <h3 class="float-md-start mb-0">GaboSys</h3>
                <nav class="nav nav-masthead justify-content-center float-md-end" >
                    <a class="nav-link fw-bold py-1 px-0 active" aria-current="page" href="#" style="color: black !important;">Inicio</a>
                    <a class="nav-link fw-bold py-1 px-0" href="register.html" style="color: black !important;">Registro</a>
                    <?php if (isset($_SESSION['username'])) { ?>
                        <a class="nav-link fw-bold py-1 px-0" href="dashboard.php" style="color: black !important;">Dashboar</a>
                        <a class="nav-link fw-bold py-1 px-0" href="logout.php" style="color: black !important;">Cerrar sesión</a>
                    <?php } else { ?>
                        <a class="nav-link fw-bold py-1 px-0" href="login.html" style="color: black !important;">Login</a>
                    <?php } ?>
                </nav>
            </div>
        </header>

        <main class="px-3">
            <h1>Sistema de Registro y Panel de Control.</h1>
            <p class="lead">Aplicación web desarrollada utilizando tecnologías como PHP, MySQL y el framework Bootstrap.
                Diseñado como un ejemplo básico, este sistema demuestra cómo implementar funcionalidades de registro de
                usuarios, inicio de sesión y un panel de control para administrar usuarios registrados.</p>
            <p class="lead">
                <a href="#" class="btn btn-lg btn-light fw-bold border-white bg-white">Leer mas</a>
            </p>
        </main>

        <footer class="mt-auto text-white-50">
            <p> <a href="#" class="text-white">GaboSys</a>, por <a href="#" class="text-white">TSU José Torres</a>.</p>
        </footer>
    </div>

    <script src="vendor/bootstrap/5.3.2/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</body>

</html>