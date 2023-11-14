<html lang="en" class="h-100" data-bs-theme="light">

<?php

$base = $_SERVER['DOCUMENT_ROOT']; 
include_once($base.'/conn.php');
$db = new DB($base.'/db.db');

$properties = $db->select("SELECT * FROM properties");

?>

<head>

    <?php include('include/head.php'); ?>

</head>

<body class="d-flex h-100 text-center shadow-none">

<div class=" d-flex w-100 h-100 mx-auto flex-column">

        <header class="bg-secondary-subtle border-bottom shadow-sm py-2 px-2">
            <?php include('include/header.php'); ?>
        </header>

        <main class="px-3 w-100 mt-2">
            <div class="container-fluid">

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
                                <?php foreach ($properties as $property) : ?>
                                    <tr>
                                        <td><?php echo $property['id']; ?></td>
                                        <td><?php echo $property['serial']; ?></td>
                                        <td><?php echo $property['ident']; ?></td>
                                        <td><?php echo $property['status']; ?></td>
                                        <td><?php echo $property['grupo']; ?></td>
                                        <td><?php echo $property['subgrupo']; ?></td>
                                        <td><?php echo $property['seccion']; ?></td>
                                        <td><?php echo $property['adscription']; ?></td>
                                        <td><?php echo $property['description']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>

        <footer class="mt-auto text-white-50 bg-secondary-subtle py-2">
            <?php include('include/footer.php'); ?>
        </footer>

    </div>

    <?php include('include/script.php'); ?>

</body>

</html>