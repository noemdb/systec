<?php

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_login();

$title = 'Mantenimiento';

///////////////index/////////////////////
$db = db();
$maintenances = $db->index('maintenances', array(), '*', 'id DESC');

///////////////mode/////////////////////
$id = (array_key_exists('id',$_GET)) ? $_GET['id'] : null ;
$property_id = (array_key_exists('property_id',$_GET)) ? $_GET['property_id'] : null ;
$modeCreate = (array_key_exists('modeCreate',$_GET)) ? $_GET['modeCreate'] : null ;
$modeEdit = (array_key_exists('modeEdit',$_GET)) ? $_GET['modeEdit'] : null ;

$modeIndex = ( empty($modeCreate) && empty($modeEdit) ) ? 'true' : null ;

$property = $db->getFirstForId('properties',$property_id);

$list_property = $db->getListProperties("properties");

$itemArrSeleted = ($id) ? ($db->getFirstForId("maintenances",$id) ?: Array()) : Array();

ob_start(); ?>

    <div class="container-fluid text-start">

        <div class="float-end">
            <a name="register" id="register" class="btn btn-primary" href="./maintenances.php?modeCreate=true" role="button">Registrar</a>
        </div><br>

        <div class="text-center">
            <h2 class="text-center">Listado de Mantenimientos</h2>
            <p class="">Registro, actualización y finalización!</p>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="row">

                    <div class="<?php $class = ($modeIndex=="true") ? "col-12" : "col-sm-6" ; echo $class; ?>" >
                        <?php include __DIR__ . '/maintenances/table/crud.php'; ?>
                    </div>

                    <?php if ($modeCreate=="true") {  ?>
                        <div class="col-sm-6" style="z-index: 1000;">
                            <div class="bg-white shadow-lg border rounded">
                                <h3 class="alert alert-secondary text-center">
                                    <div class="d-flex justify-content-between">
                                    <div class="">Registro de Mantenimiento</div>
                                    <div>
                                        <a class="btn-close" href="./maintenances.php" role="button" aria-label="Close"></a>
                                    </div>
                                </h3>
                                <form action="<?= e(url('maintenances/register.php')) ?>" method="POST">
                                    <?= csrf_field() ?>
                                    <?php include __DIR__ . '/maintenances/form/fields.php'; ?>
                                    <footer class="mt-auto">
                                        <input type="submit" name="register" value="Registrar" class="btn btn-primary w-100">
                                    </footer>
                                </form>
                            </div>
                        </div>
                    <?php }; ?>

                    <?php if ($modeEdit=="true") {  ?>
                        <div class="col-sm-6" style="z-index: 1000;">
                            <div class="bg-white shadow-lg border rounded">
                                <h3 class="alert alert-secondary text-center">
                                    <div class="d-flex justify-content-between">
                                    <div class="">Actualizar Mantenimiento</div>
                                    <div>
                                        <a class="btn-close" href="./maintenances.php" role="button" aria-label="Close"></a>
                                    </div>
                                </h3>
                                <form action="<?= e(url('maintenances/update.php')) ?>" method="POST">
                                    <?= csrf_field() ?>
                                    <?php include __DIR__ . '/maintenances/form/edit.php'; ?>
                                    <footer class="mt-auto">
                                        <input type="submit" name="update" value="Actualizar" class="btn btn-warning w-100">
                                    </footer>
                                </form>
                            </div>
                        </div>
                    <?php }; ?>

                    </div>
            </div>
        </div>

    </div>

    <script>
        const search = <?= js(isset($_GET['search']) ? $_GET['search'] : '') ?>;

        const table = new DataTable('#myTable', {
            search: {
                search: search ?? ''
            },
        });

        table.on('search', () => {
            const url = new URL(window.location.href);
            const params = new URLSearchParams(url);

            params.set('search', table.search())

            const newUrl = window.location.pathname + '?' + params.toString();
            history.replaceState(null, '', newUrl);
        })

        // Fase 2.1 — Modal de detalle (Razón y Notas) + tooltips
        const detalleModal = new bootstrap.Modal('#detalleModal');

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-detail');
            if (!btn) return;

            const d = JSON.parse(btn.dataset.detail);
            document.getElementById('detalleCode').textContent = d.code;
            document.getElementById('detalleReason').textContent = d.reason;
            document.getElementById('detalleNotes').textContent = d.notes;
            detalleModal.show();
        });

        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

        // Fase 2.2 — Filtros por columna y "solo vencidos"
        // Filtro global personalizado: solo filas marcadas como vencidas por el servidor
        $.fn.dataTable.ext.search.push((settings, data, dataIndex) => {
            if (!table.filterVencidos) return true;
            const node = table.row(dataIndex).node();
            return !node || node.classList.contains('js-vencido');
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
            const venc = document.getElementById('soloVencidos');
            if (venc.checked) { venc.checked = false; table.filterVencidos = false; }
            table.search('').columns().search('').draw();
        });
    </script>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
