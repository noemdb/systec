<?php
$types = [];
$technician = "ndomiguez";
?>

<h1 class="mt-2">Historial de mantenimiento</h1>

<div class="card py-0">
    <div class="card-header">
        <h2 class="card-title">Datos del Bien</h2>
    </div>
    <div class="card-body py-0">
        <div class="card-text text-start">
            <div>Código: <?php echo $db->getCodeId($property["id"]); ?></div>
            <div>Descripción: <?php echo $property["description"]; ?></div>
            <div>Modelo: <?php echo $property["model"]; ?> || Serial: <?php echo $property["serial"]; ?> || Color: <?php echo $property["color"]; ?></div>
            <div>Estado: <?php echo $property["status"]; ?></div>
        </div>
    </div>
</div>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Descripción</th>
            <th>Técnico</th>
            <th>Tiempo (Hrs)</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($maintenances as $maintenance) : ?>
            <tr>
                <td><?php echo date("d-m-Y", strtotime($maintenance["date"])); ?></td>
                <td><?php echo $maintenance["type"]; ?></td>
                <td><?php echo $maintenance["description"]; ?></td>
                <td><?php echo $maintenance["technician"]; ?></td>
                <td><?php echo $maintenance["time_taken"]; ?></td>
                <td class="<?php echo ($maintenance['status'] == 'finalizado') ? "text-uppercase fw-bold " : null; ?>"><?php echo $maintenance["status"]; ?></td>
            </tr>
            <?php $types[$maintenance["type"]] = (array_key_exists($maintenance["type"],$types)) ? $types[$maintenance["type"]]+1 : 1 ; ?>
            <?php $technician = $maintenance['technician']; ?>
        <?php endforeach; ?>
    </tbody>
</table>

<?php //print("<pre>".print_r($types)."</pre>"); ?>

<hr>

<div class="card p-2 m-2">
    <h5 class="card-title text-dark pb-2 mb-2">Matenimiento por tipo (Preventivo, Correctivo y Predictivo)</h5>
    <div class="card-body p-2">       
        
        <div class="bar-chart-vertical">
            <?php $total = count($maintenances); ?>
            <?php foreach ($types as $k=>$v) : ?>
                <?php 
                    $height = (! empty($total)) ? $v / $total: null ;
                    $height = 100 * round($height,2);
                ?>
                <div class="item pt-2 mt-2">
                    <div class="bar" style="height:<?php echo $height; ?>%">
                        <div class="value text-white small"><?php echo $height; ?>%</div>
                    </div>
                    <div class="title text-nowrap"><?php echo $k; ?></div> 
                </div>
            <?php endforeach; ?>

        </div>       
        
    </div>
</div>

<hr>

<div class=" container-fluid text-start">
    <div>Observaciones:</div>
    <ul class="list-group list-group-numbered">
        <?php foreach ($maintenances as $maintenance) : ?>
            <?php if (!empty($maintenance["notes"])) { ?>
                <li class="list-group-item"> <small class="small">[<?php echo date("d-m-Y", strtotime($maintenance["date"])); ?>]</small> <?php echo $maintenance["notes"]; ?></li>
            <?php } ?>
        <?php endforeach; ?>
    </ul>
</div>


<div class=" container-fluid text-center">
    <div>____________________________________________</div> 
    <div>Realizado por: <span class=" text-uppercase"><?php echo $technician; ?></span></div>
    <div><?php echo date("d/m/Y H:i"); ?></div>   
    <div></div> 
</div>
