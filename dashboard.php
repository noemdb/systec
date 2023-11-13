<html lang="en" class="h-100" data-bs-theme="light">

<?php

include('conn.php');
$db = new DB(); //var_dump($db);exit();
$result = $db->select("SELECT * FROM properties"); //var_dump($result);exit();

?>

<head>

    <?php include('include/head.php'); ?>

</head>

<body class="d-flex h-100 text-center shadow-none">

    <div class="d-flex w-100 h-100 p-3 mx-auto flex-column">

        <header>
            <?php include('include/header.php'); ?>
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
                                    <th>Identificador</th>
                                    <th>Estado</th>
                                    <th>Grupo</th>
                                    <th>Subgrupo</th>
                                    <th>Seccion</th>
                                    <th>Adscripcion</th>
                                    <th>Descripcion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($result as $good) : ?>
                                    <tr>
                                        <td><?php echo $good['id']; ?></td>
                                        <td><?php echo $good['serial']; ?></td>
                                        <td><?php echo $good['ident']; ?></td>
                                        <td><?php echo $good['status']; ?></td>
                                        <td><?php echo $good['grupo']; ?></td>
                                        <td><?php echo $good['subgrupo']; ?></td>
                                        <td><?php echo $good['seccion']; ?></td>
                                        <td><?php echo $good['adscription']; ?></td>
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
            <?php include('include/footer.php'); ?>
        </footer>

    </div>

    <?php include('include/script.php'); ?>

</body>

</html>