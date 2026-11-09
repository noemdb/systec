<?php
$canPropertyStatus = auth_has_role(array('admin', 'operador'));
?>
<div class="container-fluid text-start">
    <table class="table table-striped table-sm table-hover" id="myTable">
        <thead>
            <tr class="table-secondary">
                <th>N</th>
                <th>Gr</th>
                <th>SG</th>
                <th>Sec</th>
                <th>Ident.</th>
                <th>Descripción</th>
                <th>Serial</th>
                <th>Estado</th>
                <th>Mant.</th>
                <th class="text-center">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($properties as $property) : ?>
                <?php $maintenances = $db->index('maintenances', array('property_id' => $property['id'])); ?>
                <tr>
                    <td><?= e($property['id']) ?></td>
                    <td><?= e($property['grupo']) ?></td>
                    <td><?= e(str_pad((string) $property['subgrupo'], 4, '0', STR_PAD_LEFT)) ?></td>
                    <td><?= e(str_pad((string) $property['seccion'], 4, '0', STR_PAD_LEFT)) ?></td>
                    <td><?= e(str_pad((string) $property['ident'], 4, '0', STR_PAD_LEFT)) ?></td>
                    <td><?= e($property['description']) ?></td>
                    <td><?= e($property['serial']) ?></td>
                    <td><?= sat_badge(badge_clase_bien((string) $property['status']), (string) $property['status']) ?></td>
                    <td><?= e(count($maintenances)) ?></td>
                    <td>
                        <div class="btn-group" role="group" aria-label="Acciones del bien">
                            <a class="btn btn-outline-primary btn-sm property-action-btn"
                               href="<?= e(url('maintenances.php?modeCreate=true&property_id=' . (int) $property['id'])) ?>"
                               role="button" aria-label="Registrar mantenimiento" title="Registrar mantenimiento">
                                <i class="bi bi-wrench-adjustable-circle" aria-hidden="true"></i>
                            </a>

                            <a class="btn btn-outline-secondary btn-sm property-action-btn" target="_blank" rel="noopener"
                               href="<?= e(url('historials.php?property_id=' . (int) $property['id'])) ?>"
                               role="button" aria-label="Ver historial en una nueva pestaña" title="Ver historial">
                                <i class="bi bi-journal-text" aria-hidden="true"></i>
                            </a>

                            <?php if ($canPropertyStatus) : ?>
                                <form method="post" action="<?= e(url('dashboard/actions/status.php')) ?>"
                                      class="btn-group" role="group" aria-label="Cambiar estado del bien">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="property_id" value="<?= e($property['id']) ?>">
                                    <input type="hidden" name="ident" value="<?= e($property['ident']) ?>">
                                    <button type="submit" class="btn btn-outline-success btn-sm property-action-btn"
                                            aria-label="Cambiar estado del bien" title="Cambiar estado">
                                        <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
