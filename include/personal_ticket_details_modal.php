<div class="modal fade" id="personalTicketDetailsModal" tabindex="-1"
     aria-labelledby="personalTicketDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable personal-ticket-modal-dialog">
        <div class="modal-content text-start">
            <div class="modal-header">
                <div class="personal-ticket-modal-heading">
                    <h2 class="modal-title fs-5" id="personalTicketDetailsTitle">
                        <i class="bi bi-ticket-detailed text-primary me-2" aria-hidden="true"></i>Ticket
                    </h2>
                    <p class="small text-body-secondary text-break mb-0" id="personalTicketDetailsSubtitle"></p>
                </div>
                <span class="badge text-bg-light border" id="personalTicketDetailsStatus"></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 bento-grid">
                    <div class="col-12 col-lg-8">
                        <div class="row g-3">
                            <div class="col-12">
                                <section class="bento-card card border h-100" aria-labelledby="personalTicketDescriptionTitle">
                                    <div class="card-body">
                                        <h3 class="h6 mb-3" id="personalTicketDescriptionTitle">
                                            <i class="bi bi-card-text me-2" aria-hidden="true"></i>Descripción
                                        </h3>
                                        <div class="ticket-modal-copy text-break" id="personalTicketDescription"></div>
                                    </div>
                                </section>
                            </div>
                            <div class="col-12">
                                <section class="bento-card card border h-100" aria-labelledby="personalTicketObservationTitle">
                                    <div class="card-body">
                                        <h3 class="h6 mb-3" id="personalTicketObservationTitle">
                                            <i class="bi bi-clipboard-check me-2" aria-hidden="true"></i>Observación
                                        </h3>
                                        <div class="ticket-modal-copy text-break" id="personalTicketObservation"></div>
                                    </div>
                                </section>
                            </div>
                            <div class="col-12">
                                <section class="bento-card card border h-100" aria-labelledby="personalTicketHistoryTitle">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                            <h3 class="h6 mb-0" id="personalTicketHistoryTitle">
                                                <i class="bi bi-clock-history me-2" aria-hidden="true"></i>Historial
                                            </h3>
                                            <span class="badge bg-light text-secondary border" id="personalTicketHistoryCount"></span>
                                        </div>
                                        <div class="d-flex flex-column gap-2" id="personalTicketHistory"></div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                    <aside class="col-12 col-lg-4" aria-label="Datos del ticket">
                        <section class="bento-card card border h-100" aria-labelledby="personalTicketInfoTitle">
                            <div class="card-body">
                                <h3 class="h6 mb-3" id="personalTicketInfoTitle">
                                    <i class="bi bi-info-circle me-2" aria-hidden="true"></i>Datos del ticket
                                </h3>
                                <dl class="ticket-modal-meta row g-2 mb-0">
                                    <dt class="col-5">Bien</dt>
                                    <dd class="col-7 text-end" id="personalTicketProperty"></dd>
                                    <dt class="col-5">Código</dt>
                                    <dd class="col-7 text-end" id="personalTicketPropertyCode"></dd>
                                    <dt class="col-5">Estado del bien</dt>
                                    <dd class="col-7 text-end" id="personalTicketPropertyStatus"></dd>
                                    <dt class="col-5">Categoría</dt>
                                    <dd class="col-7 text-end" id="personalTicketPropertyCategory"></dd>
                                    <dt class="col-5">Estado del ticket</dt>
                                    <dd class="col-7 text-end" id="personalTicketStatusValue"></dd>
                                    <dt class="col-5">Creado</dt>
                                    <dd class="col-7 text-end" id="personalTicketCreatedAt"></dd>
                                    <dt class="col-5">Actualizado</dt>
                                    <dd class="col-7 text-end" id="personalTicketUpdatedAt"></dd>
                                    <dt class="col-5">Cerrado</dt>
                                    <dd class="col-7 text-end" id="personalTicketClosedAt"></dd>
                                </dl>
                            </div>
                        </section>
                    </aside>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    const personalTicketDetailsModal = document.getElementById('personalTicketDetailsModal');
    personalTicketDetailsModal.addEventListener('show.bs.modal', (event) => {
        const ticket = JSON.parse(event.relatedTarget.dataset.ticket);
        document.getElementById('personalTicketDetailsTitle').textContent = `Ticket #${ticket.id}`;
        document.getElementById('personalTicketDetailsSubtitle').textContent = ticket.title;
        const statusBadge = document.getElementById('personalTicketDetailsStatus');
        const allowedStatusClasses = ['primary', 'info', 'warning', 'secondary', 'success', 'light'];
        statusBadge.className = `badge text-bg-${allowedStatusClasses.includes(ticket.status_class) ? ticket.status_class : 'light'}`;
        statusBadge.textContent = ticket.status;
        document.getElementById('personalTicketProperty').textContent = ticket.property;
        document.getElementById('personalTicketPropertyCode').textContent = ticket.property_code;
        document.getElementById('personalTicketPropertyStatus').textContent = ticket.property_status || 'No disponible';
        const categories = { informatica: 'Informática', redes: 'Redes y telecomunicaciones', otros: 'Otros bienes' };
        document.getElementById('personalTicketPropertyCategory').textContent = categories[ticket.property_category] || 'Otros bienes';
        document.getElementById('personalTicketStatusValue').textContent = ticket.status;
        document.getElementById('personalTicketCreatedAt').textContent = ticket.created_at || 'No disponible';
        document.getElementById('personalTicketUpdatedAt').textContent = ticket.updated_at || 'No disponible';
        document.getElementById('personalTicketClosedAt').textContent = ticket.closed_at || 'No cerrado';
        document.getElementById('personalTicketDescription').textContent = ticket.description || 'Sin descripción.';
        document.getElementById('personalTicketObservation').textContent = ticket.observation || 'Sin observación adicional.';

        const formatDateTime = (value) => {
            if (!value) return 'No disponible';
            const date = new Date(String(value).replace(' ', 'T'));
            return Number.isNaN(date.getTime())
                ? value
                : new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
        };
        document.getElementById('personalTicketCreatedAt').textContent = formatDateTime(ticket.created_at);
        document.getElementById('personalTicketUpdatedAt').textContent = formatDateTime(ticket.updated_at);
        document.getElementById('personalTicketClosedAt').textContent = ticket.closed_at
            ? formatDateTime(ticket.closed_at)
            : 'No cerrado';

        const history = document.getElementById('personalTicketHistory');
        history.replaceChildren();
        const entries = Array.isArray(ticket.history) ? ticket.history : [];
        document.getElementById('personalTicketHistoryCount').textContent = entries.length === 1
            ? '1 evento'
            : `${entries.length} eventos`;
        if (entries.length === 0) {
            const emptyMessage = document.createElement('p');
            emptyMessage.className = 'text-body-secondary mb-0';
            emptyMessage.textContent = 'Todavía no hay acciones registradas.';
            history.appendChild(emptyMessage);
            return;
        }

        const labels = { created: 'Ticket creado', status_change: 'Cambio de estado', comment: 'Seguimiento' };
        const actorStyles = [
            { border: 'border-primary', badge: 'bg-primary-subtle text-primary-emphasis' },
            { border: 'border-success', badge: 'bg-success-subtle text-success-emphasis' },
            { border: 'border-danger', badge: 'bg-danger-subtle text-danger-emphasis' },
            { border: 'border-warning', badge: 'bg-warning-subtle text-warning-emphasis' },
            { border: 'border-info', badge: 'bg-info-subtle text-info-emphasis' },
            { border: 'border-secondary', badge: 'bg-secondary-subtle text-secondary-emphasis' }
        ];
        const actorStyleByName = new Map();
        entries.forEach((entry) => {
            const actorName = entry.actor_username || 'Usuario no disponible';
            if (!actorStyleByName.has(actorName)) {
                actorStyleByName.set(actorName, actorStyles[actorStyleByName.size % actorStyles.length]);
            }
            const actorStyle = actorStyleByName.get(actorName);
            const article = document.createElement('article');
            article.className = `border-start border-3 ps-3 ${actorStyle.border}`;
            const date = document.createElement('div');
            date.className = 'small text-body-secondary mb-1';
            date.textContent = formatDateTime(entry.created_at);
            const actor = document.createElement('span');
            actor.className = `badge rounded-pill ${actorStyle.badge} mb-2`;
            actor.textContent = `Registrado por: ${actorName}`;
            const action = document.createElement('div');
            action.className = 'fw-semibold mb-1';
            action.textContent = labels[entry.action_type] || entry.action_type;
            const description = document.createElement('div');
            description.className = 'ticket-modal-copy text-break';
            description.textContent = entry.description;
            article.append(date, actor, action, description);
            if (entry.observation) {
                const observation = document.createElement('div');
                observation.className = 'small text-body-secondary mt-1 ticket-modal-copy text-break';
                observation.textContent = entry.observation;
                article.appendChild(observation);
            }
            history.appendChild(article);
        });
    });

    personalTicketDetailsModal.addEventListener('hide.bs.modal', () => {
        if (personalTicketDetailsModal.contains(document.activeElement)) document.activeElement.blur();
    });
</script>