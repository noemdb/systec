<?php

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/inventory_import.php';
require_login();

$title = 'Mantenimiento';
$maintenanceNotice = isset($_SESSION['maintenance_notice']) && is_string($_SESSION['maintenance_notice'])
    ? trim($_SESSION['maintenance_notice'])
    : '';
unset($_SESSION['maintenance_notice']);
$notifications = $maintenanceNotice !== ''
    ? array(toast_notification('success', $maintenanceNotice))
    : array();

///////////////index/////////////////////
$db = db();
$maintenances = $db->index('maintenances', array(), '*', 'id DESC');
$propertyCategories = array();
$propertiesById = array();
$propertiesForSelect = $db->index(
    'properties',
    array(),
    'id,grupo,subgrupo,seccion,ident,adscription,description,model,color,status',
    'id ASC'
);
foreach ($propertiesForSelect as $propertyRow) {
    $propertyId = (int) $propertyRow['id'];
    $propertyCategories[$propertyId] = property_category($propertyRow);
    $propertiesById[$propertyId] = $propertyRow;
}

$legacyAdscriptionsByIdent = array();
$adscriptionSourceWarning = '';
try {
    $identifiers = array_map(function ($propertyRow) {
        return trim((string) $propertyRow['ident']);
    }, $propertiesForSelect);
    $adscriptionSnapshot = legacy_postgres_cached_property_adscriptions($identifiers);
    $legacyAdscriptionsByIdent = $adscriptionSnapshot['adscriptions'];
    if (!empty($adscriptionSnapshot['stale'])) {
        $adscriptionSourceWarning = 'La fuente institucional no responde; se usa la última consulta guardada.';
    }
} catch (Throwable $e) {
    error_log('Mantenimientos no pudo cargar las adscripciones institucionales: ' . $e->getMessage());
    $adscriptionSourceWarning = 'No se pudo consultar la fuente institucional; se usan los datos locales guardados.';
}

$adscriptionsByPropertyId = array();
$adscriptionOptions = array();
foreach ($propertiesForSelect as $propertyRow) {
    $propertyId = (int) $propertyRow['id'];
    $assignment = property_adscription_assignment($propertyRow, $legacyAdscriptionsByIdent);
    $adscriptionsByPropertyId[$propertyId] = $assignment;
    $adscriptionOptions[$assignment['key']] = $assignment['label'];
}
asort($adscriptionOptions, SORT_NATURAL | SORT_FLAG_CASE);

$maintenanceCounts = array(
    'total' => count($maintenances),
    'iniciado' => 0,
    'revision' => 0,
    'finalizado' => 0,
    'vencido' => 0,
);

foreach ($maintenances as $maintenance) {
    $status = mb_strtolower(trim((string) $maintenance['status']));
    if (isset($maintenanceCounts[$status])) {
        $maintenanceCounts[$status]++;
    }

    $nextDate = trim((string) $maintenance['next_maintenance_date']);
    $nextTimestamp = ($nextDate !== '') ? strtotime($nextDate) : false;
    if ($status !== 'finalizado' && $nextTimestamp !== false && $nextTimestamp < time()) {
        $maintenanceCounts['vencido']++;
    }
}

///////////////mode/////////////////////
$id = (array_key_exists('id',$_GET)) ? $_GET['id'] : null ;
$property_id = (array_key_exists('property_id',$_GET)) ? $_GET['property_id'] : null ;
$modeCreate = (array_key_exists('modeCreate',$_GET)) ? $_GET['modeCreate'] : null ;
$modeEdit = (array_key_exists('modeEdit',$_GET)) ? $_GET['modeEdit'] : null ;

$modeIndex = ( empty($modeCreate) && empty($modeEdit) ) ? 'true' : null ;

$property = $db->getFirstForId('properties',$property_id);

$itemArrSeleted = ($id) ? ($db->getFirstForId("maintenances",$id) ?: Array()) : Array();

ob_start(); ?>

    <div class="container-fluid text-start">
        <section class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-3"
                 aria-labelledby="maintenance-page-title">
            <div>
                <h1 class="h3 mb-1" id="maintenance-page-title">Mantenimientos</h1>
                <p class="text-body-secondary mb-0">Consulta, registra y da seguimiento a los mantenimientos de los bienes.</p>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2 flex-shrink-0">
                <button type="button" class="btn btn-outline-primary" id="printMaintenanceHistories"
                        title="Imprime un historial por bien según los filtros aplicados">
                    <i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir historiales
                </button>
                <button type="button" class="btn btn-outline-secondary" id="exportMaintenanceCsv"
                        title="Descarga en CSV los mantenimientos filtrados">
                    <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Exportar CSV
                </button>
                <button type="button" class="btn btn-primary"
                        data-bs-toggle="offcanvas" data-bs-target="#createMaintenanceDrawer"
                        aria-controls="createMaintenanceDrawer">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Registrar mantenimiento
                </button>
            </div>
        </section>

        <?php
        $bulkUpdated = isset($_GET['bulk_updated']) ? max(0, (int) $_GET['bulk_updated']) : null;
        $bulkSkipped = isset($_GET['bulk_skipped']) ? max(0, (int) $_GET['bulk_skipped']) : 0;
        ?>
        <?php if ($bulkUpdated !== null) : ?>
            <div class="alert alert-<?= $bulkUpdated > 0 ? 'success' : 'warning' ?> d-flex align-items-start gap-2"
                 role="status">
                <i class="bi <?= $bulkUpdated > 0 ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> mt-1"
                   aria-hidden="true"></i>
                <div>
                    Se actualizaron <strong><?= e((string) $bulkUpdated) ?></strong>
                    <?= $bulkUpdated === 1 ? 'mantenimiento' : 'mantenimientos' ?>.
                    <?php if ($bulkSkipped > 0) : ?>
                        Se omitieron <strong><?= e((string) $bulkSkipped) ?></strong>
                        <?= $bulkSkipped === 1 ? 'registro' : 'registros' ?> que no permitían esa transición.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <section class="row g-3 mb-4" aria-label="Resumen de mantenimientos">
            <?php
            $indicators = array(
                array('total', 'Total', $maintenanceCounts['total'], 'primary', 'bi-tools', 'Quitar todos los filtros'),
                array('iniciado', 'Iniciados', $maintenanceCounts['iniciado'], 'info', 'bi-play-circle', 'Filtrar mantenimientos iniciados'),
                array('revision', 'En revisión', $maintenanceCounts['revision'], 'warning', 'bi-search', 'Filtrar mantenimientos en revisión'),
                array('finalizado', 'Finalizados', $maintenanceCounts['finalizado'], 'success', 'bi-check-circle', 'Filtrar mantenimientos finalizados'),
                array('vencido', 'Vencidos', $maintenanceCounts['vencido'], 'danger', 'bi-exclamation-triangle', 'Mostrar solo mantenimientos vencidos'),
            );
            ?>
            <?php foreach ($indicators as $indicator) : ?>
                <div class="col-6 col-md">
                    <button type="button"
                            class="card h-100 w-100 border-start border-4 border-<?= e($indicator[3]) ?> shadow-sm text-start text-body js-kpi-filter"
                            data-kpi="<?= e($indicator[0]) ?>"
                            aria-controls="myTable" aria-pressed="false"
                            title="<?= e($indicator[5]) ?>">
                        <div class="card-body d-flex align-items-center gap-2">
                            <span class="rounded-circle bg-<?= e($indicator[3]) ?>-subtle text-<?= e($indicator[3]) ?> p-2"
                                  aria-hidden="true">
                                <i class="bi <?= e($indicator[4]) ?> fs-4"></i>
                            </span>
                            <div class="flex-grow-1">
                                <p class="small text-body-secondary mb-0"><?= e($indicator[1]) ?></p>
                                <p class="h3 mb-0" data-kpi-count="<?= e($indicator[0]) ?>"><?= e((string) $indicator[2]) ?></p>
                            </div>
                        </div>
                    </button>
                </div>
            <?php endforeach; ?>
        </section>

        <?php if ($modeEdit === 'true') : ?>
            <section class="card shadow-sm mb-3 maintenance-edit-form" aria-labelledby="maintenance-form-title">
                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                    <h2 class="h5 mb-0" id="maintenance-form-title">
                        <i class="bi bi-pencil-square text-primary me-2" aria-hidden="true"></i>Actualizar mantenimiento
                    </h2>
                    <a class="btn-close" href="<?= e(url('maintenances.php')) ?>"
                       aria-label="Cerrar formulario"></a>
                </div>
                <form action="<?= e(url('maintenances/update.php')) ?>" method="POST">
                    <?= csrf_field() ?>
                    <?php include __DIR__ . '/maintenances/form/edit.php'; ?>
                    <div class="card-footer d-flex flex-column flex-sm-row justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= e(url('maintenances.php')) ?>">Cancelar</a>
                        <button type="submit" name="update" value="Actualizar" class="btn btn-primary">
                            <i class="bi bi-save me-1" aria-hidden="true"></i>Guardar cambios
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="card shadow-sm" aria-labelledby="maintenance-list-title">
            <div class="card-header d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                <h2 class="h5 mb-0" id="maintenance-list-title">
                    <i class="bi bi-list-task text-primary me-2" aria-hidden="true"></i>Registro de mantenimientos
                </h2>
                <span class="badge text-bg-light border text-body-secondary">
                    <?= e((string) $maintenanceCounts['total']) ?> registros
                </span>
            </div>
            <div class="card-body">
                <?php include __DIR__ . '/maintenances/table/crud.php'; ?>
            </div>
        </section>
    </div>

    <div class="offcanvas offcanvas-end maintenance-create-drawer text-start" tabindex="-1"
         id="createMaintenanceDrawer" aria-labelledby="createMaintenanceDrawerTitle">
        <div class="offcanvas-header border-bottom">
            <div>
                <h2 class="offcanvas-title h5 mb-1" id="createMaintenanceDrawerTitle">
                    <i class="bi bi-plus-circle text-primary me-2" id="createMaintenanceDrawerTitleIcon" aria-hidden="true"></i>
                    <span id="createMaintenanceDrawerTitleLabel">Registrar mantenimiento</span>
                </h2>
                <p class="small text-body-secondary mb-0" id="createMaintenanceDrawerDescription">
                    Completa los datos para crear un registro.
                </p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <form id="createMaintenanceForm" class="maintenance-create-drawer-form"
              action="<?= e(url('maintenances/register.php')) ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="createMaintenanceId" value="">
            <div class="offcanvas-body">
                <?php $maintenanceFieldPrefix = 'create'; ?>
                <?php include __DIR__ . '/maintenances/form/fields.php'; ?>
                <div class="mt-3 d-none" id="createMaintenanceStatusField">
                    <label for="create_status" class="form-label fw-semibold small">
                        Estado <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <select class="form-select" name="status" id="create_status" disabled required>
                        <?php foreach ($db->getListMaintenanceStatus() as $key => $value) : ?>
                            <option value="<?= e($key) ?>"><?= e($value) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="border-top p-3 d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="offcanvas">
                    Cancelar
                </button>
                <button type="submit" id="createMaintenanceSubmit" name="register" value="Registrar"
                        class="btn btn-primary flex-fill">
                    <i class="bi bi-check-lg me-1" id="createMaintenanceSubmitIcon" aria-hidden="true"></i>
                    <span id="createMaintenanceSubmitLabel">Registrar</span>
                </button>
            </div>
        </form>
    </div>

    <style nonce="<?= e(systec_csp_nonce()) ?>">
        .maintenance-create-drawer {
            --bs-offcanvas-width: min(34rem, 100vw);
        }

        .js-kpi-filter {
            appearance: none;
            font: inherit;
            cursor: pointer;
            transition: box-shadow .15s ease, transform .15s ease;
        }

        .js-kpi-filter:hover {
            transform: translateY(-1px);
        }

        .js-kpi-filter:focus-visible,
        .js-kpi-filter[aria-pressed="true"] {
            outline: 0;
            box-shadow: 0 0 0 .2rem rgba(var(--bs-emphasis-color-rgb, 33, 37, 41), .3),
                        var(--bs-box-shadow-sm, 0 .125rem .25rem rgba(0, 0, 0, .075));
        }

        @media (prefers-reduced-motion: reduce) {
            .js-kpi-filter {
                transition: none;
            }

            .js-kpi-filter:hover {
                transform: none;
            }
        }

        .maintenance-create-drawer-form {
            display: flex;
            flex: 1 1 auto;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }

        .maintenance-create-drawer-form .offcanvas-body {
            min-height: 0;
            overflow-y: auto;
        }

        .maintenance-create-drawer-form .card-body {
            padding: 0;
        }

        .maintenance-description-cell {
            min-width: 16rem;
            overflow-wrap: anywhere;
        }

        @media (min-width: 992px) {
            .maintenance-edit-form .card-body:first-of-type {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0 1rem;
                align-items: start;
            }

            .maintenance-edit-form .property-picker {
                grid-column: 1 / -1;
            }
        }
    </style>

    <script src="<?= e(url('assets/js/property-picker.js')) ?>"></script>
    <script nonce="<?= e(systec_csp_nonce()) ?>">
        const search = <?= js(isset($_GET['search']) ? $_GET['search'] : '') ?>;
        const improveMaintenanceNarrativeUrl = <?= js(url('maintenances/actions/improve-narrative.php')) ?>;
        const categoryFilter = document.getElementById('filterCategory');
        const adscriptionFilter = document.getElementById('filterAdscription');
        const dateFromFilter = document.getElementById('filterDateFrom');
        const dateToFilter = document.getElementById('filterDateTo');
        const next30Filter = document.getElementById('proximos30');
        const vencidosFilter = document.getElementById('soloVencidos');
        const statusFilterSelect = document.getElementById('filterEstado');
        const typeFilterSelect = document.getElementById('filterTipo');
        const technicianFilterSelect = document.getElementById('filterTecnico');
        const maintenanceParams = new URLSearchParams(window.location.search);
        const initialUrlFilters = maintenanceParams;
        categoryFilter.value = maintenanceParams.get('category') ?? '';
        const requestedAdscription = maintenanceParams.get('adscription') ?? '';
        if (requestedAdscription
            && !Array.from(adscriptionFilter.options).some((option) => option.value === requestedAdscription)) {
            adscriptionFilter.add(new Option('Unidad seleccionada (no disponible)', requestedAdscription));
        }
        adscriptionFilter.value = requestedAdscription;

        const table = new DataTable('#myTable', {
            order: [],
            pageLength: 10,
            language: {
                search: 'Buscar:',
                searchPlaceholder: 'Código, descripción, modelo, color, técnico…',
                lengthMenu: 'Mostrar _MENU_ registros',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'Sin registros',
                infoFiltered: '(filtrado de _MAX_ registros totales)',
                zeroRecords: 'Ningún mantenimiento coincide con los filtros aplicados.',
                emptyTable: 'Aún no hay mantenimientos registrados.',
                paginate: {
                    first: 'Primero',
                    last: 'Último',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            },
            search: {
                search: search ?? ''
            },
        });

        const tableInfo = document.querySelector('.dataTables_info');
        if (tableInfo) {
            tableInfo.setAttribute('role', 'status');
            tableInfo.setAttribute('aria-live', 'polite');
        }

        document.querySelectorAll('[data-property-picker]').forEach((select) => {
            window.Systec.initPropertyPicker(select);
        });

        const createMaintenanceDrawer = document.getElementById('createMaintenanceDrawer');
        const createMaintenanceForm = document.getElementById('createMaintenanceForm');
        const createMaintenanceSubmit = document.getElementById('createMaintenanceSubmit');
        const createMaintenanceStatusField = document.getElementById('createMaintenanceStatusField');
        const createMaintenanceStatusSelect = document.getElementById('create_status');
        const createMaintenanceId = document.getElementById('createMaintenanceId');
        const maintenanceNarrativeStatus = document.getElementById('createImproveMaintenanceNarrativeStatus');
        const maintenanceNarrativeButton = document.getElementById('createImproveMaintenanceNarrative');
        const maintenanceDrawerSubmitLabel = document.getElementById('createMaintenanceSubmitLabel');
        const maintenanceDrawerSubmitIcon = document.getElementById('createMaintenanceSubmitIcon');
        const maintenanceDrawerTitleLabel = document.getElementById('createMaintenanceDrawerTitleLabel');
        const maintenanceDrawerTitleIcon = document.getElementById('createMaintenanceDrawerTitleIcon');
        const maintenanceDrawerDescription = document.getElementById('createMaintenanceDrawerDescription');
        const registerMaintenanceUrl = <?= js(url('maintenances/register.php')) ?>;
        const updateMaintenanceUrl = <?= js(url('maintenances/update.php')) ?>;
        let pendingMaintenanceEditTrigger = null;

        document.addEventListener('click', (event) => {
            if (!(event.target instanceof Element)) return;
            const trigger = event.target.closest('.js-edit-maintenance');
            if (trigger) pendingMaintenanceEditTrigger = trigger;
        }, true);

        const resetMaintenanceDrawer = () => {
            createMaintenanceForm.reset();
            createMaintenanceForm.action = registerMaintenanceUrl;
            createMaintenanceId.value = '';
            createMaintenanceSubmit.name = 'register';
            createMaintenanceSubmit.value = 'Registrar';
            maintenanceDrawerSubmitLabel.textContent = 'Registrar';
            maintenanceDrawerSubmitIcon.className = 'bi bi-check-lg me-1';
            maintenanceDrawerTitleLabel.textContent = 'Registrar mantenimiento';
            maintenanceDrawerTitleIcon.className = 'bi bi-plus-circle text-primary me-2';
            maintenanceDrawerDescription.textContent = 'Completa los datos para crear un registro.';
            createMaintenanceStatusField.classList.add('d-none');
            createMaintenanceStatusSelect.disabled = true;
            maintenanceNarrativeButton.disabled = false;
            maintenanceNarrativeStatus.textContent = 'Mejora razón, descripción y notas con contenido; no se guarda hasta registrar el mantenimiento.';
            maintenanceNarrativeStatus.className = 'form-text';
            document.getElementById('create_property_category_filter')
                .dispatchEvent(new Event('change', { bubbles: true }));
        };

        createMaintenanceDrawer.addEventListener('show.bs.offcanvas', (event) => {
            resetMaintenanceDrawer();
            const relatedTarget = event.relatedTarget;
            const trigger = relatedTarget instanceof Element
                && relatedTarget.matches('.js-edit-maintenance')
                ? relatedTarget
                : pendingMaintenanceEditTrigger;
            pendingMaintenanceEditTrigger = null;
            if (!trigger) return;

            const maintenance = JSON.parse(trigger.dataset.maintenance);
            const setSelectValue = (id, value) => {
                const select = document.getElementById(id);
                const normalizedValue = String(value ?? '');
                if (!Array.from(select.options).some((option) => option.value === normalizedValue)) {
                    select.add(new Option(normalizedValue, normalizedValue));
                }
                select.value = normalizedValue;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            };

            createMaintenanceForm.action = updateMaintenanceUrl;
            createMaintenanceId.value = maintenance.id;
            createMaintenanceSubmit.name = 'update';
            createMaintenanceSubmit.value = 'Actualizar';
            maintenanceDrawerSubmitLabel.textContent = 'Guardar cambios';
            maintenanceDrawerSubmitIcon.className = 'bi bi-save me-1';
            maintenanceDrawerTitleLabel.textContent = 'Editar mantenimiento';
            maintenanceDrawerTitleIcon.className = 'bi bi-pencil-square text-primary me-2';
            maintenanceDrawerDescription.textContent = `Actualiza el mantenimiento ${maintenance.code}.`;
            createMaintenanceStatusField.classList.remove('d-none');
            createMaintenanceStatusSelect.disabled = false;

            setSelectValue('create_property_id', maintenance.property_id);
            setSelectValue('create_type', maintenance.type);
            setSelectValue('create_technician', maintenance.technician);
            document.getElementById('create_date').value = String(maintenance.date ?? '')
                .replace(' ', 'T').slice(0, 16);
            document.getElementById('create_failure_reason').value = maintenance.failure_reason ?? '';
            document.getElementById('create_description').value = maintenance.description ?? '';
            document.getElementById('create_notes').value = maintenance.notes ?? '';
            document.getElementById('create_next_maintenance_date').value = maintenance.next_maintenance_date ?? '';
            setSelectValue('create_status', maintenance.status);
            maintenanceNarrativeStatus.textContent = 'La propuesta se aplicará al formulario; revisa los cambios antes de guardar.';
            maintenanceNarrativeStatus.className = 'form-text';
        });

        if (new URLSearchParams(window.location.search).get('modeCreate') === 'true') {
            bootstrap.Offcanvas.getOrCreateInstance(createMaintenanceDrawer).show();
        }

        const selectFilters = {
            status: 'filterEstado',
            type: 'filterTipo',
            technician: 'filterTecnico'
        };
        const activeFilters = { status: '', type: '', technician: '' };
        let applyingHistory = false;

        const readSelectFilters = () => {
            Object.entries(selectFilters).forEach(([key, id]) => {
                activeFilters[key] = document.getElementById(id).value;
            });
        };

        const setSelectFromParam = (select, value) => {
            if (!value) return;
            let option = Array.from(select.options).find((candidate) => candidate.value === value);
            if (!option) {
                option = Array.from(select.options).find(
                    (candidate) => candidate.textContent.trim().toLowerCase() === value.trim().toLowerCase()
                );
            }
            if (option) select.value = option.value;
        };

        const hasActiveFilters = () => (
            categoryFilter.value !== ''
            || adscriptionFilter.value !== ''
            || activeFilters.status !== ''
            || activeFilters.type !== ''
            || activeFilters.technician !== ''
            || dateFromFilter.value !== ''
            || dateToFilter.value !== ''
            || next30Filter.checked
            || vencidosFilter.checked
            || table.search().trim() !== ''
        );

        const syncMaintenanceFilters = (push = false) => {
            if (applyingHistory) return;
            const url = new URL(window.location.href);
            const filters = {
                category: categoryFilter.value,
                adscription: adscriptionFilter.value,
                search: table.search().trim(),
                estado: activeFilters.status,
                tipo: activeFilters.type,
                tecnico: activeFilters.technician,
                desde: dateFromFilter.value,
                hasta: dateToFilter.value,
                proximo: next30Filter.checked ? '30' : '',
                vencido: vencidosFilter.checked ? '1' : ''
            };
            Object.entries(filters).forEach(([key, value]) => {
                if (value) {
                    url.searchParams.set(key, value);
                } else {
                    url.searchParams.delete(key);
                }
            });
            url.searchParams.delete('bulk_updated');
            url.searchParams.delete('bulk_skipped');
            const nextUrl = url.pathname + url.search + url.hash;
            const currentUrl = window.location.pathname + window.location.search + window.location.hash;
            if (nextUrl === currentUrl) return;
            if (push) {
                history.pushState(null, '', nextUrl);
            } else {
                history.replaceState(null, '', nextUrl);
            }
        };

        const resetFilters = (pushHistory = false) => {
            applyingHistory = true;
            Object.values(selectFilters).forEach((id) => { document.getElementById(id).value = ''; });
            readSelectFilters();
            categoryFilter.value = '';
            adscriptionFilter.value = '';
            dateFromFilter.value = '';
            dateToFilter.value = '';
            next30Filter.checked = false;
            table.filterNext30 = false;
            vencidosFilter.checked = false;
            table.filterVencidos = false;
            table.search('').columns().search('');
            applyingHistory = false;
            syncMaintenanceFilters(pushHistory);
            table.draw();
        };

        const bindMaintenanceNarrativeImprover = ({
            container,
            form,
            button,
            icon,
            label,
            status,
            fields,
            idInput = null,
            idleLabel
        }) => {
            const setStatus = (message, className) => {
                status.textContent = message;
                status.className = `small ${className}`;
            };

            button.addEventListener('click', async () => {
                const maintenanceId = idInput ? idInput.value : '';
                const originalTexts = Object.fromEntries(
                    Object.entries(fields).map(([field, input]) => [field, input.value])
                );
                if (!Object.values(originalTexts).some((text) => text.trim() !== '')) {
                    setStatus('Completa al menos un campo narrativo para generar una propuesta.', 'text-danger');
                    return;
                }

                const requestData = {
                    csrf_token: form.querySelector('[name="csrf_token"]').value,
                    ...originalTexts
                };
                if (maintenanceId !== '') requestData.id = maintenanceId;

                button.disabled = true;
                icon.className = 'spinner-border spinner-border-sm me-2';
                label.textContent = 'Generando…';
                setStatus('Enviando razón, descripción y notas con contenido para su mejora…', 'text-body-secondary');

                try {
                    const response = await fetch(improveMaintenanceNarrativeUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                        },
                        body: new URLSearchParams(requestData)
                    });
                    let result;
                    try {
                        result = await response.json();
                    } catch {
                        throw new Error('El servidor devolvió una respuesta inválida.');
                    }
                    if (!result || typeof result !== 'object' || Array.isArray(result)) {
                        throw new Error('El servidor devolvió una respuesta inválida.');
                    }
                    if (!response.ok) {
                        throw new Error(typeof result.error === 'string'
                            ? result.error
                            : 'No se pudo generar la propuesta narrativa.');
                    }
                    if (!['description', 'failure_reason', 'notes'].every((field) => typeof result[field] === 'string')) {
                        throw new Error('La respuesta no contiene todos los textos narrativos esperados.');
                    }

                    const valuesUnchanged = Object.entries(fields).every(
                        ([field, input]) => input.value === originalTexts[field]
                    );
                    if (!container.classList.contains('show')
                        || (idInput && idInput.value !== maintenanceId)
                        || !valuesUnchanged) {
                        setStatus(
                            'Los campos cambiaron durante la solicitud; la propuesta se descartó. Vuelve a solicitarla.',
                            'text-warning'
                        );
                        return;
                    }

                    const hasImprovements = Object.keys(fields).some(
                        (field) => result[field] !== originalTexts[field]
                    );
                    if (!hasImprovements) {
                        setStatus(
                            'No hubo cambios: el texto no aporta contexto suficiente para mejorarlo sin inventar información.',
                            'text-warning'
                        );
                        return;
                    }

                    Object.entries(fields).forEach(([field, input]) => {
                        input.value = result[field];
                    });
                    setStatus(
                        'Propuesta para razón, descripción y notas generada. Revísala antes de guardar.',
                        'text-success'
                    );
                } catch (error) {
                    setStatus(
                        error instanceof Error ? error.message : 'No se pudo generar la propuesta narrativa.',
                        'text-danger'
                    );
                } finally {
                    button.disabled = false;
                    icon.className = 'bi bi-magic me-1';
                    label.textContent = idleLabel;
                }
            });
        };

        const createNarrativeButton = document.getElementById('createImproveMaintenanceNarrative');
        if (createMaintenanceDrawer && createNarrativeButton) {
            bindMaintenanceNarrativeImprover({
                container: createMaintenanceDrawer,
                form: createMaintenanceForm,
                button: createNarrativeButton,
                icon: document.getElementById('createImproveMaintenanceNarrativeIcon'),
                label: document.getElementById('createImproveMaintenanceNarrativeLabel'),
                status: document.getElementById('createImproveMaintenanceNarrativeStatus'),
                idInput: createMaintenanceId,
                idleLabel: 'Mejorar con IA',
                fields: {
                    description: document.getElementById('create_description'),
                    failure_reason: document.getElementById('create_failure_reason'),
                    notes: document.getElementById('create_notes')
                }
            });
        }

        const transitionModal = document.getElementById('maintenanceTransitionModal');
        if (transitionModal) {
            transitionModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const nextStatus = button.dataset.nextStatus;
                const actionLabel = button.dataset.actionLabel;
                const isFinalizing = nextStatus === 'finalizado';

                document.getElementById('transitionMaintenanceId').value = button.dataset.maintenanceId;
                document.getElementById('transitionMaintenanceStatus').value = nextStatus;
                document.getElementById('transitionMaintenanceCode').textContent = button.dataset.maintenanceCode;
                document.getElementById('transitionMaintenanceTitleLabel').textContent = actionLabel;
                document.getElementById('transitionMaintenanceMessage').textContent = isFinalizing
                    ? '¿Confirmas finalizar este mantenimiento? Se calculará el tiempo transcurrido.'
                    : '¿Confirmas enviar este mantenimiento a revisión?';

                const confirmButton = document.getElementById('confirmMaintenanceTransition');
                confirmButton.className = `btn ${isFinalizing ? 'btn-success' : 'btn-warning'}`;
                document.getElementById('confirmMaintenanceTransitionIcon').className =
                    `bi ${isFinalizing ? 'bi-check2-circle' : 'bi-search'} me-1`;
                document.getElementById('confirmMaintenanceTransitionLabel').textContent = actionLabel;
            });
        }

        // Fase 2.1 — Modal de detalle (Razón y Notas) + tooltips
        const detalleModal = new bootstrap.Modal('#detalleModal');

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-detail');
            if (!btn) return;

            const d = JSON.parse(btn.dataset.detail);
            const setBadge = (id, text, badgeClass) => {
                const el = document.getElementById(id);
                el.className = `badge rounded-pill ${badgeClass || 'text-bg-secondary'}`;
                el.textContent = text || '—';
            };
            const details = {
                detalleId: d.id,
                detalleCode: d.code,
                detallePropertyId: d.property_id,
                detalleDate: d.date,
                detalleTimeTaken: d.time_taken,
                detalleTechnician: d.technician,
                detalleNextDate: d.next_maintenance_date,
                detalleReason: d.reason,
                detalleDescription: d.description,
                detalleNotes: d.notes
            };
            Object.entries(details).forEach(([id, value]) => {
                document.getElementById(id).textContent = value || '—';
            });
            setBadge('detalleType', d.type, d.type_badge);
            setBadge('detalleStatus', d.status, d.status_badge);
            detalleModal.show();
        });

        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

        // Filtros combinados: vencidos, categoría, adscripción, estado, tipo, técnico y fechas
        $.fn.dataTable.ext.search.push((settings, data, dataIndex) => {
            if (settings.nTable.id !== 'myTable') return true;
            const node = table.row(dataIndex).node();
            if (!node) return true;

            const ds = node.dataset;
            const vencidosMatch = !table.filterVencidos || ds.vencido === '1';
            const categoryMatches = !categoryFilter.value || ds.category === categoryFilter.value;
            const adscriptionMatches = !adscriptionFilter.value || ds.adscription === adscriptionFilter.value;
            const statusMatches = !activeFilters.status || ds.status === activeFilters.status;
            const typeMatches = !activeFilters.type || ds.type === activeFilters.type;
            const technicianMatches = !activeFilters.technician || ds.technician === activeFilters.technician;
            const dateFromMatches = !dateFromFilter.value || (ds.date && ds.date >= dateFromFilter.value);
            const dateToMatches = !dateToFilter.value || (ds.date && ds.date <= dateToFilter.value);
            const next30Matches = !table.filterNext30 || ds.next30 === '1';

            return vencidosMatch && categoryMatches && adscriptionMatches
                && statusMatches && typeMatches && technicianMatches
                && dateFromMatches && dateToMatches && next30Matches;
        });

        const updateKpiCounts = () => {
            const counts = { total: 0, iniciado: 0, revision: 0, finalizado: 0, vencido: 0 };
            table.rows({ search: 'applied' }).nodes().toArray().forEach((row) => {
                counts.total++;
                const status = row.dataset.status;
                if (Object.prototype.hasOwnProperty.call(counts, status)) {
                    counts[status]++;
                }
                if (row.dataset.vencido === '1') {
                    counts.vencido++;
                }
            });

            document.querySelectorAll('[data-kpi-count]').forEach((node) => {
                node.textContent = String(counts[node.dataset.kpiCount] ?? 0);
            });

            document.querySelectorAll('.js-kpi-filter').forEach((button) => {
                const kpi = button.dataset.kpi;
                let pressed = false;
                if (kpi === 'total') {
                    pressed = !hasActiveFilters();
                } else if (kpi === 'vencido') {
                    pressed = vencidosFilter.checked;
                } else {
                    pressed = activeFilters.status === kpi;
                }
                button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
            });
        };

        const printHistoriesButton = document.getElementById('printMaintenanceHistories');
        const exportCsvButton = document.getElementById('exportMaintenanceCsv');

        const updateActionStates = () => {
            const hasRows = table.rows({ search: 'applied' }).count() > 0;
            if (printHistoriesButton) {
                printHistoriesButton.disabled = !hasRows;
            }
            if (exportCsvButton) {
                exportCsvButton.disabled = !hasRows;
            }
        };

        // Acciones masivas: solo se envían filas visibles con los filtros activos
        const bulkToolbar = document.getElementById('maintenanceBulkToolbar');
        const bulkForm = document.getElementById('bulkMaintenanceForm');
        const bulkModalElement = document.getElementById('bulkTransitionModal');
        const bulkModal = bulkModalElement ? bootstrap.Modal.getOrCreateInstance(bulkModalElement) : null;
        const bulkSelectAll = document.getElementById('bulkSelectAll');
        const bulkActions = {
            revision: {
                label: 'Enviar a revisión',
                statuses: ['iniciado', 'finalizado'],
                buttonClass: 'btn-warning',
                icon: 'bi-search',
                message: (count) => `¿Enviar a revisión ${count} ${count === 1 ? 'mantenimiento' : 'mantenimientos'}?`
            },
            finalizado: {
                label: 'Finalizar',
                statuses: ['iniciado', 'revision'],
                buttonClass: 'btn-success',
                icon: 'bi-check2-circle',
                message: (count) => `¿Finalizar ${count} ${count === 1 ? 'mantenimiento' : 'mantenimientos'}? Se calculará el tiempo transcurrido.`
            }
        };

        const bulkSelectedRows = () => table.rows({ search: 'applied' }).nodes().toArray().filter((row) => {
            const checkbox = row.querySelector('.js-bulk-select');
            return checkbox !== null && checkbox.checked;
        });

        const bulkEligibleRows = (status) => bulkSelectedRows().filter(
            (row) => bulkActions[status].statuses.includes(row.dataset.status)
        );

        const setBulkSelection = (checked) => {
            table.rows({ page: 'current' }).nodes().toArray().forEach((row) => {
                const checkbox = row.querySelector('.js-bulk-select');
                if (checkbox) checkbox.checked = checked;
            });
            updateBulkState();
        };

        const updateBulkState = () => {
            if (!bulkToolbar) return;
            const selected = bulkSelectedRows();
            document.getElementById('maintenanceBulkCount').textContent = String(selected.length);
            document.getElementById('maintenanceBulkCountLabel').textContent =
                selected.length === 1 ? 'seleccionado' : 'seleccionados';
            bulkToolbar.hidden = selected.length === 0;

            document.querySelectorAll('.js-bulk-select').forEach((checkbox) => {
                checkbox.closest('tr')?.classList.toggle('table-active', checkbox.checked);
            });

            [['bulkSendReview', 'revision'], ['bulkFinalize', 'finalizado']].forEach(([buttonId, status]) => {
                const button = document.getElementById(buttonId);
                if (!button) return;
                const eligible = bulkEligibleRows(status).length;
                button.disabled = eligible === 0;
                button.title = eligible === 0
                    ? 'La selección no tiene registros que permitan esta transición.'
                    : `${bulkActions[status].label} ${eligible} ${eligible === 1 ? 'registro' : 'registros'} de la selección`;
            });

            if (bulkSelectAll) {
                const pageCheckboxes = table.rows({ page: 'current' }).nodes().toArray()
                    .map((row) => row.querySelector('.js-bulk-select'))
                    .filter((checkbox) => checkbox !== null);
                const checkedCount = pageCheckboxes.filter((checkbox) => checkbox.checked).length;
                bulkSelectAll.checked = pageCheckboxes.length > 0 && checkedCount === pageCheckboxes.length;
                bulkSelectAll.indeterminate = checkedCount > 0 && checkedCount < pageCheckboxes.length;
            }
        };

        if (bulkToolbar && bulkForm && bulkModal) {
            document.addEventListener('change', (event) => {
                if (event.target.classList.contains('js-bulk-select')) updateBulkState();
            });

            bulkSelectAll?.addEventListener('change', () => setBulkSelection(bulkSelectAll.checked));

            document.getElementById('bulkClear')?.addEventListener('click', () => setBulkSelection(false));

            const openBulkTransition = (status) => {
                const eligible = bulkEligibleRows(status);
                if (!eligible.length) return;
                const config = bulkActions[status];

                document.getElementById('bulkMaintenanceStatus').value = status;
                document.getElementById('bulkMaintenanceReturnQuery').value = window.location.search.replace(/^\?/, '');
                const idsContainer = document.getElementById('bulkMaintenanceIds');
                idsContainer.replaceChildren(...eligible.map((row) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = row.querySelector('.js-bulk-select').value;
                    return input;
                }));

                document.getElementById('bulkTransitionMessage').textContent = config.message(eligible.length);
                const confirmButton = document.getElementById('bulkTransitionConfirm');
                confirmButton.className = `btn ${config.buttonClass}`;
                document.getElementById('bulkTransitionConfirmIcon').className = `bi ${config.icon} me-1`;
                document.getElementById('bulkTransitionConfirmLabel').textContent = config.label;
                bulkModal.show();
            };

            document.getElementById('bulkFinalize')?.addEventListener('click', () => openBulkTransition('finalizado'));
            document.getElementById('bulkSendReview')?.addEventListener('click', () => openBulkTransition('revision'));
        }

        table.on('draw', () => {
            updateKpiCounts();
            updateActionStates();
            updateBulkState();
        });

        categoryFilter.addEventListener('change', () => {
            syncMaintenanceFilters(true);
            table.draw();
        });

        adscriptionFilter.addEventListener('change', () => {
            syncMaintenanceFilters(true);
            table.draw();
        });

        vencidosFilter.addEventListener('change', () => {
            table.filterVencidos = vencidosFilter.checked;
            syncMaintenanceFilters(true);
            table.draw();
        });

        next30Filter.addEventListener('change', () => {
            table.filterNext30 = next30Filter.checked;
            syncMaintenanceFilters(true);
            table.draw();
        });

        [dateFromFilter, dateToFilter].forEach((input) => {
            input.addEventListener('change', () => {
                if (dateFromFilter.value && dateToFilter.value && dateFromFilter.value > dateToFilter.value) {
                    if (input === dateFromFilter) {
                        dateToFilter.value = dateFromFilter.value;
                    } else {
                        dateFromFilter.value = dateToFilter.value;
                    }
                }
                syncMaintenanceFilters(true);
                table.draw();
            });
        });

        document.querySelectorAll('.js-col-filter').forEach((select) => {
            select.addEventListener('change', () => {
                readSelectFilters();
                syncMaintenanceFilters(true);
                table.draw();
            });
        });

        document.querySelectorAll('.js-kpi-filter').forEach((button) => {
            button.addEventListener('click', () => {
                const kpi = button.dataset.kpi;
                if (kpi === 'total') {
                    resetFilters(true);
                    return;
                }
                if (kpi === 'vencido') {
                    vencidosFilter.checked = !vencidosFilter.checked;
                    table.filterVencidos = vencidosFilter.checked;
                } else {
                    statusFilterSelect.value = statusFilterSelect.value === kpi ? '' : kpi;
                    readSelectFilters();
                }
                syncMaintenanceFilters(true);
                table.draw();
            });
        });

        document.getElementById('resetFiltros').addEventListener('click', () => resetFilters(true));

        // Aplica filtros desde la URL, incluidos enlaces con las etiquetas antiguas
        const applyFiltersFromUrl = (urlFilters) => {
            categoryFilter.value = urlFilters.get('category') ?? '';

            const requestedAdscription = urlFilters.get('adscription') ?? '';
            if (requestedAdscription
                && !Array.from(adscriptionFilter.options).some((option) => option.value === requestedAdscription)) {
                adscriptionFilter.add(new Option('Unidad seleccionada (no disponible)', requestedAdscription));
            }
            adscriptionFilter.value = requestedAdscription;

            [statusFilterSelect, typeFilterSelect, technicianFilterSelect].forEach((select) => {
                select.value = '';
            });
            setSelectFromParam(statusFilterSelect, urlFilters.get('estado'));
            setSelectFromParam(typeFilterSelect, urlFilters.get('tipo'));
            setSelectFromParam(technicianFilterSelect, urlFilters.get('tecnico'));
            readSelectFilters();

            ['desde', 'hasta'].forEach((param) => {
                const value = urlFilters.get(param) ?? '';
                (param === 'desde' ? dateFromFilter : dateToFilter).value =
                    /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : '';
            });

            next30Filter.checked = urlFilters.get('proximo') === '30';
            table.filterNext30 = next30Filter.checked;
            vencidosFilter.checked = urlFilters.get('vencido') === '1';
            table.filterVencidos = vencidosFilter.checked;

            table.search(urlFilters.get('search') ?? '');
            table.draw();
        };

        applyFiltersFromUrl(initialUrlFilters);
        table.on('search', () => syncMaintenanceFilters());
        syncMaintenanceFilters();

        window.addEventListener('popstate', () => {
            applyingHistory = true;
            applyFiltersFromUrl(new URLSearchParams(window.location.search));
            applyingHistory = false;
        });

        // Exportación CSV de las filas que cumplen los filtros activos
        const csvEscape = (value) => `"${String(value).replace(/"/g, '""')}"`;
        if (exportCsvButton) {
            exportCsvButton.addEventListener('click', () => {
                const headers = [
                    'Código', 'Tipo', 'Descripción', 'Fecha', 'Horas', 'Técnico',
                    'Estado', 'Próximo mantenimiento', 'Modelo', 'Color'
                ];
                const lines = [headers.map(csvEscape).join(';')];
                table.rows({ search: 'applied' }).nodes().toArray().forEach((row) => {
                    const values = Array.from(row.cells)
                        .filter((cell) => !cell.classList.contains('maintenance-select-cell'))
                        .slice(0, headers.length)
                        .map((cell) => cell.textContent.replace(/\s+/g, ' ').trim());
                    lines.push(values.map(csvEscape).join(';'));
                });

                const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                const downloadUrl = URL.createObjectURL(blob);
                const today = new Date();
                const dateStamp = [
                    today.getFullYear(),
                    String(today.getMonth() + 1).padStart(2, '0'),
                    String(today.getDate()).padStart(2, '0')
                ].join('-');
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = `mantenimientos-${dateStamp}.csv`;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(downloadUrl);
            });
        }

        // Impresión por lotes: un historial por bien según los filtros activos
        if (printHistoriesButton) {
            printHistoriesButton.addEventListener('click', () => {
                const params = new URLSearchParams();
                const filters = {
                    category: categoryFilter.value,
                    adscription: adscriptionFilter.value,
                    search: table.search().trim(),
                    status: activeFilters.status,
                    type: activeFilters.type,
                    technician: activeFilters.technician,
                    desde: dateFromFilter.value,
                    hasta: dateToFilter.value,
                    proximo: next30Filter.checked ? '30' : '',
                    vencido: vencidosFilter.checked ? '1' : ''
                };
                Object.entries(filters).forEach(([key, value]) => {
                    if (value) {
                        params.set(key, value);
                    }
                });
                const printUrl = <?= js(url('maintenances/print.php')) ?>;
                window.open(printUrl + (params.toString() ? '?' + params.toString() : ''), '_blank', 'noopener');
            });
        }

        updateKpiCounts();
        updateActionStates();
    </script>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
