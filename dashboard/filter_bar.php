<section class="card mb-4 shadow-sm text-start" aria-labelledby="inventory-filters-title">
    <div class="card-body">
        <h2 class="h6 mb-3" id="inventory-filters-title">Filtrar inventario</h2>
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="filterCategory">Categoría</label>
                <select class="form-select" id="filterCategory">
                    <option value="">Todas las categorías</option>
                    <option value="informatica">Informática</option>
                    <option value="redes">Redes y telecomunicaciones</option>
                    <option value="otros">Otros bienes</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="filterStatus">Estado</label>
                <select class="form-select" id="filterStatus">
                    <option value="">Todos los estados</option>
                    <option value="__attention__">Requieren atención</option>
                    <option value="BUENO">Bueno</option>
                    <option value="REGULAR">Regular</option>
                    <option value="MALO">Malo</option>
                    <option value="MUY MALO">Muy malo</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold" for="filterAdscription">Adscripción / unidad operativa</label>
                <select class="form-select" id="filterAdscription">
                    <option value="">Todas las unidades</option>
                    <?php foreach ($adscriptionOptions as $key => $label) : ?>
                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($canViewAssignments) : ?>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="filterResponsible">Responsable del bien</label>
                    <select class="form-select" id="filterResponsible">
                        <option value="">Todos los responsables</option>
                        <?php foreach ($assignmentFilterOptions['responsible'] as $key => $label) : ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="filterAssigned">Usuario asignado al bien</label>
                    <select class="form-select" id="filterAssigned">
                        <option value="">Todos los usuarios asignados</option>
                        <?php foreach ($assignmentFilterOptions['assigned'] as $key => $label) : ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-12 col-md-4">
                <button type="button" class="btn btn-outline-secondary" id="resetInventoryFilters">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Limpiar filtros
                </button>
            </div>
        </div>
    </div>
</section>
