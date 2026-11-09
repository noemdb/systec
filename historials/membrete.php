<?php
$propertyCode = ($property && isset($property['id'])) ? $db->getCodeId($property['id']) : '';
$propertyDescription = ($property && isset($property['description'])) ? $property['description'] : '';
$propertyModel = ($property && isset($property['model'])) ? $property['model'] : '';
$propertySerial = ($property && isset($property['serial'])) ? $property['serial'] : '';
$propertyColor = ($property && isset($property['color'])) ? $property['color'] : '';
$propertyStatus = ($property && isset($property['status'])) ? $property['status'] : '';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="text-start">
                <h5 class="my-0 py-0">Oficina de Informática, San Felipe, Edo Yaracuy, <?= e(date('d-m-Y H:i')) ?></h5>
            </div>
        </div>

        <div class="col-12">
            <div class="text-start small">
                <div class="my-0 py-0">
                    <strong>Datos del Bien</strong> - Código: <?= e($propertyCode) ?>
                    | Descripción: <?= e($propertyDescription) ?>
                    | Modelo: <?= e($propertyModel) ?>
                    || Serial: <?= e($propertySerial) ?>
                    || Color: <?= e($propertyColor) ?>
                    | Estado: <?= e($propertyStatus) ?>
                </div>
            </div>
        </div>
    </div>
</div>
