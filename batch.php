<html lang="en" class="h-100" data-bs-theme="light">

<?php

$title = "Historial - Batch";

$base = $_SERVER['DOCUMENT_ROOT']; 
include_once($base.'/conn.php');
$db = new DB($base.'/db.db');



$condition = " where property_id=".$property_id;
$maintenances = $db->index("maintenances",$condition,"*"," order by date ASC ");

?>

<head>

    <?php include('include/head.php'); ?>

    <style>
        html { margin: 0.8rem; text-transform: uppercase}
        /* body {max-width: 14cm !important;} */
    </style>

    <style type="text/css" media="print">
        html { margin: 0.8cm; text-transform: uppercase}
        body{
            text-transform: uppercase;
        }
    </style>

</head>

<body class="d-flex h-100 text-center shadow-none">

    <div class="container-fluid">
        <div class="row">

            <div class="col-6">

                <?php
                    $property_id = (array_key_exists('property_id',$_GET)) ? $_GET['property_id'] : null ; //var_dump($property_id);die();
                    $property = ($property_id) ? $db->getFirstForId("properties",$property_id) : Array(); //var_dump($property);die();
                    $condition = " where property_id=".$property_id;
                    $maintenances = $db->index("maintenances",$condition,"*"," order by date ASC ");
                ?>

                <div class=" d-flex w-100 h-100 mx-auto flex-column">

                    <header class="bg-secondary-subtle border-bottom shadow-sm py-1 px-2">            
                        <?php include('historials/membrete.php'); ?>            
                    </header>

                    <div>
                        <?php include('historials/main.php'); ?>
                    </div>

                    <div class=" container-fluid text-center" style="font-size:0.8rem">
                        <div class="my-0 py-0">____________________________________________</div> 
                        <div class="my-0 py-0">Realizado por: <span class=" text-uppercase"><?php echo $technician; ?></span></div>
                        <div class="my-0 py-0"><?php echo date("d/m/Y H:i"); ?></div>  
                    </div>

                </div>
                
            </div>

            <div class="col-6">

                <?php
                    $property_id = (array_key_exists('property_id2',$_GET)) ? $_GET['property_id2'] : null ; //var_dump($property_id);die();
                    $property = ($property_id) ? $db->getFirstForId("properties",$property_id) : Array();
                    $condition = " where property_id=".$property_id;
                    $maintenances = $db->index("maintenances",$condition,"*"," order by date ASC ");
                ?>

                <div class=" d-flex w-100 h-100 mx-auto flex-column">

                    <header class="bg-secondary-subtle border-bottom shadow-sm py-1 px-2">            
                        <?php include('historials/membrete.php'); ?>            
                    </header>

                    <div>
                        <?php include('historials/main.php'); ?>
                    </div>

                    <div class=" container-fluid text-center" style="font-size:0.8rem">
                        <div class="my-0 py-0">____________________________________________</div> 
                        <div class="my-0 py-0">Realizado por: <span class=" text-uppercase"><?php echo $technician; ?></span></div>
                        <div class="my-0 py-0"><?php echo date("d/m/Y H:i"); ?></div>  
                    </div>
                    
                </div>

            </div>
            
        </div>

    </div>    

    </div>

    <?php include('include/script.php'); ?>

</body>

</html>

<?php $db->close(); ?>