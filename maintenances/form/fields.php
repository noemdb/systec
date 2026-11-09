<?php
$property_id = null;

if (!empty($itemArrSeleted) && isset($itemArrSeleted['property_id'])) {
    $property_id = $itemArrSeleted['property_id'];
}

if ($property) {
    $property_id = $property['id'];
}

$selectedType = isset($itemArrSeleted['type']) ? $itemArrSeleted['type'] : '';
$selectedTechnician = isset($itemArrSeleted['technician']) ? $itemArrSeleted['technician'] : '';
$selectedDate = isset($itemArrSeleted['date']) ? $itemArrSeleted['date'] : date('Y-m-d\TH:i');
$selectedReason = isset($itemArrSeleted['failure_reason']) ? $itemArrSeleted['failure_reason'] : '';
$selectedDescription = isset($itemArrSeleted['description']) ? $itemArrSeleted['description'] : '';
$selectedNotes = isset($itemArrSeleted['notes']) ? $itemArrSeleted['notes'] : '';
$selectedNext = isset($itemArrSeleted['next_maintenance_date']) ? $itemArrSeleted['next_maintenance_date'] : '';
?>

<div class="container-fluid">
    <div class="row">
        <div class="form-group pb-2">
            <label for="property_id">Cod. del Bien:</label>
            <select class="form-control" name="property_id" id="property_id" required>
                <option value="">Seleccione</option>
                <?php foreach ($db->getListProperties('properties') as $key => $value) : ?>
                    <option value="<?= e($key) ?>" <?= ((string) $key === (string) $property_id) ? 'selected' : '' ?>>
                        <?= e($value) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="type">Tipo de mantenimiento:</label>
            <select class="form-control" name="type" id="type" required>
                <option value="">Seleccione</option>
                <?php foreach ($db->getListMaintenanceType() as $key => $value) : ?>
                    <option value="<?= e($key) ?>" <?= ((string) $key === (string) $selectedType) ? 'selected' : '' ?>>
                        <?= e($value) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="technician">Técnico:</label>
            <select class="form-control" name="technician" id="technician" required>
                <option value="">Seleccione</option>
                <?php foreach ($db->getListMaintenanceTechnician() as $key => $value) : ?>
                    <option value="<?= e($key) ?>" <?= ((string) $key === (string) $selectedTechnician) ? 'selected' : '' ?>>
                        <?= e($value) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="date">Fecha de inicio:</label>
            <input required type="datetime-local" name="date" id="date" class="form-control"
                   value="<?= e($selectedDate) ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="failure_reason">Razón del mantenimiento:</label>
            <input type="text" name="failure_reason" id="failure_reason" class="form-control"
                   value="<?= e($selectedReason) ?>" required>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="description">Descripción:</label>
            <textarea name="description" id="description" rows="5" class="form-control no-resize" required><?= e($selectedDescription) ?></textarea>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="notes">Notas:</label>
            <textarea name="notes" id="notes" rows="5" class="form-control no-resize"><?= e($selectedNotes) ?></textarea>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="next_maintenance_date">
                <abbr title="Valor sugerido para el primer lunes dentro de 3 meses">
                    Siguiente mantenimiento:
                </abbr>
            </label>
            <input type="date" name="next_maintenance_date" id="next_maintenance_date" class="form-control"
                   value="<?= e($selectedNext) ?>">
        </div>
    </div>
</div>
