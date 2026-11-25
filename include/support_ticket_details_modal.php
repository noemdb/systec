<div class="offcanvas offcanvas-end support-ticket-details-drawer text-start" tabindex="-1"
     id="supportTicketDetailsDrawer" aria-labelledby="supportTicketDetailsTitle">
    <div class="offcanvas-header border-bottom">
        <div class="support-ticket-drawer-heading me-2">
            <h2 class="offcanvas-title h5 mb-1" id="supportTicketDetailsTitle">
                <i class="bi bi-ticket-detailed text-primary me-2" aria-hidden="true"></i>Ticket
            </h2>
            <p class="small text-body-secondary text-break mb-0" id="supportTicketDetailsSubtitle"></p>
        </div>
        <span class="badge text-bg-light border me-2" id="supportTicketDetailsStatus"></span>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="support-ticket-drawer-form">
        <div class="offcanvas-body">
            <div class="row g-3 bento-grid">
                <!-- Descripción -->
                <div class="col-12 col-md-6">
                    <section class="bento-card card border h-100" aria-labelledby="supportTicketDescriptionTitle">
                        <div class="card-body">
                            <h3 class="h6 mb-3" id="supportTicketDescriptionTitle">
                                <i class="bi bi-card-text me-2" aria-hidden="true"></i>Descripción
                            </h3>
                            <div class="ticket-modal-copy text-break" id="supportTicketDescription"></div>
                        </div>
                    </section>
                </div>

                <!-- Información del bien -->
                <div class="col-12 col-md-6">
                    <section class="bento-card card border h-100" aria-labelledby="supportTicketInfoTitle">
                        <div class="card-body">
                            <h3 class="h6 mb-3" id="supportTicketInfoTitle">
                                <i class="bi bi-box me-2" aria-hidden="true"></i>Información del bien
                            </h3>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <div class="d-flex flex-column">
                                        <span class="small text-secondary mb-1">Bien</span>
                                        <span class="fw-medium text-break" id="supportTicketProperty"></span>
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

                <!-- Observación -->
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

                <!-- Información del ticket -->
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
                                    <span class="text-secondary">Usuario asignado al bien</span>
                                </div>
                                <div class="col-6 text-end">
                                    <span class="d-block text-break" id="supportTicketPropertyAssigned"></span>
                                    <small class="d-block text-body-secondary" id="supportTicketPropertyAssignedStatus"></small>
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

                <!-- Mantenimientos asociados -->
                <div class="col-12">
                    <section class="bento-card card border" aria-labelledby="supportTicketMaintenanceTitle">
                        <div class="card-body">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <h3 class="h6 mb-0" id="supportTicketMaintenanceTitle">
                                        <i class="bi bi-tools me-2" aria-hidden="true"></i>Mantenimientos asociados
                                    </h3>
                                    <span class="badge bg-light text-secondary border" id="supportTicketMaintenanceCount"></span>
                                </div>
                                <a class="btn btn-sm btn-outline-secondary d-none" id="supportTicketMaintenanceLink" href="#">
                                    <i class="bi bi-tools me-1" aria-hidden="true"></i>Ver mantenimientos del bien
                                </a>
                            </div>
                            <div class="d-flex flex-column gap-3" id="supportTicketMaintenances" aria-live="polite"></div>
                        </div>
                    </section>
                </div>

                <!-- Historial -->
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
        <div class="border-top p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">Cerrar</button>
            <?php if ($canManageTickets) : ?>
                <a class="btn btn-outline-primary" id="supportTicketFullLink" href="#">
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Abrir detalle completo
                </a>
            <?php else : ?>
                <button type="button" class="btn btn-outline-primary" disabled>
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Abrir detalle completo
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    const supportTicketDetailsDrawer = document.getElementById('supportTicketDetailsDrawer');
    const supportTicketMaintenanceSearchUrl = <?= js(url('maintenances.php?search=')) ?>;

    const renderSupportTicketMaintenances = (ticket) => {
        const container = document.getElementById('supportTicketMaintenances');
        const countBadge = document.getElementById('supportTicketMaintenanceCount');
        const link = document.getElementById('supportTicketMaintenanceLink');
        const maintenances = Array.isArray(ticket.maintenances) ? ticket.maintenances : [];
        const allowedBadgeClasses = ['text-bg-primary', 'text-bg-info', 'text-bg-warning', 'text-bg-secondary', 'text-bg-success', 'text-bg-danger'];

        container.replaceChildren();
        countBadge.textContent = maintenances.length === 1
            ? '1 mantenimiento'
            : `${maintenances.length} mantenimientos`;

        const searchCode = String(ticket.property_search_code || ticket.property_code || '').trim();
        if (searchCode && searchCode !== 'Sin código') {
            link.href = supportTicketMaintenanceSearchUrl + encodeURIComponent(searchCode);
            link.classList.remove('d-none');
        } else {
            link.href = '#';
            link.classList.add('d-none');
        }

        if (maintenances.length === 0) {
            const emptyMessage = document.createElement('p');
            emptyMessage.className = 'text-body-secondary mb-0';
            emptyMessage.textContent = 'No hay mantenimientos asociados a este ticket.';
            container.appendChild(emptyMessage);
            return;
        }

        const appendMetaRow = (list, label, value) => {
            const term = document.createElement('dt');
            term.className = 'col-12 col-sm-4 text-body-secondary fw-normal';
            term.textContent = label;
            const description = document.createElement('dd');
            description.className = 'col-12 col-sm-8 text-sm-end text-break mb-2 mb-sm-0';
            description.textContent = value || 'No disponible';
            list.append(term, description);
        };

        const appendCopyBlock = (card, label, value) => {
            if (!String(value || '').trim()) {
                return;
            }
            const heading = document.createElement('h4');
            heading.className = 'h6 text-body-secondary mb-1';
            heading.textContent = label;
            const copy = document.createElement('p');
            copy.className = 'ticket-modal-copy text-break mb-0';
            copy.textContent = value;
            card.append(heading, copy);
        };

        maintenances.forEach((maintenance) => {
            const card = document.createElement('article');
            card.className = 'border rounded-3 bg-body-tertiary p-3';

            const header = document.createElement('div');
            header.className = 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3';

            const headingGroup = document.createElement('div');
            headingGroup.className = 'd-flex flex-wrap align-items-center gap-2';
            const heading = document.createElement('h4');
            heading.className = 'h6 mb-0';
            heading.textContent = `Mantenimiento #${maintenance.id}`;
            const typeBadge = document.createElement('span');
            typeBadge.className = `badge rounded-pill ${allowedBadgeClasses.includes(maintenance.type_badge) ? maintenance.type_badge : 'text-bg-secondary'}`;
            typeBadge.textContent = maintenance.type || 'Sin tipo';
            headingGroup.append(heading, typeBadge);

            const statusBadge = document.createElement('span');
            statusBadge.className = `badge rounded-pill ${allowedBadgeClasses.includes(maintenance.status_badge) ? maintenance.status_badge : 'text-bg-secondary'}`;
            statusBadge.textContent = maintenance.status || 'Sin estado';
            header.append(headingGroup, statusBadge);

            const meta = document.createElement('dl');
            meta.className = 'row g-2 small mb-3';
            appendMetaRow(meta, 'Código', maintenance.code);
            appendMetaRow(meta, 'Fecha', maintenance.date);
            appendMetaRow(meta, 'Técnico', maintenance.technician);
            appendMetaRow(meta, 'Tiempo empleado', maintenance.time_taken);
            appendMetaRow(meta, 'Próximo mantenimiento', maintenance.next_maintenance_date);

            card.append(header, meta);
            appendCopyBlock(card, 'Trabajo realizado', maintenance.description || 'Sin descripción.');
            if (String(maintenance.failure_reason || '').trim()) {
                const reasonBlock = document.createElement('div');
                reasonBlock.className = 'mt-3';
                appendCopyBlock(reasonBlock, 'Razón del mantenimiento', maintenance.failure_reason);
                card.appendChild(reasonBlock);
            }
            if (String(maintenance.notes || '').trim()) {
                const notesBlock = document.createElement('div');
                notesBlock.className = 'mt-3';
                appendCopyBlock(notesBlock, 'Notas', maintenance.notes);
                card.appendChild(notesBlock);
            }
            container.appendChild(card);
        });
    };

    supportTicketDetailsDrawer.addEventListener('show.bs.offcanvas', (event) => {
        const ticket = JSON.parse(event.relatedTarget.dataset.ticket);
        document.getElementById('supportTicketDetailsTitle').textContent = `Ticket #${ticket.id}`;
        document.getElementById('supportTicketDetailsSubtitle').textContent = ticket.title;
        const statusBadge = document.getElementById('supportTicketDetailsStatus');
        const allowedStatusClasses = ['primary', 'info', 'warning', 'secondary', 'success', 'light'];
        statusBadge.className = `badge text-bg-${allowedStatusClasses.includes(ticket.status_class) ? ticket.status_class : 'light'} border me-2`;
        statusBadge.textContent = ticket.status;
        document.getElementById('supportTicketProperty').textContent = ticket.property;
        document.getElementById('supportTicketPropertyCode').textContent = ticket.property_code;
        document.getElementById('supportTicketPropertyStatus').textContent = ticket.property_status || 'No disponible';
        const categories = { informatica: 'Informática', redes: 'Redes y telecomunicaciones', otros: 'Otros bienes' };
        document.getElementById('supportTicketPropertyCategory').textContent = categories[ticket.property_category] || 'Otros bienes';
        document.getElementById('supportTicketCreatedBy').textContent = ticket.created_by_name
            ? `${ticket.created_by_name} (#${ticket.created_by})`
            : `Usuario #${ticket.created_by}`;
        document.getElementById('supportTicketPropertyAssigned').textContent = ticket.property_assigned_label || 'Sin asignar';
        const propertyAssignedStatus = document.getElementById('supportTicketPropertyAssignedStatus');
        propertyAssignedStatus.textContent = ticket.property_assigned_status_label || '';
        propertyAssignedStatus.classList.toggle('d-none', !ticket.property_assigned_name);
        document.getElementById('supportTicketDescription').textContent = ticket.description || 'Sin descripción.';
        document.getElementById('supportTicketObservation').textContent = ticket.observation || 'Sin observación adicional.';
        const fullTicketLink = document.getElementById('supportTicketFullLink');
        if (fullTicketLink) {
            fullTicketLink.href = <?= js(url('tickets/detail.php?id=')) ?> + ticket.id;
        }

        renderSupportTicketMaintenances(ticket);

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

    supportTicketDetailsDrawer.addEventListener('hide.bs.offcanvas', () => {
        if (supportTicketDetailsDrawer.contains(document.activeElement)) document.activeElement.blur();
    });
</script>
