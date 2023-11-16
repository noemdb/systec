<?php
$base = $_SERVER['DOCUMENT_ROOT']; 
include_once($base.'/conn.php');
$db = new DB($base.'/db.db');
?>

<?php include('maintenances/form/fields.php'); ?>

<input type="hidden" name="id" id="id" value="<?php echo $id; ?>">

<?php  ?>
<div class="form-group pb-2">
    <label for="status">Estado:</label>
    <select class="form-control" name="status" id="status">
        <?php foreach ($db->getListMaintenanceStatus() as $key => $value) : ?>
            <option value=<?php echo $key; ?> <?php echo ($key== $itemArrSeleted['status']) ? 'selected' : null; ?>><?php echo $value; ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php  ?>