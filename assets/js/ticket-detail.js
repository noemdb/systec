(function () {
    'use strict';

    const form = document.getElementById('ticketHistoryForm');
    if (!form) return;

    const note = document.getElementById('history_description');
    const button = document.getElementById('improveHistoryNote');
    const icon = document.getElementById('improveHistoryNoteIcon');
    const label = document.getElementById('improveHistoryNoteLabel');
    const status = document.getElementById('historyImproveStatus');
    let requestId = 0;

    const setStatus = (message, className = 'form-text') => {
        status.textContent = message;
        status.className = className;
    };

    button.addEventListener('click', async () => {
        const originalNote = note.value;
        if (!originalNote.trim()) {
            setStatus('Escribe una nota antes de solicitar una propuesta.', 'form-text text-danger');
            return;
        }

        const thisRequestId = ++requestId;
        button.disabled = true;
        icon.className = 'spinner-border spinner-border-sm me-2';
        label.textContent = 'Generando…';
        setStatus('Enviando la nota para mejorar su redacción…', 'form-text text-body-secondary');

        try {
            const response = await fetch(form.dataset.improveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                },
                body: new URLSearchParams({
                    csrf_token: form.querySelector('[name="csrf_token"]').value,
                    ticket_id: form.querySelector('[name="ticket_id"]').value,
                    note: originalNote
                })
            });
            let result;
            try {
                result = await response.json();
            } catch {
                throw new Error('El servidor devolvió una respuesta inválida.');
            }
            if (!result || typeof result !== 'object' || Array.isArray(result)) {
                throw new Error('El servidor devolvió una respuesta inválida.');
            }
            if (!response.ok) {
                throw new Error(typeof result.error === 'string'
                    ? result.error
                    : 'No se pudo generar la propuesta para la nota.');
            }
            if (typeof result.note !== 'string' || !result.note.trim()) {
                throw new Error('La respuesta no contiene una nota válida.');
            }
            if (note.value !== originalNote) {
                setStatus('La nota cambió durante la solicitud; la propuesta se descartó.', 'form-text text-warning');
                return;
            }
            if (result.note === originalNote) {
                setStatus('No se encontraron cambios de redacción que mejoren la nota.', 'form-text text-warning');
                return;
            }

            note.value = result.note;
            setStatus('Propuesta aplicada al campo. Revísala antes de registrar el seguimiento.', 'form-text text-success');
        } catch (error) {
            setStatus(
                error instanceof Error ? error.message : 'No se pudo generar la propuesta para la nota.',
                'form-text text-danger'
            );
        } finally {
            if (thisRequestId === requestId) {
                button.disabled = false;
                icon.className = 'bi bi-magic me-1';
                label.textContent = 'Mejorar con IA';
            }
        }
    });
})();
