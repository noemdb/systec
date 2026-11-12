<?php
$types = array();
$technician = '';
$count = 0;

$maintenances = $db->index('maintenances', array('property_id' => $property['id']), '*', 'date ASC');
$count = count($maintenances);
?>

<div class="table-responsive overflow-y-hidden rounded border">
    <table class="table table-sm table-hover align-middle mb-0">
        <caption class="visually-hidden">Mantenimientos del bien <?= e(property_code($property)) ?></caption>
        <thead class="table-light">
            <tr>
                <th scope="col">ID bien</th>
                <th scope="col">Fecha</th>
                <th scope="col">Tipo</th>
                <th scope="col">Técnico</th>
                <th scope="col">Estado</th>
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
                <tr class="maintenance-description-row">
                    <td colspan="5" class="small bg-body-tertiary">
                        <span class="fw-semibold text-body-secondary">Descripción:</span>
                        <?= e($maintenance['description']) ?>
                    </td>
                </tr>
                <?php $types[$maintenance['type']] = array_key_exists($maintenance['type'], $types) ? $types[$maintenance['type']] + 1 : 1; ?>
                <?php $technician = $maintenance['technician']; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<section class="mt-3" aria-labelledby="maintenanceObservationsHeading<?= e((string) $property['id']) ?>">
    <h4 class="h6 d-flex align-items-center gap-2 mb-2" id="maintenanceObservationsHeading<?= e((string) $property['id']) ?>">
        <i class="bi bi-chat-left-text text-body-secondary" aria-hidden="true"></i>
        Observaciones
    </h4>
    <?php $hasObservations = false; ?>
    <ul class="list-group list-group-flush">
        <?php foreach ($maintenances as $maintenance) : ?>
            <?php if (!empty($maintenance['notes'])) : ?>
                <li class="list-group-item px-0 d-flex flex-column flex-sm-row align-items-sm-start gap-2">
                    <span class="badge rounded-pill text-bg-light border text-body-secondary flex-shrink-0">
                        <?= e(date('d-m-Y', strtotime($maintenance['date']))) ?>
                    </span>
                    <span class="text-break"><?= e($maintenance['notes']) ?></span>
                </li>
                <?php $hasObservations = true; ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (!$hasObservations) : ?>
            <li class="list-group-item px-0 text-body-secondary fst-italic">No hay observaciones registradas.</li>
        <?php endif; ?>
    </ul>
</section>

<div class="card bg-body-tertiary border-0 mt-3">
    <div class="card-body d-flex flex-column align-items-center gap-1 py-3 text-center">
        <div class="border-bottom border-secondary-subtle w-50 mb-1"></div>
        <div class="small">Realizado por: <span class="fw-semibold text-uppercase"><?= e($technician) ?></span></div>
        <div class="small text-body-secondary"><?= e(date('d/m/Y H:i')) ?> · <?= e($count) ?> <?= $count === 1 ? 'mantenimiento' : 'mantenimientos' ?></div>
    </div>
</div>
