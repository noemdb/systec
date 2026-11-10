<?php
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../include/auth.php';
require_personal_scope();

$db = db();
$user = auth_user();
$conditions = array('created_by' => (int) $user['id']);
$tickets = $db->index('tickets', $conditions, '*', 'updated_at DESC');
$statuses = $db->getTicketStatuses();
$properties = array();
$summaryCounts = array(
    'total' => count($tickets),
    'in_progress' => 0,
    'delayed' => 0,
    'waiting' => 0,
    'closed' => 0,
);

foreach ($tickets as $ticket) {
    $property = $db->getFirstForId('properties', (int) $ticket['property_id']);
    $properties[(int) $ticket['id']] = $property ?: array('description' => 'Bien eliminado');

    $statusCode = isset($statuses[(int) $ticket['status_id']]['code']) ? (string) $statuses[(int) $ticket['status_id']]['code'] : '';
    switch ($statusCode) {
        case 'en_progreso':
            $summaryCounts['in_progress']++;
            break;
        case 'con_retraso':
            $summaryCounts['delayed']++;
            break;
        case 'en_espera':
            $summaryCounts['waiting']++;
            break;
        case 'finalizado':
            $summaryCounts['closed']++;
            break;
    }
}

$title = 'Tickets personales';
ob_start();
?>
<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Mi área de tickets</h1>
            <p class="text-muted mb-0">Seguimiento y registro de incidencias del personal.</p>
        </div>
        <a href="<?= e(url('personal/register.php')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> Nuevo ticket
        </a>
    </div>

    <section class="row g-3 mb-4" aria-label="Indicadores del área personal">
        <div class="col-12 col-sm-6 col-xl-3">
            <article class="card h-100 border-start border-4 border-primary shadow-sm text-start">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-primary-subtle text-primary p-3" aria-hidden="true">
                        <i class="bi bi-ticket-detailed fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">Total</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['total']) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <article class="card h-100 border-start border-4 border-info shadow-sm text-start">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-info-subtle text-info p-3" aria-hidden="true">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">En progreso</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['in_progress']) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <article class="card h-100 border-start border-4 border-warning shadow-sm text-start">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-warning-subtle text-warning p-3" aria-hidden="true">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">Con retraso</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['delayed']) ?></p>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <article class="card h-100 border-start border-4 border-success shadow-sm text-start">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-circle bg-success-subtle text-success p-3" aria-hidden="true">
                        <i class="bi bi-check-circle fs-4"></i>
                    </span>
                    <div>
                        <p class="small text-muted mb-1">Finalizados</p>
                        <p class="h3 mb-0"><?= e((string) $summaryCounts['closed']) ?></p>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <div class="card shadow-sm text-start">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                <h3 class="h5 mb-0">Mis tickets</h3>
                <p class="small text-body-secondary mb-0"><?= e((string) $summaryCounts['total']) ?> registros</p>
            </div>

            <?php if ($tickets === array()) : ?>
                <div class="alert alert-light border mb-0">No tienes tickets registrados.</div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Bien</th>
                            <th>Título</th>
                            <th>Estado</th>
                            <th>Actualización</th>
                            <th class="text-end">Acción</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tickets as $ticket) :
                            $property = $properties[(int) $ticket['id']];
                            $status = $statuses[(int) $ticket['status_id']] ?? array('name' => 'Sin estado');
                            ?>
                            <tr>
                                <td>#<?= e((string) $ticket['id']) ?></td>
                                <td>
                                    <?= e((string) ($property['description'] ?? 'Bien desconocido')) ?>
                                    <div class="small text-muted">Código: <?= e((string) ($property['ident'] ?? '—')) ?></div>
                                </td>
                                <td><?= e((string) $ticket['title']) ?></td>
                                <td><span class="badge text-bg-light border"><?= e((string) $status['name']) ?></span></td>
                                <td><?= e((string) $ticket['updated_at']) ?></td>
                                <td class="text-end">
                                    <a href="<?= e(url('personal/detail.php?id=' . (int) $ticket['id'])) ?>" class="btn btn-sm btn-outline-primary">Ver detalle</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$main = ob_get_clean();
include __DIR__ . '/../include/layout.php';
