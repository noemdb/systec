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
                <th>Cant.Manten.</th>
                <th class="text-center">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($properties as $property) : ?>
                <?php $status_class = ($property['status']=="BUENO") ? "fw-bold text-success" : null; ?>
                <?php $status_class = ($property['status']=="REGULAR") ? "fw-bold text-warning" : $status_class; ?>
                <?php $status_class = ($property['status']=="MALO") ? "fw-bold text-danger" : $status_class; ?>
                <?php $condition = " WHERE property_id=".$property['id']; $maintenances = $db->index("maintenances",$condition); ?>
                <tr>
                    <td><?php echo $property['id']; ?></td>
                    <td><?php echo $property['grupo']; ?></td>
                    <td><?php echo str_pad($property['subgrupo'], 4, "0", STR_PAD_LEFT); ?></td>
                    <td><?php echo str_pad($property['seccion'], 4, "0", STR_PAD_LEFT); ?></td>
                    <td><?php echo str_pad($property['ident'], 4, "0", STR_PAD_LEFT); ?></td>
                    <td><?php echo $property['description']; ?></td>
                    <td><?php echo $property['serial']; ?></td>
                    <td class="<?php echo $status_class; ?>"><?php echo $property['status']; ?></td>
                    <td><?php echo count($maintenances); ?></td>
                    <td>
                        <div class="btn-group" role="group" aria-label="Button group name">
                            <a class="btn btn-primary btn-sm " href="./maintenances.php?modeCreate=true&property_id=<?php echo $property['id']; ?>" role="button">Mantenimiento</a>
                            <a class="btn btn-dark btn-sm " target="_blank" href="./historials.php?property_id=<?php echo $property['id']; ?>" role="button">Historial</a>
                            <a class="btn btn-success btn-sm " href="./dashboard/actions/status.php?property_id=<?php echo $property['id']; ?>&status=<?php echo $property['status']; ?>&ident=<?php echo $property['ident']; ?>" role="button">Estado</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>