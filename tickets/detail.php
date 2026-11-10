<?php
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../include/auth.php';

if (auth_check() && auth_has_role(array('personal'))) {
    $ticketId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : false;
    $redirect = 'personal/index.php';
    if ($ticketId !== false && $ticketId !== null) {
        $redirect = 'personal/detail.php?id=' . (int) $ticketId;
    }
    header('Location: ' . url($redirect));
    exit();
}

require_role(array('admin', 'operador', 'tecnico'));

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
<div class="container py-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Ticket #<?= e((string) $ticket['id']) ?></h1>
            <p class="text-muted mb-0"><?= e((string) $ticket['title']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= e(url('tickets.php')) ?>" class="btn btn-outline-secondary">Volver</a>
            <?php if (!auth_has_role(array('personal'))) : ?>
                <a href="#status-form" class="btn btn-primary">Cambiar estado</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Datos del ticket</span>
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
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header">Historial</div>
                <div class="card-body">
                    <?php if ($history === array()) : ?>
                        <p class="text-muted mb-0">Todavía no hay acciones registradas.</p>
                    <?php else : ?>
                        <div class="timeline">
                            <?php foreach ($history as $entry) : ?>
                                <div class="border-start ps-3 pb-3 position-relative">
                                    <span class="position-absolute start-0 top-0 translate-middle rounded-circle bg-primary" style="width:10px;height:10px;"></span>
                                    <div class="small text-muted mb-1"><?= e((string) $entry['created_at']) ?></div>
                                    <div class="fw-semibold"><?= e((string) $entry['action_type']) ?></div>
                                    <div><?= nl2br(e((string) $entry['description'])) ?></div>
                                    <?php if (!empty($entry['observation'])) : ?>
                                        <div class="small text-muted mt-1"><?= nl2br(e((string) $entry['observation'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header">Seguimiento</div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('tickets/actions/history.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ticket_id" value="<?= e((string) $ticket['id']) ?>">
                        <div class="mb-3">
                            <label for="history_description" class="form-label">Nueva nota</label>
                            <textarea class="form-control" id="history_description" name="description" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-primary w-100">Registrar seguimiento</button>
                    </form>
                </div>
            </div>

            <?php if (!auth_has_role(array('personal'))) : ?>
                <div id="status-form" class="card shadow-sm border-0">
                    <div class="card-header">Cambiar estado</div>
                    <div class="card-body">
                        <form method="post" action="<?= e(url('tickets/actions/status.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="ticket_id" value="<?= e((string) $ticket['id']) ?>">
                            <div class="mb-3">
                                <label for="status_id" class="form-label">Estado</label>
                                <select class="form-select" id="status_id" name="status_id" required>
                                    <?php foreach ($db->index('ticket_status', array('active' => 1), '*', 'sort_order ASC') as $statusOption) : ?>
                                        <option value="<?= e((string) $statusOption['id']) ?>" <?= ((int) $ticket['status_id'] === (int) $statusOption['id'] ? 'selected' : '') ?>>
                                            <?= e((string) $statusOption['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Actualizar estado</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$main = ob_get_clean();
include __DIR__ . '/../include/layout.php';
