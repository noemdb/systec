<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="text-start">
                <h5 class="my-0 py-0">Oficina de Informática, San Felipe, Edo Yaracuy, <?php echo date("d/m/Y H:i"); ?></h5>
            </div>
        </div>

        <div class="col-12">
            <div class="text-start small">
                <div class="my-0 py-0"> <strong>Datos del Bien</strong> - Código: <?php echo $db->getCodeId($property["id"]); ?> | Descripción: <?php echo $property["description"]; ?> | Modelo: <?php echo $property["model"]; ?> || Serial: <?php echo $property["serial"]; ?> || Color: <?php echo $property["color"]; ?> | Estado: <?php echo $property["status"]; ?></div>
            </div>
        </div>
        
    </div>
</div>