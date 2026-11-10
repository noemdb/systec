<?php

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_login();

$title = 'Historial';
$db = db();

$property_id = isset($_GET['property_id']) ? filter_var($_GET['property_id'], FILTER_VALIDATE_INT) : false;
$property = ($property_id !== false && $property_id !== null) ? $db->getFirstForId('properties', $property_id) : null;
$maintenances = $property ? $db->index('maintenances', array('property_id' => $property_id), '*', 'date ASC') : array();
$technician = '';

if (isset($_GET['embed']) && $_GET['embed'] === '1') {
    if (!$property) {
        echo '<div class="alert alert-warning mb-0" role="alert">No se encontró el bien solicitado.</div>';
        exit();
    }

    echo '<div class="container-fluid p-3">';
    include __DIR__ . '/historials/membrete.php';
    echo '<hr class="my-2">';
    include __DIR__ . '/historials/main.php';
    echo '</div>';
    exit();
}

$no_footer = true;
$header_file = __DIR__ . '/historials/membrete.php';

ob_start(); ?>

    <div class="container-fluid">
        <h4>Historial de mantenimiento</h4>
        <?php if (!$property) : ?>
            <div class="alert alert-warning">No se encontró el bien solicitado.</div>
        <?php else : ?>
            <?php include __DIR__ . '/historials/main.php'; ?>
        <?php endif; ?>
    </div>

    <div class="container-fluid text-center">
        <div class="my-0 py-0">____________________________________________</div>
        <div class="my-0 py-0">Realizado por: <span class="text-uppercase"><?= e($technician) ?></span></div>
        <div class="my-0 py-0"><?= e(date('d-m-Y H:i')) ?></div>
    </div>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
