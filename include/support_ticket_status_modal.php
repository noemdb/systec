<?php
$supportTicketStatuses = $db->index('ticket_status', array('active' => 1), '*', 'sort_order ASC');
?>
<div class="modal fade" id="supportTicketStatusModal" tabindex="-1"
     aria-labelledby="supportTicketStatusModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered support-ticket-followup-dialog">
        <div class="modal-content text-start">
            <form method="post" action="<?= e(url('tickets/actions/status.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="ticket_id" id="supportTicketStatusModalId">
                <div class="modal-header">
                    <div class="support-ticket-modal-heading">
                        <h2 class="modal-title fs-5" id="supportTicketStatusModalTitle">
                            <i class="bi bi-arrow-repeat me-2" aria-hidden="true"></i>Cambiar estado
                        </h2>
                        <p class="small text-body-secondary text-break mb-0" id="supportTicketStatusModalSubtitle"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="supportTicketStatusModalSelect" class="form-label">Nuevo estado</label>
                    <select class="form-select" id="supportTicketStatusModalSelect" name="status_id" required>
                        <?php foreach ($supportTicketStatuses as $statusOption) : ?>
                            <option value="<?= e((string) $statusOption['id']) ?>"><?= e((string) $statusOption['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Aplicar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    const supportTicketStatusModal = document.getElementById('supportTicketStatusModal');
    supportTicketStatusModal.addEventListener('show.bs.modal', (event) => {
        const ticket = JSON.parse(event.relatedTarget.dataset.ticket);
        document.getElementById('supportTicketStatusModalId').value = String(ticket.id);
        document.getElementById('supportTicketStatusModalSubtitle').textContent =
            `Ticket #${ticket.id} · ${ticket.title} · Estado actual: ${ticket.status}`;
        document.getElementById('supportTicketStatusModalSelect').value = String(ticket.status_id);
    });

    supportTicketStatusModal.addEventListener('hide.bs.modal', () => {
        if (supportTicketStatusModal.contains(document.activeElement)) document.activeElement.blur();
    });
</script>
