<?php

$base = $_SERVER['DOCUMENT_ROOT'];
include($base.'/conn.php');
$title = "Mantenimiento";

//////////////index/////////////////////
$db = new DB($base.'/db.db');
$maintenances = $db->index("maintenances");

//////////////mode/////////////////////
$id = (array_key_exists('id',$_GET)) ? $_GET['id'] : null ;
$property_id = (array_key_exists('property_id',$_GET)) ? $_GET['property_id'] : null ;
$modeCreate = (array_key_exists('modeCreate',$_GET)) ? $_GET['modeCreate'] : null ;
$modeEdit = (array_key_exists('modeEdit',$_GET)) ? $_GET['modeEdit'] : null ;

$modeIndex = ( empty($modeCreate) && empty($modeEdit) ) ? 'true' : null ;

$property = $db->getFirstForId('properties',$property_id);

$list_property = $db->getListProperties("properties");

$itemArrSeleted = ($id) ? $db->getFirstForId("maintenances",$id) : Array();

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

                <div class="float-end">
                    <a name="register" id="register" class="btn btn-primary" href="./maintenances.php?modeCreate=true" role="button">Registrar</a>
                </div><br>
                
                <div class="text-center">
                    <h2 class="text-center">Listado de Mantenimientos</h2>      
                    <p class="">Registro, actualización y finalización!</p>                    
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="row">

                            <div class="<?php $class = ($modeIndex=="true") ? "col-12" : "col-sm-6" ; echo $class; ?>" >
                                <?php include('maintenances/table/crud.php'); ?>
                            </div>

                            <?php if ($modeCreate=="true") {  ?>
                                <div class="col-sm-6">
                                    <div class="bg-white shadow-lg border rounded">                            
                                        <h3 class="alert alert-secondary text-center">
                                            <div class="d-flex justify-content-between">
                                            <div class="">Registro de Mantenimiento</div>
                                            <div>
                                                <a class="btn-close" href="./maintenances.php" role="button" aria-label="Close"></a>
                                            </div>
                                        </h3>
                                        <form action="./maintenances/register.php" method="POST">
                                            <?php include('maintenances/form/fields.php'); ?>
                                            <footer class="mt-auto">
                                                <input type="submit" name="register" value="Registrar" class="btn btn-primary w-100">
                                            </footer>
                                        </form>
                                    </div>                        
                                </div>
                            <?php }; ?>

                            <?php if ($modeEdit=="true") {  ?>
                                <div class="col-sm-6">
                                    <div class="bg-white shadow-lg border rounded">                            
                                        <h3 class="alert alert-secondary text-center">
                                            <div class="d-flex justify-content-between">
                                            <div class="">Actualizar Mantenimiento</div>
                                            <div>
                                                <a class="btn-close" href="./maintenances.php" role="button" aria-label="Close"></a>
                                            </div>
                                        </h3>
                                        <form action="./maintenances/update.php" method="POST">
                                            
                                            <?php include('maintenances/form/edit.php'); ?>
                                            <footer class="mt-auto">
                                                <input type="submit" name="update" value="Actualizar" class="btn btn-warning w-100">
                                            </footer>
                                        </form>
                                    </div>                        
                                </div>
                            <?php }; ?>

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

    <script> let table = new DataTable('#myTable', {});</script>

</body>

</html>

<?php $db->close(); ?>