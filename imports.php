<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/csv_import.php';

require_role(array('admin', 'operador'));

$title = 'Importar mantenimientos';
$db = db();
$currentUser = auth_user();
$actorUsername = is_array($currentUser) && isset($currentUser['username']) ? (string) $currentUser['username'] : 'usuario';
$serverCsvPath = SYSTEC_STORAGE . '/maintenances.csv';
$serverCsvExists = is_file($serverCsvPath) && is_readable($serverCsvPath);
$serverCsvSize = $serverCsvExists ? (int) filesize($serverCsvPath) : 0;
$serverCsvMtime = $serverCsvExists ? filemtime($serverCsvPath) : false;

$confirmed = false;
$uploadError = '';
$importSource = $serverCsvExists ? 'server' : 'upload';
$targetCsvPath = '';
$targetCsvLabel = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $importSource = isset($_POST['import_source']) && is_string($_POST['import_source'])
        ? $_POST['import_source']
        : 'server';

    if (!isset($_POST['confirm_import']) || $_POST['confirm_import'] !== '1') {
        $uploadError = 'Confirma que deseas ejecutar la importación de mantenimientos.';
    } elseif ($importSource === 'server') {
        if (!$serverCsvExists) {
            $uploadError = 'El archivo storage/maintenances.csv no existe o no es legible en el servidor.';
        } else {
            $targetCsvPath = $serverCsvPath;
            $targetCsvLabel = basename($serverCsvPath) . ' (servidor)';
            $confirmed = true;
        }
    } elseif ($importSource === 'upload') {
        if (!isset($_FILES['maintenances_csv']) || !is_array($_FILES['maintenances_csv'])) {
            $uploadError = 'Selecciona un archivo CSV de mantenimientos.';
        } else {
            $upload = $_FILES['maintenances_csv'];
            $errorCode = isset($upload['error']) && is_int($upload['error']) ? $upload['error'] : UPLOAD_ERR_NO_FILE;
            if ($errorCode !== UPLOAD_ERR_OK) {
                $uploadError = ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE)
                    ? 'El archivo supera el límite permitido (máximo 5 MB).'
                    : 'No se pudo cargar el archivo CSV seleccionado.';
            } else {
                $tmpPath = isset($upload['tmp_name']) && is_string($upload['tmp_name']) ? $upload['tmp_name'] : '';
                $origName = isset($upload['name']) && is_string($upload['name']) ? basename($upload['name']) : '';
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
                    $uploadError = 'No se pudo validar el archivo recibido. Vuelve a seleccionarlo.';
                } elseif ($ext !== 'csv') {
                    $uploadError = 'El archivo debe tener extensión .csv.';
                } elseif ((int) ($upload['size'] ?? 0) > 5 * 1024 * 1024) {
                    $uploadError = 'El archivo supera el límite de 5 MB.';
                } else {
                    $targetCsvPath = $tmpPath;
                    $targetCsvLabel = $origName;
                    $confirmed = true;
                }
            }
        }
    } else {
        $uploadError = 'Selecciona un origen de importación válido.';
    }
}

$importOutputHtml = '';
$importExecutionError = '';
if ($confirmed) {
    try {
        $importOutputHtml = execute_csv_import($db, $targetCsvPath, ',', function ($handle) {
            if (!defined('SYSTEC_IMPORT_CONTEXT')) {
                define('SYSTEC_IMPORT_CONTEXT', true);
            }
            include __DIR__ . '/maintenances/table/import.php';
        });
        error_log("Auditoría mantenimientos: '{$actorUsername}' importó lote desde '{$targetCsvLabel}'.");
    } catch (CsvImportException $e) {
        $importExecutionError = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Error en importación de mantenimientos: ' . $e->getMessage());
        $importExecutionError = 'La importación falló y no se guardaron cambios.';
    }
}

$maintenanceCounts = $db->maintenances()->dashboardSummaryCounts();
$propertySummary = $db->properties()->statusSummaryCounts();

ob_start(); ?>

    <div class="container-fluid text-start">
        <section class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3"
                 aria-labelledby="imports-page-title">
            <div>
                <h1 class="h3 mb-1" id="imports-page-title">Importar mantenimientos</h1>
                <p class="text-body-secondary mb-0">
                    Carga mantenimientos históricos en lote desde un CSV separado por comas.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-self-md-start flex-shrink-0">
                <a href="<?= e(url('maintenances.php')) ?>" class="btn btn-outline-primary">
                    <i class="bi bi-tools me-1" aria-hidden="true"></i>Ir a Mantenimientos
                </a>
            </div>
        </section>

        <section class="row g-3 mb-4" aria-label="Contexto actual antes de importar">
            <div class="col-12 col-md-4">
                <article class="card h-100 border-start border-4 border-primary shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-primary-subtle text-primary p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-tools fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-body-secondary mb-1">Mantenimientos registrados</p>
                            <p class="h3 mb-0" role="status" aria-live="polite"><?= e((string) $maintenanceCounts['total']) ?></p>
                        </div>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-4">
                <article class="card h-100 border-start border-4 border-info shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-info-subtle text-info p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-box-seam fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-body-secondary mb-1">Bienes disponibles para vincular</p>
                            <p class="h3 mb-0"><?= e((string) $propertySummary['total']) ?></p>
                        </div>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-4">
                <article class="card h-100 border-start border-4 border-<?= $serverCsvExists ? 'success' : 'secondary' ?> shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-<?= $serverCsvExists ? 'success' : 'secondary' ?>-subtle text-<?= $serverCsvExists ? 'success' : 'secondary' ?> p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-filetype-csv fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-body-secondary mb-1">Archivo en servidor</p>
                            <p class="h6 mb-0"><?= $serverCsvExists ? 'maintenances.csv disponible' : 'No encontrado' ?></p>
                            <p class="small text-body-secondary mb-0">
                                <?php if ($serverCsvExists) : ?>
                                    <?= e(number_format($serverCsvSize / 1024, 1)) ?> KB<?= $serverCsvMtime !== false ? ' · ' . e(date('d/m/Y H:i', $serverCsvMtime)) : '' ?>
                                <?php else : ?>
                                    Puedes subir un archivo CSV desde tu equipo
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section class="card shadow-sm border-0" aria-labelledby="imports-card-title">
            <div class="card-header">
                <h2 class="h5 mb-0" id="imports-card-title">
                    <i class="bi bi-calendar2-plus text-primary me-2" aria-hidden="true"></i>
                    <?= $confirmed ? 'Resultado de la importación' : 'Configurar importación de mantenimientos' ?>
                </h2>
            </div>
            <div class="card-body">
                <?php if (!$confirmed) : ?>
                    <div class="alert alert-warning">
                        <strong>Atención:</strong> esta operación inserta registros en la base de datos y no se puede deshacer automáticamente.
                        Verifica que exista un backup antes de continuar.
                    </div>

                    <?php if ($uploadError !== '') : ?>
                        <div class="alert alert-danger" role="alert"><?= e($uploadError) ?></div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url('imports.php')) ?>" enctype="multipart/form-data" id="maintenancesImportForm">
                        <?= csrf_field() ?>
                        <fieldset class="mb-3">
                            <legend class="fs-6 fw-semibold mb-2">1. Selecciona el origen del archivo CSV</legend>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check border rounded p-3 h-100">
                                        <input class="form-check-input" type="radio" name="import_source"
                                               id="maintSourceUpload" value="upload"
                                               <?= $importSource === 'upload' ? 'checked' : '' ?>>
                                        <label class="form-check-label d-block" for="maintSourceUpload">
                                            <strong>Subir archivo CSV desde tu equipo</strong>
                                            <span class="d-block small text-body-secondary">
                                                Archivo .csv separado por comas (máximo 5 MB).
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check border rounded p-3 h-100">
                                        <input class="form-check-input" type="radio" name="import_source"
                                               id="maintSourceServer" value="server"
                                               <?= $importSource === 'server' ? 'checked' : '' ?>
                                               <?= !$serverCsvExists ? 'disabled' : '' ?>>
                                        <label class="form-check-label d-block" for="maintSourceServer">
                                            <strong>Archivo en servidor (storage/maintenances.csv)</strong>
                                            <span class="d-block small text-body-secondary">
                                                <?= $serverCsvExists ? 'Disponible para importación directa.' : 'No disponible actualmente en storage/.' ?>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <div id="maintUploadField" class="mb-3" <?= $importSource !== 'upload' ? 'hidden' : '' ?>>
                            <label for="maintenances_csv" class="form-label fw-semibold">2. Archivo CSV de mantenimientos</label>
                            <input type="file" class="form-control" id="maintenances_csv" name="maintenances_csv"
                                   accept=".csv,text/csv" <?= $importSource === 'upload' ? 'required' : 'disabled' ?>>
                            <div class="form-text">
                                Columnas esperadas: código del bien, tipo, descripción, técnico, (reservado), razón de falla, fecha, notas.
                            </div>
                        </div>

                        <div class="border-top pt-3 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirmMaintImport"
                                       name="confirm_import" value="1" required>
                                <label class="form-check-label" for="confirmMaintImport">
                                    Confirmo la importación por lote y que verifiqué el backup.
                                </label>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-danger" id="maintImportSubmit">
                                <i class="bi bi-upload me-1" id="maintImportSubmitIcon" aria-hidden="true"></i>
                                <span id="maintImportSubmitLabel">Confirmar importación</span>
                            </button>
                            <a href="<?= e(url('maintenances.php')) ?>" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                <?php else : ?>
                    <p class="text-body-secondary">
                        Origen procesado: <strong><?= e($targetCsvLabel) ?></strong>
                    </p>
                    <?php if ($importExecutionError !== '') : ?>
                        <div class="alert alert-danger" role="alert"><?= e($importExecutionError) ?></div>
                    <?php else : ?>
                        <?= $importOutputHtml ?>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="<?= e(url('maintenances.php')) ?>" class="btn btn-primary">
                            <i class="bi bi-tools me-1" aria-hidden="true"></i>Ir a Mantenimientos
                        </a>
                        <a href="<?= e(url('imports.php')) ?>" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Realizar otra importación
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script nonce="<?= e(systec_csp_nonce()) ?>">
        (function () {
            const radios = document.querySelectorAll('input[name="import_source"]');
            const uploadField = document.getElementById('maintUploadField');
            const fileInput = document.getElementById('maintenances_csv');
            const form = document.getElementById('maintenancesImportForm');
            const submitButton = document.getElementById('maintImportSubmit');
            const submitIcon = document.getElementById('maintImportSubmitIcon');
            const submitLabel = document.getElementById('maintImportSubmitLabel');

            if (radios.length && uploadField && fileInput) {
                const syncSource = () => {
                    const selected = document.querySelector('input[name="import_source"]:checked');
                    const isUpload = !selected || selected.value === 'upload';
                    uploadField.hidden = !isUpload;
                    fileInput.disabled = !isUpload;
                    fileInput.required = isUpload;
                };
                radios.forEach((r) => r.addEventListener('change', syncSource));
                syncSource();
            }

            if (form && submitButton) {
                form.addEventListener('submit', () => {
                    submitButton.disabled = true;
                    if (submitIcon) submitIcon.className = 'spinner-border spinner-border-sm me-2';
                    if (submitLabel) submitLabel.textContent = 'Importando…';
                });
            }
        })();
    </script>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
