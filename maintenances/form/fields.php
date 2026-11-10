<?php
$maintenanceFieldPrefix = isset($maintenanceFieldPrefix) ? $maintenanceFieldPrefix : '';
$fieldId = function (string $id) use ($maintenanceFieldPrefix): string {
    return ($maintenanceFieldPrefix !== '' ? $maintenanceFieldPrefix . '_' : '') . $id;
};

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

<div class="card-body">
    <div class="mb-3 property-picker">
        <label for="<?= e($fieldId('property_category_filter')) ?>" class="form-label fw-semibold small">
            Categoría del bien
        </label>
        <select id="<?= e($fieldId('property_category_filter')) ?>"
                class="form-select form-select-sm mb-2"
                data-property-picker-category>
            <option value="">Todas las categorías</option>
            <option value="informatica">Informática</option>
            <option value="redes">Redes y telecomunicaciones</option>
            <option value="otros">Otros bienes</option>
        </select>

        <label for="<?= e($fieldId('property_search')) ?>" class="form-label fw-semibold small">
            Buscar bien por código o descripción
        </label>
        <input type="search" class="form-control form-control-sm mb-2"
               id="<?= e($fieldId('property_search')) ?>"
               data-property-picker-search="<?= e($fieldId('property_id')) ?>"
               placeholder="Ej.: 2-0101, laptop, impresora"
               autocomplete="off">
        <div class="form-text mb-2" id="<?= e($fieldId('property_matches')) ?>"
             aria-live="polite" aria-atomic="true"></div>
        <label for="<?= e($fieldId('property_id')) ?>" class="form-label fw-semibold small">Bien asociado <span class="text-danger">*</span></label>
        <select class="form-select" name="property_id" id="<?= e($fieldId('property_id')) ?>"
                data-property-picker data-search-input="<?= e($fieldId('property_search')) ?>"
                data-result-count="<?= e($fieldId('property_matches')) ?>"
                data-category-filter="<?= e($fieldId('property_category_filter')) ?>" required>
            <option value="">Seleccione</option>
            <?php foreach ($propertiesForSelect as $propertyOption) : ?>
                <?php
                $optionCode = property_code($propertyOption);
                $optionDescription = trim((string) $propertyOption['description']);
                $optionStatus = property_status_label((string) $propertyOption['status']);
                $optionCategory = property_category($propertyOption);
                $optionSearchText = implode(' ', array(
                    $optionCode,
                    $optionDescription,
                    $optionStatus,
                    (string) $propertyOption['id'],
                ));
                ?>
                <option value="<?= e($propertyOption['id']) ?>"
                        data-search="<?= e($optionSearchText) ?>"
                        data-category="<?= e($optionCategory) ?>"
                    <?= ((string) $propertyOption['id'] === (string) $property_id) ? 'selected' : '' ?>>
                    <?= e($optionCode) ?> — <?= e($optionDescription !== '' ? $optionDescription : 'Sin descripción') ?>
                    (<?= e($optionStatus) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-2">
        <label for="<?= e($fieldId('type')) ?>" class="form-label fw-semibold small">Tipo de mantenimiento</label>
        <select class="form-select" name="type" id="<?= e($fieldId('type')) ?>" required>
            <option value="">Seleccione</option>
            <?php foreach ($db->getListMaintenanceType() as $key => $value) : ?>
                <option value="<?= e($key) ?>" <?= ((string) $key === (string) $selectedType) ? 'selected' : '' ?>>
                    <?= e($value) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-2">
        <label for="<?= e($fieldId('technician')) ?>" class="form-label fw-semibold small">Técnico</label>
        <select class="form-select" name="technician" id="<?= e($fieldId('technician')) ?>" required>
            <option value="">Seleccione</option>
            <?php foreach ($db->getListMaintenanceTechnician() as $key => $value) : ?>
                <option value="<?= e($key) ?>" <?= ((string) $key === (string) $selectedTechnician) ? 'selected' : '' ?>>
                    <?= e($value) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-2">
        <label for="<?= e($fieldId('date')) ?>" class="form-label fw-semibold small">Fecha de inicio</label>
        <input required type="datetime-local" name="date" id="<?= e($fieldId('date')) ?>" class="form-control"
               value="<?= e($selectedDate) ?>">
    </div>

    <div class="mb-2">
        <label for="<?= e($fieldId('failure_reason')) ?>" class="form-label fw-semibold small">Razón del mantenimiento</label>
        <input type="text" name="failure_reason" id="<?= e($fieldId('failure_reason')) ?>" class="form-control"
               value="<?= e($selectedReason) ?>" required>
    </div>

    <div class="mb-2">
        <label for="<?= e($fieldId('description')) ?>" class="form-label fw-semibold small">Descripción</label>
        <textarea name="description" id="<?= e($fieldId('description')) ?>" rows="3" class="form-control no-resize" required><?= e($selectedDescription) ?></textarea>
    </div>

    <div class="mb-2">
        <label for="<?= e($fieldId('notes')) ?>" class="form-label fw-semibold small">Notas</label>
        <textarea name="notes" id="<?= e($fieldId('notes')) ?>" rows="3" class="form-control no-resize"><?= e($selectedNotes) ?></textarea>
    </div>

    <div>
        <label for="<?= e($fieldId('next_maintenance_date')) ?>" class="form-label fw-semibold small">
            <abbr title="Valor sugerido para el primer lunes dentro de 3 meses">
                Siguiente mantenimiento
            </abbr>
        </label>
        <input type="date" name="next_maintenance_date" id="<?= e($fieldId('next_maintenance_date')) ?>" class="form-control"
               value="<?= e($selectedNext) ?>">
    </div>
</div>
