<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';

require_role(array('admin'));

$db = db();
$currentUser = auth_user();
$roles = $db->getListRoles();
$users = $db->index('users', array(), 'id, username, role, active, created_at', 'id ASC');

$summaryCounts = array(
    'total' => count($users),
    'active' => 0,
    'technicians' => 0,
    'inactive' => 0,
);
foreach ($users as $account) {
    $isActive = (int) ($account['active'] ?? 0) === 1;
    if ($isActive) {
        $summaryCounts['active']++;
        if ((string) ($account['role'] ?? '') === 'tecnico') {
            $summaryCounts['technicians']++;
        }
    } else {
        $summaryCounts['inactive']++;
    }
}

$roleBadgeClasses = array(
    'admin' => 'danger',
    'operador' => 'primary',
    'tecnico' => 'info',
    'consulta' => 'secondary',
    'personal' => 'warning',
);

$usersFeedback = isset($_SESSION['users_feedback']) && is_array($_SESSION['users_feedback'])
    ? $_SESSION['users_feedback']
    : null;
unset($_SESSION['users_feedback']);
$notifications = array();
if ($usersFeedback !== null && !empty($usersFeedback['message'])) {
    $feedbackType = isset($usersFeedback['type']) && in_array($usersFeedback['type'], array('success', 'info', 'warning', 'danger'), true)
        ? $usersFeedback['type']
        : 'info';
    $notifications[] = toast_notification($feedbackType, (string) $usersFeedback['message']);
}

$title = 'Usuarios';
ob_start();
?>
<div class="container-fluid text-start">
    <section class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3"
             aria-labelledby="users-page-title">
        <div>
            <h1 class="h3 mb-1" id="users-page-title">Usuarios</h1>
            <p class="text-body-secondary mb-0">Administración de cuentas, roles, técnicos activos y credenciales de acceso.</p>
        </div>
        <button type="button" class="btn btn-primary align-self-md-start flex-shrink-0"
                data-bs-toggle="modal" data-bs-target="#userManageModal">
            <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Nuevo usuario
        </button>
    </section>

    <section class="row g-3 mb-4" aria-label="Indicadores de cuentas de usuario">
        <div class="col-6 col-md-3">
            <article class="card h-100 border-start border-4 border-primary shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-primary-subtle text-primary p-2" aria-hidden="true">
                        <i class="bi bi-people fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Total cuentas</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['total']) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-6 col-md-3">
            <article class="card h-100 border-start border-4 border-success shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-success-subtle text-success p-2" aria-hidden="true">
                        <i class="bi bi-person-check fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Activos</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['active']) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-6 col-md-3">
            <article class="card h-100 border-start border-4 border-info shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-info-subtle text-info p-2" aria-hidden="true">
                        <i class="bi bi-tools fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Técnicos activos</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['technicians']) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-6 col-md-3">
            <article class="card h-100 border-start border-4 border-secondary shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-secondary-subtle text-secondary p-2" aria-hidden="true">
                        <i class="bi bi-person-slash fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Inactivos</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['inactive']) ?></p>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <section class="card shadow-sm border-0" aria-labelledby="users-list-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <h2 class="h5 mb-0" id="users-list-title">
                <i class="bi bi-shield-lock text-primary me-2" aria-hidden="true"></i>Cuentas registradas
            </h2>
            <span class="badge text-bg-light border text-body-secondary" aria-live="polite">
                <?= e((string) $summaryCounts['total']) ?> usuarios
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <caption class="visually-hidden">Listado de usuarios del sistema</caption>
                    <thead class="table-light">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Usuario</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Fecha de alta</th>
                        <th scope="col" class="text-end">Acción</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $account) :
                        $accountId = (int) $account['id'];
                        $roleCode = (string) ($account['role'] ?? 'consulta');
                        $roleLabel = isset($roles[$roleCode]) ? $roles[$roleCode] : $roleCode;
                        $roleBadge = isset($roleBadgeClasses[$roleCode]) ? $roleBadgeClasses[$roleCode] : 'secondary';
                        $isActive = (int) ($account['active'] ?? 0) === 1;
                        $isSelf = isset($currentUser['id']) && (int) $currentUser['id'] === $accountId;
                        $userData = array(
                            'id' => $accountId,
                            'username' => (string) $account['username'],
                            'role' => $roleCode,
                            'active' => $isActive ? 1 : 0,
                        );
                        ?>
                        <tr>
                            <td><span class="fw-semibold">#<?= e((string) $accountId) ?></span></td>
                            <td>
                                <span class="fw-semibold"><?= e((string) $account['username']) ?></span>
                                <?php if ($isSelf) : ?>
                                    <span class="badge text-bg-light border ms-1">Sesión actual</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= e($roleBadge) ?>">
                                    <?= e($roleLabel) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isActive) : ?>
                                    <span class="badge text-bg-success">Activo</span>
                                <?php else : ?>
                                    <span class="badge text-bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= e((string) ($account['created_at'] ?? '—')) ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary text-nowrap"
                                        aria-label="Editar usuario <?= e((string) $account['username']) ?>"
                                        data-bs-toggle="modal" data-bs-target="#userManageModal"
                                        data-user="<?= e(js($userData)) ?>">
                                    <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
<?php include __DIR__ . '/include/user_manage_modal.php'; ?>
<?php
$main = ob_get_clean();
include __DIR__ . '/include/layout.php';
