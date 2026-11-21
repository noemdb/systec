<?php
if (is_array($property) && isset($property['grupo'], $property['subgrupo'], $property['seccion'], $property['ident'])) {
    $propertyCode = property_code($property);
} else {
    $propertyCode = ($property && isset($property['id'])) ? $db->getCodeId($property['id']) : '';
}
$propertyDescription = ($property && isset($property['description'])) ? $property['description'] : '';
$propertyAdscription = isset($propertyAdscriptionLabel) && is_string($propertyAdscriptionLabel)
    ? $propertyAdscriptionLabel
    : (($property && isset($property['adscription'])) ? (string) $property['adscription'] : '');
$propertyModel = ($property && isset($property['model'])) ? $property['model'] : '';
$propertySerial = ($property && isset($property['serial'])) ? $property['serial'] : '';
$propertyColor = ($property && isset($property['color'])) ? $property['color'] : '';
$propertyStatus = ($property && isset($property['status'])) ? $property['status'] : '';

$letterheadFields = array(
    'Código' => $propertyCode,
    'Adscripción' => $propertyAdscription,
    'Descripción' => $propertyDescription,
    'Modelo' => $propertyModel,
    'Serial' => $propertySerial,
    'Color' => $propertyColor,
    'Estado' => $propertyStatus,
);
$includeLetterheadId = empty($letterheadOmitId);
?>
<div class="container-fluid text-start history-letterhead"<?= $includeLetterheadId ? ' id="historyLetterhead"' : '' ?>>
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-1">
        <h5 class="mb-0">Oficina de Informática, San Felipe, Edo Yaracuy</h5>
        <p class="small text-body-secondary mb-0 text-md-end">
            <i class="bi bi-calendar3 me-1" aria-hidden="true"></i><?= e(date('d-m-Y H:i')) ?>
        </p>
    </div>

    <dl class="row g-1 small mt-1 mb-0">
        <?php foreach ($letterheadFields as $letterheadLabel => $letterheadValue) : ?>
            <?php if ($letterheadLabel !== 'Código' && trim((string) $letterheadValue) === '') {
                continue;
            } ?>
            <div class="<?= e($letterheadLabel === 'Descripción' ? 'col-12' : 'col-6 col-xl-4') ?> d-flex align-items-baseline gap-1">
                <dt class="mb-0 fw-semibold text-body-secondary text-nowrap"><?= e($letterheadLabel) ?>:</dt>
                <dd class="mb-0 text-break">
                    <?= e(trim((string) $letterheadValue) === '' ? '—' : (string) $letterheadValue) ?>
                </dd>
            </div>
        <?php endforeach; ?>
    </dl>
</div>
