<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';

require_role(array('admin'));

$db = db();
$currentUser = auth_user();
$roles = $db->getListRoles();
$users = $db->fetchAllQuery(
    'SELECT users.id, users.username, users.email, users.role, users.active, users.created_at,
            profile.first_name, profile.last_name, profile.avatar_url
     FROM users
     LEFT JOIN profile ON profile.user_id = users.id
     ORDER BY users.id ASC'
);

$ticketCountsByUser = array();
foreach ($db->fetchAllQuery('SELECT created_by, COUNT(*) AS total FROM tickets GROUP BY created_by') as $row) {
    $ticketCountsByUser[(int) $row['created_by']] = (int) $row['total'];
}
$historyCountsByUser = array();
foreach ($db->fetchAllQuery('SELECT actor_user_id, COUNT(*) AS total FROM ticket_histories GROUP BY actor_user_id') as $row) {
    $historyCountsByUser[(int) $row['actor_user_id']] = (int) $row['total'];
}

$summaryCounts = array(
    'total' => count($users),
    'active' => 0,
    'technicians' => 0,
    'inactive' => 0,
);
$throttleByUserId = array();
foreach ($users as $account) {
    $accountId = (int) $account['id'];
    $isActive = (int) ($account['active'] ?? 0) === 1;
    if ($isActive) {
        $summaryCounts['active']++;
        if ((string) ($account['role'] ?? '') === 'tecnico') {
            $summaryCounts['technicians']++;
        }
    } else {
        $summaryCounts['inactive']++;
    }
    $throttleByUserId[$accountId] = auth_throttle_check((string) $account['username'], '0.0.0.0');
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
            <p class="text-body-secondary mb-0">Administración de cuentas, perfiles, roles, técnicos y credenciales de acceso.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-self-md-start flex-shrink-0">
            <button type="button" class="btn btn-outline-secondary" id="exportUsersCsv"
                    <?= $summaryCounts['total'] === 0 ? 'disabled' : '' ?>
                    title="Descargar en CSV los usuarios filtrados">
                <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Exportar CSV
            </button>
            <button type="button" class="btn btn-primary"
                    data-bs-toggle="offcanvas" data-bs-target="#userManageDrawer">
                <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Nuevo usuario
            </button>
        </div>
    </section>

    <section class="row g-3 mb-3" aria-label="Indicadores de cuentas de usuario">
        <div class="col-6 col-md-3">
            <button type="button"
                    class="card h-100 w-100 border-start border-4 border-primary shadow-sm text-start text-body js-users-kpi"
                    data-kpi="total" aria-controls="usersTable" aria-pressed="true"
                    title="Mostrar todas las cuentas">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-primary-subtle text-primary p-2" aria-hidden="true">
                        <i class="bi bi-people fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Total cuentas</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['total']) ?></p>
                    </div>
                </div>
            </button>
        </div>
        <div class="col-6 col-md-3">
            <button type="button"
                    class="card h-100 w-100 border-start border-4 border-success shadow-sm text-start text-body js-users-kpi"
                    data-kpi="active" aria-controls="usersTable" aria-pressed="false"
                    title="Filtrar cuentas activas">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-success-subtle text-success p-2" aria-hidden="true">
                        <i class="bi bi-person-check fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Activos</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['active']) ?></p>
                    </div>
                </div>
            </button>
        </div>
        <div class="col-6 col-md-3">
            <button type="button"
                    class="card h-100 w-100 border-start border-4 border-info shadow-sm text-start text-body js-users-kpi"
                    data-kpi="technicians" aria-controls="usersTable" aria-pressed="false"
                    title="Filtrar técnicos activos">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-info-subtle text-info p-2" aria-hidden="true">
                        <i class="bi bi-tools fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Técnicos activos</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['technicians']) ?></p>
                    </div>
                </div>
            </button>
        </div>
        <div class="col-6 col-md-3">
            <button type="button"
                    class="card h-100 w-100 border-start border-4 border-secondary shadow-sm text-start text-body js-users-kpi"
                    data-kpi="inactive" aria-controls="usersTable" aria-pressed="false"
                    title="Filtrar cuentas inactivas">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-secondary-subtle text-secondary p-2" aria-hidden="true">
                        <i class="bi bi-person-slash fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Inactivos</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['inactive']) ?></p>
                    </div>
                </div>
            </button>
        </div>
    </section>

    <section class="card shadow-sm border-0 mb-3" aria-labelledby="users-filter-title">
        <div class="card-body py-2">
            <h2 class="visually-hidden" id="users-filter-title">Filtrar usuarios</h2>
            <div class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-4">
                    <label for="filterUserRole" class="form-label fw-semibold small mb-1">Rol</label>
                    <select id="filterUserRole" class="form-select form-select-sm">
                        <option value="">Todos los roles</option>
                        <?php foreach ($roles as $roleKey => $roleLabel) : ?>
                            <option value="<?= e($roleKey) ?>"><?= e($roleLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-4">
                    <label for="filterUserStatus" class="form-label fw-semibold small mb-1">Estado</label>
                    <select id="filterUserStatus" class="form-select form-select-sm">
                        <option value="">Todos los estados</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex justify-content-md-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="resetUserFilters">
                        <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Limpiar filtros
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0" aria-labelledby="users-list-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <h2 class="h5 mb-0" id="users-list-title">
                <i class="bi bi-shield-lock text-primary me-2" aria-hidden="true"></i>Cuentas registradas
            </h2>
            <span class="badge text-bg-light border text-body-secondary" id="usersResultCount"
                  role="status" aria-live="polite">
                <?= e((string) $summaryCounts['total']) ?> usuarios
            </span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="usersTable">
                    <caption class="visually-hidden">Listado de usuarios del sistema</caption>
                    <thead class="table-light">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Usuario</th>
                        <th scope="col">Perfil</th>
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
                        $throttle = $throttleByUserId[$accountId] ?? array('blocked' => false, 'retry_minutes' => 0);
                        $isBlocked = !empty($throttle['blocked']);
                        $retryMinutes = (int) ($throttle['retry_minutes'] ?? 0);
                        $firstName = trim((string) ($account['first_name'] ?? ''));
                        $lastName = trim((string) ($account['last_name'] ?? ''));
                        $fullName = trim($firstName . ' ' . $lastName);
                        $avatarUrl = trim((string) ($account['avatar_url'] ?? ''));
                        $userData = array(
                            'id' => $accountId,
                            'username' => (string) $account['username'],
                            'email' => (string) ($account['email'] ?? ''),
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'avatar_url' => $avatarUrl,
                            'role' => $roleCode,
                            'active' => $isActive ? 1 : 0,
                            'is_self' => $isSelf,
                        );
                        ?>
                        <tr data-role="<?= e($roleCode) ?>"
                            data-active="<?= $isActive ? '1' : '0' ?>"
                            data-export-id="<?= e((string) $accountId) ?>"
                            data-export-username="<?= e((string) $account['username']) ?>"
                            data-export-profile="<?= e($fullName !== '' ? $fullName : 'Sin perfil') ?>"
                            data-export-email="<?= e((string) ($account['email'] ?? '')) ?>"
                            data-export-role="<?= e($roleLabel) ?>"
                            data-export-status="<?= e($isActive ? 'Activo' : 'Inactivo') ?>"
                            data-export-blocked="<?= e($isBlocked ? 'Sí (' . $retryMinutes . ' min)' : 'No') ?>"
                            data-export-created="<?= e((string) ($account['created_at'] ?? '—')) ?>">
                            <td><span class="fw-semibold">#<?= e((string) $accountId) ?></span></td>
                            <td>
                                <span class="fw-semibold"><?= e((string) $account['username']) ?></span>
                                <?php if ($isSelf) : ?>
                                    <span class="badge text-bg-light border ms-1">Sesión actual</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($avatarUrl !== '') : ?>
                                        <img alt="Avatar de <?= e((string) $account['username']) ?>" src="<?= e($avatarUrl) ?>"
                                             class="rounded-circle border user-avatar-thumb" width="40" height="40"
                                             loading="lazy" referrerpolicy="no-referrer">
                                    <?php else : ?>
                                        <span class="rounded-circle border bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center user-avatar-thumb"
                                              aria-hidden="true">
                                            <i class="bi bi-person"></i>
                                        </span>
                                    <?php endif; ?>
                                    <div class="user-profile-text">
                                        <?php if ($fullName !== '') : ?>
                                            <span class="fw-semibold text-break"><?= e($fullName) ?></span>
                                        <?php else : ?>
                                            <span class="text-body-secondary">Sin perfil</span>
                                        <?php endif; ?>
                                        <?php if (!empty($account['email'])) : ?>
                                            <span class="d-block small text-body-secondary text-break"><?= e((string) $account['email']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
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
                                <?php if ($isBlocked) : ?>
                                    <span class="badge text-bg-warning ms-1" title="Bloqueado temporalmente por intentos fallidos">
                                        <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>Bloqueado (<?= e((string) $retryMinutes) ?> min)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= e((string) ($account['created_at'] ?? '—')) ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                    <?php if ($isBlocked) : ?>
                                        <form method="post" action="<?= e(url('users/actions/save.php')) ?>" class="m-0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="unlock">
                                            <input type="hidden" name="user_id" value="<?= e((string) $accountId) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning text-nowrap"
                                                    aria-label="Desbloquear usuario <?= e((string) $account['username']) ?>">
                                                <i class="bi bi-unlock me-1" aria-hidden="true"></i>Desbloquear
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!$isSelf) : ?>
                                        <form method="post" action="<?= e(url('users/actions/save.php')) ?>" class="m-0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="user_id" value="<?= e((string) $accountId) ?>">
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-<?= $isActive ? 'secondary' : 'success' ?> text-nowrap"
                                                    aria-label="<?= $isActive ? 'Desactivar' : 'Activar' ?> usuario <?= e((string) $account['username']) ?>">
                                                <i class="bi <?= $isActive ? 'bi-person-dash' : 'bi-person-check' ?> me-1" aria-hidden="true"></i>
                                                <?= $isActive ? 'Desactivar' : 'Activar' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary text-nowrap"
                                            aria-label="Editar usuario <?= e((string) $account['username']) ?>"
                                            data-bs-toggle="offcanvas" data-bs-target="#userManageDrawer"
                                            data-user="<?= e(js($userData)) ?>">
                                        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar
                                    </button>
                                    <?php if (!$isSelf) : ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger text-nowrap"
                                                aria-label="Eliminar usuario <?= e((string) $account['username']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                                                data-user-id="<?= e((string) $accountId) ?>"
                                                data-username="<?= e((string) $account['username']) ?>"
                                                data-role-label="<?= e($roleLabel) ?>"
                                                data-tickets-count="<?= e((string) ($ticketCountsByUser[$accountId] ?? 0)) ?>"
                                                data-histories-count="<?= e((string) ($historyCountsByUser[$accountId] ?? 0)) ?>">
                                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<style nonce="<?= e(systec_csp_nonce()) ?>">
    .user-manage-drawer {
        --bs-offcanvas-width: min(34rem, 100vw);
        max-width: 100vw;
    }

    .user-manage-drawer-form {
        min-height: 0;
        overflow: hidden;
    }

    .user-manage-drawer-form .offcanvas-body {
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    .user-avatar-thumb {
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        object-fit: cover;
    }

    .user-avatar-preview {
        width: 72px;
        height: 72px;
        object-fit: cover;
    }

    .user-profile-text {
        min-width: 0;
    }

    .js-users-kpi {
        appearance: none;
        font: inherit;
        cursor: pointer;
        transition: box-shadow .15s ease, transform .15s ease;
    }

    .js-users-kpi:hover {
        transform: translateY(-1px);
    }

    .js-users-kpi:focus-visible,
    .js-users-kpi[aria-pressed="true"] {
        outline: 0;
        box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .2), var(--bs-box-shadow-sm);
    }

    @media (prefers-reduced-motion: reduce) {
        .js-users-kpi {
            transition: none;
        }

        .js-users-kpi:hover {
            transform: none;
        }
    }
</style>

<script nonce="<?= e(systec_csp_nonce()) ?>">
    (function () {
        const totalUsers = <?= js($summaryCounts['total']) ?>;
        const roleFilter = document.getElementById('filterUserRole');
        const statusFilter = document.getElementById('filterUserStatus');
        const resetButton = document.getElementById('resetUserFilters');
        const resultCountBadge = document.getElementById('usersResultCount');
        const exportCsvButton = document.getElementById('exportUsersCsv');
        const kpiButtons = Array.from(document.querySelectorAll('.js-users-kpi'));

        const usersTable = new window.Systec.TablePaginator('#usersTable', {
            pageLength: 10,
            language: {
                search: 'Buscar usuarios:',
                searchPlaceholder: 'Usuario, perfil, rol, estado…',
                emptyTable: 'No hay cuentas registradas.',
                zeroRecords: 'Ningún usuario coincide con los filtros aplicados.',
                unitLabel: 'usuarios'
            }
        });

        usersTable.setFilterPredicate((row) => {
            if (!row) return true;
            const roleMatch = !roleFilter.value || row.dataset.role === roleFilter.value;
            const statusMatch = !statusFilter.value || row.dataset.active === statusFilter.value;
            return roleMatch && statusMatch;
        });

        const syncKpiState = () => {
            kpiButtons.forEach((button) => {
                const kpi = button.dataset.kpi;
                let pressed = false;
                if (kpi === 'total') {
                    pressed = !roleFilter.value && !statusFilter.value && !usersTable.search().trim();
                } else if (kpi === 'active') {
                    pressed = !roleFilter.value && statusFilter.value === '1';
                } else if (kpi === 'technicians') {
                    pressed = roleFilter.value === 'tecnico' && statusFilter.value === '1';
                } else if (kpi === 'inactive') {
                    pressed = !roleFilter.value && statusFilter.value === '0';
                }
                button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
            });
        };

        const updateFeedback = () => {
            const filteredCount = usersTable.rows({ search: 'applied' }).count();
            const hasFilter = Boolean(roleFilter.value || statusFilter.value || usersTable.search().trim());
            if (resultCountBadge) {
                resultCountBadge.textContent = hasFilter
                    ? `Mostrando ${filteredCount} de ${totalUsers} usuarios`
                    : `${totalUsers} usuarios`;
            }
            if (exportCsvButton) {
                exportCsvButton.disabled = filteredCount === 0;
            }
            syncKpiState();
        };

        usersTable.on('draw', updateFeedback);
        usersTable.on('search', updateFeedback);

        [roleFilter, statusFilter].forEach((select) => {
            select.addEventListener('change', () => {
                usersTable.draw(true);
            });
        });

        kpiButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const kpi = button.dataset.kpi;
                if (kpi === 'total') {
                    roleFilter.value = '';
                    statusFilter.value = '';
                    usersTable.search('');
                } else if (kpi === 'active') {
                    roleFilter.value = '';
                    statusFilter.value = statusFilter.value === '1' ? '' : '1';
                } else if (kpi === 'technicians') {
                    const alreadyActive = roleFilter.value === 'tecnico' && statusFilter.value === '1';
                    roleFilter.value = alreadyActive ? '' : 'tecnico';
                    statusFilter.value = alreadyActive ? '' : '1';
                } else if (kpi === 'inactive') {
                    roleFilter.value = '';
                    statusFilter.value = statusFilter.value === '0' ? '' : '0';
                }
                usersTable.draw(true);
            });
        });

        if (resetButton) {
            resetButton.addEventListener('click', () => {
                roleFilter.value = '';
                statusFilter.value = '';
                usersTable.search('');
                usersTable.draw(true);
            });
        }

        if (exportCsvButton) {
            const csvEscape = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
            exportCsvButton.addEventListener('click', () => {
                const columns = [
                    { header: 'ID', key: 'exportId' },
                    { header: 'Usuario', key: 'exportUsername' },
                    { header: 'Perfil', key: 'exportProfile' },
                    { header: 'Email', key: 'exportEmail' },
                    { header: 'Rol', key: 'exportRole' },
                    { header: 'Estado', key: 'exportStatus' },
                    { header: 'Bloqueado', key: 'exportBlocked' },
                    { header: 'Fecha de alta', key: 'exportCreated' }
                ];
                const lines = [columns.map((col) => csvEscape(col.header)).join(';')];
                usersTable.rows({ search: 'applied' }).nodes().toArray().forEach((row) => {
                    const values = columns.map((col) => (row.dataset[col.key] || '').replace(/\s+/g, ' ').trim());
                    lines.push(values.map(csvEscape).join(';'));
                });

                const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                const downloadUrl = URL.createObjectURL(blob);
                const today = new Date();
                const dateStamp = [
                    today.getFullYear(),
                    String(today.getMonth() + 1).padStart(2, '0'),
                    String(today.getDate()).padStart(2, '0')
                ].join('-');
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = `usuarios-${dateStamp}.csv`;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(downloadUrl);
            });
        }

        updateFeedback();
    })();
</script>
<?php include __DIR__ . '/include/user_manage_drawer.php'; ?>
<?php include __DIR__ . '/include/user_delete_modal.php'; ?>
<?php
$main = ob_get_clean();
include __DIR__ . '/include/layout.php';
