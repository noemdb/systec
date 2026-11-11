<div class="modal fade" id="personalTicketFollowupModal" tabindex="-1"
     aria-labelledby="personalTicketFollowupModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered support-ticket-followup-dialog">
        <div class="modal-content text-start">
            <form method="post" action="<?= e(url('personal/actions/history.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="ticket_id" id="personalTicketFollowupModalId">
                <div class="modal-header">
                    <div class="support-ticket-modal-heading">
                        <h2 class="modal-title fs-5" id="personalTicketFollowupModalTitle">
                            <i class="bi bi-chat-left-text me-2" aria-hidden="true"></i>Añadir seguimiento
                        </h2>
                        <p class="small text-body-secondary text-break mb-0" id="personalTicketFollowupModalSubtitle"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="personalTicketFollowupModalDescription" class="form-label">Nueva nota</label>
                    <textarea class="form-control" id="personalTicketFollowupModalDescription" name="description"
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
    const personalTicketFollowupModal = document.getElementById('personalTicketFollowupModal');
    personalTicketFollowupModal.addEventListener('show.bs.modal', (event) => {
        const ticket = JSON.parse(event.relatedTarget.dataset.ticket);
        document.getElementById('personalTicketFollowupModalId').value = String(ticket.id);
        document.getElementById('personalTicketFollowupModalSubtitle').textContent = `Ticket #${ticket.id} · ${ticket.title}`;
        document.getElementById('personalTicketFollowupModalDescription').value = '';
    });

    personalTicketFollowupModal.addEventListener('hide.bs.modal', () => {
        if (personalTicketFollowupModal.contains(document.activeElement)) document.activeElement.blur();
    });
</script>
