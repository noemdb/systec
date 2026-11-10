<?php

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_login();

$title = 'Mantenimiento';

///////////////index/////////////////////
$db = db();
$maintenances = $db->index('maintenances', array(), '*', 'id DESC');
$propertyCategories = array();
$propertiesForSelect = $db->index(
    'properties',
    array(),
    'id,grupo,subgrupo,seccion,ident,description,status',
    'id ASC'
);
foreach ($propertiesForSelect as $propertyRow) {
    $propertyCategories[(int) $propertyRow['id']] = property_category($propertyRow);
}

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
        <section class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-3"
                 aria-labelledby="maintenance-page-title">
            <div>
                <h1 class="h2 mb-1" id="maintenance-page-title">Mantenimientos</h1>
                <p class="text-body-secondary mb-0">Consulta, registra y da seguimiento a los mantenimientos de los bienes.</p>
            </div>
            <button type="button" class="btn btn-primary align-self-sm-start flex-shrink-0"
                    data-bs-toggle="offcanvas" data-bs-target="#createMaintenanceDrawer"
                    aria-controls="createMaintenanceDrawer">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Registrar mantenimiento
            </button>
        </section>

        <section class="row g-2 mb-3" aria-label="Resumen de mantenimientos">
            <?php
            $indicators = array(
                array('Total', $maintenanceCounts['total'], 'primary', 'bi-tools'),
                array('Iniciados', $maintenanceCounts['iniciado'], 'info', 'bi-play-circle'),
                array('En revisión', $maintenanceCounts['revision'], 'warning', 'bi-search'),
                array('Finalizados', $maintenanceCounts['finalizado'], 'success', 'bi-check-circle'),
                array('Vencidos', $maintenanceCounts['vencido'], 'danger', 'bi-exclamation-triangle'),
            );
            ?>
            <?php foreach ($indicators as $indicator) : ?>
                <div class="col-6 col-md">
                    <article class="card h-100 border-start border-3 border-<?= e($indicator[2]) ?> shadow-sm">
                        <div class="card-body d-flex align-items-center gap-2">
                            <span class="rounded-circle bg-<?= e($indicator[2]) ?>-subtle text-<?= e($indicator[2]) ?> p-2"
                                  aria-hidden="true">
                                <i class="bi <?= e($indicator[3]) ?>"></i>
                            </span>
                            <div class="flex-grow-1">
                                <p class="small text-body-secondary mb-0"><?= e($indicator[0]) ?></p>
                                <p class="h4 mb-0"><?= e($indicator[1]) ?></p>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="row g-3 align-items-start">
            <section class="<?= ($modeEdit === 'true') ? 'col-12 col-xl-8' : 'col-12' ?>"
                     aria-labelledby="maintenance-list-title">
                <div class="card shadow-sm">
                    <div class="card-header d-flex align-items-center justify-content-between gap-2">
                        <h2 class="h5 mb-0" id="maintenance-list-title">Registro de mantenimientos</h2>
                        <span class="badge text-bg-secondary"><?= e($maintenanceCounts['total']) ?> registros</span>
                    </div>
                    <div class="card-body">
                        <?php include __DIR__ . '/maintenances/table/crud.php'; ?>
                    </div>
                </div>
            </section>

            <?php if ($modeEdit === 'true') : ?>
                <section class="col-12 col-xl-4" aria-labelledby="maintenance-form-title">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex align-items-center justify-content-between gap-2">
                            <h2 class="h5 mb-0" id="maintenance-form-title">Actualizar mantenimiento</h2>
                            <a class="btn-close" href="<?= e(url('maintenances.php')) ?>"
                               aria-label="Cerrar formulario"></a>
                        </div>
                        <form action="<?= e(url('maintenances/update.php')) ?>" method="POST">
                            <?= csrf_field() ?>
                            <?php include __DIR__ . '/maintenances/form/edit.php'; ?>
                            <div class="card-footer">
                                <button type="submit" name="update" value="Actualizar" class="btn btn-warning w-100">
                                    <i class="bi bi-save me-1" aria-hidden="true"></i>Guardar cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <div class="offcanvas offcanvas-end maintenance-create-drawer" tabindex="-1"
         id="createMaintenanceDrawer" aria-labelledby="createMaintenanceDrawerTitle">
        <div class="offcanvas-header border-bottom">
            <div>
                <h2 class="offcanvas-title h5 mb-1" id="createMaintenanceDrawerTitle">Registrar mantenimiento</h2>
                <p class="small text-body-secondary mb-0">Completa los datos para crear un registro.</p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <form class="maintenance-create-drawer-form" action="<?= e(url('maintenances/register.php')) ?>" method="POST">
            <?= csrf_field() ?>
            <div class="offcanvas-body">
                <?php $maintenanceFieldPrefix = 'create'; ?>
                <?php include __DIR__ . '/maintenances/form/fields.php'; ?>
            </div>
            <div class="border-top p-3 d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="offcanvas">
                    Cancelar
                </button>
                <button type="submit" name="register" value="Registrar" class="btn btn-primary flex-fill">
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Registrar
                </button>
            </div>
        </form>
    </div>

    <style nonce="<?= e(systec_csp_nonce()) ?>">
        .maintenance-create-drawer {
            --bs-offcanvas-width: min(34rem, 100vw);
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
            padding: 0 !important;
        }
    </style>

    <script src="<?= e(url('assets/js/property-picker.js')) ?>"></script>
    <script nonce="<?= e(systec_csp_nonce()) ?>">
        const search = <?= js(isset($_GET['search']) ? $_GET['search'] : '') ?>;
        const categoryFilter = document.getElementById('filterCategory');
        const maintenanceParams = new URLSearchParams(window.location.search);
        categoryFilter.value = maintenanceParams.get('category') ?? '';

        const table = new DataTable('#myTable', {
            search: {
                search: search ?? ''
            },
        });

        document.querySelectorAll('[data-property-picker]').forEach((select) => {
            window.Systec.initPropertyPicker(select);
        });

        const createMaintenanceDrawer = document.getElementById('createMaintenanceDrawer');
        if (new URLSearchParams(window.location.search).get('modeCreate') === 'true') {
            bootstrap.Offcanvas.getOrCreateInstance(createMaintenanceDrawer).show();
        }

        const syncMaintenanceFilters = () => {
            const url = new URL(window.location.href);
            if (categoryFilter.value) {
                url.searchParams.set('category', categoryFilter.value);
            } else {
                url.searchParams.delete('category');
            }
            if (table.search().trim()) {
                url.searchParams.set('search', table.search().trim());
            } else {
                url.searchParams.delete('search');
            }
            history.replaceState(null, '', url.pathname + url.search + url.hash);
        };
        table.on('search', () => syncMaintenanceFilters());

        const editMaintenanceModal = document.getElementById('editMaintenanceModal');
        if (editMaintenanceModal) {
            editMaintenanceModal.addEventListener('show.bs.modal', (event) => {
                const maintenance = JSON.parse(event.relatedTarget.dataset.maintenance);
                document.getElementById('editMaintenanceId').value = maintenance.id;
                document.getElementById('editMaintenanceCode').textContent = maintenance.code;

                const values = {
                    editMaintenanceProperty: maintenance.property_id,
                    editMaintenanceType: maintenance.type,
                    editMaintenanceTechnician: maintenance.technician
                };
                Object.entries(values).forEach(([id, value]) => {
                    const select = document.getElementById(id);
                    if (!Array.from(select.options).some((option) => option.value === String(value))) {
                        const option = document.createElement('option');
                        option.value = value;
                        option.textContent = value;
                        select.add(option);
                    }
                    select.value = value;
                });

                document.getElementById('editMaintenanceDate').value = maintenance.date.replace(' ', 'T').slice(0, 16);
                document.getElementById('editMaintenanceReason').value = maintenance.failure_reason;
                document.getElementById('editMaintenanceDescription').value = maintenance.description;
                document.getElementById('editMaintenanceNotes').value = maintenance.notes;
                document.getElementById('editNextMaintenanceDate').value = maintenance.next_maintenance_date;

                const status = document.getElementById('editMaintenanceStatus');
                if (!Array.from(status.options).some((option) => option.value === maintenance.status)) {
                    const option = document.createElement('option');
                    option.value = maintenance.status;
                    option.textContent = maintenance.status;
                    status.add(option);
                }
                status.value = maintenance.status;
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
                document.getElementById('maintenanceTransitionModalTitle').textContent = actionLabel;
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
            const details = {
                detalleId: d.id,
                detalleCode: d.code,
                detallePropertyId: d.property_id,
                detalleType: d.type,
                detalleDate: d.date,
                detalleTimeTaken: d.time_taken,
                detalleTechnician: d.technician,
                detalleStatus: d.status,
                detalleNextDate: d.next_maintenance_date,
                detalleReason: d.reason,
                detalleDescription: d.description,
                detalleNotes: d.notes
            };
            Object.entries(details).forEach(([id, value]) => {
                document.getElementById(id).textContent = value || '—';
            });
            detalleModal.show();
        });

        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

        // Fase 2.2 — Filtros por columna y "solo vencidos"
        // Filtro global personalizado: solo filas marcadas como vencidas por el servidor
        $.fn.dataTable.ext.search.push((settings, data, dataIndex) => {
            if (settings.nTable.id !== 'myTable') return true;
            const node = table.row(dataIndex).node();
            if (!node) return true;

            const vencidosMatch = !table.filterVencidos || node.classList.contains('js-vencido');
            const categoryMatches = !categoryFilter.value || node.dataset.category === categoryFilter.value;
            return vencidosMatch && categoryMatches;
        });

        table.draw();

        categoryFilter.addEventListener('change', () => {
            table.draw();
            syncMaintenanceFilters();
        });

        document.getElementById('soloVencidos').addEventListener('change', (e) => {
            table.filterVencidos = e.target.checked;
            table.draw();
        });

        document.querySelectorAll('.js-col-filter').forEach((sel) => {
            sel.addEventListener('change', () => {
                const v = sel.value.trim();
                if (v) {
                    // match exacto contra el texto de la celda (badges incluidos)
                    table.column(parseInt(sel.dataset.column, 10)).search('^' + v + '$', true, false);
                } else {
                    table.column(parseInt(sel.dataset.column, 10)).search('', true, false);
                }
                table.draw();
            });
        });

        document.getElementById('resetFiltros').addEventListener('click', () => {
            document.querySelectorAll('.js-col-filter').forEach(sel => { sel.value = ''; });
            categoryFilter.value = '';
            const venc = document.getElementById('soloVencidos');
            if (venc.checked) { venc.checked = false; table.filterVencidos = false; }
            table.search('').columns().search('').draw();
            syncMaintenanceFilters();
        });
    </script>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
