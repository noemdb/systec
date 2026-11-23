<div class="offcanvas offcanvas-end personal-ticket-followup-drawer text-start" tabindex="-1"
     id="personalTicketFollowupDrawer"
     data-improve-note-url="<?= e(url('personal/actions/improve-history-note.php')) ?>"
     aria-labelledby="personalTicketFollowupDrawerTitle">
    <div class="offcanvas-header border-bottom">
        <div class="support-ticket-modal-heading me-2">
            <h2 class="offcanvas-title h5 mb-1" id="personalTicketFollowupDrawerTitle">
                <i class="bi bi-chat-left-text text-primary me-2" aria-hidden="true"></i>Añadir seguimiento
            </h2>
            <p class="small text-body-secondary text-break mb-0" id="personalTicketFollowupDrawerSubtitle"></p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <form class="personal-ticket-drawer-form" id="personalTicketFollowupDrawerForm"
          method="post" action="<?= e(url('personal/actions/history.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="ticket_id" id="personalTicketFollowupDrawerId">
        <div class="offcanvas-body">
            <div class="mb-3">
                <p class="form-label fw-semibold small mb-2" id="personalTicketFollowupPresetsLabel">
                    Textos rápidos frecuentes
                </p>
                <div class="d-flex flex-column gap-1" role="group" aria-labelledby="personalTicketFollowupPresetsLabel">
                    <?php
                    $followupPresets = array(
                        'La incidencia persiste sin cambios desde el último reporte.',
                        'Se realizaron las pruebas indicadas y el problema continúa.',
                        'El bien se encuentra disponible en la oficina para su revisión técnica.',
                        'Solicito actualización sobre el estado y tiempo estimado de atención.',
                        'La falla se presenta de forma intermitente durante la jornada laboral.',
                    );
                    foreach ($followupPresets as $presetText) : ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary text-start d-flex align-items-start gap-2"
                                data-followup-preset="<?= e($presetText) ?>">
                            <i class="bi bi-plus-circle flex-shrink-0 mt-1" aria-hidden="true"></i>
                            <span><?= e($presetText) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <label for="personalTicketFollowupDrawerDescription" class="form-label fw-semibold">Nueva nota</label>
            <textarea class="form-control" id="personalTicketFollowupDrawerDescription" name="description"
                      rows="5" maxlength="10000" placeholder="Escribe tu comentario aquí..."
                      aria-describedby="personalTicketFollowupImproveStatus" required></textarea>
            <div class="mt-2 d-flex flex-column gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm align-self-start"
                        id="personalTicketFollowupImproveBtn">
                    <i class="bi bi-magic me-1" id="personalTicketFollowupImproveIcon" aria-hidden="true"></i>
                    <span id="personalTicketFollowupImproveLabel">Mejorar con IA</span>
                </button>
                <span class="form-text m-0" id="personalTicketFollowupImproveStatus" role="status" aria-live="polite">
                    Tu nota quedará registrada en el historial del ticket para el equipo de soporte.
                </span>
            </div>
        </div>
        <div class="border-top p-3 d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="offcanvas">Cancelar</button>
            <button type="submit" class="btn btn-primary flex-fill">
                <i class="bi bi-send me-1" aria-hidden="true"></i>Registrar
            </button>
        </div>
    </form>
</div>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    const personalTicketFollowupDrawer = document.getElementById('personalTicketFollowupDrawer');
    const personalTicketFollowupForm = document.getElementById('personalTicketFollowupDrawerForm');
    const personalTicketFollowupId = document.getElementById('personalTicketFollowupDrawerId');
    const personalTicketFollowupSubtitle = document.getElementById('personalTicketFollowupDrawerSubtitle');
    const personalTicketFollowupDescription = document.getElementById('personalTicketFollowupDrawerDescription');
    const personalTicketFollowupImproveBtn = document.getElementById('personalTicketFollowupImproveBtn');
    const personalTicketFollowupImproveIcon = document.getElementById('personalTicketFollowupImproveIcon');
    const personalTicketFollowupImproveLabel = document.getElementById('personalTicketFollowupImproveLabel');
    const personalTicketFollowupImproveStatus = document.getElementById('personalTicketFollowupImproveStatus');
    const defaultFollowupStatusText = 'Tu nota quedará registrada en el historial del ticket para el equipo de soporte.';

    const setPersonalFollowupImproveBusy = (busy) => {
        personalTicketFollowupImproveBtn.disabled = busy;
        personalTicketFollowupImproveIcon.className = busy
            ? 'spinner-border spinner-border-sm me-1'
            : 'bi bi-magic me-1';
        personalTicketFollowupImproveLabel.textContent = busy ? 'Mejorando…' : 'Mejorar con IA';
    };

    const populatePersonalFollowupDrawer = (ticket) => {
        personalTicketFollowupId.value = String(ticket.id);
        personalTicketFollowupSubtitle.textContent = `Ticket #${ticket.id} · ${ticket.title}`;
        personalTicketFollowupDescription.value = '';
        personalTicketFollowupImproveStatus.className = 'form-text m-0';
        personalTicketFollowupImproveStatus.textContent = defaultFollowupStatusText;
        setPersonalFollowupImproveBusy(false);
    };

    window.SystecOpenPersonalFollowup = (ticket) => {
        if (!ticket || !ticket.id) return;
        populatePersonalFollowupDrawer(ticket);
        if (window.bootstrap && window.bootstrap.Offcanvas) {
            window.bootstrap.Offcanvas.getOrCreateInstance(personalTicketFollowupDrawer).show();
        }
    };

    personalTicketFollowupDrawer.querySelectorAll('[data-followup-preset]').forEach((button) => {
        button.addEventListener('click', () => {
            const preset = button.dataset.followupPreset || '';
            if (!preset) return;
            const current = personalTicketFollowupDescription.value.trim();
            personalTicketFollowupDescription.value = current === '' ? preset : `${current}\n${preset}`;
            personalTicketFollowupDescription.focus();
            personalTicketFollowupDescription.setSelectionRange(
                personalTicketFollowupDescription.value.length,
                personalTicketFollowupDescription.value.length
            );
        });
    });

    personalTicketFollowupImproveBtn.addEventListener('click', async () => {
        const note = personalTicketFollowupDescription.value.trim();
        if (!note) {
            personalTicketFollowupImproveStatus.className = 'form-text text-danger m-0';
            personalTicketFollowupImproveStatus.textContent = 'Escribe una nota de seguimiento antes de mejorarla.';
            personalTicketFollowupDescription.focus();
            return;
        }
        const improveUrl = personalTicketFollowupDrawer.dataset.improveNoteUrl || '';
        if (!improveUrl) return;

        const csrfInput = personalTicketFollowupForm.querySelector('input[name="csrf_token"]');
        setPersonalFollowupImproveBusy(true);
        personalTicketFollowupImproveStatus.className = 'form-text text-body-secondary m-0';
        personalTicketFollowupImproveStatus.textContent = 'Mejorando redacción de la nota…';

        try {
            const body = new URLSearchParams();
            body.set('csrf_token', csrfInput ? csrfInput.value : '');
            body.set('ticket_id', personalTicketFollowupId.value);
            body.set('note', personalTicketFollowupDescription.value);
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
            const previousNote = personalTicketFollowupDescription.value;
            let changed = false;
            if (typeof data.note === 'string') {
                changed = data.note.trim() !== previousNote.trim();
                personalTicketFollowupDescription.value = data.note;
                personalTicketFollowupDescription.dispatchEvent(new Event('input', { bubbles: true }));
                personalTicketFollowupDescription.focus();
            }
            if (data.fallback) {
                personalTicketFollowupImproveStatus.className = 'form-text text-warning-emphasis m-0';
                personalTicketFollowupImproveStatus.textContent = changed
                    ? 'IA no disponible temporalmente; se aplicó una mejora básica local. Revisa la nota antes de registrar.'
                    : 'IA no disponible temporalmente y la nota ya tenía formato básico correcto.';
            } else if (!changed) {
                personalTicketFollowupImproveStatus.className = 'form-text text-body-secondary m-0';
                personalTicketFollowupImproveStatus.textContent = 'La redacción ya es clara y adecuada; no requirió cambios.';
            } else {
                personalTicketFollowupImproveStatus.className = 'form-text text-success m-0';
                personalTicketFollowupImproveStatus.textContent = 'Nota mejorada. Revisa el texto antes de registrar el seguimiento.';
            }
        } catch (error) {
            personalTicketFollowupImproveStatus.className = 'form-text text-danger m-0';
            personalTicketFollowupImproveStatus.textContent = error.message || 'No se pudo mejorar la nota.';
        } finally {
            setPersonalFollowupImproveBusy(false);
        }
    });

    personalTicketFollowupDrawer.addEventListener('show.bs.offcanvas', (event) => {
        if (event.relatedTarget && event.relatedTarget.dataset.ticket) {
            const ticket = JSON.parse(event.relatedTarget.dataset.ticket);
            populatePersonalFollowupDrawer(ticket);
        }
    });

    personalTicketFollowupDrawer.addEventListener('shown.bs.offcanvas', () => {
        personalTicketFollowupDescription.focus();
    });

    personalTicketFollowupDrawer.addEventListener('hide.bs.offcanvas', () => {
        if (personalTicketFollowupDrawer.contains(document.activeElement)) document.activeElement.blur();
    });
</script>
