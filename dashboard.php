<html lang="en" class="h-100" data-bs-theme="light">

<?php

include('conn.php');
$db = new DB();
$result = $db->select("SELECT * FROM goods");

?>

<head>

    <?php include('head.php'); ?>

</head>

<body class="d-flex h-100 text-center">

    <div class="d-flex w-100 h-100 p-3 mx-auto flex-column">

        <header class="mb-auto">
            <?php include('header.php'); ?>
        </header>

        <main class="px-3 w-100">
            <div class="container">

                <h2 class="">Dashboard</h2>
                <p class="">Bienvenido!</p>

                <div class="card">
                    <div class="card-body">

                        <h3>Lista de Bienes Registrados</h3>

                        <table class="table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Codigo</th>
                                    <th>Estado</th>
                                    <th>Descripcion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($result as $good) : ?>
                                    <tr>
                                        <td><?php echo $good['id']; ?></td>
                                        <td><?php echo $good['code']; ?></td>
                                        <td><?php echo $good['state']; ?></td>
                                        <td><?php echo $good['description']; ?></td>
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