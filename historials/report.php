<?php
$types = array();
$technician = '';
$count = 0;

$maintenances = $db->index('maintenances', array('property_id' => $property['id']), '*', 'date ASC');
$count = count($maintenances);
?>

<table class="table table-bordered table-sm" style="font-size:0.8rem">
    <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Técnico</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($maintenances as $maintenance) : ?>
            <tr>
                <td class="small"><?= e($property['id']) ?></td>
                <td class="small text-nowrap"><?= e(date('d-m-Y', strtotime($maintenance['date']))) ?></td>
                <td class="small"><?= sat_badge(badge_clase_tipo((string) $maintenance['type']), texto_tipo((string) $maintenance['type'])) ?></td>
                <td class="small"><?= e($maintenance['technician']) ?></td>
                <td class="small"><?= sat_badge(badge_clase_mantenimiento((string) $maintenance['status']), texto_mantenimiento((string) $maintenance['status'])) ?></td>
            </tr>
            <tr>
                <td colspan="5" class="small" style="border-bottom: 5px #ccc solid !important;">
                    <i>Descripción:</i> <?= e($maintenance['description']) ?>
                </td>
            </tr>
            <?php $types[$maintenance['type']] = array_key_exists($maintenance['type'], $types) ? $types[$maintenance['type']] + 1 : 1; ?>
            <?php $technician = $maintenance['technician']; ?>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="container-fluid text-start" style="font-size:0.8rem">
    <div>Observaciones:</div>
    <ul class="list-group list-group-numbered list-group-flush">
        <?php foreach ($maintenances as $maintenance) : ?>
            <?php if (!empty($maintenance['notes'])) : ?>
                <li class="list-group-item my-0 p-0">
                    <small class="small">[<?= e(date('d-m-Y', strtotime($maintenance['date']))) ?>]</small>
                    <?= e($maintenance['notes']) ?>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li>
            <div class="text-center">
                <div class="my-0 pt-1">____________________________________________</div>
                <div class="my-0 py-0">Realizado por: <span class="text-uppercase"><?= e($technician) ?></span></div>
                <div class="my-0 py-0"><?= e(date('d/m/Y H:i')) ?> <small>[<?= e($count) ?>]</small></div>
            </div>
        </li>
    </ul>
</div>
