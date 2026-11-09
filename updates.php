<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/inventory_import.php';

require_role(array('admin', 'operador'));

$title = 'Actualizar inventario';
$confirmed = false;
$uploadError = '';
$filenameCSV = '';
$uploadedName = '';
$uploadedExtension = '';
$importSource = 'file';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $importSource = isset($_POST['import_source']) && is_string($_POST['import_source'])
        ? $_POST['import_source']
        : '';

    if (!isset($_POST['confirm_import']) || $_POST['confirm_import'] !== '1') {
        $uploadError = 'Confirma que deseas importar el inventario.';
    } elseif ($importSource === 'legacy_postgres') {
        $confirmed = true;
    } elseif ($importSource !== 'file') {
        $uploadError = 'Selecciona un origen de importación válido.';
    } elseif (!isset($_FILES['properties_csv']) || !is_array($_FILES['properties_csv'])) {
        $uploadError = 'Selecciona un CSV o el reporte XLS de inventario.';
    } else {
        $upload = $_FILES['properties_csv'];
        $uploadErrorCode = isset($upload['error']) && is_int($upload['error'])
            ? $upload['error']
            : UPLOAD_ERR_NO_FILE;

        if ($uploadErrorCode !== UPLOAD_ERR_OK) {
            switch ($uploadErrorCode) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $uploadError = 'El archivo supera el límite de carga del servidor; SysTec admite hasta 5 MB.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $uploadError = 'La carga del archivo quedó incompleta. Inténtalo de nuevo.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $uploadError = 'Selecciona un CSV o el reporte XLS de inventario.';
                    break;
                default:
                    $uploadError = 'No se pudo cargar el archivo. Verifica la configuración de carga del servidor.';
                    break;
            }
        } else {
            $filenameCSV = isset($upload['tmp_name']) && is_string($upload['tmp_name'])
                ? $upload['tmp_name']
                : '';
            $uploadedName = isset($upload['name']) && is_string($upload['name'])
                ? basename($upload['name'])
                : '';
            $uploadedExtension = strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION));

            if ($filenameCSV === '' || !is_uploaded_file($filenameCSV)) {
                $uploadError = 'No se pudo validar el archivo recibido. Vuelve a seleccionarlo.';
            } elseif (!in_array($uploadedExtension, array('csv', 'xls'), true)) {
                $uploadError = 'El archivo debe tener extensión .csv o .xls.';
            } else {
                $confirmed = true;
            }
        }
    }
}

ob_start(); ?>

    <style>
        .inventory-source-card {
            cursor: pointer;
            border: 1px solid var(--bs-border-color);
            transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
        }

        .inventory-source-choice:checked + .inventory-source-card {
            border-color: var(--bs-primary);
            background-color: var(--bs-primary-bg-subtle);
            box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
        }

        .inventory-source-choice:focus-visible + .inventory-source-card {
            outline: 3px solid rgba(var(--bs-primary-rgb), .35);
            outline-offset: 2px;
        }

        .inventory-source-icon {
            display: grid;
            width: 3rem;
            height: 3rem;
            flex: 0 0 3rem;
            place-items: center;
            border-radius: .75rem;
            background: var(--bs-body-bg);
            color: var(--bs-primary);
            font-size: 1.4rem;
        }

        .inventory-source-panel {
            border-left: .3rem solid var(--bs-primary) !important;
            background: var(--bs-tertiary-bg);
        }

        .inventory-source-panel-postgres {
            border-left-color: var(--bs-info) !important;
        }
    </style>

    <div class="container-fluid text-start">
        <div class="text-center mb-3">
            <h2>Actualizar inventario</h2>
            <p class="text-muted mb-0">Importa bienes desde un archivo o directamente desde el sistema de bienes.</p>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (!$confirmed) : ?>
                    <div class="alert alert-warning">
                        <strong>Antes de importar:</strong> los bienes existentes solo actualizarán su estado y los nuevos podrán insertarse.
                        Verifica que exista un backup. Todos los orígenes están limitados a 5000 bienes; los archivos CSV/XLS tienen
                        además un límite de 5 MB.
                    </div>

                    <?php if ($uploadError !== '') : ?>
                        <div class="alert alert-danger" role="alert"><?= e($uploadError) ?></div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url('updates.php')) ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <fieldset class="mb-4">
                            <legend class="fs-6 fw-semibold mb-1">1. Selecciona el origen de los bienes</legend>
                            <p class="form-text mt-0 mb-3">Elige una fuente. El control de carga aparecerá solo para CSV/XLS.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input class="btn-check inventory-source-choice" type="radio" id="import_source_file"
                                           name="import_source" value="file" required
                                           <?= $importSource === 'file' ? 'checked' : '' ?>>
                                    <label class="card inventory-source-card h-100" for="import_source_file">
                                        <span class="card-body d-flex gap-3 align-items-start">
                                            <span class="inventory-source-icon" aria-hidden="true">
                                                <i class="bi bi-file-earmark-spreadsheet"></i>
                                            </span>
                                            <span>
                                                <span class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                                    <strong>Archivo CSV o XLS</strong>
                                                    <span class="badge text-bg-primary">Desde tu equipo</span>
                                                </span>
                                                <span class="small text-muted d-block">
                                                    Usa el CSV separado por punto y coma o el reporte descargado del sistema de bienes.
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <input class="btn-check inventory-source-choice" type="radio" id="import_source_postgres"
                                           name="import_source" value="legacy_postgres" required
                                           <?= $importSource === 'legacy_postgres' ? 'checked' : '' ?>>
                                    <label class="card inventory-source-card h-100" for="import_source_postgres">
                                        <span class="card-body d-flex gap-3 align-items-start">
                                            <span class="inventory-source-icon" aria-hidden="true">
                                                <i class="bi bi-database"></i>
                                            </span>
                                            <span>
                                                <span class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                                    <strong>Base PostgreSQL</strong>
                                                    <span class="badge text-bg-info">Solo lectura</span>
                                                </span>
                                                <span class="small text-muted d-block">
                                                    Consulta directamente los bienes vigentes del sistema de gestión.
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </fieldset>

                        <div id="inventory-file-source" class="card inventory-source-panel mb-4">
                            <div class="card-body">
                                <label for="properties_csv" class="form-label fw-semibold">
                                    2. Selecciona el archivo de inventario
                                </label>
                                <input type="file" class="form-control" id="properties_csv" name="properties_csv"
                                       accept=".csv,.xls,text/csv,application/vnd.ms-excel">
                                <div class="form-text">
                                    Formatos admitidos: .csv o .xls. Máximo 5 MB y 5000 bienes.
                                </div>
                            </div>
                        </div>
                        <div id="inventory-postgres-source" class="card inventory-source-panel inventory-source-panel-postgres mb-4" hidden>
                            <div class="card-body d-flex gap-3 align-items-start">
                                <span class="inventory-source-icon text-info" aria-hidden="true">
                                    <i class="bi bi-shield-lock"></i>
                                </span>
                                <div>
                                    <h3 class="h6 mb-1">Conexión de solo lectura</h3>
                                    <p class="mb-0 small text-muted">
                                        Se consultarán los bienes no desincorporados. La base PostgreSQL legacy no se modifica;
                                        solo se guardarán los resultados en SysTec.
                                    </p>
                                    <span class="badge text-bg-info mt-2">Límite: 5000 bienes</span>
                                </div>
                            </div>
                        </div>

                        <div class="border-top pt-3 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirm_import"
                                       name="confirm_import" value="1" required>
                                <label class="form-check-label" for="confirm_import">
                                    Confirmo la importación y que verifiqué el backup.
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1" aria-hidden="true"></i>Iniciar importación
                        </button>
                        <a href="<?= e(url('dashboard.php')) ?>" class="btn btn-outline-secondary">Cancelar</a>
                    </form>
                <?php else : ?>
                    <p class="text-muted">
                        Origen:
                        <strong><?= $importSource === 'legacy_postgres' ? 'PostgreSQL del sistema de bienes (solo lectura)' : e($uploadedName) ?></strong>
                    </p>
                    <?php
                    try {
                        $db = db();
                        $processor = function ($rows) {
                            $inventoryImportRows = $rows;
                            define('SYSTEC_IMPORT_CONTEXT', true);
                            include __DIR__ . '/properties/table/propertiesCsv.php';
                        };

                        if ($importSource === 'legacy_postgres') {
                            echo execute_legacy_postgres_inventory_import($db, $processor);
                        } else {
                            echo execute_inventory_import($db, $filenameCSV, $uploadedExtension, $processor);
                        }
                    } catch (CsvImportException $e) {
                        echo '<div class="alert alert-danger">' . e($e->getMessage()) . '</div>';
                    } catch (Throwable $e) {
                        error_log('Error en actualización de inventario: ' . $e->getMessage());
                        echo '<div class="alert alert-danger">La actualización falló y no se guardaron cambios.</div>';
                    }
                    ?>
                    <a href="<?= e(url('updates.php')) ?>" class="btn btn-outline-primary mt-3">
                        Realizar otra importación
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var sourceChoices = document.querySelectorAll('input[name="import_source"]');
            var fileSection = document.getElementById('inventory-file-source');
            var postgresSection = document.getElementById('inventory-postgres-source');
            var fileInput = document.getElementById('properties_csv');

            if (!sourceChoices.length || !fileSection || !postgresSection || !fileInput) {
                return;
            }

            function updateImportSource() {
                var selectedSource = document.querySelector('input[name="import_source"]:checked');
                var usesFile = !selectedSource || selectedSource.value === 'file';
                fileSection.hidden = !usesFile;
                postgresSection.hidden = usesFile;
                fileInput.required = usesFile;
                fileInput.disabled = !usesFile;
            }

            sourceChoices.forEach(function (choice) {
                choice.addEventListener('change', updateImportSource);
            });
            updateImportSource();
        })();
    </script>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
