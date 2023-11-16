<?php

$base = $_SERVER['DOCUMENT_ROOT']; 
include_once($base.'/conn.php');
$db = new DB($base.'/db.db');

$table = "maintenances";
$id = (array_key_exists('id',$_GET)) ? $_GET['id'] : null;
$status = (array_key_exists('status',$_GET)) ? $_GET['status'] : null;
$maintenance = $db->getFirstForId("maintenances",$id);

if ($status && $id) {    
    try {
        $now =  date("Y-m-d H:i:s");
        $fecha1 = new DateTime($now); 
        $fecha2 = new DateTime($maintenance['date']);

        $time_taken = $db->restarFechasEnHoras($fecha1,$fecha2);
        $data = array(
            'status' => $status,
            'time_taken' => $time_taken,
        );
        $result = $db->update($table, $id, $data);

        header("Location: ../../maintenances.php?id=".$id);
        exit();
    } catch (PDOException $e) {
        echo "Error al procesar los datos: " . $e->getMessage();
    }
}
