<?php

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/inventory_import.php';
require_login();

$user = auth_user();
$title = 'Inicio';
$targetPage = is_array($user) && isset($user['role']) && $user['role'] === 'personal' ? 'tickets.php' : 'dashboard.php';

$db = db();
$roles = $db->getListRoles();
$userRoleCode = is_array($user) && isset($user['role']) ? (string) $user['role'] : 'consulta';
$userRoleLabel = isset($roles[$userRoleCode]) ? $roles[$userRoleCode] : $userRoleCode;
$username = is_array($user) && isset($user['username']) ? (string) $user['username'] : 'Usuario';

$canViewTickets = auth_has_role(array('admin', 'operador', 'tecnico'));
$canImport = auth_has_role(array('admin', 'operador'));
$isAdmin = auth_has_role(array('admin'));

// Resumen rápido de bienes
$properties = $db->index('properties', array(), 'id,status', 'id ASC');
$statusSummary = $db->properties()->summarizeStatuses($properties);
$propertyCount = $statusSummary['total'];
$goodPropertyCount = $statusSummary['good'];
$attentionPropertyCount = $statusSummary['attention'];

// Resumen rápido de mantenimientos
$maintenances = $db->index('maintenances', array(), 'id,status,next_maintenance_date', 'id DESC');
$maintenanceCounts = $db->maintenances()->summarizeCounts($maintenances);
$activeMaintenanceCount = $maintenanceCounts['iniciado'] + $maintenanceCounts['revision'];
$completedMaintenanceCount = $maintenanceCounts['finalizado'];
$overdueMaintenanceCount = $maintenanceCounts['vencido'];

// Resumen rápido de tickets
$ticketSummary = $canViewTickets
    ? $db->tickets()->supportTicketDashboardSummary(null)
    : array('counts' => array('total' => 0, 'in_progress' => 0, 'delayed' => 0, 'waiting' => 0, 'closed' => 0));
$ticketCounts = $ticketSummary['counts'];
$openTicketCount = max(0, $ticketCounts['total'] - $ticketCounts['closed']);

// Bienes candidatos a reporte de dañados
$damagedReportRows = $db->indexWithMaintenances('properties.*', 'date ASC');
$damagedReportCount = count($damagedReportRows);

// Estado de sincronización en caché local
$localCache = legacy_postgres_read_local_cache();
$legacySyncAvailable = !empty($localCache['found']);
$legacyAdscriptionCount = isset($localCache['adscriptions']) && is_array($localCache['adscriptions'])
    ? count($localCache['adscriptions'])
    : 0;
$legacySyncCheckedAt = isset($localCache['cached_at']) && $localCache['cached_at'] !== null
    ? date('d/m/Y H:i', (int) $localCache['cached_at'])
    : '—';
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
                — Centro de inicio, resumen operativo y acceso rápido a los módulos del sistema.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-self-md-start flex-shrink-0">
            <?php if ($canViewTickets) : ?>
                <a href="<?= e(url('tickets.php')) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-ticket-detailed me-1" aria-hidden="true"></i>Tickets
                </a>
            <?php endif; ?>
            <a href="<?= e(url($targetPage)) ?>" class="btn btn-primary">
                <i class="bi <?= $targetPage === 'tickets.php' ? 'bi-ticket-detailed' : 'bi-speedometer2' ?> me-1" aria-hidden="true"></i>
                <?= $targetPage === 'tickets.php' ? 'Ir a Tickets' : 'Ir al Dashboard' ?>
            </a>
        </div>
    </section>

    <section class="row g-3 mb-4" aria-label="Indicadores generales del sistema">
        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-primary shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-primary-subtle text-primary p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-box-seam fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Bienes registrados</p>
                        <p class="h3 mb-0" role="status" aria-live="polite"><?= e((string) $propertyCount) ?></p>
                        <p class="small text-body-secondary mb-0"><?= e((string) $goodPropertyCount) ?> en buen estado</p>
                    </div>
                </div>
            </article>
        </div>

        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-danger shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-danger-subtle text-danger p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Requieren atención</p>
                        <p class="h3 mb-0"><?= e((string) $attentionPropertyCount) ?></p>
                        <p class="small text-body-secondary mb-0"><?= e((string) $damagedReportCount) ?> con informe técnico</p>
                    </div>
                </div>
            </article>
        </div>

        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-<?= $overdueMaintenanceCount > 0 ? 'warning' : 'info' ?> shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-<?= $overdueMaintenanceCount > 0 ? 'warning' : 'info' ?>-subtle text-<?= $overdueMaintenanceCount > 0 ? 'warning' : 'info' ?> p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-tools fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Mantenimientos activos</p>
                        <p class="h3 mb-0"><?= e((string) $activeMaintenanceCount) ?></p>
                        <p class="small text-body-secondary mb-0">
                            <?= $overdueMaintenanceCount > 0 ? e((string) $overdueMaintenanceCount) . ' vencidos' : 'Iniciados o en revisión' ?>
                        </p>
                    </div>
                </div>
            </article>
        </div>

        <div class="col-6 col-md-4 col-xl">
            <article class="card h-100 border-start border-4 border-success shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-success-subtle text-success p-2 flex-shrink-0" aria-hidden="true">
                        <i class="bi bi-check-circle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-body-secondary mb-1">Mantenimientos realizados</p>
                        <p class="h3 mb-0"><?= e((string) $completedMaintenanceCount) ?></p>
                        <p class="small text-body-secondary mb-0">Total: <?= e((string) $maintenanceCounts['total']) ?> registros</p>
                    </div>
                </div>
            </article>
        </div>

        <?php if ($canViewTickets) : ?>
            <div class="col-12 col-md-4 col-xl">
                <article class="card h-100 border-start border-4 border-<?= $ticketCounts['delayed'] > 0 ? 'warning' : 'primary' ?> shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-<?= $ticketCounts['delayed'] > 0 ? 'warning' : 'primary' ?>-subtle text-<?= $ticketCounts['delayed'] > 0 ? 'warning' : 'primary' ?> p-2 flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-ticket-detailed fs-4"></i>
                        </span>
                        <div>
                            <p class="small text-body-secondary mb-1">Tickets abiertos</p>
                            <p class="h3 mb-0"><?= e((string) $openTicketCount) ?></p>
                            <p class="small text-body-secondary mb-0">
                                <?= $ticketCounts['delayed'] > 0
                                    ? e((string) $ticketCounts['delayed']) . ' con retraso'
                                    : e((string) $ticketCounts['total']) . ' registrados' ?>
                            </p>
                        </div>
                    </div>
                </article>
            </div>
        <?php endif; ?>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-8">
            <section class="card h-100 shadow-sm border-0" aria-labelledby="about-gmb-title">
                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                    <h2 class="h5 mb-0" id="about-gmb-title">
                        <i class="bi bi-building-gear text-primary me-2" aria-hidden="true"></i>Acerca del Sistema GMB
                    </h2>
                    <span class="badge text-bg-light border text-body-secondary">SysTec GMB</span>
                </div>
                <div class="card-body">
                    <p class="text-body-secondary mb-3">
                        El Sistema de Gestión de los Mantenimientos (GMB) es un sistema informático que permite administrar una base de datos de bienes de una institución,
                        así como llevar registro de todos los mantenimientos realizados a cada bien. El sistema también genera informes técnicos por cada bien, en los que se muestra
                        la información de todos los mantenimientos realizados.
                    </p>
                    <div class="row g-3">
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
                                Las adscripciones y asignaciones institucionales están almacenadas en caché local para consulta rápida.
                            </p>
                            <dl class="row g-2 small mb-0">
                                <dt class="col-6 text-body-secondary">Adscripciones en caché</dt>
                                <dd class="col-6 text-end fw-semibold mb-1"><?= e((string) $legacyAdscriptionCount) ?></dd>
                                <dt class="col-6 text-body-secondary">Última sincronización</dt>
                                <dd class="col-6 text-end fw-semibold mb-0"><?= e($legacySyncCheckedAt) ?></dd>
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

    <section class="card shadow-sm border-0" aria-labelledby="quick-access-title">
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
                                            Gestiona solicitudes de soporte e incidencias reportadas sobre los bienes institucionales.
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
</div>

<?php $main = ob_get_clean();
include __DIR__ . '/include/layout.php';
