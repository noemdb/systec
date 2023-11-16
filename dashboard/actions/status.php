<?php

$base = $_SERVER['DOCUMENT_ROOT']; 
include_once($base.'/conn.php');
$db = new DB($base.'/db.db');

$table = "properties";
$property_id = (array_key_exists('property_id',$_GET)) ? $_GET['property_id'] : null;
$status = (array_key_exists('status',$_GET)) ? $_GET['status'] : null;

if ($status && $property_id) {
    try {
        switch ($status) {
            case 'BUENO': $status="MALO"; break;
            case 'REGULAR': $status="BUENO"; break;
            case 'MALO': $status="REGULAR"; break;            
            default: $status="BUENO"; break;
        }
        $data = array(
            'status' => $status,
        );
        $result = $db->update($table, $property_id, $data);
        header("Location: ../../dashboard.php?id=".$property_id);
        exit();
    } catch (PDOException $e) {
        echo "Error al procesar los datos: " . $e->getMessage();
    }
}
