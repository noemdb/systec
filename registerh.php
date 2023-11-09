<html lang="en" class="h-100" data-bs-theme="light">

<head>

<?php include('head.php'); ?>
    
</head>

<body class="d-flex h-100 text-center">

    <div class="cover-container d-flex w-100 h-100 p-3 mx-auto flex-column">

        <header class="mb-auto" >
            <?php include('header.php'); ?> 
        </header>              

        <main class="px-3">
            <div class="container">
                <div class="card p-4 m-4">
                    <div class="card-body text-start">
                        <h2 class="card-title text-center">Registro de Usuario</h2>
                        <form action="register.php" method="POST">

                            <div class="container-fluid">

                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="form-group pb-2">
                                            <label>Nombre:</label>
                                            <input type="text" name="firstname" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group pb-2">
                                            <label>Apellido:</label>
                                            <input type="text" name="lastname" class="form-control" required>
                                        </div>
                                    </div>                                
                                </div>

                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="form-group pb-2">
                                            <label>Nombre de usuario:</label>
                                            <input type="text" name="username" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group pb-2">
                                            <label>Correo electrónico:</label>
                                            <input type="email" name="email" class="form-control" required>
                                        </div>
                                    </div>                                
                                </div>
                                
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="form-group pb-2">
                                            <label>Edad:</label>
                                            <input type="number" name="age" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group pb-2">
                                            <label>País:</label>
                                            <input type="text" name="country" class="form-control">
                                        </div>
                                    </div>                                
                                </div>
                                
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="form-group pb-2">
                                            <label>Perfil:</label>
                                            <input type="text" name="profile" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group pb-2">
                                            <label>Rol:</label>
                                            <input type="text" name="rol" class="form-control">
                                        </div>
                                    </div>                                
                                </div>
                                
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="form-group pb-2">
                                            <label>Contraseña:</label>
                                            <input type="password" name="password" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group pb-2">
                                            <label>Confirmar contraseña:</label>
                                            <input type="password" name="confirm_password" class="form-control" required>
                                        </div>
                                    </div>                                
                                </div>

                            </div>

                            <hr>

                            <footer class="mt-auto">
                                <input type="submit" name="register" value="Registrar" class="btn btn-primary w-100">
                            </footer>

                        </form>
                    </div>
                </div>
            </div>
        </main>

        <footer class="mt-auto text-white-50">
            <?php include('footer.php'); ?>
        </footer>

    </div>    

    <?php include('script.php'); ?>

</body>

</html>