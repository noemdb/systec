<?php
$maintenanceFieldPrefix = isset($maintenanceFieldPrefix) ? $maintenanceFieldPrefix : '';
$fieldId = function (string $id) use ($maintenanceFieldPrefix): string {
    return ($maintenanceFieldPrefix !== '' ? $maintenanceFieldPrefix . '_' : '') . $id;
};

$isMaintenanceCreate = $maintenanceFieldPrefix === 'create';
$selectedMaintenance = $isMaintenanceCreate || !isset($itemArrSeleted) || !is_array($itemArrSeleted)
    ? array()
    : $itemArrSeleted;

$property_id = null;

if (!$isMaintenanceCreate && isset($selectedMaintenance['property_id'])) {
    $property_id = $selectedMaintenance['property_id'];
}

if (!$isMaintenanceCreate && isset($property['id'])) {
    $property_id = $property['id'];
}

$selectedType = isset($selectedMaintenance['type']) ? $selectedMaintenance['type'] : '';
$selectedTechnician = isset($selectedMaintenance['technician']) ? $selectedMaintenance['technician'] : '';
$selectedDate = $isMaintenanceCreate
    ? ''
    : (isset($selectedMaintenance['date']) ? $selectedMaintenance['date'] : date('Y-m-d\TH:i'));
$selectedReason = isset($selectedMaintenance['failure_reason']) ? $selectedMaintenance['failure_reason'] : '';
$selectedDescription = isset($selectedMaintenance['description']) ? $selectedMaintenance['description'] : '';
$selectedNotes = isset($selectedMaintenance['notes']) ? $selectedMaintenance['notes'] : '';
$selectedNext = isset($selectedMaintenance['next_maintenance_date']) ? $selectedMaintenance['next_maintenance_date'] : '';
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
        <label for="<?= e($fieldId('property_id')) ?>" class="form-label fw-semibold small">Bien asociado <span class="text-danger" aria-hidden="true">*</span></label>
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
        <label for="<?= e($fieldId('type')) ?>" class="form-label fw-semibold small">Tipo de mantenimiento <span class="text-danger" aria-hidden="true">*</span></label>
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
        <label for="<?= e($fieldId('technician')) ?>" class="form-label fw-semibold small">Técnico <span class="text-danger" aria-hidden="true">*</span></label>
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
        <label for="<?= e($fieldId('date')) ?>" class="form-label fw-semibold small">Fecha de inicio <span class="text-danger" aria-hidden="true">*</span></label>
        <input required type="datetime-local" name="date" id="<?= e($fieldId('date')) ?>" class="form-control"
               value="<?= e($selectedDate) ?>">
    </div>

    <div class="mb-2">
        <label for="<?= e($fieldId('failure_reason')) ?>" class="form-label fw-semibold small">Razón del mantenimiento <span class="text-danger" aria-hidden="true">*</span></label>
        <textarea name="failure_reason" id="<?= e($fieldId('failure_reason')) ?>" rows="2"
                  class="form-control no-resize" required><?= e($selectedReason) ?></textarea>
    </div>

    <div class="mb-2">
        <label for="<?= e($fieldId('description')) ?>" class="form-label fw-semibold small">Descripción <span class="text-danger" aria-hidden="true">*</span></label>
        <textarea name="description" id="<?= e($fieldId('description')) ?>" rows="3" class="form-control no-resize" required><?= e($selectedDescription) ?></textarea>
    </div>

    <div class="mb-2">
        <label for="<?= e($fieldId('notes')) ?>" class="form-label fw-semibold small">Notas</label>
        <textarea name="notes" id="<?= e($fieldId('notes')) ?>" rows="3" class="form-control no-resize"><?= e($selectedNotes) ?></textarea>
    </div>

    <?php if ($isMaintenanceCreate) : ?>
        <div class="mb-3 d-flex flex-column gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm align-self-start"
                    id="createImproveMaintenanceNarrative">
                <i class="bi bi-magic me-1" id="createImproveMaintenanceNarrativeIcon" aria-hidden="true"></i>
                <span id="createImproveMaintenanceNarrativeLabel">Mejorar con IA</span>
            </button>
            <span class="form-text" id="createImproveMaintenanceNarrativeStatus" role="status" aria-live="polite">
                Mejora razón, descripción y notas con contenido; no se guarda hasta registrar el mantenimiento.
            </span>
        </div>
    <?php endif; ?>

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
