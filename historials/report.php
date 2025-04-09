<?php
$types = [];
$technician = "ndomiguez";

$condition = " where property_id=".$property["id"];
// $condition = " where property_id=".'404';
$maintenances = $db->index("maintenances",$condition,"*"," order by date ASC ");
?>

<table class="table table-bordered table-sm" style="font-size:0.8rem">
    <thead>
        <tr>
            <th>ID </th>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Técnico</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($maintenances as $maintenance) : ?>
            <tr>
                <td class="small"><?php echo $property["id"]; ?></td>
                <td class="small text-nowrap"><?php echo date("d-m-Y", strtotime($maintenance["date"])); ?></td>
                <td class="small"><?php echo $maintenance["type"]; ?></td>                
                <td class="small"><?php echo $maintenance["technician"]; ?></td>
                <!-- <td><?php echo $maintenance["time_taken"]; ?></td> -->
                <td class="small <?php echo ($maintenance['status'] == 'finalizado') ? "text-uppercase fw-bold " : null; ?>"><?php echo $maintenance["status"]; ?></td>
            </tr>
            <tr> <td colspan="5" class="small" style="border-bottom: 5px #ccc solid !important;"><i>Descripción:</i> <?php echo $maintenance["description"]; ?></td> </tr>
            <?php $types[$maintenance["type"]] = (array_key_exists($maintenance["type"],$types)) ? $types[$maintenance["type"]]+1 : 1 ; ?>
            <?php $technician = $maintenance['technician']; ?>
        <?php endforeach; ?>
    </tbody>
</table>

<div class=" container-fluid text-start" style="font-size:0.8rem">
    <div>Observaciones:</div>
    <ul class="list-group list-group-numbered list-group-flush">
        <?php foreach ($maintenances as $maintenance) : ?>
            <?php if (!empty($maintenance["notes"])) { ?>
                <li class="list-group-item my-0 p-0"> <small class="small">[<?php echo date("d-m-Y", strtotime($maintenance["date"])); ?>]</small> <?php echo $maintenance["notes"]; ?></li>
            <?php } ?>
        <?php endforeach; ?>
        <li>
            <div class="text-center">                
                <div class="my-0 pt-1">____________________________________________</div> 
                <div class="my-0 py-0">Realizado por: <span class=" text-uppercase"><?php echo $technician; ?></span></div>
                <div class="my-0 py-0"><?php echo date("d/m/Y H:i"); ?> <small>[<?php echo $count; ?>]</small></div>  
            </div>
        <li>
    </ul>
</div>


