<?php
$ticketDetailsIsPersonal = auth_has_role(array('personal'));
$ticketDetailsCanChangeStatus = auth_has_role(array('admin', 'operador', 'tecnico'));
$ticketDetailsHistoryAction = url($ticketDetailsIsPersonal ? 'personal/actions/history.php' : 'tickets/actions/history.php');
$ticketDetailsPagePrefix = url($ticketDetailsIsPersonal ? 'personal/detail.php?id=' : 'tickets/detail.php?id=');
$ticketDetailsStatuses = $ticketDetailsCanChangeStatus
    ? $db->index('ticket_status', array('active' => 1), '*', 'sort_order ASC')
    : array();
?>
<div class="modal fade" id="ticketDetailsModal" tabindex="-1"
     aria-labelledby="ticketDetailsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable ticket-details-modal-dialog">
        <div class="modal-content text-start">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="ticketDetailsModalTitle">Detalle del ticket</h2>
                    <p class="small text-body-secondary mb-0" id="ticketDetailsModalSubtitle"></p>
                </div>
                <span class="badge text-bg-light border" id="ticketDetailsModalStatus">Sin estado</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-12 col-xl-8">
                        <section class="mb-4" aria-labelledby="ticketDetailsModalDescriptionTitle">
                            <h3 class="h6" id="ticketDetailsModalDescriptionTitle">Descripción</h3>
                            <div class="ticket-modal-copy text-break" id="ticketDetailsModalDescription"></div>
                        </section>
                        <section class="mb-4" aria-labelledby="ticketDetailsModalObservationTitle">
                            <h3 class="h6" id="ticketDetailsModalObservationTitle">Observación</h3>
                            <div class="ticket-modal-copy text-break" id="ticketDetailsModalObservation"></div>
                        </section>
                        <section class="border-top pt-3" aria-labelledby="ticketDetailsModalHistoryTitle">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                <h3 class="h6 mb-0" id="ticketDetailsModalHistoryTitle">Historial</h3>
                                <span class="small text-body-secondary" id="ticketDetailsModalHistoryCount"></span>
                            </div>
                            <div class="d-flex flex-column gap-3" id="ticketDetailsModalHistory"></div>
                        </section>
                    </div>
                    <aside class="col-12 col-xl-4" aria-label="Datos y acciones del ticket">
                        <section class="border-start border-3 ps-3 mb-4" aria-labelledby="ticketDetailsModalInfoTitle">
                            <h3 class="h6 mb-3" id="ticketDetailsModalInfoTitle">Datos del ticket</h3>
                            <dl class="ticket-modal-meta mb-0">
                                <dt>Bien</dt>
                                <dd id="ticketDetailsModalProperty"></dd>
                                <dt>Código del bien</dt>
                                <dd id="ticketDetailsModalPropertyCode"></dd>
                                <dt>Solicitante (ID)</dt>
                                <dd id="ticketDetailsModalCreatedBy"></dd>
                                <dt>Asignado a</dt>
                                <dd id="ticketDetailsModalAssignedTo"></dd>
                                <dt>Creado</dt>
                                <dd id="ticketDetailsModalCreatedAt"></dd>
                                <dt>Actualizado</dt>
                                <dd id="ticketDetailsModalUpdatedAt"></dd>
                                <dt>Cerrado</dt>
                                <dd id="ticketDetailsModalClosedAt"></dd>
                            </dl>
                        </section>

                        <section class="border-top pt-3 mb-4" aria-labelledby="ticketDetailsFollowupTitle">
                            <h3 class="h6" id="ticketDetailsFollowupTitle">Añadir seguimiento</h3>
                            <form method="post" action="<?= e($ticketDetailsHistoryAction) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="ticket_id" id="ticketDetailsFollowupId">
                                <label for="ticketDetailsFollowupDescription" class="form-label small">Nueva nota</label>
                                <textarea class="form-control mb-2" id="ticketDetailsFollowupDescription" name="description" rows="3" required></textarea>
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-chat-left-text me-1" aria-hidden="true"></i>Registrar seguimiento
                                </button>
                            </form>
                        </section>

                        <?php if ($ticketDetailsCanChangeStatus) : ?>
                            <section class="border-top pt-3" aria-labelledby="ticketDetailsStatusTitle">
                                <h3 class="h6" id="ticketDetailsStatusTitle">Cambiar estado</h3>
                                <form method="post" action="<?= e(url('tickets/actions/status.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="ticket_id" id="ticketDetailsStatusTicketId">
                                    <label for="ticketDetailsStatusSelect" class="form-label small">Estado</label>
                                    <select class="form-select mb-2" id="ticketDetailsStatusSelect" name="status_id" required>
                                        <?php foreach ($ticketDetailsStatuses as $statusOption) : ?>
                                            <option value="<?= e((string) $statusOption['id']) ?>"><?= e((string) $statusOption['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Actualizar estado
                                    </button>
                                </form>
                            </section>
                        <?php endif; ?>
                    </aside>
                </div>
            </div>
            <div class="modal-footer">
                <a class="btn btn-outline-primary" id="ticketDetailsModalFullLink" href="#">
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Abrir detalle completo
                </a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    const ticketDetailsModal = document.getElementById('ticketDetailsModal');
    ticketDetailsModal.addEventListener('show.bs.modal', (event) => {
        const ticket = JSON.parse(event.relatedTarget.dataset.ticket);
        document.getElementById('ticketDetailsModalTitle').textContent = `Ticket #${ticket.id}`;
        document.getElementById('ticketDetailsModalSubtitle').textContent = ticket.title;
        const statusBadge = document.getElementById('ticketDetailsModalStatus');
        const allowedStatusClasses = ['primary', 'info', 'warning', 'secondary', 'success', 'light'];
        const statusClass = allowedStatusClasses.includes(ticket.status_class) ? ticket.status_class : 'light';
        statusBadge.className = `badge text-bg-${statusClass}`;
        statusBadge.textContent = ticket.status;
        document.getElementById('ticketDetailsModalProperty').textContent = ticket.property;
        document.getElementById('ticketDetailsModalPropertyCode').textContent = ticket.property_code;
        document.getElementById('ticketDetailsModalCreatedBy').textContent = String(ticket.created_by || '');
        document.getElementById('ticketDetailsModalAssignedTo').textContent = ticket.assigned_to || 'Sin asignar';
        document.getElementById('ticketDetailsModalCreatedAt').textContent = ticket.created_at || 'No disponible';
        document.getElementById('ticketDetailsModalUpdatedAt').textContent = ticket.updated_at || 'No disponible';
        document.getElementById('ticketDetailsModalClosedAt').textContent = ticket.closed_at || 'No cerrado';
        document.getElementById('ticketDetailsModalDescription').textContent = ticket.description || 'Sin descripción.';
        document.getElementById('ticketDetailsModalObservation').textContent = ticket.observation || 'Sin observación adicional.';
        document.getElementById('ticketDetailsModalFullLink').href = <?= js($ticketDetailsPagePrefix) ?> + ticket.id;
        document.getElementById('ticketDetailsFollowupId').value = String(ticket.id);
        const statusTicketId = document.getElementById('ticketDetailsStatusTicketId');
        const statusSelect = document.getElementById('ticketDetailsStatusSelect');
        if (statusTicketId && statusSelect) {
            statusTicketId.value = String(ticket.id);
            statusSelect.value = String(ticket.status_id);
        }

        const historyContainer = document.getElementById('ticketDetailsModalHistory');
        historyContainer.replaceChildren();
        document.getElementById('ticketDetailsModalHistoryCount').textContent = ticket.history.length === 1
            ? '1 evento'
            : `${ticket.history.length} eventos`;
        if (!Array.isArray(ticket.history) || ticket.history.length === 0) {
            const emptyMessage = document.createElement('p');
            emptyMessage.className = 'text-body-secondary mb-0';
            emptyMessage.textContent = 'Todavía no hay acciones registradas.';
            historyContainer.appendChild(emptyMessage);
            return;
        }

        ticket.history.forEach((entry) => {
            const article = document.createElement('article');
            article.className = 'border-start border-3 ps-3';
            const date = document.createElement('div');
            date.className = 'small text-body-secondary mb-1';
            date.textContent = entry.created_at;
            const action = document.createElement('div');
            action.className = 'fw-semibold mb-1';
            action.textContent = entry.action_type;
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
            historyContainer.appendChild(article);
        });
    });

    ticketDetailsModal.addEventListener('hide.bs.modal', () => {
        if (ticketDetailsModal.contains(document.activeElement)) {
            document.activeElement.blur();
        }
    });
</script>