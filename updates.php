<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/inventory_import.php';

require_role(array('admin', 'operador'));

$title = 'Actualizar inventario';
$db = db();
$currentUser = auth_user();
$actorUsername = is_array($currentUser) && isset($currentUser['username']) ? (string) $currentUser['username'] : 'usuario';
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

$importOutputHtml = '';
$importExecutionError = '';
if ($confirmed) {
    try {
        $preserveExistingStatus = $importSource === 'legacy_postgres';
        $processor = function ($rows) use ($preserveExistingStatus) {
            $inventoryImportRows = $rows;
            if (!defined('SYSTEC_IMPORT_CONTEXT')) {
                define('SYSTEC_IMPORT_CONTEXT', true);
            }
            include __DIR__ . '/properties/table/propertiesCsv.php';
        };

        if ($importSource === 'legacy_postgres') {
            $importOutputHtml = execute_legacy_postgres_inventory_import($db, $processor);
            error_log("Auditoría inventario: '{$actorUsername}' ejecutó importación de bienes desde PostgreSQL legacy.");
        } else {
            $importOutputHtml = execute_inventory_import($db, $filenameCSV, $uploadedExtension, $processor);
            error_log("Auditoría inventario: '{$actorUsername}' ejecutó importación de bienes desde archivo '{$uploadedName}'.");
        }
    } catch (CsvImportException $e) {
        $importExecutionError = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Error en actualización de inventario: ' . $e->getMessage());
        $importExecutionError = 'La actualización falló y no se guardaron cambios.';
    }
}

$propertySummary = $db->properties()->statusSummaryCounts();
$localCache = legacy_postgres_read_local_cache();
$legacySyncAvailable = !empty($localCache['found']);
$legacySyncStale = !empty($localCache['stale']);
$legacyAdscriptionCount = isset($localCache['adscriptions']) && is_array($localCache['adscriptions'])
    ? count($localCache['adscriptions'])
    : 0;
$legacyAssignmentCount = isset($localCache['assignments']) && is_array($localCache['assignments'])
    ? count($localCache['assignments'])
    : 0;
$legacySyncRelativeAge = format_relative_age(
    isset($localCache['age_seconds']) && is_int($localCache['age_seconds']) ? $localCache['age_seconds'] : null
);
$syncColor = !$legacySyncAvailable ? 'secondary' : ($legacySyncStale ? 'warning' : 'success');

// Aviso cuando la importación institucional no pudo persistir su caché local (p. ej. permisos).
$importCacheWarning = '';
if ($confirmed && $importExecutionError === '' && $importSource === 'legacy_postgres'
    && (!$legacySyncAvailable || $legacySyncStale)) {
    $importCacheWarning = !empty($localCache['unreadable'])
        ? 'El inventario se actualizó, pero el servidor web no puede leer storage/cache; el filtro «Unidad de adscripción» seguirá usando los datos locales hasta que se corrijan los permisos.'
        : 'El inventario se actualizó, pero no se pudo guardar la caché institucional de adscripciones. Revisa los permisos de storage/cache y vuelve a sincronizar.';
}

ob_start(); ?>

    <style nonce="<?= e(systec_csp_nonce()) ?>">
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

        .card.inventory-source-panel {
            border-left: .3rem solid var(--bs-primary);
            background: var(--bs-tertiary-bg);
        }

        .card.inventory-source-panel-postgres {
            border-left-color: var(--bs-info);
        }
    </style>

    <div class="container-fluid text-start">
        <section class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3"
                 aria-labelledby="updates-page-title">
            <div>
                <h1 class="h3 mb-1" id="updates-page-title">Actualizar inventario</h1>
                <p class="text-body-secondary mb-0">Importa bienes desde un archivo CSV/XLS o directamente desde PostgreSQL.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-self-md-start flex-shrink-0">
                <a href="<?= e(url('sync-assignments.php')) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-people me-1" aria-hidden="true"></i>Sincronizar asignaciones
                </a>
                <a href="<?= e(url('dashboard.php')) ?>" class="btn btn-outline-primary">
                    <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Ir al Dashboard
                </a>
            </div>
        </section>

        <section class="row g-3 mb-4" aria-label="Estado actual del inventario">
            <div class="col-6 col-md-3">
                <article class="card h-100 border-start border-4 border-primary shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-primary-subtle text-primary p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-box-seam fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-body-secondary mb-1">Bienes en SysTec</p>
                            <p class="h3 mb-0" role="status" aria-live="polite"><?= e((string) $propertySummary['total']) ?></p>
                        </div>
                    </div>
                </article>
            </div>
            <div class="col-6 col-md-3">
                <article class="card h-100 border-start border-4 border-info shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-info-subtle text-info p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-building fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-body-secondary mb-1">Adscripciones en caché</p>
                            <p class="h3 mb-0"><?= e((string) $legacyAdscriptionCount) ?></p>
                        </div>
                    </div>
                </article>
            </div>
            <div class="col-6 col-md-3">
                <article class="card h-100 border-start border-4 border-secondary shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-secondary-subtle text-secondary p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-people fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-body-secondary mb-1">Asignaciones en caché</p>
                            <p class="h3 mb-0"><?= e((string) $legacyAssignmentCount) ?></p>
                        </div>
                    </div>
                </article>
            </div>
            <div class="col-6 col-md-3">
                <article class="card h-100 border-start border-4 border-<?= e($syncColor) ?> shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-<?= e($syncColor) ?>-subtle text-<?= e($syncColor) ?> p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-clock-history fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-body-secondary mb-1">Caché institucional</p>
                            <p class="h6 mb-0">
                                <?= !$legacySyncAvailable ? 'Sin caché' : ($legacySyncStale ? 'Desactualizado' : 'Sincronizado') ?>
                            </p>
                            <p class="small text-body-secondary mb-0"><?= e($legacySyncRelativeAge) ?></p>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section class="card shadow-sm border-0" aria-labelledby="updates-form-title">
            <div class="card-header">
                <h2 class="h5 mb-0" id="updates-form-title">
                    <i class="bi bi-box-arrow-in-down text-primary me-2" aria-hidden="true"></i>
                    <?= $confirmed ? 'Resultado de la importación' : 'Configurar importación de bienes' ?>
                </h2>
            </div>
            <div class="card-body">
                <?php if (!$confirmed) : ?>
                    <div class="alert alert-warning">
                        <strong>Antes de importar:</strong> al usar PostgreSQL, los bienes existentes conservan su estado actual en SysTec;
                        el estado de legacy solo se usa para los bienes nuevos. Los CSV/XLS mantienen la actualización de estado habitual.
                        Verifica que exista un backup. Todos los orígenes están limitados a 5000 bienes; los archivos CSV/XLS tienen
                        además un límite de 5 MB.
                    </div>

                    <?php if ($uploadError !== '') : ?>
                        <div class="alert alert-danger" role="alert"><?= e($uploadError) ?></div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url('updates.php')) ?>" enctype="multipart/form-data" id="inventoryImportForm">
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
                                                <span class="small text-body-secondary d-block">
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
                                                <span class="small text-body-secondary d-block">
                                                    Consulta los bienes vigentes y actualiza también adscripciones y asignaciones en caché local.
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
                                    <p class="mb-0 small text-body-secondary">
                                        Se consultarán los bienes no desincorporados. La base PostgreSQL legacy no se modifica;
                                        los bienes existentes conservarán su estado en SysTec y solo los bienes nuevos recibirán
                                        el estado indicado por legacy.
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
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary" id="inventoryImportSubmit">
                                <i class="bi bi-upload me-1" id="inventoryImportSubmitIcon" aria-hidden="true"></i>
                                <span id="inventoryImportSubmitLabel">Iniciar importación</span>
                            </button>
                            <a href="<?= e(url('dashboard.php')) ?>" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                <?php else : ?>
                    <p class="text-body-secondary">
                        Origen:
                        <strong><?= $importSource === 'legacy_postgres' ? 'PostgreSQL del sistema de bienes (solo lectura)' : e($uploadedName) ?></strong>
                    </p>
                    <?php if ($importExecutionError !== '') : ?>
                        <div class="alert alert-danger" role="alert"><?= e($importExecutionError) ?></div>
                    <?php else : ?>
                        <?= $importOutputHtml ?>
                        <?php if ($importCacheWarning !== '') : ?>
                            <div class="alert alert-warning mt-3 d-flex align-items-start gap-2" role="alert">
                                <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
                                <div><?= e($importCacheWarning) ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="<?= e(url('dashboard.php')) ?>" class="btn btn-primary">
                            <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Ir al Dashboard
                        </a>
                        <a href="<?= e(url('updates.php')) ?>" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Realizar otra importación
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script nonce="<?= e(systec_csp_nonce()) ?>">
        (function () {
            const sourceChoices = document.querySelectorAll('input[name="import_source"]');
            const fileSection = document.getElementById('inventory-file-source');
            const postgresSection = document.getElementById('inventory-postgres-source');
            const fileInput = document.getElementById('properties_csv');
            const importForm = document.getElementById('inventoryImportForm');
            const submitButton = document.getElementById('inventoryImportSubmit');
            const submitIcon = document.getElementById('inventoryImportSubmitIcon');
            const submitLabel = document.getElementById('inventoryImportSubmitLabel');

            if (sourceChoices.length && fileSection && postgresSection && fileInput) {
                const updateImportSource = () => {
                    const selectedSource = document.querySelector('input[name="import_source"]:checked');
                    const usesFile = !selectedSource || selectedSource.value === 'file';
                    fileSection.hidden = !usesFile;
                    postgresSection.hidden = usesFile;
                    fileInput.required = usesFile;
                    fileInput.disabled = !usesFile;
                };

                sourceChoices.forEach((choice) => {
                    choice.addEventListener('change', updateImportSource);
                });
                updateImportSource();
            }

            if (importForm && submitButton) {
                importForm.addEventListener('submit', () => {
                    submitButton.disabled = true;
                    if (submitIcon) submitIcon.className = 'spinner-border spinner-border-sm me-2';
                    if (submitLabel) submitLabel.textContent = 'Importando…';
                });
            }
        })();
    </script>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
