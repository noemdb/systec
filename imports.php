<?php
$base = $_SERVER['DOCUMENT_ROOT'];
include_once($base.'/conn.php');

$title = "Importar";

// Obtener el nombre del archivo CSV (grupo;subgrupo;seccion;ident;description;model;serial;color;status)
$filenameCSV = "maintenances.csv";
$handle = fopen($filenameCSV, "r"); //var_dump($handle);die();

?>

<html lang="en" class="h-100" data-bs-theme="light">

<head>

    <?php include('include/head.php'); ?>

</head>

<body class="d-flex h-100 text-center shadow-none">

    <div class=" d-flex w-100 h-100 mx-auto flex-column">

        <header class="bg-secondary-subtle border-bottom shadow-sm py-2 px-2">
            <?php include('include/header.php'); ?>
        </header>

        <main class="px-3 w-100 mt-2">

        <div class="container-fluid text-start">

            <div class="text-center">
                <h2 class="text-center">Listado de registros encontrados en el archivo <?php echo $filenameCSV; ?></h2>            
                <p class="">Actualización del listado de mantenimientos!</p>                    
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-12">
                            <?php include('maintenances/table/import.php'); ?>
                        </div>
                    </div>
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