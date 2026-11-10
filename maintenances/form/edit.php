<?php
$db = db();
$maintenanceFieldPrefix = 'edit';
$currentStatus = isset($itemArrSeleted['status']) ? (string) $itemArrSeleted['status'] : '';
$statuses = $db->getListMaintenanceStatus();

if ($currentStatus !== '' && !array_key_exists($currentStatus, $statuses)) {
    $statuses[$currentStatus] = texto_mantenimiento($currentStatus);
}
?>

<?php include __DIR__ . '/fields.php'; ?>

<input type="hidden" name="id" id="id" value="<?= e($id) ?>">

<div class="card-body pt-0">
    <div>
        <label for="<?= e($fieldId('status')) ?>" class="form-label fw-semibold small">Estado</label>
        <select class="form-select" name="status" id="<?= e($fieldId('status')) ?>">
            <?php foreach ($statuses as $key => $value) : ?>
                <option value="<?= e($key) ?>" <?= ((string) $key === $currentStatus) ? 'selected' : '' ?>>
                    <?= e($value) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
