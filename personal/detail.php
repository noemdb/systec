<?php
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../include/auth.php';
require_personal_scope();

$db = db();
$user = auth_user();
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : false;
if ($id === false || $id === null) {
    http_response_code(404);
    exit('Ticket no encontrado.');
}

$ticket = $db->getFirstForId('tickets', $id);
if (!$ticket) {
    http_response_code(404);
    exit('Ticket no encontrado.');
}

if (!auth_can_access_ticket_for_user((int) $ticket['created_by'])) {
    http_response_code(403);
    exit('No tiene permiso para ver este ticket.');
}

$property = $db->getFirstForId('properties', (int) $ticket['property_id']);
$status = $db->getFirstForId('ticket_status', (int) $ticket['status_id']);
$history = $db->index('ticket_histories', array('ticket_id' => (int) $ticket['id']), '*', 'created_at ASC, id ASC');

$title = 'Detalle del ticket';
ob_start();
?>
<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Ticket #<?= e((string) $ticket['id']) ?></h1>
            <p class="text-muted mb-0"><?= e((string) $ticket['title']) ?></p>
        </div>
        <a href="<?= e(url('personal/index.php')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a mis tickets
        </a>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <section class="card shadow-sm mb-3" aria-labelledby="ticket-details-title">
                <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                    <h2 class="h6 mb-0" id="ticket-details-title">Datos del ticket</h2>
                    <span class="badge text-bg-light border"><?= e((string) ($status['name'] ?? 'Sin estado')) ?></span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Bien</dt>
                        <dd class="col-sm-8"><?= e((string) ($property['description'] ?? 'No disponible')) ?></dd>
                        <dt class="col-sm-4">Código</dt>
                        <dd class="col-sm-8"><?= e((string) ($property['ident'] ?? '—')) ?></dd>
                        <dt class="col-sm-4">Descripción</dt>
                        <dd class="col-sm-8"><?= nl2br(e((string) $ticket['description'])) ?></dd>
                        <dt class="col-sm-4">Observación</dt>
                        <dd class="col-sm-8"><?= nl2br(e((string) ($ticket['observation'] ?: 'Sin observación adicional.'))) ?></dd>
                    </dl>
                </div>
            </section>

            <section class="card shadow-sm" aria-labelledby="ticket-history-title">
                <div class="card-header">
                    <h2 class="h6 mb-0" id="ticket-history-title">Historial</h2>
                </div>
                <div class="card-body">
                    <?php if ($history === array()) : ?>
                        <p class="text-muted mb-0">Todavía no hay acciones registradas.</p>
                    <?php else : ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($history as $entry) : ?>
                                <article class="border-start border-3 ps-3">
                                    <div class="small text-muted mb-1"><?= e((string) $entry['created_at']) ?></div>
                                    <div class="fw-semibold mb-1"><?= e((string) $entry['action_type']) ?></div>
                                    <div><?= nl2br(e((string) $entry['description'])) ?></div>
                                    <?php if (!empty($entry['observation'])) : ?>
                                        <div class="small text-muted mt-1"><?= nl2br(e((string) $entry['observation'])) ?></div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</div>
<?php
$main = ob_get_clean();
include __DIR__ . '/../include/layout.php';
