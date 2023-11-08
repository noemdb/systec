<div>
    <h3 class="float-md-start mb-0">GaboSys</h3>
    <nav class="nav nav-masthead justify-content-center float-md-end" >
        <a class="nav-link fw-bold py-1 px-0 active" aria-current="page" href="index.php" style="color: black !important;">Inicio</a>
        <a class="nav-link fw-bold py-1 px-0" href="registerh.php" style="color: black !important;">Registro</a>
        <?php if (isset($_SESSION['username'])) { ?>
            <a class="nav-link fw-bold py-1 px-0" href="dashboard.php" style="color: black !important;">Dashboar</a>
            <a class="nav-link fw-bold py-1 px-0" href="logout.php" style="color: black !important;">Cerrar sesión</a>
        <?php } else { ?>
            <a class="nav-link fw-bold py-1 px-0" href="loginh.php" style="color: black !important;">Login</a>
        <?php } ?>
    </nav>
</div>
