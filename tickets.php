<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';

if (auth_check() && auth_has_role(array('personal'))) {
    $redirect = 'personal/index.php';
    if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
        $redirect = 'personal/detail.php?id=' . (int) $_GET['id'];
    }
    header('Location: ' . url($redirect));
    exit();
}

require_role(array('admin', 'operador', 'tecnico'));

$db = db();
$user = auth_user();
$conditions = array();
if (auth_has_role(array('personal'))) {
    $conditions['created_by'] = (int) $user['id'];
}

$tickets = $db->index('tickets', $conditions, '*', 'updated_at DESC');
$statuses = $db->getTicketStatuses();
$properties = array();
foreach ($tickets as $ticket) {
    $property = $db->getFirstForId('properties', (int) $ticket['property_id']);
    $properties[(int) $ticket['id']] = $property ?: array('description' => 'Bien eliminado');
}

$title = 'Tickets';
ob_start();
?>
<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Tickets</h1>
            <p class="text-muted mb-0">Registro y seguimiento de incidencias por bien.</p>
        </div>
        <a href="<?= e(url('tickets/register.php')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> Nuevo ticket
        </a>
    </div>

    <?php if ($tickets === array()) : ?>
        <div class="alert alert-light border">No hay tickets registrados para este usuario.</div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
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
                        <td>
                            <span class="badge text-bg-light border">
                                <?= e((string) $status['name']) ?>
                            </span>
                        </td>
                        <td><?= e((string) $ticket['updated_at']) ?></td>
                        <td class="text-end">
                            <a href="<?= e(url('tickets/detail.php?id=' . (int) $ticket['id'])) ?>" class="btn btn-sm btn-outline-primary">
                                Ver detalle
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php
$main = ob_get_clean();
include __DIR__ . '/include/layout.php';
