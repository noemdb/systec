<div class="offcanvas offcanvas-end personal-ticket-details-drawer text-start" tabindex="-1"
     id="personalTicketDetailsDrawer"
     data-details-url="<?= e(url('personal/actions/details.php')) ?>"
     aria-labelledby="personalTicketDetailsTitle">
    <div class="offcanvas-header border-bottom">
        <div class="personal-ticket-modal-heading me-2">
            <h2 class="offcanvas-title h5 mb-1" id="personalTicketDetailsTitle">
                <i class="bi bi-ticket-detailed text-primary me-2" aria-hidden="true"></i>Ticket
            </h2>
            <p class="small text-body-secondary text-break mb-0" id="personalTicketDetailsSubtitle"></p>
        </div>
        <span class="badge text-bg-light border me-2" id="personalTicketDetailsStatus"></span>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="personal-ticket-drawer-form">
        <div class="offcanvas-body">
            <div id="personalTicketDetailsLoading" class="d-none align-items-center gap-2 text-body-secondary small mb-3" role="status" aria-live="polite">
                <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
                <span>Cargando detalle e historial…</span>
            </div>
            <p id="personalTicketDetailsFeedback" class="small text-body-secondary mb-2" role="status" aria-live="polite"></p>
            <div class="row g-3 bento-grid" id="personalTicketDetailsContent">
                <div class="col-12 col-xl-7">
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
                                    <div class="d-flex flex-column gap-2" id="personalTicketHistory" aria-live="polite"></div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
                <aside class="col-12 col-xl-5" aria-label="Datos del ticket">
                    <section class="bento-card card border h-100" aria-labelledby="personalTicketInfoTitle">
                        <div class="card-body">
                            <h3 class="h6 mb-3" id="personalTicketInfoTitle">
                                <i class="bi bi-info-circle me-2" aria-hidden="true"></i>Datos del ticket
                            </h3>
                            <dl class="ticket-modal-meta row g-2 mb-0">
                                <dt class="col-12 col-sm-5 text-body-secondary">Bien</dt>
                                <dd class="col-12 col-sm-7 text-sm-end text-break mb-2 mb-sm-0" id="personalTicketProperty"></dd>
                                <dt class="col-12 col-sm-5 text-body-secondary">Código</dt>
                                <dd class="col-12 col-sm-7 text-sm-end text-break mb-2 mb-sm-0" id="personalTicketPropertyCode"></dd>
                                <dt class="col-12 col-sm-5 text-body-secondary">Estado del bien</dt>
                                <dd class="col-12 col-sm-7 text-sm-end text-break mb-2 mb-sm-0" id="personalTicketPropertyStatus"></dd>
                                <dt class="col-12 col-sm-5 text-body-secondary">Categoría</dt>
                                <dd class="col-12 col-sm-7 text-sm-end text-break mb-2 mb-sm-0" id="personalTicketDetailsPropertyCategory"></dd>
                                <dt class="col-12 col-sm-5 text-body-secondary">Usuario del bien</dt>
                                <dd class="col-12 col-sm-7 text-sm-end text-break mb-2 mb-sm-0" id="personalTicketPropertyAssigned"></dd>
                                <dt class="col-12 col-sm-5 text-body-secondary">Técnico asignado</dt>
                                <dd class="col-12 col-sm-7 text-sm-end text-break mb-2 mb-sm-0" id="personalTicketAssignedTech"></dd>
                                <dt class="col-12 col-sm-5 text-body-secondary">Estado del ticket</dt>
                                <dd class="col-12 col-sm-7 text-sm-end text-break mb-2 mb-sm-0" id="personalTicketStatusValue"></dd>
                                <dt class="col-12 col-sm-5 text-body-secondary">Creado</dt>
                                <dd class="col-12 col-sm-7 text-sm-end text-break mb-2 mb-sm-0" id="personalTicketCreatedAt"></dd>
                                <dt class="col-12 col-sm-5 text-body-secondary">Actualizado</dt>
                                <dd class="col-12 col-sm-7 text-sm-end text-break mb-2 mb-sm-0" id="personalTicketUpdatedAt"></dd>
                                <dt class="col-12 col-sm-5 text-body-secondary">Cerrado</dt>
                                <dd class="col-12 col-sm-7 text-sm-end text-break mb-0" id="personalTicketClosedAt"></dd>
                            </dl>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
        <div class="border-top p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">Cerrar</button>
            <button type="button" class="btn btn-primary d-none" id="personalTicketDetailsFollowupBtn">
                <i class="bi bi-chat-left-text me-1" aria-hidden="true"></i>Añadir seguimiento
            </button>
        </div>
    </div>
</div>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    const personalTicketDetailsDrawer = document.getElementById('personalTicketDetailsDrawer');
    const personalTicketDetailsLoading = document.getElementById('personalTicketDetailsLoading');
    const personalTicketDetailsContent = document.getElementById('personalTicketDetailsContent');
    const personalTicketDetailsFollowupBtn = document.getElementById('personalTicketDetailsFollowupBtn');
    let personalTicketDetailsRequest = null;
    let currentPersonalDetailsTicket = null;

    const setPersonalDetailsLoading = (isLoading) => {
        personalTicketDetailsLoading.classList.toggle('d-none', !isLoading);
        personalTicketDetailsLoading.classList.toggle('d-flex', isLoading);
        personalTicketDetailsContent.classList.toggle('opacity-50', isLoading);
    };

    const renderPersonalTicketDetails = (ticket) => {
        currentPersonalDetailsTicket = ticket;
        document.getElementById('personalTicketDetailsTitle').textContent = `Ticket #${ticket.id}`;
        document.getElementById('personalTicketDetailsSubtitle').textContent = ticket.title;
        const statusBadge = document.getElementById('personalTicketDetailsStatus');
        const statusClassByCode = {
            iniciado: 'primary',
            en_progreso: 'info',
            con_retraso: 'warning',
            en_espera: 'secondary',
            finalizado: 'success'
        };
        const statusClass = statusClassByCode[ticket.status_code] || 'light';
        statusBadge.className = `badge text-bg-${statusClass} border me-2`;
        statusBadge.textContent = ticket.status_name || 'Sin estado';
        document.getElementById('personalTicketProperty').textContent = ticket.property_description || 'Bien desconocido';
        document.getElementById('personalTicketPropertyCode').textContent = ticket.property_ident || 'Sin código';
        document.getElementById('personalTicketPropertyStatus').textContent = ticket.property_status || 'No disponible';
        const categories = { informatica: 'Informática', redes: 'Redes y telecomunicaciones', otros: 'Otros bienes' };
        document.getElementById('personalTicketDetailsPropertyCategory').textContent =
            categories[ticket.property_category] || 'Otros bienes';
        document.getElementById('personalTicketPropertyAssigned').textContent =
            ticket.property_assigned_label || 'Sin asignar';
        document.getElementById('personalTicketAssignedTech').textContent =
            ticket.assigned_username || 'Sin técnico asignado';
        document.getElementById('personalTicketStatusValue').textContent = ticket.status_name || 'Sin estado';
        document.getElementById('personalTicketDescription').textContent = ticket.description || 'Sin descripción.';
        document.getElementById('personalTicketObservation').textContent = ticket.observation || 'Sin observación adicional.';
        document.getElementById('personalTicketDetailsFeedback').textContent = '';

        const isFinalized = ticket.status_code === 'finalizado';
        personalTicketDetailsFollowupBtn.classList.toggle('d-none', isFinalized);

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
    };

    personalTicketDetailsFollowupBtn.addEventListener('click', () => {
        if (!currentPersonalDetailsTicket || currentPersonalDetailsTicket.status_code === 'finalizado') return;
        const ticketPayload = {
            id: Number(currentPersonalDetailsTicket.id),
            title: String(currentPersonalDetailsTicket.title || '')
        };
        const detailsInstance = window.bootstrap?.Offcanvas?.getOrCreateInstance(personalTicketDetailsDrawer);
        if (detailsInstance) {
            personalTicketDetailsDrawer.addEventListener('hidden.bs.offcanvas', () => {
                if (typeof window.SystecOpenPersonalFollowup === 'function') {
                    window.SystecOpenPersonalFollowup(ticketPayload);
                }
            }, { once: true });
            detailsInstance.hide();
        } else if (typeof window.SystecOpenPersonalFollowup === 'function') {
            window.SystecOpenPersonalFollowup(ticketPayload);
        }
    });

    personalTicketDetailsDrawer.addEventListener('show.bs.offcanvas', async (event) => {
        const ticketId = event.relatedTarget?.dataset.ticketId;
        const feedback = document.getElementById('personalTicketDetailsFeedback');
        const history = document.getElementById('personalTicketHistory');
        currentPersonalDetailsTicket = null;
        personalTicketDetailsFollowupBtn.classList.add('d-none');

        if (!ticketId) {
            feedback.className = 'small text-danger';
            feedback.textContent = 'No se pudo identificar el ticket.';
            setPersonalDetailsLoading(false);
            return;
        }

        personalTicketDetailsRequest?.abort();
        personalTicketDetailsRequest = new AbortController();
        document.getElementById('personalTicketDetailsTitle').textContent = `Ticket #${ticketId}`;
        document.getElementById('personalTicketDetailsSubtitle').textContent = '';
        document.getElementById('personalTicketDetailsStatus').textContent = 'Cargando…';
        feedback.className = 'small text-body-secondary';
        feedback.textContent = '';
        history.replaceChildren();
        setPersonalDetailsLoading(true);

        try {
            const endpoint = new URL(personalTicketDetailsDrawer.dataset.detailsUrl, window.location.href);
            endpoint.searchParams.set('ticket_id', ticketId);
            const response = await fetch(endpoint, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: personalTicketDetailsRequest.signal
            });
            let ticket;
            try {
                ticket = await response.json();
            } catch (error) {
                throw new Error('El servidor devolvió una respuesta inválida al cargar el detalle.');
            }
            if (!response.ok) throw new Error(ticket.error || 'No se pudo cargar el ticket.');
            if (document.getElementById('personalTicketDetailsTitle').textContent !== `Ticket #${ticketId}`) return;
            renderPersonalTicketDetails(ticket);
        } catch (error) {
            if (error.name === 'AbortError') return;
            feedback.className = 'small text-danger';
            feedback.textContent = `${error.message} Cierra el detalle e inténtalo de nuevo.`;
        } finally {
            setPersonalDetailsLoading(false);
        }
    });

    personalTicketDetailsDrawer.addEventListener('hide.bs.offcanvas', () => {
        personalTicketDetailsRequest?.abort();
        if (personalTicketDetailsDrawer.contains(document.activeElement)) document.activeElement.blur();
    });
</script>
