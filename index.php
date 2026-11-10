<?php

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_login();

$user = auth_user();
$title = 'Inicio';
$targetPage = is_array($user) && isset($user['role']) && $user['role'] === 'personal' ? 'tickets.php' : 'dashboard.php';

ob_start(); ?>

<div class="container mx-auto">
    <h1>Gestión de los Mantenimiento realizados a los Bienes de la institución</h1>
    <p class="lead">
        El Sistema de Gestión de los Mantenimiento  (GMB) es un sistema informático que permite administrar una base de datos de bienes de una institución,
        así como llevar registro de todos los mantenimientos realizados a cada bien. El sistema también genera informes técnicos por cada bien, en los que se muestra
        la información de todos los mantenimientos realizados.
    </p>
    <p class="lead">
        <a href="<?= e(url($targetPage)) ?>" class="btn btn-lg btn-light fw-bold border-white bg-white">
            <?= $targetPage === 'tickets.php' ? 'Ir a Tickets' : 'Ir al Dashboard' ?>
        </a>
    </p>
</div>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
