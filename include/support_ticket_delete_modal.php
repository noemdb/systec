<?php if (auth_has_role(array('admin', 'operador'))) : ?>
    <div class="modal fade" id="deleteTicketModal" tabindex="-1"
         aria-labelledby="deleteTicketModalTitle" aria-describedby="deleteTicketModalHelp" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content text-start">
                <form method="post" action="<?= e(url('tickets/actions/delete.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="ticket_id" id="deleteTicketId">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="deleteTicketModalTitle">Eliminar ticket</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p id="deleteTicketModalHelp">
                            ¿Confirmas eliminar <strong id="deleteTicketTitle"></strong>?
                        </p>
                        <div class="alert alert-warning mb-0" role="note">
                            El ticket dejará de aparecer en las listas, pero su historial se conservará.
                            Esta acción no se puede deshacer desde la aplicación.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Confirmar eliminación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script nonce="<?= e(systec_csp_nonce()) ?>">
        document.getElementById('deleteTicketModal').addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (!button) return;
            document.getElementById('deleteTicketId').value = button.dataset.ticketId;
            document.getElementById('deleteTicketTitle').textContent = `#${button.dataset.ticketId} — ${button.dataset.ticketTitle}`;
        });
    </script>
<?php endif; ?>
