<html lang="en" class="h-100" data-bs-theme="light">

<head>

    <?php include('head.php'); ?>

</head>

<body class="d-flex h-100 text-center">

    <div class="cover-container d-flex w-100 h-100 p-3 mx-auto flex-column">

        <header class="mb-auto">
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
                                    <div class="form-group pb-2">
                                        <label for="code">Código:</label>
                                        <input type="text" name="code" id="code" class="form-control" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group pb-2">
                                        <label for="description">Descripción:</label>
                                        <textarea name="description" id="description" rows="5" class="form-control" required></textarea>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group pb-2">
                                        <label for="state">Estado:</label>
                                        <select class="form-control" name="state" id="state">
                                            <option value="bueno">Bueno</option>
                                            <option value="malo">Malo</option>
                                        </select>
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