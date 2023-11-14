<?php //var_dump($itemArrSeleted);die(); ?>

<div class="container-fluid">
    <div class="row">
        <div class="form-group pb-2">
            <label for="property_id">Cod. del Bien:</label>
            <input type="text" name="property_id" id="property_id" class="form-control" value="<?php echo (array_key_exists('property_id',$itemArrSeleted)) ? $itemArrSeleted['property_id'] : null; ?>" required>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="type">Tipo de mantenimiento:</label>
            <select class="form-control" name="type" id="type">
                <?php foreach ($db->getListMaintenanceType() as $key => $value) : ?>
                    <option value=<?php echo $key; ?> <?php echo ($key== $itemArrSeleted['type']) ? 'selected' : null; ?>><?php echo $value; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="technician">Técnico:</label>
            <select class="form-control" name="technician" id="technician">
                <?php foreach ($db->getListMaintenanceTechnician() as $key => $value) : ?>
                    <option value=<?php echo $key; ?> <?php echo ($key== $itemArrSeleted['technician']) ? 'selected' : null; ?>><?php echo $value; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="date">Fecha de inicio:</label>
            <input required type="datetime-local" name="date" id="date" class="form-control" value="<?php echo (array_key_exists('date',$itemArrSeleted)) ? $itemArrSeleted['date'] : date('Y-m-d\TH:i'); ?>" >
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="failure_reason">Razón del mantenimiento:</label>
            <input type="text" name="failure_reason" id="failure_reason" class="form-control" value="<?php echo (array_key_exists('failure_reason',$itemArrSeleted)) ? $itemArrSeleted['failure_reason'] : null; ?>" required>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="description">Descripción:</label>
            <textarea name="description" id="description" rows="5" class="form-control no-resize" required><?php echo (array_key_exists('description',$itemArrSeleted)) ? $itemArrSeleted['description'] : null; ?></textarea>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="notes">Notas:</label>
            <textarea name="notes" id="notes" rows="5" class="form-control no-resize" required><?php echo (array_key_exists('notes',$itemArrSeleted)) ? $itemArrSeleted['notes'] : null; ?></textarea>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="next_maintenance_date">
                <abbr title="Valor sugerido para el primer lunes dentro de 3 meses">
                    Siguiente mantenimiento:
                </abbr>
            </label>
            <input type="date" name="next_maintenance_date" id="next_maintenance_date" class="form-control" value="<?php echo (array_key_exists('next_maintenance_date',$itemArrSeleted)) ? $itemArrSeleted['next_maintenance_date'] : null; ?>">
        </div>
    </div>

</div>

<!-- property_id
type
description
date
technician
next_maintenance_date
failure_reason
notes -->