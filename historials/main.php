<?php
$types = [];
$technician = "ndomiguez";
?>

<!-- <h1 class="mt-2">Historial de mantenimiento</h1> -->

<!-- <div class="card py-0">
    <div class="card-body py-0">
        <h5 class="text-start mt-2">Datos del Bien</h5>
        <div class="card-text text-start small">
            <div class="my-0 py-0">Código: <?php echo $db->getCodeId($property["id"]); ?></div>
            <div class="my-0 py-0">Descripción: <?php echo $property["description"]; ?></div>
            <div class="my-0 py-0">Modelo: <?php echo $property["model"]; ?> || Serial: <?php echo $property["serial"]; ?> || Color: <?php echo $property["color"]; ?></div>
            <div class="my-0 py-0">Estado: <?php echo $property["status"]; ?></div>
        </div>
    </div>
</div> -->

<table class="table table-bordered table-sm" style="font-size:0.8rem">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <!-- <th>Descripción</th> -->
            <th>Técnico</th>
            <!-- <th>Tiempo (Hrs)</th> -->
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($maintenances as $maintenance) : ?>
            <tr>
                <td class="small text-nowrap"><?php echo date("d-m-Y", strtotime($maintenance["date"])); ?></td>
                <td class="small"><?php echo $maintenance["type"]; ?></td>                
                <td class="small"><?php echo $maintenance["technician"]; ?></td>
                <!-- <td><?php echo $maintenance["time_taken"]; ?></td> -->
                <td class="small <?php echo ($maintenance['status'] == 'finalizado') ? "text-uppercase fw-bold " : null; ?>"><?php echo $maintenance["status"]; ?></td>
            </tr>
            <tr> <td colspan="4" class="small" style="border-bottom: 5px #ccc solid !important;"><i>Descripción:</i> <?php echo $maintenance["description"]; ?></td> </tr>
            <?php $types[$maintenance["type"]] = (array_key_exists($maintenance["type"],$types)) ? $types[$maintenance["type"]]+1 : 1 ; ?>
            <?php $technician = $maintenance['technician']; ?>
        <?php endforeach; ?>
    </tbody>
</table>

<?php //print("<pre>".print_r($types)."</pre>"); ?>

<div class=" container-fluid text-start" style="font-size:0.8rem">
    <div>Observaciones:</div>
    <ul class="list-group list-group-numbered list-group-flush">
        <?php foreach ($maintenances as $maintenance) : ?>
            <?php if (!empty($maintenance["notes"])) { ?>
                <li class="list-group-item my-0 p-0"> <small class="small">[<?php echo date("d-m-Y", strtotime($maintenance["date"])); ?>]</small> <?php echo $maintenance["notes"]; ?></li>
            <?php } ?>
        <?php endforeach; ?>
    </ul>
</div>

<p>&nbsp;</p>
<!-- <p>&nbsp;</p> -->


