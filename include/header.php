<?php
require_once __DIR__ . '/auth.php';

$currentUser = auth_user();
$currentPage = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
$roleLabels = array(
    'admin' => 'Administrador',
    'operador' => 'Operador',
    'tecnico' => 'Técnico',
    'consulta' => 'Consulta',
);

$navigation = array(
    array('label' => 'Inicio', 'page' => 'index.php', 'icon' => 'bi-house-door'),
    array('label' => 'Dashboard', 'page' => 'dashboard.php', 'icon' => 'bi-speedometer2'),
    array('label' => 'Mantenimientos', 'page' => 'maintenances.php', 'icon' => 'bi-tools'),
    array('label' => 'Dañados', 'page' => 'report.php', 'icon' => 'bi-exclamation-triangle'),
);

if (auth_has_role(array('admin', 'operador'))) {
    $navigation[] = array('label' => 'Importar bienes', 'page' => 'updates.php', 'icon' => 'bi-box-arrow-in-down');
    $navigation[] = array('label' => 'Importar mantenimientos', 'page' => 'imports.php', 'icon' => 'bi-calendar2-plus');
}
?>
<div class="container-fluid px-2">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
        <a class="d-inline-flex align-items-center gap-2 text-decoration-none text-body flex-shrink-0"
           href="<?= e(url('index.php')) ?>" aria-label="SysTec, inicio">
            <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary text-white p-2">
                <i class="bi bi-tools fs-5" aria-hidden="true"></i>
            </span>
            <span>
                <span class="d-block fw-bold fs-5 lh-1">SysTec</span>
                <span class="small text-body-secondary">Gestión de bienes</span>
            </span>
        </a>

        <nav class="nav nav-pills flex-wrap gap-1" aria-label="Navegación principal">
            <?php foreach ($navigation as $item) :
                $isActive = $currentPage === $item['page'];
                ?>
                <a class="nav-link d-inline-flex align-items-center gap-2 px-3 py-2<?= $isActive ? ' active' : '' ?>"
                   href="<?= e(url($item['page'])) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                    <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($currentUser) :
            $role = isset($currentUser['role']) ? $currentUser['role'] : '';
            $roleLabel = isset($roleLabels[$role]) ? $roleLabels[$role] : $role;
            ?>
            <div class="d-flex align-items-center justify-content-between justify-content-xl-end gap-3 flex-shrink-0">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-person-circle fs-4 text-primary" aria-hidden="true"></i>
                    <span class="lh-sm">
                        <span class="d-block fw-semibold"><?= e($currentUser['username']) ?></span>
                        <span class="small text-body-secondary"><?= e($roleLabel) ?></span>
                    </span>
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
</div>
