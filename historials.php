<html lang="en" class="h-100" data-bs-theme="light">

<?php

$title = "Historial";

$base = $_SERVER['DOCUMENT_ROOT']; 
include_once($base.'/conn.php');
$db = new DB($base.'/db.db');

$property_id = (array_key_exists('property_id',$_GET)) ? $_GET['property_id'] : null ; //var_dump($property_id);die();
$property = ($property_id) ? $db->getFirstForId("properties",$property_id) : Array();

$condition = " where property_id=".$property_id;
$maintenances = $db->index("maintenances",$condition,"*"," order by date ASC ");

?>

<head>

    <?php include('include/head.php'); ?>

</head>

<body class="d-flex h-100 text-center shadow-none">

    <div class=" d-flex w-100 h-100 mx-auto flex-column">

        <header class="bg-secondary-subtle border-bottom shadow-sm py-2 px-2">
            <div class="container-fluid">
                <?php include('historials/membrete.php'); ?>
            </div>
        </header>

        <main class="px-3 w-100 mt-2">
            <div class="container-fluid">
                <h4>Historial de mantenimiento</h4>
                <?php include('historials/main.php'); ?>
                <?php //include('historials/chart.php'); ?>
            </div>

            <div class=" container-fluid text-center">
                <div class="my-0 py-0">____________________________________________</div> 
                <div class="my-0 py-0">Realizado por: <span class=" text-uppercase"><?php echo $technician; ?></span></div>
                <div class="my-0 py-0"><?php echo date("d/m/Y H:i"); ?></div>  
            </div>
        </main>

    </div>

    <?php include('include/script.php'); ?>

</body>

</html>

<?php $db->close(); ?>