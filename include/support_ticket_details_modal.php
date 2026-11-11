<div class="modal fade" id="supportTicketDetailsModal" tabindex="-1"
     aria-labelledby="supportTicketDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable support-ticket-modal-dialog">
        <div class="modal-content text-start">
            <div class="modal-header">
                <div class="support-ticket-modal-heading">
                    <h2 class="modal-title fs-5" id="supportTicketDetailsTitle">Ticket</h2>
                    <p class="small text-body-secondary text-break mb-0" id="supportTicketDetailsSubtitle"></p>
                </div>
                <span class="badge text-bg-light border" id="supportTicketDetailsStatus"></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 bento-grid">
                    <!-- Columna izquierda - Información principal -->
                    <div class="col-12 col-lg-6">
                        <!-- Tarjeta 1: Descripción -->
                        <section class="bento-card card border h-100" aria-labelledby="supportTicketDescriptionTitle">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h3 class="h6 mb-0" id="supportTicketDescriptionTitle">
                                        <i class="bi bi-card-text me-2" aria-hidden="true"></i>Descripción
                                    </h3>
                                </div>
                                <div class="ticket-modal-copy text-break" id="supportTicketDescription"></div>
                            </div>
                        </section>
                    </div>

                    <!-- Columna derecha - Información del bien -->
                    <div class="col-12 col-lg-6">
                        <!-- Tarjeta 2: Información del bien -->
                        <section class="bento-card card border h-100" aria-labelledby="supportTicketInfoTitle">
                            <div class="card-body">
                                <h3 class="h6 mb-3" id="supportTicketInfoTitle">
                                    <i class="bi bi-box me-2" aria-hidden="true"></i>Información del bien
                                </h3>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <div class="d-flex flex-column">
                                            <span class="small text-secondary mb-1">Bien</span>
                                            <span class="fw-medium" id="supportTicketProperty"></span>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="d-flex flex-column">
                                            <span class="small text-secondary mb-1">Código</span>
                                            <span class="fw-medium" id="supportTicketPropertyCode"></span>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="d-flex flex-column">
                                            <span class="small text-secondary mb-1">Estado</span>
                                            <span class="fw-medium" id="supportTicketPropertyStatus"></span>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="d-flex flex-column">
                                            <span class="small text-secondary mb-1">Categoría</span>
                                            <span class="fw-medium" id="supportTicketPropertyCategory"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- Observación (mitad ancho) -->
                    <div class="col-12 col-md-6">
                        <section class="bento-card card border h-100" aria-labelledby="supportTicketObservationTitle">
                            <div class="card-body">
                                <h3 class="h6 mb-3" id="supportTicketObservationTitle">
                                    <i class="bi bi-clipboard-check me-2" aria-hidden="true"></i>Observación
                                </h3>
                                <div class="ticket-modal-copy text-break" id="supportTicketObservation"></div>
                            </div>
                        </section>
                    </div>

                    <!-- Información del ticket (mitad ancho) -->
                    <div class="col-12 col-md-6">
                        <section class="bento-card card border h-100" aria-labelledby="supportTicketMetaTitle">
                            <div class="card-body">
                                <h3 class="h6 mb-3" id="supportTicketMetaTitle">
                                    <i class="bi bi-info-circle me-2" aria-hidden="true"></i>Información del ticket
                                </h3>
                                <div class="row g-2 small">
                                    <div class="col-6">
                                        <span class="text-secondary">Solicitante</span>
                                    </div>
                                    <div class="col-6 text-end">
                                        <span id="supportTicketCreatedBy"></span>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-secondary">Asignado a</span>
                                    </div>
                                    <div class="col-6 text-end">
                                        <span id="supportTicketAssignedTo"></span>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-secondary">Creado</span>
                                    </div>
                                    <div class="col-6 text-end">
                                        <span id="supportTicketCreatedAt"></span>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-secondary">Actualizado</span>
                                    </div>
                                    <div class="col-6 text-end">
                                        <span id="supportTicketUpdatedAt"></span>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-secondary">Cerrado</span>
                                    </div>
                                    <div class="col-6 text-end">
                                        <span id="supportTicketClosedAt"></span>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- Historial (ancho completo) -->
                    <div class="col-12">
                        <section class="bento-card card border" aria-labelledby="supportTicketHistoryTitle">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                    <h3 class="h6 mb-0" id="supportTicketHistoryTitle">
                                        <i class="bi bi-clock-history me-2" aria-hidden="true"></i>Historial
                                    </h3>
                                    <span class="badge bg-light text-secondary border" id="supportTicketHistoryCount"></span>
                                </div>
                                <div class="d-flex flex-column gap-3" id="supportTicketHistory"></div>
                            </div>
                        </section>
                    </div>
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

        const labels = { created: 'Ticket creado', status_change: 'Cambio de estado', comment: 'Seguimiento', deleted: 'Ticket eliminado' };
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

    supportTicketDetailsModal.addEventListener('hide.bs.modal', () => {
        if (supportTicketDetailsModal.contains(document.activeElement)) document.activeElement.blur();
    });
</script>