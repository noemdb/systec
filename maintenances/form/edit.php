<?php
$db = db();
$currentStatus = isset($itemArrSeleted['status']) ? (string) $itemArrSeleted['status'] : '';
$statuses = $db->getListMaintenanceStatus();

if ($currentStatus !== '' && !array_key_exists($currentStatus, $statuses)) {
    $statuses[$currentStatus] = texto_mantenimiento($currentStatus);
}
?>

<?php include __DIR__ . '/fields.php'; ?>

<input type="hidden" name="id" id="id" value="<?= e($id) ?>">

<div class="form-group pb-2">
    <label for="status">Estado:</label>
    <select class="form-control" name="status" id="status">
        <?php foreach ($statuses as $key => $value) : ?>
            <option value="<?= e($key) ?>" <?= ((string) $key === $currentStatus) ? 'selected' : '' ?>>
                <?= e($value) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
