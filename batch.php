<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_login();

$title = 'Historial - Batch';
$db = db();
?>
<html lang="es" class="h-100" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/include/head.php'; ?>

    <style nonce="<?= e(systec_csp_nonce()) ?>">
        html { margin: 0.8rem; text-transform: uppercase }
    </style>

    <style type="text/css" media="print" nonce="<?= e(systec_csp_nonce()) ?>">
        html { margin: 0.8cm; text-transform: uppercase }
        body { text-transform: uppercase; }
    </style>
</head>

<body class="d-flex h-100 text-center shadow-none">

    <div class="container-fluid">
        <div class="row">

            <div class="col-6">
                <?php
                $property_id = isset($_GET['property_id']) ? filter_var($_GET['property_id'], FILTER_VALIDATE_INT) : false;
                $property = ($property_id !== false && $property_id !== null) ? $db->getFirstForId('properties', $property_id) : null;
                $maintenances = $property ? $db->index('maintenances', array('property_id' => $property_id), '*', 'date ASC') : array();
                $technician = '';
                ?>

                <div class="d-flex w-100 h-100 mx-auto flex-column">
                    <header class="bg-secondary-subtle border-bottom shadow-sm py-1 px-2">
                        <?php include __DIR__ . '/historials/membrete.php'; ?>
                    </header>

                    <div>
                        <?php if ($property) : ?>
                            <?php include __DIR__ . '/historials/main.php'; ?>
                        <?php else : ?>
                            <div class="alert alert-warning m-3">Bien no encontrado.</div>
                        <?php endif; ?>
                    </div>

                    <div class="container-fluid text-center" style="font-size:0.8rem">
                        <div class="my-0 py-0">____________________________________________</div>
                        <div class="my-0 py-0">Realizado por: <span class="text-uppercase"><?= e($technician) ?></span></div>
                        <div class="my-0 py-0"><?= e(date('d/m/Y H:i')) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-6">
                <?php
                $property_id = isset($_GET['property_id2']) ? filter_var($_GET['property_id2'], FILTER_VALIDATE_INT) : false;
                $property = ($property_id !== false && $property_id !== null) ? $db->getFirstForId('properties', $property_id) : null;
                $maintenances = $property ? $db->index('maintenances', array('property_id' => $property_id), '*', 'date ASC') : array();
                $technician = '';
                ?>

                <div class="d-flex w-100 h-100 mx-auto flex-column">
                    <header class="bg-secondary-subtle border-bottom shadow-sm py-1 px-2">
                        <?php include __DIR__ . '/historials/membrete.php'; ?>
                    </header>

                    <div>
                        <?php if ($property) : ?>
                            <?php include __DIR__ . '/historials/main.php'; ?>
                        <?php else : ?>
                            <div class="alert alert-warning m-3">Bien no encontrado.</div>
                        <?php endif; ?>
                    </div>

                    <div class="container-fluid text-center" style="font-size:0.8rem">
                        <div class="my-0 py-0">____________________________________________</div>
                        <div class="my-0 py-0">Realizado por: <span class="text-uppercase"><?= e($technician) ?></span></div>
                        <div class="my-0 py-0"><?= e(date('d/m/Y H:i')) ?></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include __DIR__ . '/include/script.php'; ?>
</body>
</html>
