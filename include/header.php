<?php
require_once __DIR__ . '/auth.php';

$currentUser = auth_user();
$currentPage = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');

if (auth_has_role(array('personal'))) {
    $navigation = array(
        array('label' => 'Tickets', 'page' => 'personal/index.php', 'icon' => 'bi-ticket-detailed'),
    );
} else {
    $navigation = array(
        array('label' => 'Inicio', 'page' => 'index.php', 'icon' => 'bi-house-door'),
        array('label' => 'Dashboard', 'page' => 'dashboard.php', 'icon' => 'bi-speedometer2'),
        array('label' => 'Mantenimientos', 'page' => 'maintenances.php', 'icon' => 'bi-tools'),
        array('label' => 'Tickets', 'page' => 'tickets.php', 'icon' => 'bi-ticket-detailed'),
        array('label' => 'Dañados', 'page' => 'report.php', 'icon' => 'bi-exclamation-triangle'),
    );
}

$canImport = auth_has_role(array('admin', 'operador'));
$importPageActive = in_array($currentPage, array('updates.php', 'imports.php'), true);
?>
<div class="container-fluid px-0">
    <nav class="navbar navbar-expand-xl p-0" aria-label="Navegación principal">
        <a class="d-inline-flex align-items-center gap-2 text-decoration-none text-body flex-shrink-0"
           href="<?= e(url('index.php')) ?>" aria-label="SysTec, inicio">
            <img src="<?= e(url('favicon.svg')) ?>" width="36" height="36"
                 class="flex-shrink-0" alt="" aria-hidden="true">
            <span class="fw-bold fs-5 lh-1">SysTec</span>
        </a>

        <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse"
                data-bs-target="#primaryNavigation" aria-controls="primaryNavigation"
                aria-expanded="false" aria-label="Abrir o cerrar navegación">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="primaryNavigation">
            <div class="navbar-nav nav-pills flex-column flex-xl-row flex-wrap gap-0 ms-xl-auto me-xl-2">
                <?php foreach ($navigation as $item) :
                    $isActive = $currentPage === $item['page'];
                    ?>
                    <a class="nav-link d-inline-flex align-items-center gap-1 px-2 py-1 small<?= $isActive ? ' active' : '' ?>"
                       href="<?= e(url($item['page'])) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                        <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>

                <?php if ($canImport) : ?>
                    <div class="nav-item dropdown">
                        <button class="nav-link dropdown-toggle d-inline-flex align-items-center gap-1 px-2 py-1 small<?= $importPageActive ? ' active' : '' ?>"
                                type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                <?= $importPageActive ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-box-arrow-in-down" aria-hidden="true"></i>
                            <span>Importar</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item<?= $currentPage === 'updates.php' ? ' active' : '' ?>"
                                   href="<?= e(url('updates.php')) ?>"<?= $currentPage === 'updates.php' ? ' aria-current="page"' : '' ?>>
                                    <i class="bi bi-box-arrow-in-down me-2" aria-hidden="true"></i>
                                    Bienes
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item<?= $currentPage === 'imports.php' ? ' active' : '' ?>"
                                   href="<?= e(url('imports.php')) ?>"<?= $currentPage === 'imports.php' ? ' aria-current="page"' : '' ?>>
                                    <i class="bi bi-calendar2-plus me-2" aria-hidden="true"></i>
                                    Mantenimientos
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($currentUser) :
                ?>
                <div class="app-user-menu d-flex align-items-center justify-content-between gap-2 mt-2 mt-xl-0">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-person-circle text-primary" aria-hidden="true"></i>
                        <span class="fw-semibold small"><?= e($currentUser['username']) ?></span>
                    </div>
                    <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2">
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                            <span>Salir</span>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </nav>
</div>
