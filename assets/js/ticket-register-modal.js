(function () {
    'use strict';

    const modal = document.getElementById('ticketRegisterModal');
    if (!modal) return;

    const byId = (id) => modal.querySelector(`#${id}`);
    const propertyPicker = byId('ticketRegisterPropertyId');
    const propertyForm = propertyPicker.closest('form');
    const propertySearch = byId('ticketRegisterPropertySearch');
    const propertyDirection = byId('ticketRegisterPropertyDirection');
    const propertyCategory = byId('ticketRegisterPropertyCategory');
    const propertyMatches = byId('ticketRegisterPropertyMatches');
    const propertySourceNotice = byId('ticketRegisterPropertySourceNotice');
    const propertySelection = byId('ticketRegisterPropertySelection');
    const propertySelectionText = byId('ticketRegisterPropertySelectionText');
    const propertyError = byId('ticketRegisterPropertyError');
    const ticketTitle = byId('ticketRegisterTitleInput');
    const ticketDescription = byId('ticketRegisterDescriptionInput');
    const ticketObservation = byId('ticketRegisterObservationInput');
    const wizardSteps = Array.from(modal.querySelectorAll('[data-wizard-step]'));
    const wizardIndicators = Array.from(modal.querySelectorAll('[data-wizard-indicator]'));
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
        if (!selected) {
            summaryProperty.textContent = 'Sin seleccionar';
        } else {
            const option = selected.closest('[data-property-picker-option]');
            const code = option.querySelector('[data-property-code]').textContent;
            const description = option.querySelector('[data-property-description]').textContent;
            const direction = option.querySelector('[data-property-direction-label]').textContent;
            summaryProperty.textContent = `${code} — ${description} (${direction})`;
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
            Object.entries(data.directions).forEach(([key, label]) => {
                propertyDirection.add(new Option(label, key));
            });

            data.properties.forEach((property) => {
                const option = document.createElement('label');
                option.className = 'list-group-item list-group-item-action d-flex align-items-start gap-2 property-picker-option';
                option.dataset.propertyPickerOption = '';
                option.dataset.category = property.category;
                option.dataset.direction = property.direction_key;
                option.dataset.search = [
                    property.code,
                    property.description,
                    property.status,
                    property.direction_label,
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
                const status = document.createElement('span');
                status.className = 'small text-body-secondary';
                status.textContent = `Estado: ${property.status}`;
                content.append(code, description, direction, status);
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
    [ticketTitle, ticketDescription, ticketObservation].forEach((input) => {
        input.addEventListener('input', updateWizardSummary);
    });
    propertyPicker.addEventListener('change', updateWizardSummary);
    modal.addEventListener('show.bs.modal', loadPropertyOptions);
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
        updateWizardSummary();
    });
    if (modal.dataset.openAfterRedirect === 'true') {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
    modal.addEventListener('hide.bs.modal', () => {
        if (modal.contains(document.activeElement)) {
            document.activeElement.blur();
        }
    });
})();
