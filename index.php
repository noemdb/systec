<?php

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/inventory_import.php';
require_login();
require_non_personal_area();

$user = auth_user();
$title = 'Inicio';

$db = db();
$roles = $db->getListRoles();
$userRoleCode = is_array($user) && isset($user['role']) ? (string) $user['role'] : 'consulta';
$userRoleLabel = isset($roles[$userRoleCode]) ? $roles[$userRoleCode] : $userRoleCode;
$username = is_array($user) && isset($user['username']) ? (string) $user['username'] : 'Usuario';

$canViewTickets = auth_can_view_tickets();
$canManageTickets = auth_can_manage_all_tickets();
$canManageMaintenances = auth_has_role(array('admin', 'operador', 'tecnico'));
$canImport = auth_has_role(array('admin', 'operador'));
$isAdmin = auth_has_role(array('admin'));

// Resumen agregado de bienes (SQL O(1))
$statusSummary = $db->properties()->statusSummaryCounts();
$propertyCount = $statusSummary['total'];
$goodPropertyCount = $statusSummary['good'];
$attentionPropertyCount = $statusSummary['attention'];

// Resumen agregado de mantenimientos (SQL O(1))
$maintenanceCounts = $db->maintenances()->dashboardSummaryCounts();
$activeMaintenanceCount = $maintenanceCounts['iniciado'] + $maintenanceCounts['revision'];
$completedMaintenanceCount = $maintenanceCounts['finalizado'];
$overdueMaintenanceCount = $maintenanceCounts['vencido'];

// Resumen agregado de tickets
$ticketSummary = $canViewTickets
    ? $db->tickets()->supportTicketDashboardSummary(null)
    : array('counts' => array('total' => 0, 'in_progress' => 0, 'delayed' => 0, 'waiting' => 0, 'closed' => 0));
$ticketCounts = $ticketSummary['counts'];
$openTicketCount = max(0, $ticketCounts['total'] - $ticketCounts['closed']);

// Bienes candidatos a reporte de dañados (COUNT DISTINCT SQL)
$damagedReportCount = $db->maintenances()->damagedReportCount();

// Listados acotados para el panel operativo "Requiere atención hoy"
$urgentMaintenances = $db->maintenances()->urgentMaintenances(5);
$urgentTickets = $canViewTickets ? $db->tickets()->urgentOpenTickets(5) : array();
$urgentTotalCount = count($urgentMaintenances) + count($urgentTickets);

$ticketStatusClasses = array(
    'iniciado' => 'primary',
    'en_progreso' => 'info',
    'con_retraso' => 'warning',
    'en_espera' => 'secondary',
    'finalizado' => 'success',
);

// Destinos deep-link para los KPIs
$activeMaintenanceUrl = $overdueMaintenanceCount > 0
    ? url('maintenances.php?vencido=1')
    : url('maintenances.php?estado=iniciado');
$openTicketUrl = $ticketCounts['delayed'] > 0
    ? url('tickets.php?status=con_retraso')
    : url('tickets.php?status=abiertos');

// Estado de sincronización en caché local (TTL real de 24h por defecto)
$localCache = legacy_postgres_read_local_cache();
$legacySyncAvailable = !empty($localCache['found']);
$legacyAdscriptionCount = isset($localCache['adscriptions']) && is_array($localCache['adscriptions'])
    ? count($localCache['adscriptions'])
    : 0;
$legacyAssignmentCount = isset($localCache['assignments']) && is_array($localCache['assignments'])
    ? count($localCache['assignments'])
    : 0;
$legacySyncCheckedAt = isset($localCache['cached_at']) && $localCache['cached_at'] !== null
    ? date('d/m/Y H:i', (int) $localCache['cached_at'])
    : '—';
$legacySyncAgeSeconds = isset($localCache['age_seconds']) && is_int($localCache['age_seconds'])
    ? $localCache['age_seconds']
    : null;
$legacySyncRelativeAge = format_relative_age($legacySyncAgeSeconds);
$legacySyncStale = !empty($localCache['stale']);

ob_start(); ?>

<div class="container-fluid text-start">
    <section class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3"
             aria-labelledby="home-page-title">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0" id="home-page-title">
                    Gestión de los Mantenimientos realizados a los Bienes de la institución
                </h1>
                <span class="badge rounded-pill text-bg-primary">GMB</span>
            </div>
            <p class="text-body-secondary mb-0">
                Bienvenido, <strong><?= e($username) ?></strong>
                <span class="badge text-bg-light border text-body-secondary ms-1"><?= e($userRoleLabel) ?></span>
                — Centro operativo, alertas prioritarias y acceso rápido a los módulos del sistema.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-self-md-start flex-shrink-0">
            <?php if ($canManageMaintenances) : ?>
                <a href="<?= e(url('maintenances.php?modeCreate=true')) ?>" class="btn btn-outline-primary">
                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Nuevo mantenimiento
                </a>
            <?php endif; ?>
            <?php if ($canManageTickets) : ?>
                <a href="<?= e(url('tickets.php?action=new')) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-ticket-detailed me-1" aria-hidden="true"></i>Nuevo ticket
                </a>
            <?php endif; ?>
            <a href="<?= e(url('dashboard.php')) ?>" class="btn btn-primary">
                <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Ir al Dashboard
            </a>
        </div>
    </section>

    <section class="card shadow-sm border-0 mb-3" aria-label="Búsqueda rápida de bienes">
        <div class="card-body py-2">
            <form method="get" action="<?= e(url('dashboard.php')) ?>" class="row g-2 align-items-center" role="search">
                <div class="col-12 col-md">
                    <label class="visually-hidden" for="homeQuickSearch">Buscar bien por código, serial o descripción</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body-tertiary text-body-secondary" aria-hidden="true">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="search" class="form-control" id="homeQuickSearch" name="search"
                               placeholder="Buscar bien por código, serial, modelo o descripción…"
                               autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-md-auto d-grid d-md-block">
                    <button type="submit" class="btn btn-sm btn-primary">
                        Buscar en inventario
                    </button>
                </div>
            </form>
        </div>
    </section>

    <section class="row g-3 mb-4" aria-label="Indicadores generales del sistema">
        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-primary shadow-sm position-relative home-kpi-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-primary-subtle text-primary p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-box-seam fs-4"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="small text-body-secondary mb-1">Bienes registrados</p>
                        <p class="h3 mb-0"><?= e((string) $propertyCount) ?></p>
                        <p class="small text-body-secondary mb-0"><?= e((string) $goodPropertyCount) ?> en buen estado</p>
                        <a href="<?= e(url('dashboard.php')) ?>"
                           class="stretched-link"
                           aria-label="Ver los <?= e((string) $propertyCount) ?> bienes registrados en el Dashboard"></a>
                    </div>
                </div>
            </article>
        </div>

        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-danger shadow-sm position-relative home-kpi-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-danger-subtle text-danger p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="small text-body-secondary mb-1">Requieren atención</p>
                        <p class="h3 mb-0"><?= e((string) $attentionPropertyCount) ?></p>
                        <p class="small text-body-secondary mb-0"><?= e((string) $damagedReportCount) ?> con informe técnico</p>
                        <a href="<?= e(url('dashboard.php?status=__attention__')) ?>"
                           class="stretched-link"
                           aria-label="Filtrar los <?= e((string) $attentionPropertyCount) ?> bienes que requieren atención"></a>
                    </div>
                </div>
            </article>
        </div>

        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-<?= $overdueMaintenanceCount > 0 ? 'warning' : 'info' ?> shadow-sm position-relative home-kpi-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-<?= $overdueMaintenanceCount > 0 ? 'warning' : 'info' ?>-subtle text-<?= $overdueMaintenanceCount > 0 ? 'warning' : 'info' ?> p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-tools fs-4"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="small text-body-secondary mb-1">Mantenimientos activos</p>
                        <p class="h3 mb-0"><?= e((string) $activeMaintenanceCount) ?></p>
                        <p class="small text-body-secondary mb-0">
                            <?= $overdueMaintenanceCount > 0 ? e((string) $overdueMaintenanceCount) . ' vencidos' : 'Iniciados o en revisión' ?>
                        </p>
                        <a href="<?= e($activeMaintenanceUrl) ?>"
                           class="stretched-link"
                           aria-label="Ver mantenimientos <?= $overdueMaintenanceCount > 0 ? 'vencidos' : 'activos' ?>"></a>
                    </div>
                </div>
            </article>
        </div>

        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-success shadow-sm position-relative home-kpi-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-success-subtle text-success p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-check-circle fs-4"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="small text-body-secondary mb-1">Mantenimientos realizados</p>
                        <p class="h3 mb-0"><?= e((string) $completedMaintenanceCount) ?></p>
                        <p class="small text-body-secondary mb-0">Total: <?= e((string) $maintenanceCounts['total']) ?> registros</p>
                        <a href="<?= e(url('maintenances.php?estado=finalizado')) ?>"
                           class="stretched-link"
                           aria-label="Ver los <?= e((string) $completedMaintenanceCount) ?> mantenimientos finalizados"></a>
                    </div>
                </div>
            </article>
        </div>

        <?php if ($canViewTickets) : ?>
            <div class="col-12 col-md-4 col-xl">
                <article class="card h-100 border-start border-4 border-<?= $ticketCounts['delayed'] > 0 ? 'warning' : 'primary' ?> shadow-sm position-relative home-kpi-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-<?= $ticketCounts['delayed'] > 0 ? 'warning' : 'primary' ?>-subtle text-<?= $ticketCounts['delayed'] > 0 ? 'warning' : 'primary' ?> p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-ticket-detailed fs-4"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="small text-body-secondary mb-1">Tickets abiertos</p>
                            <p class="h3 mb-0"><?= e((string) $openTicketCount) ?></p>
                            <p class="small text-body-secondary mb-0">
                                <?= $ticketCounts['delayed'] > 0
                                    ? e((string) $ticketCounts['delayed']) . ' con retraso'
                                    : e((string) $ticketCounts['total']) . ' registrados' ?>
                            </p>
                            <a href="<?= e($openTicketUrl) ?>"
                               class="stretched-link"
                               aria-label="Ver tickets <?= $ticketCounts['delayed'] > 0 ? 'con retraso' : 'abiertos' ?>"></a>
                        </div>
                    </div>
                </article>
            </div>
        <?php endif; ?>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-8">
            <section class="card h-100 shadow-sm border-0" aria-labelledby="attention-today-title">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <h2 class="h5 mb-0" id="attention-today-title">
                            <i class="bi bi-bell text-warning me-2" aria-hidden="true"></i>Requiere atención hoy
                        </h2>
                        <span class="badge rounded-pill text-bg-<?= $urgentTotalCount > 0 ? 'warning' : 'success' ?>">
                            <?= e((string) $urgentTotalCount) ?> <?= $urgentTotalCount === 1 ? 'pendiente' : 'pendientes' ?>
                        </span>
                    </div>
                    <ul class="nav nav-pills nav-fill small gap-1" id="urgentAttentionTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-1 px-2" id="urgent-maintenances-tab"
                                    data-bs-toggle="tab" data-bs-target="#urgent-maintenances-pane"
                                    type="button" role="tab" aria-controls="urgent-maintenances-pane"
                                    aria-selected="true">
                                Mantenimientos (<?= e((string) count($urgentMaintenances)) ?>)
                            </button>
                        </li>
                        <?php if ($canViewTickets) : ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link py-1 px-2" id="urgent-tickets-tab"
                                        data-bs-toggle="tab" data-bs-target="#urgent-tickets-pane"
                                        type="button" role="tab" aria-controls="urgent-tickets-pane"
                                        aria-selected="false">
                                    Tickets (<?= e((string) count($urgentTickets)) ?>)
                                </button>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content" id="urgentAttentionTabContent">
                        <div class="tab-pane fade show active" id="urgent-maintenances-pane"
                             role="tabpanel" aria-labelledby="urgent-maintenances-tab" tabindex="0">
                            <?php if ($urgentMaintenances === array()) : ?>
                                <div class="p-4 text-center">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success p-2 mb-2" aria-hidden="true">
                                        <i class="bi bi-check2-circle fs-4"></i>
                                    </span>
                                    <p class="fw-semibold mb-1">Sin mantenimientos vencidos ni próximos</p>
                                    <p class="small text-body-secondary mb-0">
                                        No hay intervenciones activas vencidas ni programadas para los próximos 7 días.
                                    </p>
                                </div>
                            <?php else : ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <caption class="visually-hidden">Mantenimientos vencidos o próximos a vencer</caption>
                                        <thead class="table-light">
                                        <tr>
                                            <th scope="col">Bien</th>
                                            <th scope="col">Estado</th>
                                            <th scope="col">Próxima fecha</th>
                                            <th scope="col" class="text-end">Acción</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($urgentMaintenances as $item) :
                                            $identRaw = trim((string) ($item['property_ident'] ?? ''));
                                            $ident = $identRaw !== '' ? str_pad($identRaw, 4, '0', STR_PAD_LEFT) : '';
                                            $propDesc = trim((string) ($item['property_description'] ?? 'Bien sin descripción'));
                                            $isOverdue = !empty($item['is_overdue']);
                                            $nextDateRaw = trim((string) ($item['next_maintenance_date'] ?? ''));
                                            $nextDateTs = $nextDateRaw !== '' ? strtotime($nextDateRaw) : false;
                                            $nextDateLabel = $nextDateTs !== false ? date('d/m/Y', $nextDateTs) : $nextDateRaw;
                                            $searchTarget = $ident !== '' ? $ident : (string) ($item['code'] ?? '');
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold text-break"><?= e($propDesc) ?></div>
                                                    <div class="small text-body-secondary">
                                                        Código: <?= e($ident !== '' ? $ident : '—') ?>
                                                        <?php if (!empty($item['technician'])) : ?>
                                                            · Técnico: <?= e((string) $item['technician']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge text-bg-<?= $isOverdue ? 'danger' : 'warning' ?>">
                                                        <?= $isOverdue ? 'Vencido' : 'Próximo' ?>
                                                    </span>
                                                </td>
                                                <td class="text-nowrap small"><?= e($nextDateLabel) ?></td>
                                                <td class="text-end text-nowrap">
                                                    <a href="<?= e(url('maintenances.php?search=' . rawurlencode($searchTarget))) ?>"
                                                       class="btn btn-sm btn-outline-primary">
                                                        Revisar
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($canViewTickets) : ?>
                            <div class="tab-pane fade" id="urgent-tickets-pane"
                                 role="tabpanel" aria-labelledby="urgent-tickets-tab" tabindex="0">
                                <?php if ($urgentTickets === array()) : ?>
                                    <div class="p-4 text-center">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success p-2 mb-2" aria-hidden="true">
                                            <i class="bi bi-check2-circle fs-4"></i>
                                        </span>
                                        <p class="fw-semibold mb-1">Sin tickets abiertos</p>
                                        <p class="small text-body-secondary mb-0">
                                            Todas las incidencias registradas se encuentran atendidas o finalizadas.
                                        </p>
                                    </div>
                                <?php else : ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <caption class="visually-hidden">Tickets abiertos prioritarios</caption>
                                            <thead class="table-light">
                                            <tr>
                                                <th scope="col">Ticket</th>
                                                <th scope="col">Bien</th>
                                                <th scope="col">Estado</th>
                                                <th scope="col" class="text-end">Acción</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($urgentTickets as $ticket) :
                                                $tCode = (string) ($ticket['status_code'] ?? '');
                                                $tBadge = isset($ticketStatusClasses[$tCode]) ? $ticketStatusClasses[$tCode] : 'secondary';
                                                $tIdentRaw = trim((string) ($ticket['property_ident'] ?? ''));
                                                $tIdent = $tIdentRaw !== '' ? str_pad($tIdentRaw, 4, '0', STR_PAD_LEFT) : '';
                                                $tPropDesc = trim((string) ($ticket['property_description'] ?? 'Bien sin descripción'));
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold text-break">
                                                            #<?= e((string) $ticket['id']) ?> — <?= e((string) $ticket['title']) ?>
                                                        </div>
                                                        <div class="small text-body-secondary text-nowrap">
                                                            Actualizado: <?= e((string) ($ticket['updated_at'] ?? '—')) ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="small fw-semibold text-break"><?= e($tPropDesc) ?></div>
                                                        <div class="small text-body-secondary">Código: <?= e($tIdent !== '' ? $tIdent : '—') ?></div>
                                                    </td>
                                                    <td>
                                                        <span class="badge text-bg-<?= e($tBadge) ?>">
                                                            <?= e((string) ($ticket['status_name'] ?? 'Abierto')) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end text-nowrap">
                                                        <a href="<?= e(url('tickets.php?search=' . rawurlencode((string) $ticket['id']))) ?>"
                                                           class="btn btn-sm btn-outline-primary">
                                                            Ver
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-4">
            <section class="card h-100 shadow-sm border-0" aria-labelledby="sync-status-title">
                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                    <h2 class="h5 mb-0" id="sync-status-title">
                        <i class="bi bi-database-check text-primary me-2" aria-hidden="true"></i>Estado de sincronización
                    </h2>
                    <?php if ($legacySyncAvailable) : ?>
                        <span class="badge text-bg-<?= $legacySyncStale ? 'warning' : 'success' ?>">
                            <?= $legacySyncStale ? 'Desactualizado' : 'Sincronizado' ?>
                        </span>
                    <?php else : ?>
                        <span class="badge text-bg-secondary">Sin caché local</span>
                    <?php endif; ?>
                </div>
                <div class="card-body d-flex flex-column justify-content-between gap-3">
                    <div>
                        <?php if ($legacySyncAvailable) : ?>
                            <p class="small text-body-secondary mb-2">
                                <?= $legacySyncStale
                                    ? 'La caché local superó el umbral de vigencia (24 h) o proviene de un respaldo previo. Conviene sincronizar.'
                                    : 'Las adscripciones y asignaciones institucionales están disponibles en caché local.' ?>
                            </p>
                            <dl class="row g-2 small mb-0">
                                <dt class="col-6 text-body-secondary">Adscripciones</dt>
                                <dd class="col-6 text-end fw-semibold mb-1"><?= e((string) $legacyAdscriptionCount) ?></dd>
                                <dt class="col-6 text-body-secondary">Asignaciones</dt>
                                <dd class="col-6 text-end fw-semibold mb-1"><?= e((string) $legacyAssignmentCount) ?></dd>
                                <dt class="col-6 text-body-secondary">Última sincronización</dt>
                                <dd class="col-6 text-end fw-semibold mb-0">
                                    <?= e($legacySyncCheckedAt) ?>
                                    <?php if ($legacySyncAgeSeconds !== null) : ?>
                                        <div class="fw-normal text-body-secondary"><?= e($legacySyncRelativeAge) ?></div>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        <?php else : ?>
                            <p class="small text-body-secondary mb-0">
                                Aún no se ha sincronizado el catálogo local con la base institucional PostgreSQL.
                                Importa o sincroniza el inventario para disponer de adscripciones y asignaciones sin conexión externa.
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if ($canImport) : ?>
                        <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                            <a href="<?= e(url('updates.php')) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-box-arrow-in-down me-1" aria-hidden="true"></i>Importar bienes
                            </a>
                            <a href="<?= e(url('sync-assignments.php')) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-people me-1" aria-hidden="true"></i>Sincronizar asignaciones
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <section class="card shadow-sm border-0 mb-4" aria-labelledby="quick-access-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <h2 class="h5 mb-0" id="quick-access-title">
                <i class="bi bi-grid-1x2 text-primary me-2" aria-hidden="true"></i>Módulos y accesos rápidos
            </h2>
            <span class="badge text-bg-light border text-body-secondary">Navegación directa</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-4">
                    <article class="card h-100 border shadow-sm">
                        <div class="card-body d-flex flex-column justify-content-between gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="rounded-circle bg-primary-subtle text-primary p-2 flex-shrink-0" aria-hidden="true">
                                    <i class="bi bi-speedometer2 fs-4"></i>
                                </span>
                                <div>
                                    <h3 class="h6 mb-1">Dashboard de Bienes</h3>
                                    <p class="small text-body-secondary mb-0">
                                        Consulta el inventario completo, filtra por estado o adscripción e inspecciona el historial de cada bien.
                                    </p>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                <span class="small text-body-secondary"><?= e((string) $propertyCount) ?> bienes</span>
                                <a href="<?= e(url('dashboard.php')) ?>" class="small fw-semibold text-decoration-none">
                                    Abrir Dashboard <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-4">
                    <article class="card h-100 border shadow-sm">
                        <div class="card-body d-flex flex-column justify-content-between gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="rounded-circle bg-info-subtle text-info p-2 flex-shrink-0" aria-hidden="true">
                                    <i class="bi bi-tools fs-4"></i>
                                </span>
                                <div>
                                    <h3 class="h6 mb-1">Mantenimientos</h3>
                                    <p class="small text-body-secondary mb-0">
                                        Registra nuevas intervenciones, actualiza estados, exporta a CSV e imprime historiales de mantenimiento.
                                    </p>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                <span class="small text-body-secondary"><?= e((string) $maintenanceCounts['total']) ?> registros</span>
                                <a href="<?= e(url('maintenances.php')) ?>" class="small fw-semibold text-decoration-none">
                                    Ir a Mantenimientos <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                </div>

                <?php if ($canViewTickets) : ?>
                    <div class="col-12 col-sm-6 col-xl-4">
                        <article class="card h-100 border shadow-sm">
                            <div class="card-body d-flex flex-column justify-content-between gap-3">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="rounded-circle bg-primary-subtle text-primary p-2 flex-shrink-0" aria-hidden="true">
                                        <i class="bi bi-ticket-detailed fs-4"></i>
                                    </span>
                                    <div>
                                        <h3 class="h6 mb-1">Bandeja de Tickets</h3>
                                        <p class="small text-body-secondary mb-0">
                                            Gestiona y consulta solicitudes de soporte e incidencias reportadas sobre los bienes institucionales.
                                        </p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                    <span class="small text-body-secondary"><?= e((string) $openTicketCount) ?> abiertos</span>
                                    <a href="<?= e(url('tickets.php')) ?>" class="small fw-semibold text-decoration-none">
                                        Ver Tickets <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endif; ?>

                <div class="col-12 col-sm-6 col-xl-4">
                    <article class="card h-100 border shadow-sm">
                        <div class="card-body d-flex flex-column justify-content-between gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="rounded-circle bg-danger-subtle text-danger p-2 flex-shrink-0" aria-hidden="true">
                                    <i class="bi bi-exclamation-triangle fs-4"></i>
                                </span>
                                <div>
                                    <h3 class="h6 mb-1">Reporte de Bienes Dañados</h3>
                                    <p class="small text-body-secondary mb-0">
                                        Genera e imprime informes técnicos de bienes en condición mala o muy mala con nota de desincorporación.
                                    </p>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                <span class="small text-body-secondary"><?= e((string) $damagedReportCount) ?> identificados</span>
                                <a href="<?= e(url('report.php')) ?>" class="small fw-semibold text-decoration-none">
                                    Ver Reporte <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                </div>

                <?php if ($canImport) : ?>
                    <div class="col-12 col-sm-6 col-xl-4">
                        <article class="card h-100 border shadow-sm">
                            <div class="card-body d-flex flex-column justify-content-between gap-3">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="rounded-circle bg-success-subtle text-success p-2 flex-shrink-0" aria-hidden="true">
                                        <i class="bi bi-box-arrow-in-down fs-4"></i>
                                    </span>
                                    <div>
                                        <h3 class="h6 mb-1">Importación y Sincronización</h3>
                                        <p class="small text-body-secondary mb-0">
                                            Actualiza bienes desde CSV/XLS o PostgreSQL, importa mantenimientos por lote y sincroniza responsables.
                                        </p>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
                                    <a href="<?= e(url('imports.php')) ?>" class="small text-body-secondary text-decoration-none">
                                        Importar mantenimientos
                                    </a>
                                    <a href="<?= e(url('updates.php')) ?>" class="small fw-semibold text-decoration-none">
                                        Actualizar bienes <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endif; ?>

                <?php if ($isAdmin) : ?>
                    <div class="col-12 col-sm-6 col-xl-4">
                        <article class="card h-100 border shadow-sm">
                            <div class="card-body d-flex flex-column justify-content-between gap-3">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="rounded-circle bg-secondary-subtle text-secondary p-2 flex-shrink-0" aria-hidden="true">
                                        <i class="bi bi-people fs-4"></i>
                                    </span>
                                    <div>
                                        <h3 class="h6 mb-1">Gestión de Usuarios</h3>
                                        <p class="small text-body-secondary mb-0">
                                            Administra cuentas de acceso, asigna roles operativos o técnicos y controla el estado de los usuarios.
                                        </p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                    <span class="small text-body-secondary">Solo administradores</span>
                                    <a href="<?= e(url('users.php')) ?>" class="small fw-semibold text-decoration-none">
                                        Administrar usuarios <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0" aria-labelledby="about-gmb-title">
        <details class="home-about-details">
            <summary class="card-header d-flex align-items-center justify-content-between gap-2 list-unstyled">
                <h2 class="h6 mb-0" id="about-gmb-title">
                    <i class="bi bi-building-gear text-primary me-2" aria-hidden="true"></i>Acerca del Sistema GMB
                </h2>
                <span class="small text-body-secondary">
                    Mostrar descripción <i class="bi bi-chevron-down ms-1" aria-hidden="true"></i>
                </span>
            </summary>
            <div class="card-body">
                <p class="text-body-secondary mb-3">
                    El Sistema de Gestión de los Mantenimientos (GMB) permite administrar el inventario de bienes de la institución,
                    llevar la bitácora de mantenimientos e incidencias y emitir informes técnicos individuales o consolidados.
                </p>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <div class="p-3 rounded border bg-body-tertiary h-100">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-box-seam text-primary" aria-hidden="true"></i>
                                <h3 class="h6 mb-0">1. Inventario institucional</h3>
                            </div>
                            <p class="small text-body-secondary mb-0">
                                Control de bienes por código, adscripción, responsable, condición física y sincronización con el catálogo institucional.
                            </p>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="p-3 rounded border bg-body-tertiary h-100">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-tools text-info" aria-hidden="true"></i>
                                <h3 class="h6 mb-0">2. Mantenimientos y tickets</h3>
                            </div>
                            <p class="small text-body-secondary mb-0">
                                Bitácora de intervenciones preventivas, correctivas, predictivas y evaluativas, junto con la atención de incidencias.
                            </p>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="p-3 rounded border bg-body-tertiary h-100">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-file-earmark-medical text-danger" aria-hidden="true"></i>
                                <h3 class="h6 mb-0">3. Informes técnicos</h3>
                            </div>
                            <p class="small text-body-secondary mb-0">
                                Emisión e impresión de historiales individuales por bien y reportes consolidados para evaluación o desincorporación.
                            </p>
                        </div>
                    </div>
                </div>
                <a href="<?= e(url('documentation.php')) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-journal-text me-1" aria-hidden="true"></i>Ver documentación del sistema
                </a>
            </div>
        </details>
    </section>
</div>

<style nonce="<?= e(systec_csp_nonce()) ?>">
    .home-kpi-card {
        transition: box-shadow .15s ease, transform .15s ease;
    }

    .home-kpi-card:hover {
        transform: translateY(-1px);
    }

    .home-kpi-card:focus-within {
        box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .2), var(--bs-box-shadow-sm);
    }

    .home-about-details > summary {
        cursor: pointer;
    }

    .home-about-details > summary::-webkit-details-marker {
        display: none;
    }

    @media (prefers-reduced-motion: reduce) {
        .home-kpi-card {
            transition: none;
        }

        .home-kpi-card:hover {
            transform: none;
        }
    }
</style>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
