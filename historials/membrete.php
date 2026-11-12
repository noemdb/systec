<?php
$propertyCode = ($property && isset($property['id'])) ? $db->getCodeId($property['id']) : '';
$propertyDescription = ($property && isset($property['description'])) ? $property['description'] : '';
$propertyModel = ($property && isset($property['model'])) ? $property['model'] : '';
$propertySerial = ($property && isset($property['serial'])) ? $property['serial'] : '';
$propertyColor = ($property && isset($property['color'])) ? $property['color'] : '';
$propertyStatus = ($property && isset($property['status'])) ? $property['status'] : '';

$letterheadFields = array(
    'Código' => $propertyCode,
    'Descripción' => $propertyDescription,
    'Modelo' => $propertyModel,
    'Serial' => $propertySerial,
    'Color' => $propertyColor,
    'Estado' => $propertyStatus,
);
?>
<div class="container-fluid text-start" id="historyLetterhead">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-1">
        <h5 class="mb-0">Oficina de Informática, San Felipe, Edo Yaracuy</h5>
        <p class="small text-body-secondary mb-0 text-md-end">
            <i class="bi bi-calendar3 me-1" aria-hidden="true"></i><?= e(date('d-m-Y H:i')) ?>
        </p>
    </div>

    <dl class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2 small mt-2 mb-0">
        <?php foreach ($letterheadFields as $letterheadLabel => $letterheadValue) : ?>
            <?php if ($letterheadLabel !== 'Código' && trim((string) $letterheadValue) === '') {
                continue;
            } ?>
            <div class="col">
                <dt class="fw-semibold text-body-secondary text-uppercase"><?= e($letterheadLabel) ?></dt>
                <dd class="mb-0 text-break">
                    <?= e(trim((string) $letterheadValue) === '' ? '—' : (string) $letterheadValue) ?>
                </dd>
            </div>
        <?php endforeach; ?>
    </dl>
</div>
