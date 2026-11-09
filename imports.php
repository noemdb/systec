<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/csv_import.php';

require_role(array('admin', 'operador'));

$title = 'Importar';
$filenameCSV = SYSTEC_STORAGE . '/maintenances.csv';
$confirmed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $confirmed = true;
}

ob_start(); ?>

    <div class="container-fluid text-start">
        <div class="text-center">
            <h2 class="text-center">Importar mantenimientos</h2>
            <p class="text-muted">Archivo: <?= e(basename($filenameCSV)) ?></p>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (!$confirmed) : ?>
                    <div class="alert alert-warning">
                        <strong>Atención:</strong> esta operación escribe en la base de datos y no se puede deshacer automáticamente.
                        Verifica que exista un backup antes de continuar.
                    </div>
                    <form method="post" action="<?= e(url('imports.php')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger">Confirmar importación</button>
                        <a href="<?= e(url('maintenances.php')) ?>" class="btn btn-secondary">Cancelar</a>
                    </form>
                <?php else : ?>
                    <?php
                    try {
                        $db = db();
                        echo execute_csv_import($db, $filenameCSV, ',', function ($handle) {
                            define('SYSTEC_IMPORT_CONTEXT', true);
                            include __DIR__ . '/maintenances/table/import.php';
                        });
                    } catch (CsvImportException $e) {
                        echo '<div class="alert alert-danger">' . e($e->getMessage()) . '</div>';
                    } catch (Throwable $e) {
                        error_log('Error en importación de mantenimientos: ' . $e->getMessage());
                        echo '<div class="alert alert-danger">La importación falló y no se guardaron cambios.</div>';
                    }
                    ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
