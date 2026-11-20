<div class="offcanvas offcanvas-end dashboard-ticket-drawer text-start" tabindex="-1"
     id="dashboardTicketDrawer" aria-labelledby="dashboardTicketDrawerTitle">
    <div class="offcanvas-header border-bottom">
        <div>
            <h2 class="offcanvas-title h5 mb-1" id="dashboardTicketDrawerTitle">Registrar ticket</h2>
            <p class="small text-body-secondary mb-0" role="status" aria-live="polite">
                Bien: <span id="dashboardTicketPropertyContext"></span>
            </p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <form class="dashboard-ticket-drawer-form" action="<?= e(url('tickets/actions/register.php')) ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="property_id" id="dashboardTicketPropertyId">
        <div class="offcanvas-body">
            <p class="text-body-secondary">
                Describe la incidencia del bien seleccionado para crear un ticket asociado.
            </p>
            <div class="mb-3">
                <label for="dashboardTicketNarrative" class="form-label fw-semibold">
                    Relato de la incidencia <span class="text-body-secondary fw-normal">(opcional)</span>
                </label>
                <textarea class="form-control" id="dashboardTicketNarrative" rows="5" maxlength="10000"
                          aria-describedby="dashboardProcessTicketNarrativeStatus"></textarea>
                <div class="form-text">
                    Resume brevemente la solicitud o el problema; no hace falta detallar acciones de mantenimiento.
                </div>
                <div class="mt-2 d-flex flex-column gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm align-self-start"
                            id="dashboardProcessTicketNarrative">
                        <i class="bi bi-magic me-1" id="dashboardProcessTicketNarrativeIcon" aria-hidden="true"></i>
                        <span id="dashboardProcessTicketNarrativeLabel">Procesar relato</span>
                    </button>
                    <span class="form-text" id="dashboardProcessTicketNarrativeStatus"
                          role="status" aria-live="polite">
                        El relato se envía a NVIDIA Build para proponer título, descripción y observación.
                    </span>
                </div>
            </div>
            <div class="mb-3">
                <label for="dashboardTicketTitle" class="form-label fw-semibold">
                    Título <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <input type="text" class="form-control" id="dashboardTicketTitle" name="title"
                       maxlength="255" required>
            </div>
            <div class="mb-3">
                <label for="dashboardTicketDescription" class="form-label fw-semibold">
                    Descripción <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <textarea class="form-control" id="dashboardTicketDescription" name="description"
                          rows="5" maxlength="5000" required></textarea>
            </div>
            <div>
                <label for="dashboardTicketObservation" class="form-label fw-semibold">
                    Observación <span class="text-body-secondary fw-normal">(opcional)</span>
                </label>
                <textarea class="form-control" id="dashboardTicketObservation" name="observation"
                          rows="3" maxlength="5000"></textarea>
            </div>
            <div class="mt-3 d-flex flex-column gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm align-self-start"
                        id="dashboardImproveTicketNarrative">
                    <i class="bi bi-magic me-1" id="dashboardImproveTicketNarrativeIcon" aria-hidden="true"></i>
                    <span id="dashboardImproveTicketNarrativeLabel">Mejorar con IA</span>
                </button>
                <span class="form-text" id="dashboardImproveTicketNarrativeStatus"
                      role="status" aria-live="polite">
                    Los textos se envían a NVIDIA Build. La propuesta no se guarda hasta registrar el ticket.
                </span>
            </div>
        </div>
        <div class="border-top p-3 d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="offcanvas">
                Cancelar
            </button>
            <button type="submit" class="btn btn-primary flex-fill">
                <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Registrar ticket
            </button>
        </div>
    </form>
</div>
