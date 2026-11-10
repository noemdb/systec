<?php
$supportTicketStatuses = $db->index('ticket_status', array('active' => 1), '*', 'sort_order ASC');
?>
<div class="modal fade" id="supportTicketDetailsModal" tabindex="-1"
     aria-labelledby="supportTicketDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable support-ticket-modal-dialog">
        <div class="modal-content text-start">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="supportTicketDetailsTitle">Ticket</h2>
                    <p class="small text-body-secondary mb-0" id="supportTicketDetailsSubtitle"></p>
                </div>
                <span class="badge text-bg-light border" id="supportTicketDetailsStatus"></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-12 col-xl-8">
                        <section class="mb-4" aria-labelledby="supportTicketDescriptionTitle">
                            <h3 class="h6" id="supportTicketDescriptionTitle">Descripción</h3>
                            <div class="ticket-modal-copy text-break" id="supportTicketDescription"></div>
                        </section>
                        <section class="mb-4" aria-labelledby="supportTicketObservationTitle">
                            <h3 class="h6" id="supportTicketObservationTitle">Observación</h3>
                            <div class="ticket-modal-copy text-break" id="supportTicketObservation"></div>
                        </section>
                        <section class="border-top pt-3" aria-labelledby="supportTicketHistoryTitle">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                <h3 class="h6 mb-0" id="supportTicketHistoryTitle">Historial</h3>
                                <span class="small text-body-secondary" id="supportTicketHistoryCount"></span>
                            </div>
                            <div class="d-flex flex-column gap-3" id="supportTicketHistory"></div>
                        </section>
                    </div>
                    <aside class="col-12 col-xl-4" aria-label="Datos y acciones del ticket">
                        <section class="border-start border-3 ps-3 mb-4" aria-labelledby="supportTicketInfoTitle">
                            <h3 class="h6 mb-3" id="supportTicketInfoTitle">Datos del ticket</h3>
                            <dl class="ticket-modal-meta mb-0">
                                <dt>Bien</dt>
                                <dd id="supportTicketProperty"></dd>
                                <dt>Código del bien</dt>
                                <dd id="supportTicketPropertyCode"></dd>
                                <dt>Estado del bien</dt>
                                <dd id="supportTicketPropertyStatus"></dd>
                                <dt>Categoría</dt>
                                <dd id="supportTicketPropertyCategory"></dd>
                                <dt>Solicitante</dt>
                                <dd id="supportTicketCreatedBy"></dd>
                                <dt>Asignado a</dt>
                                <dd id="supportTicketAssignedTo"></dd>
                                <dt>Creado</dt>
                                <dd id="supportTicketCreatedAt"></dd>
                                <dt>Actualizado</dt>
                                <dd id="supportTicketUpdatedAt"></dd>
                                <dt>Cerrado</dt>
                                <dd id="supportTicketClosedAt"></dd>
                            </dl>
                        </section>
                        <section class="border-top pt-3 mb-4" aria-labelledby="supportTicketFollowupTitle">
                            <h3 class="h6" id="supportTicketFollowupTitle">Añadir seguimiento</h3>
                            <form method="post" action="<?= e(url('tickets/actions/history.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="ticket_id" id="supportTicketFollowupId">
                                <label for="supportTicketFollowupDescription" class="form-label small">Nueva nota</label>
                                <textarea class="form-control mb-2" id="supportTicketFollowupDescription" name="description" rows="3" required></textarea>
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-chat-left-text me-1" aria-hidden="true"></i>Registrar seguimiento
                                </button>
                            </form>
                        </section>
                        <section class="border-top pt-3" aria-labelledby="supportTicketStatusTitle">
                            <h3 class="h6" id="supportTicketStatusTitle">Cambiar estado</h3>
                            <form method="post" action="<?= e(url('tickets/actions/status.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="ticket_id" id="supportTicketStatusId">
                                <label for="supportTicketStatusSelect" class="form-label small">Estado</label>
                                <select class="form-select mb-2" id="supportTicketStatusSelect" name="status_id" required>
                                    <?php foreach ($supportTicketStatuses as $statusOption) : ?>
                                        <option value="<?= e((string) $statusOption['id']) ?>"><?= e((string) $statusOption['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Actualizar estado
                                </button>
                            </form>
                        </section>
                    </aside>
                </div>
            </div>
            <div class="modal-footer">
                <a class="btn btn-outline-primary" id="supportTicketFullLink" href="#">
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Abrir detalle completo
                </a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    const supportTicketDetailsModal = document.getElementById('supportTicketDetailsModal');
    supportTicketDetailsModal.addEventListener('show.bs.modal', (event) => {
        const ticket = JSON.parse(event.relatedTarget.dataset.ticket);
        document.getElementById('supportTicketDetailsTitle').textContent = `Ticket #${ticket.id}`;
        document.getElementById('supportTicketDetailsSubtitle').textContent = ticket.title;
        const statusBadge = document.getElementById('supportTicketDetailsStatus');
        const allowedStatusClasses = ['primary', 'info', 'warning', 'secondary', 'success', 'light'];
        statusBadge.className = `badge text-bg-${allowedStatusClasses.includes(ticket.status_class) ? ticket.status_class : 'light'}`;
        statusBadge.textContent = ticket.status;
        document.getElementById('supportTicketProperty').textContent = ticket.property;
        document.getElementById('supportTicketPropertyCode').textContent = ticket.property_code;
        document.getElementById('supportTicketPropertyStatus').textContent = ticket.property_status || 'No disponible';
        const categories = { informatica: 'Informática', redes: 'Redes y telecomunicaciones', otros: 'Otros bienes' };
        document.getElementById('supportTicketPropertyCategory').textContent = categories[ticket.property_category] || 'Otros bienes';
        document.getElementById('supportTicketCreatedBy').textContent = ticket.created_by_name
            ? `${ticket.created_by_name} (#${ticket.created_by})`
            : `Usuario #${ticket.created_by}`;
        document.getElementById('supportTicketAssignedTo').textContent = ticket.assigned_to || 'Sin asignar';
        document.getElementById('supportTicketDescription').textContent = ticket.description || 'Sin descripción.';
        document.getElementById('supportTicketObservation').textContent = ticket.observation || 'Sin observación adicional.';
        document.getElementById('supportTicketFullLink').href = <?= js(url('tickets/detail.php?id=')) ?> + ticket.id;
        document.getElementById('supportTicketFollowupId').value = String(ticket.id);
        document.getElementById('supportTicketStatusId').value = String(ticket.id);
        document.getElementById('supportTicketStatusSelect').value = String(ticket.status_id);

        const formatDateTime = (value) => {
            if (!value) return 'No disponible';
            const date = new Date(String(value).replace(' ', 'T'));
            return Number.isNaN(date.getTime())
                ? value
                : new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
        };
        document.getElementById('supportTicketCreatedAt').textContent = formatDateTime(ticket.created_at);
        document.getElementById('supportTicketUpdatedAt').textContent = formatDateTime(ticket.updated_at);
        document.getElementById('supportTicketClosedAt').textContent = ticket.closed_at
            ? formatDateTime(ticket.closed_at)
            : 'No cerrado';

        const history = document.getElementById('supportTicketHistory');
        history.replaceChildren();
        const entries = Array.isArray(ticket.history) ? ticket.history : [];
        document.getElementById('supportTicketHistoryCount').textContent = entries.length === 1 ? '1 evento' : `${entries.length} eventos`;
        if (!entries.length) {
            const emptyMessage = document.createElement('p');
            emptyMessage.className = 'text-body-secondary mb-0';
            emptyMessage.textContent = 'Todavía no hay acciones registradas.';
            history.appendChild(emptyMessage);
            return;
        }

        const labels = { created: 'Ticket creado', status_change: 'Cambio de estado', comment: 'Seguimiento' };
        entries.forEach((entry) => {
            const article = document.createElement('article');
            article.className = 'border-start border-3 ps-3';
            const date = document.createElement('div');
            date.className = 'small text-body-secondary mb-1';
            date.textContent = formatDateTime(entry.created_at);
            const action = document.createElement('div');
            action.className = 'fw-semibold mb-1';
            action.textContent = labels[entry.action_type] || entry.action_type;
            const description = document.createElement('div');
            description.className = 'ticket-modal-copy text-break';
            description.textContent = entry.description;
            article.append(date, action, description);
            if (entry.observation) {
                const observation = document.createElement('div');
                observation.className = 'small text-body-secondary mt-1 ticket-modal-copy text-break';
                observation.textContent = entry.observation;
                article.appendChild(observation);
            }
            history.appendChild(article);
        });
    });

    supportTicketDetailsModal.addEventListener('hide.bs.modal', () => {
        if (supportTicketDetailsModal.contains(document.activeElement)) document.activeElement.blur();
    });
</script>