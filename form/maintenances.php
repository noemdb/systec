<div class="container-fluid">
    <div class="row">
        <div class="form-group pb-2">
            <label for="property_id">Código:</label>
            <input type="text" name="property_id" id="property_id" class="form-control" required>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="type">Tipo de mantenimiento:</label>
            <select class="form-control" name="type" id="type">
                <?php foreach ($db->getListMaintenanceType() as $key => $value) : ?>
                    <option value=<?php echo $key; ?>><?php echo $value; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="technician">Técnico:</label>
            <select class="form-control" name="technician" id="technician">
                <?php foreach ($db->getListMaintenanceTechnician() as $key => $value) : ?>
                    <option value=<?php echo $key; ?>><?php echo $value; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="date">Fecha de inicio:</label>
            <input required type="datetime-local" name="date" id="date" class="form-control" value=<?php echo date('Y-m-d\TH:i') ?>>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="reason">Razón del mantenimiento:</label>
            <input type="text" name="reason" id="reason" class="form-control" required>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="description">Descripción:</label>
            <textarea name="description" id="description" rows="5" class="form-control no-resize" required></textarea>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="notes">Notas:</label>
            <textarea name="notes" id="notes" rows="5" class="form-control no-resize" required></textarea>
        </div>
    </div>

    <div class="row">
        <div class="form-group pb-2">
            <label for="next-maintenance">
                <abbr title="Valor sugerido para el primer lunes dentro de 3 meses">
                    Siguiente mantenimiento:
                </abbr>
            </label>
            <input type="date" name="next-maintenance" id="next-maintenance" class="form-control">
        </div>
    </div>

</div>