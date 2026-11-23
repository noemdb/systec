<div class="modal fade" id="supportTicketFollowupModal" tabindex="-1"
     data-improve-note-url="<?= e(url('tickets/actions/improve-history-note.php')) ?>"
     aria-labelledby="supportTicketFollowupModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered support-ticket-followup-dialog">
        <div class="modal-content text-start">
            <form id="supportTicketFollowupModalForm" method="post" action="<?= e(url('tickets/actions/history.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="ticket_id" id="supportTicketFollowupModalId">
                <input type="hidden" name="return_to" value="tickets">
                <input type="hidden" name="return_query" id="supportTicketFollowupModalReturnQuery" value="">
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
                    <div class="mb-3">
                        <p class="form-label fw-semibold small mb-2" id="supportTicketFollowupPresetsLabel">
                            Textos rápidos frecuentes
                        </p>
                        <div class="d-flex flex-column gap-1" role="group" aria-labelledby="supportTicketFollowupPresetsLabel">
                            <?php
                            $supportFollowupPresets = array(
                                'Se inició la revisión técnica del bien y se verificó el estado reportado.',
                                'Se realizaron pruebas de diagnóstico; el equipo continúa en evaluación.',
                                'En espera de repuesto o insumo para completar la reparación.',
                                'Se coordinó con el usuario la disponibilidad del bien para su atención.',
                                'Se aplicó la solución correspondiente; pendiente confirmación de funcionamiento por el usuario.',
                            );
                            foreach ($supportFollowupPresets as $presetText) : ?>
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary text-start d-flex align-items-start gap-2"
                                        data-support-followup-preset="<?= e($presetText) ?>">
                                    <i class="bi bi-plus-circle flex-shrink-0 mt-1" aria-hidden="true"></i>
                                    <span><?= e($presetText) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <label for="supportTicketFollowupModalDescription" class="form-label fw-semibold">Nueva nota</label>
                    <textarea class="form-control" id="supportTicketFollowupModalDescription" name="description"
                              rows="4" maxlength="10000" placeholder="Escribe tu comentario aquí..."
                              aria-describedby="supportTicketFollowupImproveStatus" required></textarea>
                    <div class="mt-2 d-flex flex-column gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm align-self-start"
                                id="supportTicketFollowupImproveBtn">
                            <i class="bi bi-magic me-1" id="supportTicketFollowupImproveIcon" aria-hidden="true"></i>
                            <span id="supportTicketFollowupImproveLabel">Mejorar con IA</span>
                        </button>
                        <span class="form-text m-0" id="supportTicketFollowupImproveStatus" role="status" aria-live="polite">
                            La nota quedará registrada en el historial del ticket.
                        </span>
                    </div>
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
    const supportTicketFollowupForm = document.getElementById('supportTicketFollowupModalForm');
    const supportTicketFollowupId = document.getElementById('supportTicketFollowupModalId');
    const supportTicketFollowupReturnQuery = document.getElementById('supportTicketFollowupModalReturnQuery');
    const supportTicketFollowupSubtitle = document.getElementById('supportTicketFollowupModalSubtitle');
    const supportTicketFollowupDescription = document.getElementById('supportTicketFollowupModalDescription');
    const supportTicketFollowupImproveBtn = document.getElementById('supportTicketFollowupImproveBtn');
    const supportTicketFollowupImproveIcon = document.getElementById('supportTicketFollowupImproveIcon');
    const supportTicketFollowupImproveLabel = document.getElementById('supportTicketFollowupImproveLabel');
    const supportTicketFollowupImproveStatus = document.getElementById('supportTicketFollowupImproveStatus');
    const defaultSupportFollowupStatusText = 'La nota quedará registrada en el historial del ticket.';

    const setSupportFollowupImproveBusy = (busy) => {
        supportTicketFollowupImproveBtn.disabled = busy;
        supportTicketFollowupImproveIcon.className = busy
            ? 'spinner-border spinner-border-sm me-1'
            : 'bi bi-magic me-1';
        supportTicketFollowupImproveLabel.textContent = busy ? 'Mejorando…' : 'Mejorar con IA';
    };

    supportTicketFollowupModal.querySelectorAll('[data-support-followup-preset]').forEach((button) => {
        button.addEventListener('click', () => {
            const preset = button.dataset.supportFollowupPreset || '';
            if (!preset) return;
            const current = supportTicketFollowupDescription.value.trim();
            supportTicketFollowupDescription.value = current === '' ? preset : `${current}\n${preset}`;
            supportTicketFollowupDescription.focus();
            supportTicketFollowupDescription.setSelectionRange(
                supportTicketFollowupDescription.value.length,
                supportTicketFollowupDescription.value.length
            );
        });
    });

    supportTicketFollowupImproveBtn.addEventListener('click', async () => {
        const note = supportTicketFollowupDescription.value.trim();
        if (!note) {
            supportTicketFollowupImproveStatus.className = 'form-text text-danger m-0';
            supportTicketFollowupImproveStatus.textContent = 'Escribe una nota de seguimiento antes de mejorarla.';
            supportTicketFollowupDescription.focus();
            return;
        }
        const improveUrl = supportTicketFollowupModal.dataset.improveNoteUrl || '';
        if (!improveUrl) return;

        const csrfInput = supportTicketFollowupForm.querySelector('input[name="csrf_token"]');
        setSupportFollowupImproveBusy(true);
        supportTicketFollowupImproveStatus.className = 'form-text text-body-secondary m-0';
        supportTicketFollowupImproveStatus.textContent = 'Mejorando redacción de la nota…';

        try {
            const body = new URLSearchParams();
            body.set('csrf_token', csrfInput ? csrfInput.value : '');
            body.set('ticket_id', supportTicketFollowupId.value);
            body.set('note', supportTicketFollowupDescription.value);
            const response = await fetch(improveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                },
                body: body.toString()
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.error || 'No se pudo mejorar la nota.');
            const previousNote = supportTicketFollowupDescription.value;
            let changed = false;
            if (typeof data.note === 'string') {
                changed = data.note.trim() !== previousNote.trim();
                supportTicketFollowupDescription.value = data.note;
                supportTicketFollowupDescription.dispatchEvent(new Event('input', { bubbles: true }));
                supportTicketFollowupDescription.focus();
            }
            if (data.fallback) {
                supportTicketFollowupImproveStatus.className = 'form-text text-warning-emphasis m-0';
                supportTicketFollowupImproveStatus.textContent = changed
                    ? 'IA no disponible temporalmente; se aplicó una mejora básica local. Revisa la nota antes de registrar.'
                    : 'IA no disponible temporalmente y la nota ya tenía formato básico correcto.';
            } else if (!changed) {
                supportTicketFollowupImproveStatus.className = 'form-text text-body-secondary m-0';
                supportTicketFollowupImproveStatus.textContent = 'La redacción ya es clara y adecuada; no requirió cambios.';
            } else {
                supportTicketFollowupImproveStatus.className = 'form-text text-success m-0';
                supportTicketFollowupImproveStatus.textContent = 'Nota mejorada. Revisa el texto antes de registrar el seguimiento.';
            }
        } catch (error) {
            supportTicketFollowupImproveStatus.className = 'form-text text-danger m-0';
            supportTicketFollowupImproveStatus.textContent = error.message || 'No se pudo mejorar la nota.';
        } finally {
            setSupportFollowupImproveBusy(false);
        }
    });

    supportTicketFollowupModal.addEventListener('show.bs.modal', (event) => {
        const ticket = JSON.parse(event.relatedTarget.dataset.ticket);
        supportTicketFollowupId.value = String(ticket.id);
        supportTicketFollowupReturnQuery.value = window.location.search;
        supportTicketFollowupSubtitle.textContent = `Ticket #${ticket.id} · ${ticket.title}`;
        supportTicketFollowupDescription.value = '';
        supportTicketFollowupImproveStatus.className = 'form-text m-0';
        supportTicketFollowupImproveStatus.textContent = defaultSupportFollowupStatusText;
        setSupportFollowupImproveBusy(false);
    });

    supportTicketFollowupModal.addEventListener('hide.bs.modal', () => {
        if (supportTicketFollowupModal.contains(document.activeElement)) document.activeElement.blur();
    });
</script>
