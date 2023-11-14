<?php
$base = $_SERVER['DOCUMENT_ROOT']; 
include_once($base.'/conn.php');
$db = new DB($base.'/db.db');

//En caso de recibir un id, obtiene el array de registro correspondeinte
$id = (array_key_exists('id',$_GET)) ? $_GET['id'] : null;
$itemArrSeleted = $db->getFirstForId('maintenances',$id);

?>

<?php include('maintenances/form/fields.php'); ?>

<input type="hidden" name="id" id="id" value="<?php echo $id; ?>">

<div class="form-group pb-2">
    <label for="status">Estado:</label>
    <select class="form-control" name="status" id="status">
        <?php foreach ($db->getListMaintenanceStatus() as $key => $value) : ?>
            <option value=<?php echo $key; ?>><?php echo $value; ?></option>
        <?php endforeach; ?>
    </select>
</div>