<div class="modal fade" id="supportTicketFollowupModal" tabindex="-1"
     aria-labelledby="supportTicketFollowupModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered support-ticket-followup-dialog">
        <div class="modal-content text-start">
            <form method="post" action="<?= e(url('tickets/actions/history.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="ticket_id" id="supportTicketFollowupModalId">
                <div class="modal-header">
                    <div class="support-ticket-modal-heading">
                        <h2 class="modal-title fs-5" id="supportTicketFollowupModalTitle">
                            <i class="bi bi-chat-left-text me-2" aria-hidden="true"></i>Añadir seguimiento
                        </h2>
                        <p class="small text-body-secondary text-break mb-0" id="supportTicketFollowupModalSubtitle"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="supportTicketFollowupModalDescription" class="form-label">Nueva nota</label>
                    <textarea class="form-control" id="supportTicketFollowupModalDescription" name="description"
                              rows="4" placeholder="Escribe tu comentario aquí..." required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1" aria-hidden="true"></i>Registrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    const supportTicketFollowupModal = document.getElementById('supportTicketFollowupModal');
    supportTicketFollowupModal.addEventListener('show.bs.modal', (event) => {
        const ticket = JSON.parse(event.relatedTarget.dataset.ticket);
        document.getElementById('supportTicketFollowupModalId').value = String(ticket.id);
        document.getElementById('supportTicketFollowupModalSubtitle').textContent = `Ticket #${ticket.id} · ${ticket.title}`;
        document.getElementById('supportTicketFollowupModalDescription').value = '';
    });

    supportTicketFollowupModal.addEventListener('hide.bs.modal', () => {
        if (supportTicketFollowupModal.contains(document.activeElement)) document.activeElement.blur();
    });
</script>
