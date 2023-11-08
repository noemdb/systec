<html lang="en" class="h-100" data-bs-theme="light">

<?php session_start(); ?>

<head>

<?php include('head.php'); ?>
    
</head>

<body class="d-flex h-100 text-center">

    <div class="cover-container d-flex w-100 h-100 p-3 mx-auto flex-column">

        <header class="mb-auto" >
            <?php include('header.php'); ?> 
        </header>              

        <main class="px-3">
            <div class="container" data-bs-theme="light">
                <div class="card p-4 m-4">
                    <div class="card-body text-start">
                        <h2 class="card-title text-center">Iniciar sesión</h2>
                        <form action="login.php" method="POST">                    
                          <div class="form-floating pb-2">
                            <input name="username_or_email" type="text" class="form-control" id="floatingInput" placeholder="name@example.com">
                            <label for="floatingInput">Nombre de usuario o correo electrónico:</label>
                          </div>
                          <div class="form-floating pb-2">
                            <input name="password" type="password" class="form-control" id="floatingPassword" placeholder="Password">
                            <label for="floatingPassword">Password</label>
                          </div>
                          <button class="w-100 btn btn-lg btn-primary fw-bold" type="submit">Iniciar</button>
                        </form>
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