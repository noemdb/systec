(function () {
    'use strict';

    const modal = document.querySelector('[data-ticket-register-container]')
        || document.getElementById('ticketRegisterModal')
        || document.getElementById('ticketRegisterDrawer');
    if (!modal) return;
    const isOffcanvas = modal.classList.contains('offcanvas');
    const showEventName = isOffcanvas ? 'show.bs.offcanvas' : 'show.bs.modal';
    const shownEventName = isOffcanvas ? 'shown.bs.offcanvas' : 'shown.bs.modal';
    const hideEventName = isOffcanvas ? 'hide.bs.offcanvas' : 'hide.bs.modal';
    const hiddenEventName = isOffcanvas ? 'hidden.bs.offcanvas' : 'hidden.bs.modal';

    const byId = (id) => modal.querySelector(`#${id}`);
    const propertyPicker = byId('ticketRegisterPropertyId');
    const propertyForm = propertyPicker.closest('form');
    const propertySearch = byId('ticketRegisterPropertySearch');
    const propertyDirection = byId('ticketRegisterPropertyDirection');
    const propertyCategory = byId('ticketRegisterPropertyCategory');
    const propertyAssigned = byId('ticketRegisterPropertyAssigned');
    const propertyFiltersReset = byId('ticketRegisterPropertyFiltersReset');
    const propertyMatches = byId('ticketRegisterPropertyMatches');
    const propertySourceNotice = byId('ticketRegisterPropertySourceNotice');
    const propertySelection = byId('ticketRegisterPropertySelection');
    const propertySelectionText = byId('ticketRegisterPropertySelectionText');
    const propertyError = byId('ticketRegisterPropertyError');
    const ticketNarrative = byId('ticketRegisterNarrativeInput');
    const ticketTitle = byId('ticketRegisterTitleInput');
    const ticketDescription = byId('ticketRegisterDescriptionInput');
    const ticketObservation = byId('ticketRegisterObservationInput');
    const processNarrativeBtn = byId('ticketRegisterProcessNarrative');
    const processNarrativeIcon = byId('ticketRegisterProcessNarrativeIcon');
    const processNarrativeLabel = byId('ticketRegisterProcessNarrativeLabel');
    const processNarrativeStatus = byId('ticketRegisterProcessNarrativeStatus');
    const improveNarrativeBtn = byId('ticketRegisterImproveNarrative');
    const improveNarrativeIcon = byId('ticketRegisterImproveNarrativeIcon');
    const improveNarrativeLabel = byId('ticketRegisterImproveNarrativeLabel');
    const improveNarrativeStatus = byId('ticketRegisterImproveNarrativeStatus');
    const wizardSteps = Array.from(modal.querySelectorAll('[data-wizard-step]'));
    const wizardIndicators = Array.from(modal.querySelectorAll('[data-wizard-indicator]'));
    const wizardStepTriggers = Array.from(modal.querySelectorAll('[data-wizard-step-trigger]'));
    const wizardStatus = byId('ticketRegisterWizardStatus');
    const wizardBack = byId('ticketRegisterWizardBack');
    const wizardNext = byId('ticketRegisterWizardNext');
    const wizardSubmit = byId('ticketRegisterWizardSubmit');
    const wizardHeadings = [
        byId('ticketRegisterPropertyLabel'),
        byId('ticketRegisterDetailsLabel'),
        byId('ticketRegisterSummaryLabel')
    ];
    let currentWizardStep = 1;
    let propertyLoadPromise = null;
    let propertyOptionsLoaded = false;
    let isSubmittingForm = false;

    const setWizardStep = (step) => {
        currentWizardStep = step;
        wizardSteps.forEach((element) => {
            element.hidden = Number(element.dataset.wizardStep) !== step;
        });
        wizardIndicators.forEach((indicator) => {
            const indicatorStep = Number(indicator.dataset.wizardIndicator);
            indicator.classList.toggle('is-active', indicatorStep === step);
            indicator.classList.toggle('is-complete', indicatorStep < step);
            if (indicatorStep === step) {
                indicator.setAttribute('aria-current', 'step');
            } else {
                indicator.removeAttribute('aria-current');
            }
        });
        wizardStatus.textContent = `Paso ${step} de 3: ${['Seleccionar bien', 'Detalles', 'Resumen'][step - 1]}`;
        wizardBack.hidden = step === 1;
        wizardNext.hidden = step === 3;
        wizardSubmit.hidden = step !== 3;
    };
    setWizardStep(1);

    const updateWizardSummary = () => {
        const selected = propertyPicker.querySelector('input[type="radio"]:checked');
        const summaryProperty = byId('ticketRegisterSummaryProperty');
        const summaryAssigned = byId('ticketRegisterSummaryAssigned');
        if (!selected) {
            summaryProperty.textContent = 'Sin seleccionar';
            if (summaryAssigned) summaryAssigned.textContent = '—';
        } else {
            const option = selected.closest('[data-property-picker-option]');
            const code = option.querySelector('[data-property-code]').textContent;
            const description = option.querySelector('[data-property-description]').textContent;
            const direction = option.querySelector('[data-property-direction-label]').textContent;
            const assignedEl = option.querySelector('[data-property-assigned-label]');
            summaryProperty.textContent = `${code} — ${description} (${direction})`;
            if (summaryAssigned) {
                summaryAssigned.textContent = assignedEl
                    ? assignedEl.textContent.replace(/^Usuario asignado:\s*/i, '')
                    : 'Sin asignar';
            }
        }
        byId('ticketRegisterSummaryTitle').textContent = ticketTitle.value.trim() || '—';
        byId('ticketRegisterSummaryDescription').textContent = ticketDescription.value.trim() || '—';
        byId('ticketRegisterSummaryObservation').textContent = ticketObservation.value.trim() || 'Sin observación';
    };

    const validatePropertyStep = () => {
        const selected = propertyPicker.querySelector('input[type="radio"]:checked');
        if (!selected) {
            propertyError.classList.add('d-block');
            if (!propertyOptionsLoaded) loadPropertyOptions();
            propertySearch.focus();
            return false;
        }
        propertyError.classList.remove('d-block');
        return true;
    };

    const validateDetailsStep = () => {
        if (!ticketTitle.reportValidity()) return false;
        return ticketDescription.reportValidity();
    };

    const showPropertyLoadError = (message) => {
        propertySourceNotice.className = 'small text-danger mb-2';
        propertySourceNotice.textContent = message;
        propertyMatches.textContent = '';
    };

    const updatePropertySelection = () => {
        const selected = propertyPicker.querySelector('input[type="radio"]:checked');
        if (!selected) {
            propertySelection.classList.add('d-none');
            propertySelectionText.textContent = '';
            return;
        }
        const option = selected.closest('[data-property-picker-option]');
        const code = option.querySelector('[data-property-code]').textContent;
        const description = option.querySelector('[data-property-description]').textContent;
        const direction = option.querySelector('[data-property-direction-label]').textContent;
        const hiddenByFilters = option.classList.contains('d-none');
        propertySelectionText.textContent = `Bien seleccionado: ${code} — ${description}. Dirección: ${direction}.${hiddenByFilters ? ' No coincide con los filtros actuales.' : ''}`;
        propertySelection.classList.remove('d-none');
        updateWizardSummary();
    };

    const loadPropertyOptions = () => {
        if (propertyOptionsLoaded) return Promise.resolve();
        if (propertyLoadPromise) return propertyLoadPromise;

        propertyMatches.textContent = 'Cargando bienes y direcciones…';
        propertyLoadPromise = fetch(modal.dataset.propertiesUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        }).then(async (response) => {
            let data;
            try {
                data = await response.json();
            } catch (error) {
                throw new Error('El servidor devolvió una respuesta inválida al cargar los bienes.');
            }
            if (!response.ok) throw new Error(data.error || 'No se pudo cargar el inventario.');

            propertyPicker.replaceChildren();
            propertyDirection.replaceChildren(new Option('Todas las direcciones', ''));
            Object.entries(data.directions || {}).forEach(([key, label]) => {
                propertyDirection.add(new Option(label, key));
            });
            if (propertyAssigned) {
                propertyAssigned.replaceChildren(new Option('Todos los usuarios asignados', ''));
                Object.entries(data.assigned_users || {}).forEach(([key, label]) => {
                    propertyAssigned.add(new Option(label, key));
                });
            }

            data.properties.forEach((property) => {
                const option = document.createElement('label');
                option.className = 'list-group-item list-group-item-action d-flex align-items-start gap-2 property-picker-option';
                option.dataset.propertyPickerOption = '';
                option.dataset.category = property.category;
                option.dataset.direction = property.direction_key;
                option.dataset.assigned = property.assigned_key || '';
                option.dataset.search = [
                    property.code,
                    property.description,
                    property.status,
                    property.direction_label,
                    property.assigned_label || '',
                    property.id
                ].join(' ');

                const input = document.createElement('input');
                input.className = 'form-check-input flex-shrink-0 mt-1';
                input.type = 'radio';
                input.name = 'property_id';
                input.value = String(property.id);
                input.required = true;
                input.checked = String(property.id) === modal.dataset.selectedPropertyId;

                const content = document.createElement('span');
                content.className = 'd-flex flex-column text-break';
                const code = document.createElement('span');
                code.className = 'fw-semibold';
                code.dataset.propertyCode = '';
                code.textContent = property.code;
                const description = document.createElement('span');
                description.dataset.propertyDescription = '';
                description.textContent = property.description;
                const direction = document.createElement('span');
                direction.className = 'small text-body-secondary';
                direction.dataset.propertyDirectionLabel = '';
                direction.textContent = `Dirección: ${property.direction_label}`;
                const assigned = document.createElement('span');
                assigned.className = 'small text-body-secondary';
                assigned.dataset.propertyAssignedLabel = '';
                assigned.textContent = `Usuario asignado: ${property.assigned_label || 'Sin asignar'}`;
                const status = document.createElement('span');
                status.className = 'small text-body-secondary';
                status.textContent = `Estado: ${property.status}`;
                content.append(code, description, direction, assigned, status);
                option.append(input, content);
                propertyPicker.appendChild(option);
            });

            if (data.stale) {
                propertySourceNotice.className = 'small text-warning-emphasis mb-2';
                propertySourceNotice.textContent = `La fuente de direcciones no responde; se muestran datos guardados al ${new Intl.DateTimeFormat('es-VE', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(data.cached_at))}.`;
            } else {
                propertySourceNotice.className = 'small text-body-secondary mb-2';
                propertySourceNotice.textContent = `Direcciones verificadas: ${new Intl.DateTimeFormat('es-VE', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(data.cached_at))}.`;
            }
            window.Systec.initPropertyPicker(propertyPicker);
            propertyPicker.addEventListener('change', updatePropertySelection);
            propertySearch.addEventListener('input', updatePropertySelection);
            propertyDirection.addEventListener('change', updatePropertySelection);
            propertyCategory.addEventListener('change', updatePropertySelection);
            propertyAssigned?.addEventListener('change', updatePropertySelection);
            byId('ticketRegisterPropertyClear').addEventListener('click', () => {
                const selected = propertyPicker.querySelector('input[type="radio"]:checked');
                if (selected) {
                    selected.checked = false;
                    selected.dispatchEvent(new Event('change', { bubbles: true }));
                }
                propertyError.classList.remove('d-block');
                updatePropertySelection();
                propertySearch.focus();
            });
            propertyOptionsLoaded = true;
            updatePropertySelection();
            if (modal.dataset.openAfterRedirect === 'true') {
                setWizardStep(propertyPicker.querySelector('input[type="radio"]:checked') ? 2 : 1);
            }
            if (modal.classList.contains('show') && !propertyPicker.querySelector('input[type="radio"]:checked')) {
                propertySearch.focus();
            }
        }).catch((error) => {
            showPropertyLoadError(`${error.message} Puedes volver a intentarlo.`);
            propertyLoadPromise = null;
        });
        return propertyLoadPromise;
    };

    wizardNext.addEventListener('click', () => {
        if (currentWizardStep === 1) {
            if (!validatePropertyStep()) return;
            updateWizardSummary();
            setWizardStep(2);
            wizardHeadings[1].focus();
            return;
        }
        if (currentWizardStep === 2 && validateDetailsStep()) {
            updateWizardSummary();
            setWizardStep(3);
            wizardHeadings[2].focus();
        }
    });
    wizardBack.addEventListener('click', () => {
        if (currentWizardStep > 1) {
            setWizardStep(currentWizardStep - 1);
            wizardHeadings[currentWizardStep - 1].focus();
        }
    });
    wizardStepTriggers.forEach((button) => {
        button.addEventListener('click', () => {
            const targetStep = Number(button.dataset.wizardStepTrigger);
            if (!targetStep || targetStep === currentWizardStep) return;
            if (targetStep < currentWizardStep) {
                setWizardStep(targetStep);
                wizardHeadings[targetStep - 1]?.focus();
                return;
            }
            if (targetStep >= 2 && !validatePropertyStep()) return;
            if (targetStep === 3 && !validateDetailsStep()) {
                setWizardStep(2);
                return;
            }
            updateWizardSummary();
            setWizardStep(targetStep);
            wizardHeadings[targetStep - 1]?.focus();
        });
    });
    if (propertyFiltersReset) {
        propertyFiltersReset.addEventListener('click', () => {
            propertyDirection.value = '';
            propertyCategory.value = '';
            if (propertyAssigned) propertyAssigned.value = '';
            propertySearch.value = '';
            propertyDirection.dispatchEvent(new Event('change', { bubbles: true }));
            propertyCategory.dispatchEvent(new Event('change', { bubbles: true }));
            if (propertyAssigned) propertyAssigned.dispatchEvent(new Event('change', { bubbles: true }));
            propertySearch.dispatchEvent(new Event('input', { bubbles: true }));
            propertySearch.focus();
        });
    }

    const csrfInput = propertyForm.querySelector('input[name="csrf_token"]');
    const processNarrativeUrl = modal.dataset.processNarrativeUrl || '';
    const improveNarrativeUrl = modal.dataset.improveNarrativeUrl || '';

    const setAiBusy = (btn, icon, label, busy, defaultText, busyText) => {
        if (!btn) return;
        btn.disabled = busy;
        if (icon) icon.className = busy ? 'spinner-border spinner-border-sm me-1' : 'bi bi-magic me-1';
        if (label) label.textContent = busy ? busyText : defaultText;
    };

    if (processNarrativeBtn && ticketNarrative && processNarrativeUrl) {
        processNarrativeBtn.addEventListener('click', async () => {
            const narrative = ticketNarrative.value.trim();
            if (!narrative) {
                if (processNarrativeStatus) {
                    processNarrativeStatus.className = 'form-text text-danger m-0';
                    processNarrativeStatus.textContent = 'Escribe un relato breve antes de procesarlo.';
                }
                ticketNarrative.focus();
                return;
            }
            setAiBusy(processNarrativeBtn, processNarrativeIcon, processNarrativeLabel, true, 'Procesar relato', 'Procesando…');
            if (processNarrativeStatus) {
                processNarrativeStatus.className = 'form-text text-body-secondary m-0';
                processNarrativeStatus.textContent = 'Generando propuesta con IA…';
            }
            try {
                const body = new URLSearchParams();
                body.set('csrf_token', csrfInput ? csrfInput.value : '');
                body.set('narrative', narrative);
                const selectedRadio = propertyPicker.querySelector('input[type="radio"]:checked');
                if (selectedRadio) {
                    const selectedOption = selectedRadio.closest('[data-property-picker-option]');
                    const code = selectedOption?.querySelector('[data-property-code]')?.textContent || '';
                    const desc = selectedOption?.querySelector('[data-property-description]')?.textContent || '';
                    const ctx = `${code} ${desc}`.trim();
                    if (ctx) body.set('property_context', ctx);
                }
                const response = await fetch(processNarrativeUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body: body.toString()
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.error || 'No se pudo procesar el relato.');
                if (typeof data.title === 'string') ticketTitle.value = data.title;
                if (typeof data.description === 'string') ticketDescription.value = data.description;
                if (typeof data.observation === 'string') ticketObservation.value = data.observation;
                updateWizardSummary();
                if (processNarrativeStatus) {
                    if (data.fallback) {
                        processNarrativeStatus.className = 'form-text text-warning-emphasis m-0';
                        processNarrativeStatus.textContent = 'IA no disponible temporalmente; se aplicó una propuesta básica local. Revisa y ajusta los campos.';
                    } else {
                        processNarrativeStatus.className = 'form-text text-success m-0';
                        processNarrativeStatus.textContent = 'Propuesta aplicada. Revisa y ajusta los campos si hace falta.';
                    }
                }
            } catch (error) {
                if (processNarrativeStatus) {
                    processNarrativeStatus.className = 'form-text text-danger m-0';
                    processNarrativeStatus.textContent = error.message || 'No se pudo procesar el relato.';
                }
            } finally {
                setAiBusy(processNarrativeBtn, processNarrativeIcon, processNarrativeLabel, false, 'Procesar relato', 'Procesando…');
            }
        });
    }

    if (improveNarrativeBtn && improveNarrativeUrl) {
        improveNarrativeBtn.addEventListener('click', async () => {
            if (!ticketTitle.value.trim() && !ticketDescription.value.trim() && !ticketObservation.value.trim()) {
                if (improveNarrativeStatus) {
                    improveNarrativeStatus.className = 'form-text text-danger m-0';
                    improveNarrativeStatus.textContent = 'Completa al menos un campo antes de mejorar la redacción.';
                }
                ticketTitle.focus();
                return;
            }
            setAiBusy(improveNarrativeBtn, improveNarrativeIcon, improveNarrativeLabel, true, 'Mejorar con IA', 'Mejorando…');
            if (improveNarrativeStatus) {
                improveNarrativeStatus.className = 'form-text text-body-secondary m-0';
                improveNarrativeStatus.textContent = 'Solicitando mejora de redacción…';
            }
            try {
                const body = new URLSearchParams();
                body.set('csrf_token', csrfInput ? csrfInput.value : '');
                body.set('title', ticketTitle.value);
                body.set('description', ticketDescription.value);
                body.set('observation', ticketObservation.value);
                const response = await fetch(improveNarrativeUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body: body.toString()
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.error || 'No se pudo mejorar la redacción.');
                if (typeof data.title === 'string') ticketTitle.value = data.title;
                if (typeof data.description === 'string') ticketDescription.value = data.description;
                if (typeof data.observation === 'string') ticketObservation.value = data.observation;
                updateWizardSummary();
                if (improveNarrativeStatus) {
                    improveNarrativeStatus.className = 'form-text text-success m-0';
                    improveNarrativeStatus.textContent = 'Redacción mejorada. Revisa los textos antes de guardar.';
                }
            } catch (error) {
                if (improveNarrativeStatus) {
                    improveNarrativeStatus.className = 'form-text text-danger m-0';
                    improveNarrativeStatus.textContent = error.message || 'No se pudo mejorar la redacción.';
                }
            } finally {
                setAiBusy(improveNarrativeBtn, improveNarrativeIcon, improveNarrativeLabel, false, 'Mejorar con IA', 'Mejorando…');
            }
        });
    }

    [ticketTitle, ticketDescription, ticketObservation].forEach((input) => {
        input.addEventListener('input', updateWizardSummary);
    });
    propertyPicker.addEventListener('change', updateWizardSummary);
    modal.addEventListener(showEventName, loadPropertyOptions);
    modal.addEventListener(shownEventName, () => {
        if (propertyOptionsLoaded && currentWizardStep === 1 && !propertyPicker.querySelector('input[type="radio"]:checked')) {
            propertySearch.focus();
        }
    });
    propertyForm.addEventListener('submit', (event) => {
        if (currentWizardStep < 3) {
            event.preventDefault();
            if (currentWizardStep === 1 && validatePropertyStep()) {
                updateWizardSummary();
                setWizardStep(2);
                wizardHeadings[1].focus();
            } else if (currentWizardStep === 2 && validateDetailsStep()) {
                updateWizardSummary();
                setWizardStep(3);
                wizardHeadings[2].focus();
            }
            return;
        }
        if (!propertyPicker.querySelector('input[type="radio"]:checked')) {
            event.preventDefault();
            propertyError.classList.add('d-block');
            setWizardStep(1);
            propertySearch.focus();
            return;
        }
        if (!ticketTitle.checkValidity() || !ticketDescription.checkValidity()) {
            event.preventDefault();
            setWizardStep(2);
            validateDetailsStep();
            return;
        }
        isSubmittingForm = true;
        updateWizardSummary();
    });
    if (modal.dataset.openAfterRedirect === 'true') {
        if (isOffcanvas && window.bootstrap && window.bootstrap.Offcanvas) {
            window.bootstrap.Offcanvas.getOrCreateInstance(modal).show();
        } else if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    }
    const hasUnsavedDraft = () => Boolean(
        ticketTitle.value.trim()
        || ticketDescription.value.trim()
        || ticketObservation.value.trim()
        || (ticketNarrative && ticketNarrative.value.trim())
    );
    modal.addEventListener(hideEventName, (event) => {
        if (!isSubmittingForm && hasUnsavedDraft()) {
            if (!window.confirm('Tienes cambios sin guardar en el ticket. ¿Deseas cerrar el panel?')) {
                event.preventDefault();
                return;
            }
        }
        if (modal.contains(document.activeElement)) {
            document.activeElement.blur();
        }
    });
    modal.addEventListener(hiddenEventName, () => {
        if (!isSubmittingForm) {
            setWizardStep(1);
        }
    });
})();
