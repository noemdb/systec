(function () {
    'use strict';

    const config = window.SystecMaintenancesConfig || {};
    const search = config.search || '';
    const totalMaintenanceCount = Number(config.totalMaintenanceCount || 0);
    const canViewTickets = Boolean(config.canViewTickets);
    const improveMaintenanceNarrativeUrl = config.improveMaintenanceNarrativeUrl || '';
    const propertyTicketsUrl = config.propertyTicketsUrl || '';
    const registerMaintenanceUrl = config.registerMaintenanceUrl || '';
    const updateMaintenanceUrl = config.updateMaintenanceUrl || '';
    const printUrl = config.printUrl || '';
    const generateRenditionReportUrl = config.generateRenditionReportUrl || '';
    const saveRenditionReportUrl = config.saveRenditionReportUrl || '';
    const deleteRenditionReportUrl = config.deleteRenditionReportUrl || '';
    const canDeleteRenditionReport = Boolean(config.canDeleteRenditionReport);
    const renditionReportsUrl = config.renditionReportsUrl || '';
    const renditionPrintUrl = config.renditionPrintUrl || '';

    const categoryFilter = document.getElementById('filterCategory');
    const adscriptionFilter = document.getElementById('filterAdscription');
    const dateFromFilter = document.getElementById('filterDateFrom');
    const dateToFilter = document.getElementById('filterDateTo');
    const next30Filter = document.getElementById('proximos30');
    const vencidosFilter = document.getElementById('soloVencidos');
    const statusFilterSelect = document.getElementById('filterEstado');
    const typeFilterSelect = document.getElementById('filterTipo');
    const technicianFilterSelect = document.getElementById('filterTecnico');
    const responsibleFilterSelect = document.getElementById('filterResponsible');
    const assignedFilterSelect = document.getElementById('filterAssigned');
    if (!categoryFilter || !adscriptionFilter) return;

    const maintenanceParams = new URLSearchParams(window.location.search);
    const initialUrlFilters = maintenanceParams;
    categoryFilter.value = maintenanceParams.get('category') ?? '';
    const requestedAdscription = maintenanceParams.get('adscription') ?? '';
    if (requestedAdscription
        && !Array.from(adscriptionFilter.options).some((option) => option.value === requestedAdscription)) {
        adscriptionFilter.add(new Option('Unidad seleccionada (no disponible)', requestedAdscription));
    }
    adscriptionFilter.value = requestedAdscription;

    const table = new window.Systec.TablePaginator('#myTable', {
        order: [],
        pageLength: 10,
        language: {
            search: 'Buscar:',
            searchPlaceholder: 'Código, descripción, modelo, color, técnico…',
            zeroRecords: 'Ningún mantenimiento coincide con los filtros aplicados.',
            emptyTable: 'Aún no hay mantenimientos registrados.',
            unitLabel: 'registros'
        },
        search: {
            search: search ?? ''
        }
    });

    const tableInfo = document.querySelector('.dataTables_info');
    if (tableInfo) {
        tableInfo.setAttribute('role', 'status');
        tableInfo.setAttribute('aria-live', 'polite');
    }

    document.querySelectorAll('[data-property-picker]').forEach((select) => {
        window.Systec.initPropertyPicker(select);
    });

    const createMaintenanceDrawer = document.getElementById('createMaintenanceDrawer');
    const createMaintenanceForm = document.getElementById('createMaintenanceForm');
    const createMaintenanceSubmit = document.getElementById('createMaintenanceSubmit');
    const createMaintenanceStatusField = document.getElementById('createMaintenanceStatusField');
    const createMaintenanceStatusSelect = document.getElementById('create_status');
    const createMaintenanceId = document.getElementById('createMaintenanceId');
    const maintenanceNarrativeStatus = document.getElementById('createImproveMaintenanceNarrativeStatus');
    const maintenanceNarrativeButton = document.getElementById('createImproveMaintenanceNarrative');
    const maintenanceDrawerSubmitLabel = document.getElementById('createMaintenanceSubmitLabel');
    const maintenanceDrawerSubmitIcon = document.getElementById('createMaintenanceSubmitIcon');
    const maintenanceDrawerTitleLabel = document.getElementById('createMaintenanceDrawerTitleLabel');
    const maintenanceDrawerTitleIcon = document.getElementById('createMaintenanceDrawerTitleIcon');
    const maintenanceDrawerDescription = document.getElementById('createMaintenanceDrawerDescription');
    const maintenancePropertySelect = document.getElementById('create_property_id');
    const maintenanceTicketSelect = document.getElementById('create_ticket_select');
    const maintenanceTicketId = document.getElementById('create_ticket_id');
    const maintenanceTicketStatus = document.getElementById('createMaintenanceTicketStatus');
    const maintenanceTicketProperty = document.getElementById('createMaintenanceTicketProperty');
    let pendingMaintenanceEditTrigger = null;
    let maintenanceTicketRequest = 0;

    const resetMaintenanceTicket = () => {
        maintenanceTicketId.value = '';
        maintenanceTicketSelect.replaceChildren(new Option('Selecciona primero un bien', ''));
        maintenanceTicketSelect.value = '';
        maintenanceTicketSelect.disabled = true;
        maintenanceTicketStatus.textContent = 'Selecciona un bien para buscar sus tickets asociados.';
        maintenanceTicketStatus.className = 'form-text';
        maintenanceTicketProperty.textContent = '';
        maintenanceTicketProperty.classList.add('d-none');
    };

    const loadPropertyTickets = async (propertyId, selectedTicketId = '') => {
        const propertyIdValue = String(propertyId ?? '');
        const requestId = ++maintenanceTicketRequest;
        resetMaintenanceTicket();
        if (!propertyIdValue || !propertyTicketsUrl) return;

        maintenanceTicketStatus.textContent = 'Buscando tickets asociados al bien…';
        try {
            const endpoint = new URL(propertyTicketsUrl, window.location.href);
            endpoint.searchParams.set('property_id', propertyIdValue);
            const response = await fetch(endpoint, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'No se pudieron cargar los tickets.');
            if (requestId !== maintenanceTicketRequest || maintenancePropertySelect.value !== propertyIdValue) return;

            const tickets = Array.isArray(data.tickets) ? data.tickets : [];
            maintenanceTicketSelect.replaceChildren(new Option('Sin asociar a un ticket', ''));
            tickets.forEach((ticket) => {
                const status = ticket.status_name ? ` · ${ticket.status_name}` : '';
                const title = String(ticket.title || 'Sin título');
                maintenanceTicketSelect.add(new Option(`#${ticket.id} — ${title}${status}`, ticket.id));
                const option = maintenanceTicketSelect.options[maintenanceTicketSelect.options.length - 1];
                option.dataset.propertyId = String(ticket.property_id);
                option.dataset.propertyCode = String(ticket.property_ident || '');
                option.dataset.propertyDescription = String(ticket.property_description || '');
            });
            maintenanceTicketStatus.textContent = tickets.length
                ? `${tickets.length} ${tickets.length === 1 ? 'ticket disponible' : 'tickets disponibles'}.`
                : 'Este bien no tiene tickets registrados; puedes continuar sin asociar uno.';
            if (selectedTicketId
                && Array.from(maintenanceTicketSelect.options).some((option) => option.value === String(selectedTicketId))) {
                maintenanceTicketSelect.value = String(selectedTicketId);
                maintenanceTicketId.value = String(selectedTicketId);
                maintenanceTicketSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            maintenanceTicketSelect.disabled = false;
        } catch (error) {
            if (requestId !== maintenanceTicketRequest) return;
            maintenanceTicketSelect.replaceChildren(new Option('Sin asociar a un ticket', ''));
            maintenanceTicketSelect.disabled = false;
            maintenanceTicketStatus.textContent = error instanceof Error
                ? error.message
                : 'No se pudieron cargar los tickets del bien.';
            maintenanceTicketStatus.className = 'form-text text-danger';
        }
    };

    let loadingMaintenanceForEdit = false;
    if (createMaintenanceDrawer && createMaintenanceForm && maintenancePropertySelect && maintenanceTicketSelect) {
        maintenancePropertySelect.addEventListener('change', () => {
            if (!loadingMaintenanceForEdit) loadPropertyTickets(maintenancePropertySelect.value);
        });

        maintenanceTicketSelect.addEventListener('change', () => {
            const option = maintenanceTicketSelect.selectedOptions[0];
            maintenanceTicketId.value = maintenanceTicketSelect.value;
            if (!maintenanceTicketSelect.value || !option) {
                maintenanceTicketProperty.textContent = '';
                maintenanceTicketProperty.classList.add('d-none');
                return;
            }

            const propertyId = option.dataset.propertyId;
            const propertyOption = Array.from(maintenancePropertySelect.options)
                .find((candidate) => candidate.value === propertyId);
            if (propertyOption && maintenancePropertySelect.value !== propertyId) {
                maintenancePropertySelect.value = propertyId;
                maintenancePropertySelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            const propertyCode = option.dataset.propertyCode || 'Bien';
            const propertyDescription = option.dataset.propertyDescription || 'Sin descripción';
            maintenanceTicketProperty.textContent = `Bien cargado: ${propertyCode} — ${propertyDescription}`;
            maintenanceTicketProperty.classList.remove('d-none');
        });

        document.addEventListener('click', (event) => {
            if (!(event.target instanceof Element)) return;
            const trigger = event.target.closest('.js-edit-maintenance');
            if (trigger) pendingMaintenanceEditTrigger = trigger;
        }, true);

        const resetMaintenanceDrawer = () => {
            createMaintenanceForm.reset();
            maintenanceTicketRequest++;
            resetMaintenanceTicket();
            createMaintenanceForm.action = registerMaintenanceUrl;
            createMaintenanceId.value = '';
            createMaintenanceSubmit.name = 'register';
            createMaintenanceSubmit.value = 'Registrar';
            maintenanceDrawerSubmitLabel.textContent = 'Registrar';
            maintenanceDrawerSubmitIcon.className = 'bi bi-check-lg me-1';
            maintenanceDrawerTitleLabel.textContent = 'Registrar mantenimiento';
            maintenanceDrawerTitleIcon.className = 'bi bi-plus-circle text-primary me-2';
            maintenanceDrawerDescription.textContent = 'Completa los datos para crear un registro.';
            createMaintenanceStatusField.classList.add('d-none');
            createMaintenanceStatusSelect.disabled = true;
            maintenanceNarrativeButton.disabled = false;
            maintenanceNarrativeStatus.textContent = 'Mejora razón, descripción y notas con contenido; no se guarda hasta registrar el mantenimiento.';
            maintenanceNarrativeStatus.className = 'form-text';
            document.getElementById('create_property_category_filter')
                .dispatchEvent(new Event('change', { bubbles: true }));
        };

        createMaintenanceDrawer.addEventListener('show.bs.offcanvas', (event) => {
            resetMaintenanceDrawer();
            const relatedTarget = event.relatedTarget;
            const trigger = relatedTarget instanceof Element
                && relatedTarget.matches('.js-edit-maintenance')
                ? relatedTarget
                : pendingMaintenanceEditTrigger;
            pendingMaintenanceEditTrigger = null;
            if (!trigger) return;

            const maintenance = JSON.parse(trigger.dataset.maintenance);
            const setSelectValue = (id, value) => {
                const select = document.getElementById(id);
                const normalizedValue = String(value ?? '');
                if (!Array.from(select.options).some((option) => option.value === normalizedValue)) {
                    select.add(new Option(normalizedValue, normalizedValue));
                }
                select.value = normalizedValue;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            };

            createMaintenanceForm.action = updateMaintenanceUrl;
            createMaintenanceId.value = maintenance.id;
            createMaintenanceSubmit.name = 'update';
            createMaintenanceSubmit.value = 'Actualizar';
            maintenanceDrawerSubmitLabel.textContent = 'Guardar cambios';
            maintenanceDrawerSubmitIcon.className = 'bi bi-save me-1';
            maintenanceDrawerTitleLabel.textContent = 'Editar mantenimiento';
            maintenanceDrawerTitleIcon.className = 'bi bi-pencil-square text-primary me-2';
            maintenanceDrawerDescription.textContent = `Actualiza el mantenimiento ${maintenance.code}.`;
            createMaintenanceStatusField.classList.remove('d-none');
            createMaintenanceStatusSelect.disabled = false;

            loadingMaintenanceForEdit = true;
            setSelectValue('create_property_id', maintenance.property_id);
            loadingMaintenanceForEdit = false;
            loadPropertyTickets(maintenance.property_id, maintenance.ticket_id ?? '');
            setSelectValue('create_type', maintenance.type);
            setSelectValue('create_technician', maintenance.technician);
            document.getElementById('create_date').value = String(maintenance.date ?? '')
                .replace(' ', 'T').slice(0, 16);
            document.getElementById('create_failure_reason').value = maintenance.failure_reason ?? '';
            document.getElementById('create_description').value = maintenance.description ?? '';
            document.getElementById('create_notes').value = maintenance.notes ?? '';
            document.getElementById('create_next_maintenance_date').value = maintenance.next_maintenance_date ?? '';
            setSelectValue('create_status', maintenance.status);
            maintenanceNarrativeStatus.textContent = 'La propuesta se aplicará al formulario; revisa los cambios antes de guardar.';
            maintenanceNarrativeStatus.className = 'form-text';
        });

        if (new URLSearchParams(window.location.search).get('modeCreate') === 'true') {
            bootstrap.Offcanvas.getOrCreateInstance(createMaintenanceDrawer).show();
        }
    }

    const selectFilters = {
        status: 'filterEstado',
        type: 'filterTipo',
        technician: 'filterTecnico'
    };
    if (responsibleFilterSelect) selectFilters.responsible = 'filterResponsible';
    if (assignedFilterSelect) selectFilters.assigned = 'filterAssigned';
    const activeFilters = {
        status: '',
        type: '',
        technician: '',
        responsible: '',
        assigned: ''
    };
    let applyingHistory = false;

    const readSelectFilters = () => {
        Object.entries(selectFilters).forEach(([key, id]) => {
            activeFilters[key] = document.getElementById(id).value;
        });
    };

    const setSelectFromParam = (select, value) => {
        if (!value) return;
        let option = Array.from(select.options).find((candidate) => candidate.value === value);
        if (!option) {
            option = Array.from(select.options).find(
                (candidate) => candidate.textContent.trim().toLowerCase() === value.trim().toLowerCase()
            );
        }
        if (option) select.value = option.value;
    };

    const hasActiveFilters = () => (
        categoryFilter.value !== ''
        || adscriptionFilter.value !== ''
        || activeFilters.status !== ''
        || activeFilters.type !== ''
        || activeFilters.technician !== ''
        || activeFilters.responsible !== ''
        || activeFilters.assigned !== ''
        || dateFromFilter.value !== ''
        || dateToFilter.value !== ''
        || next30Filter.checked
        || vencidosFilter.checked
        || table.search().trim() !== ''
    );

    const syncMaintenanceFilters = (push = false) => {
        if (applyingHistory) return;
        const url = new URL(window.location.href);
        const filters = {
            category: categoryFilter.value,
            adscription: adscriptionFilter.value,
            search: table.search().trim(),
            estado: activeFilters.status,
            tipo: activeFilters.type,
            tecnico: activeFilters.technician,
            responsible: activeFilters.responsible,
            assigned: activeFilters.assigned,
            desde: dateFromFilter.value,
            hasta: dateToFilter.value,
            proximo: next30Filter.checked ? '30' : '',
            vencido: vencidosFilter.checked ? '1' : ''
        };
        Object.entries(filters).forEach(([key, value]) => {
            if (value) {
                url.searchParams.set(key, value);
            } else {
                url.searchParams.delete(key);
            }
        });
        url.searchParams.delete('bulk_updated');
        url.searchParams.delete('bulk_skipped');
        const nextUrl = url.pathname + url.search + url.hash;
        const currentUrl = window.location.pathname + window.location.search + window.location.hash;
        if (nextUrl === currentUrl) return;
        if (push) {
            history.pushState(null, '', nextUrl);
        } else {
            history.replaceState(null, '', nextUrl);
        }
    };

    const resetFilters = (pushHistory = false) => {
        applyingHistory = true;
        Object.values(selectFilters).forEach((id) => { document.getElementById(id).value = ''; });
        readSelectFilters();
        categoryFilter.value = '';
        adscriptionFilter.value = '';
        dateFromFilter.value = '';
        dateToFilter.value = '';
        next30Filter.checked = false;
        table.filterNext30 = false;
        vencidosFilter.checked = false;
        table.filterVencidos = false;
        table.search('').columns().search('');
        applyingHistory = false;
        syncMaintenanceFilters(pushHistory);
        table.draw();
    };

    const bindMaintenanceNarrativeImprover = ({
        container,
        form,
        button,
        icon,
        label,
        status,
        fields,
        idInput = null,
        idleLabel
    }) => {
        const setStatus = (message, className) => {
            status.textContent = message;
            status.className = `small ${className}`;
        };

        button.addEventListener('click', async () => {
            const maintenanceId = idInput ? idInput.value : '';
            const originalTexts = Object.fromEntries(
                Object.entries(fields).map(([field, input]) => [field, input.value])
            );
            if (!Object.values(originalTexts).some((text) => text.trim() !== '')) {
                setStatus('Completa al menos un campo narrativo para generar una propuesta.', 'text-danger');
                return;
            }

            const requestData = {
                csrf_token: form.querySelector('[name="csrf_token"]').value,
                ...originalTexts
            };
            if (maintenanceId !== '') requestData.id = maintenanceId;

            button.disabled = true;
            icon.className = 'spinner-border spinner-border-sm me-2';
            label.textContent = 'Generando…';
            setStatus('Enviando razón, descripción y notas existentes para su mejora…', 'text-body-secondary');

            try {
                const response = await fetch(improveMaintenanceNarrativeUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                    },
                    body: new URLSearchParams(requestData)
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
                        : 'No se pudo generar la propuesta narrativa.');
                }
                if (!['description', 'failure_reason', 'notes'].every((field) => typeof result[field] === 'string')) {
                    throw new Error('La respuesta no contiene todos los textos narrativos esperados.');
                }

                const valuesUnchanged = Object.entries(fields).every(
                    ([field, input]) => input.value === originalTexts[field]
                );
                if (!container.classList.contains('show')
                    || (idInput && idInput.value !== maintenanceId)
                    || !valuesUnchanged) {
                    setStatus(
                        'Los campos cambiaron durante la solicitud; la propuesta se descartó. Vuelve a solicitarla.',
                        'text-warning'
                    );
                    return;
                }

                const hasImprovements = Object.keys(fields).some(
                    (field) => result[field] !== originalTexts[field]
                );
                if (!hasImprovements) {
                    setStatus(
                        'No hubo cambios: el texto no aporta contexto suficiente para mejorarlo sin inventar información.',
                        'text-warning'
                    );
                    return;
                }

                Object.entries(fields).forEach(([field, input]) => {
                    input.value = result[field];
                });
                setStatus(
                    'Propuesta para razón, descripción y notas generada. Revísala antes de guardar.',
                    'text-success'
                );
            } catch (error) {
                setStatus(
                    error instanceof Error ? error.message : 'No se pudo generar la propuesta narrativa.',
                    'text-danger'
                );
            } finally {
                button.disabled = false;
                icon.className = 'bi bi-magic me-1';
                label.textContent = idleLabel;
            }
        });
    };

    const createNarrativeButton = document.getElementById('createImproveMaintenanceNarrative');
    if (createMaintenanceDrawer && createNarrativeButton) {
        bindMaintenanceNarrativeImprover({
            container: createMaintenanceDrawer,
            form: createMaintenanceForm,
            button: createNarrativeButton,
            icon: document.getElementById('createImproveMaintenanceNarrativeIcon'),
            label: document.getElementById('createImproveMaintenanceNarrativeLabel'),
            status: document.getElementById('createImproveMaintenanceNarrativeStatus'),
            idInput: createMaintenanceId,
            idleLabel: 'Mejorar con IA',
            fields: {
                description: document.getElementById('create_description'),
                failure_reason: document.getElementById('create_failure_reason'),
                notes: document.getElementById('create_notes')
            }
        });
    }

    const transitionModal = document.getElementById('maintenanceTransitionModal');
    if (transitionModal) {
        transitionModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            const nextStatus = button.dataset.nextStatus;
            const actionLabel = button.dataset.actionLabel;
            const isFinalizing = nextStatus === 'finalizado';

            document.getElementById('transitionMaintenanceId').value = button.dataset.maintenanceId;
            document.getElementById('transitionMaintenanceStatus').value = nextStatus;
            document.getElementById('transitionMaintenanceCode').textContent = button.dataset.maintenanceCode;
            document.getElementById('transitionMaintenanceTitleLabel').textContent = actionLabel;
            document.getElementById('transitionMaintenanceMessage').textContent = isFinalizing
                ? '¿Confirmas finalizar este mantenimiento? Se calculará el tiempo transcurrido.'
                : '¿Confirmas enviar este mantenimiento a revisión?';

            const confirmButton = document.getElementById('confirmMaintenanceTransition');
            confirmButton.className = `btn ${isFinalizing ? 'btn-success' : 'btn-warning'}`;
            document.getElementById('confirmMaintenanceTransitionIcon').className =
                `bi ${isFinalizing ? 'bi-check2-circle' : 'bi-search'} me-1`;
            document.getElementById('confirmMaintenanceTransitionLabel').textContent = actionLabel;
        });
    }

    const detalleModal = new bootstrap.Modal('#detalleModal');

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-detail');
        if (!btn) return;

        const d = JSON.parse(btn.dataset.detail);
        const setBadge = (id, text, badgeClass) => {
            const el = document.getElementById(id);
            el.className = `badge rounded-pill ${badgeClass || 'text-bg-secondary'}`;
            el.textContent = text || '—';
        };
        const details = {
            detalleId: d.id,
            detalleCode: d.code,
            detallePropertyId: d.property_id,
            detalleDate: d.date,
            detalleTimeTaken: d.time_taken,
            detalleTechnician: d.technician,
            detalleNextDate: d.next_maintenance_date,
            detalleReason: d.reason,
            detalleDescription: d.description,
            detalleNotes: d.notes
        };
        Object.entries(details).forEach(([id, value]) => {
            document.getElementById(id).textContent = value || '—';
        });
        setBadge('detalleType', d.type, d.type_badge);
        setBadge('detalleStatus', d.status, d.status_badge);
        detalleModal.show();
    });

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

    table.setFilterPredicate((node) => {
        if (!node) return true;

        const ds = node.dataset;
        const vencidosMatch = !table.filterVencidos || ds.vencido === '1';
        const categoryMatches = !categoryFilter.value || ds.category === categoryFilter.value;
        const adscriptionMatches = !adscriptionFilter.value || ds.adscription === adscriptionFilter.value;
        const statusMatches = !activeFilters.status || ds.status === activeFilters.status;
        const typeMatches = !activeFilters.type || ds.type === activeFilters.type;
        const technicianMatches = !activeFilters.technician || ds.technician === activeFilters.technician;
        const responsibleMatches = !activeFilters.responsible || ds.responsible === activeFilters.responsible;
        const assignedMatches = !activeFilters.assigned || ds.assigned === activeFilters.assigned;
        const dateFromMatches = !dateFromFilter.value || (ds.date && ds.date >= dateFromFilter.value);
        const dateToMatches = !dateToFilter.value || (ds.date && ds.date <= dateToFilter.value);
        const next30Matches = !table.filterNext30 || ds.next30 === '1';

        return vencidosMatch && categoryMatches && adscriptionMatches
            && statusMatches && typeMatches && technicianMatches
            && responsibleMatches && assignedMatches
            && dateFromMatches && dateToMatches && next30Matches;
    });

    const maintenanceResultCount = document.getElementById('maintenanceResultCount');

    const updateKpiCounts = () => {
        const counts = { total: 0, iniciado: 0, revision: 0, finalizado: 0, vencido: 0 };
        table.rows({ search: 'applied' }).nodes().toArray().forEach((row) => {
            counts.total++;
            const status = row.dataset.status;
            if (Object.prototype.hasOwnProperty.call(counts, status)) {
                counts[status]++;
            }
            if (row.dataset.vencido === '1') {
                counts.vencido++;
            }
        });

        document.querySelectorAll('[data-kpi-count]').forEach((node) => {
            node.textContent = String(counts[node.dataset.kpiCount] ?? 0);
        });

        if (maintenanceResultCount) {
            maintenanceResultCount.textContent = hasActiveFilters()
                ? `Mostrando ${counts.total} de ${totalMaintenanceCount} registros`
                : `${totalMaintenanceCount} registros`;
        }

        document.querySelectorAll('.js-kpi-filter').forEach((button) => {
            const kpi = button.dataset.kpi;
            let pressed = false;
            if (kpi === 'total') {
                pressed = !hasActiveFilters();
            } else if (kpi === 'vencido') {
                pressed = vencidosFilter.checked;
            } else {
                pressed = activeFilters.status === kpi;
            }
            button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
        });
    };

    const printHistoriesButton = document.getElementById('printMaintenanceHistories');
    const exportCsvButton = document.getElementById('exportMaintenanceCsv');

    const updateActionStates = () => {
        const hasRows = table.rows({ search: 'applied' }).count() > 0;
        if (printHistoriesButton) {
            printHistoriesButton.disabled = !hasRows;
        }
        if (exportCsvButton) {
            exportCsvButton.disabled = !hasRows;
        }
    };

    const bulkToolbar = document.getElementById('maintenanceBulkToolbar');
    const bulkForm = document.getElementById('bulkMaintenanceForm');
    const bulkModalElement = document.getElementById('bulkTransitionModal');
    const bulkModal = bulkModalElement ? bootstrap.Modal.getOrCreateInstance(bulkModalElement) : null;
    const bulkSelectAll = document.getElementById('bulkSelectAll');
    const bulkActions = {
        revision: {
            label: 'Enviar a revisión',
            statuses: ['iniciado', 'finalizado'],
            buttonClass: 'btn-warning',
            icon: 'bi-search',
            message: (count) => `¿Enviar a revisión ${count} ${count === 1 ? 'mantenimiento' : 'mantenimientos'}?`
        },
        finalizado: {
            label: 'Finalizar',
            statuses: ['iniciado', 'revision'],
            buttonClass: 'btn-success',
            icon: 'bi-check2-circle',
            message: (count) => `¿Finalizar ${count} ${count === 1 ? 'mantenimiento' : 'mantenimientos'}? Se calculará el tiempo transcurrido.`
        }
    };

    const bulkSelectedRows = () => table.rows({ search: 'applied' }).nodes().toArray().filter((row) => {
        const checkbox = row.querySelector('.js-bulk-select');
        return checkbox !== null && checkbox.checked;
    });

    const bulkEligibleRows = (status) => bulkSelectedRows().filter(
        (row) => bulkActions[status].statuses.includes(row.dataset.status)
    );

    const setBulkSelection = (checked) => {
        table.rows({ page: 'current' }).nodes().toArray().forEach((row) => {
            const checkbox = row.querySelector('.js-bulk-select');
            if (checkbox) checkbox.checked = checked;
        });
        updateBulkState();
    };

    const updateBulkState = () => {
        if (!bulkToolbar) return;
        const selected = bulkSelectedRows();
        document.getElementById('maintenanceBulkCount').textContent = String(selected.length);
        document.getElementById('maintenanceBulkCountLabel').textContent =
            selected.length === 1 ? 'seleccionado' : 'seleccionados';
        bulkToolbar.hidden = selected.length === 0;

        document.querySelectorAll('.js-bulk-select').forEach((checkbox) => {
            checkbox.closest('tr')?.classList.toggle('table-active', checkbox.checked);
        });

        [['bulkSendReview', 'revision'], ['bulkFinalize', 'finalizado']].forEach(([buttonId, status]) => {
            const button = document.getElementById(buttonId);
            if (!button) return;
            const eligible = bulkEligibleRows(status).length;
            button.disabled = eligible === 0;
            button.title = eligible === 0
                ? 'La selección no tiene registros que permitan esta transición.'
                : `${bulkActions[status].label} ${eligible} ${eligible === 1 ? 'registro' : 'registros'} de la selección`;
        });

        if (bulkSelectAll) {
            const pageCheckboxes = table.rows({ page: 'current' }).nodes().toArray()
                .map((row) => row.querySelector('.js-bulk-select'))
                .filter((checkbox) => checkbox !== null);
            const checkedCount = pageCheckboxes.filter((checkbox) => checkbox.checked).length;
            bulkSelectAll.checked = pageCheckboxes.length > 0 && checkedCount === pageCheckboxes.length;
            bulkSelectAll.indeterminate = checkedCount > 0 && checkedCount < pageCheckboxes.length;
        }
    };

    if (bulkToolbar && bulkForm && bulkModal) {
        document.addEventListener('change', (event) => {
            if (event.target.classList.contains('js-bulk-select')) updateBulkState();
        });

        bulkSelectAll?.addEventListener('change', () => setBulkSelection(bulkSelectAll.checked));

        document.getElementById('bulkClear')?.addEventListener('click', () => setBulkSelection(false));

        const openBulkTransition = (status) => {
            const eligible = bulkEligibleRows(status);
            if (!eligible.length) return;
            const configItem = bulkActions[status];

            document.getElementById('bulkMaintenanceStatus').value = status;
            document.getElementById('bulkMaintenanceReturnQuery').value = window.location.search.replace(/^\?/, '');
            const idsContainer = document.getElementById('bulkMaintenanceIds');
            idsContainer.replaceChildren(...eligible.map((row) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = row.querySelector('.js-bulk-select').value;
                return input;
            }));

            document.getElementById('bulkTransitionMessage').textContent = configItem.message(eligible.length);
            const confirmButton = document.getElementById('bulkTransitionConfirm');
            confirmButton.className = `btn ${configItem.buttonClass}`;
            document.getElementById('bulkTransitionConfirmIcon').className = `bi ${configItem.icon} me-1`;
            document.getElementById('bulkTransitionConfirmLabel').textContent = configItem.label;
            bulkModal.show();
        };

        document.getElementById('bulkFinalize')?.addEventListener('click', () => openBulkTransition('finalizado'));
        document.getElementById('bulkSendReview')?.addEventListener('click', () => openBulkTransition('revision'));
    }

    table.on('draw', () => {
        updateKpiCounts();
        updateActionStates();
        updateBulkState();
    });

    categoryFilter.addEventListener('change', () => {
        syncMaintenanceFilters(true);
        table.draw();
    });

    adscriptionFilter.addEventListener('change', () => {
        syncMaintenanceFilters(true);
        table.draw();
    });

    vencidosFilter.addEventListener('change', () => {
        table.filterVencidos = vencidosFilter.checked;
        syncMaintenanceFilters(true);
        table.draw();
    });

    next30Filter.addEventListener('change', () => {
        table.filterNext30 = next30Filter.checked;
        syncMaintenanceFilters(true);
        table.draw();
    });

    [dateFromFilter, dateToFilter].forEach((input) => {
        input.addEventListener('change', () => {
            if (dateFromFilter.value && dateToFilter.value && dateFromFilter.value > dateToFilter.value) {
                if (input === dateFromFilter) {
                    dateToFilter.value = dateFromFilter.value;
                } else {
                    dateFromFilter.value = dateToFilter.value;
                }
            }
            syncMaintenanceFilters(true);
            table.draw();
        });
    });

    document.querySelectorAll('.js-col-filter').forEach((select) => {
        select.addEventListener('change', () => {
            readSelectFilters();
            syncMaintenanceFilters(true);
            table.draw();
        });
    });

    document.querySelectorAll('.js-kpi-filter').forEach((button) => {
        button.addEventListener('click', () => {
            const kpi = button.dataset.kpi;
            if (kpi === 'total') {
                resetFilters(true);
                return;
            }
            if (kpi === 'vencido') {
                vencidosFilter.checked = !vencidosFilter.checked;
                table.filterVencidos = vencidosFilter.checked;
            } else {
                statusFilterSelect.value = statusFilterSelect.value === kpi ? '' : kpi;
                readSelectFilters();
            }
            syncMaintenanceFilters(true);
            table.draw();
        });
    });

    document.getElementById('resetFiltros').addEventListener('click', () => resetFilters(true));

    const applyFiltersFromUrl = (urlFilters) => {
        categoryFilter.value = urlFilters.get('category') ?? '';

        const requestedUnit = urlFilters.get('adscription') ?? '';
        if (requestedUnit
            && !Array.from(adscriptionFilter.options).some((option) => option.value === requestedUnit)) {
            adscriptionFilter.add(new Option('Unidad seleccionada (no disponible)', requestedUnit));
        }
        adscriptionFilter.value = requestedUnit;

        Object.values(selectFilters).forEach((id) => {
            document.getElementById(id).value = '';
        });
        setSelectFromParam(statusFilterSelect, urlFilters.get('estado'));
        setSelectFromParam(typeFilterSelect, urlFilters.get('tipo'));
        setSelectFromParam(technicianFilterSelect, urlFilters.get('tecnico'));
        if (responsibleFilterSelect) {
            setSelectFromParam(responsibleFilterSelect, urlFilters.get('responsible'));
        }
        if (assignedFilterSelect) {
            setSelectFromParam(assignedFilterSelect, urlFilters.get('assigned'));
        }
        readSelectFilters();

        ['desde', 'hasta'].forEach((param) => {
            const value = urlFilters.get(param) ?? '';
            (param === 'desde' ? dateFromFilter : dateToFilter).value =
                /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : '';
        });

        next30Filter.checked = urlFilters.get('proximo') === '30';
        table.filterNext30 = next30Filter.checked;
        vencidosFilter.checked = urlFilters.get('vencido') === '1';
        table.filterVencidos = vencidosFilter.checked;

        table.search(urlFilters.get('search') ?? '');
        table.draw();
    };

    applyFiltersFromUrl(initialUrlFilters);
    table.on('search', () => syncMaintenanceFilters());
    syncMaintenanceFilters();

    window.addEventListener('popstate', () => {
        applyingHistory = true;
        applyFiltersFromUrl(new URLSearchParams(window.location.search));
        applyingHistory = false;
    });

    const transitionForm = document.getElementById('maintenanceTransitionForm');
    const transitionReturnQuery = document.getElementById('transitionReturnQuery');
    if (transitionForm && transitionReturnQuery) {
        transitionForm.addEventListener('submit', () => {
            transitionReturnQuery.value = window.location.search;
        });
    }

    const csvEscape = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    if (exportCsvButton) {
        exportCsvButton.addEventListener('click', () => {
            const columns = [
                { header: 'Código', key: 'exportCode' },
                { header: 'Tipo', key: 'exportType' },
                { header: 'Descripción', key: 'exportDescription' },
                { header: 'Fecha', key: 'exportDate' },
                { header: 'Horas', key: 'exportHours' },
                { header: 'Técnico', key: 'exportTechnician' },
                { header: 'Estado', key: 'exportStatus' },
                { header: 'Próximo mantenimiento', key: 'exportNext' },
                { header: 'Modelo', key: 'exportModel' },
                { header: 'Color', key: 'exportColor' }
            ];
            if (canViewTickets) {
                columns.push({ header: 'Tickets', key: 'exportTickets' });
            }

            const lines = [columns.map((col) => csvEscape(col.header)).join(';')];
            table.rows({ search: 'applied' }).nodes().toArray().forEach((row) => {
                const values = columns.map((col) => (row.dataset[col.key] || '').replace(/\s+/g, ' ').trim());
                lines.push(values.map(csvEscape).join(';'));
            });

            const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
            const downloadUrl = URL.createObjectURL(blob);
            const today = new Date();
            const dateStamp = [
                today.getFullYear(),
                String(today.getMonth() + 1).padStart(2, '0'),
                String(today.getDate()).padStart(2, '0')
            ].join('-');
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = `mantenimientos-${dateStamp}.csv`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(downloadUrl);
        });
    }

    if (printHistoriesButton) {
        printHistoriesButton.addEventListener('click', () => {
            const params = new URLSearchParams();
            const filters = {
                category: categoryFilter.value,
                adscription: adscriptionFilter.value,
                search: table.search().trim(),
                status: activeFilters.status,
                type: activeFilters.type,
                technician: activeFilters.technician,
                responsible: activeFilters.responsible,
                assigned: activeFilters.assigned,
                desde: dateFromFilter.value,
                hasta: dateToFilter.value,
                proximo: next30Filter.checked ? '30' : '',
                vencido: vencidosFilter.checked ? '1' : ''
            };
            Object.entries(filters).forEach(([key, value]) => {
                if (value) {
                    params.set(key, value);
                }
            });
            window.open(printUrl + (params.toString() ? '?' + params.toString() : ''), '_blank', 'noopener');
        });
    }

    // -------------------------------------------------------------------------
    // Wizard y gestión de "Informe de Rendición"
    // -------------------------------------------------------------------------
    const renditionModalEl = document.getElementById('renditionReportWizardModal');
    const renditionHistoryModalEl = document.getElementById('renditionReportHistoryModal');

    const renderMarkdownPreviewHtml = (markdownText) => {
        const escapeHtml = (str) => String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        const formatInline = (text) => escapeHtml(text)
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/^_(.+)_$/g, '<em class="text-body-secondary">$1</em>');

        const sanitizeHtmlTableLine = (line) => {
            const tableOpen = line.match(/^<table(?:\s+class="([A-Za-z0-9_\-\s]+)")?(?:\s+border="1")?\s*>$/i);
            if (tableOpen) {
                const cls = (tableOpen[1] || '').trim() || 'table table-sm table-bordered align-middle mb-0 small';
                return `<div class="table-responsive my-2"><table class="${escapeHtml(cls)}">`;
            }
            if (/^<\/table>$/i.test(line)) {
                return '</table></div>';
            }
            const sectionOpen = line.match(/^<(thead|tbody)(?:\s+class="([A-Za-z0-9_\-\s]+)")?\s*>$/i);
            if (sectionOpen) {
                const tag = sectionOpen[1].toLowerCase();
                const clsAttr = (sectionOpen[2] || '').trim() ? ` class="${escapeHtml(sectionOpen[2].trim())}"` : '';
                return `<${tag}${clsAttr}>`;
            }
            const sectionClose = line.match(/^<\/(thead|tbody|tr)>$/i);
            if (sectionClose) {
                return `</${sectionClose[1].toLowerCase()}>`;
            }
            const trOpen = line.match(/^<tr(?:\s+class="([A-Za-z0-9_\-\s]+)")?\s*>$/i);
            if (trOpen) {
                const clsAttr = (trOpen[1] || '').trim() ? ` class="${escapeHtml(trOpen[1].trim())}"` : '';
                return `<tr${clsAttr}>`;
            }
            const cellMatch = line.match(/^<(th|td)([^>]*)>(.*)<\/\1>$/i);
            if (cellMatch) {
                const tag = cellMatch[1].toLowerCase();
                const rawAttrs = cellMatch[2] || '';
                const innerRaw = cellMatch[3] || '';
                const safeAttrs = [];
                const scopeM = rawAttrs.match(/\bscope="(col|row)"/i);
                if (scopeM) safeAttrs.push(`scope="${scopeM[1].toLowerCase()}"`);
                const rowM = rawAttrs.match(/\browspan="(\d{1,2})"/i);
                if (rowM) safeAttrs.push(`rowspan="${Number(rowM[1])}"`);
                const colM = rawAttrs.match(/\bcolspan="(\d{1,2})"/i);
                if (colM) safeAttrs.push(`colspan="${Number(colM[1])}"`);
                const clsM = rawAttrs.match(/\bclass="([A-Za-z0-9_\-\s]+)"/i);
                if (clsM) safeAttrs.push(`class="${escapeHtml(clsM[1].trim())}"`);
                const normalizedInner = innerRaw.replace(/<\/?strong>/gi, '**');
                const attrStr = safeAttrs.length ? ` ${safeAttrs.join(' ')}` : '';
                return `<${tag}${attrStr}>${formatInline(normalizedInner)}</${tag}>`;
            }
            return null;
        };

        const lines = String(markdownText ?? '').split(/\r?\n/);
        const html = [];
        let inTable = false;
        let inHtmlTable = false;
        let paragraphLines = [];

        const flushParagraph = () => {
            if (!paragraphLines.length) return;
            html.push(`<p class="mb-2">${paragraphLines.map(formatInline).join('<br>')}</p>`);
            paragraphLines = [];
        };

        const closeTable = () => {
            if (!inTable) return;
            html.push('</tbody></table></div>');
            inTable = false;
        };

        lines.forEach((rawLine) => {
            const trimmed = rawLine.trim();
            if (!trimmed) {
                if (inHtmlTable) return;
                flushParagraph();
                closeTable();
                return;
            }
            if (/^<!--.*-->$/.test(trimmed)) {
                return;
            }
            const safeTableLine = sanitizeHtmlTableLine(trimmed);
            if (safeTableLine !== null) {
                flushParagraph();
                closeTable();
                if (/^<table/i.test(trimmed)) {
                    inHtmlTable = true;
                } else if (/^<\/table/i.test(trimmed)) {
                    inHtmlTable = false;
                }
                html.push(safeTableLine);
                return;
            }
            const quoteMatch = trimmed.match(/^>\s*(.+)$/);
            if (quoteMatch) {
                flushParagraph();
                closeTable();
                html.push(`<blockquote class="bg-body-tertiary border-start border-3 border-primary-subtle ps-2 py-1 my-2 small text-body-secondary">${formatInline(quoteMatch[1])}</blockquote>`);
                return;
            }
            if (/^---+$/.test(trimmed)) {
                flushParagraph();
                closeTable();
                html.push('<hr class="my-2">');
                return;
            }
            const headingMatch = trimmed.match(/^(#{1,3})\s+(.+)$/);
            if (headingMatch) {
                flushParagraph();
                closeTable();
                const level = headingMatch[1].length;
                const text = formatInline(headingMatch[2]);
                if (level === 1) {
                    html.push(`<h4 class="h6 fw-bold text-uppercase border-bottom pb-1 mb-2">${text}</h4>`);
                } else {
                    html.push(`<h5 class="small fw-bold text-uppercase text-primary-emphasis mt-2 mb-1">${text}</h5>`);
                }
                return;
            }
            if (trimmed.startsWith('|') && trimmed.endsWith('|')) {
                flushParagraph();
                const cells = trimmed
                    .slice(1, -1)
                    .split(/(?<!\\)\|/)
                    .map((c) => c.replace(/\\\|/g, '|').trim());
                if (cells.every((c) => /^:?-{2,}:?$/.test(c))) {
                    return;
                }
                if (!inTable) {
                    inTable = true;
                    html.push('<div class="table-responsive my-2"><table class="table table-sm table-bordered align-middle mb-0 small">');
                    html.push(`<thead class="table-light"><tr>${cells.map((c) => `<th scope="col">${formatInline(c)}</th>`).join('')}</tr></thead><tbody>`);
                } else {
                    html.push(`<tr>${cells.map((c) => `<td>${formatInline(c)}</td>`).join('')}</tr>`);
                }
                return;
            }
            closeTable();
            paragraphLines.push(trimmed);
        });

        flushParagraph();
        closeTable();
        return html.join('\n');
    };

    if (renditionModalEl) {
        const renditionForm = document.getElementById('renditionReportWizardForm');
        const renditionAlert = document.getElementById('renditionWizardAlert');
        const renditionLiveStatus = document.getElementById('renditionWizardLiveStatus');
        const renditionReportIdInput = document.getElementById('renditionReportId');
        const renditionReportCodeInput = document.getElementById('renditionReportCode');
        const renditionScopeHashInput = document.getElementById('renditionScopeHash');
        const renditionAiGeneratedInput = document.getElementById('renditionAiGenerated');
        const renditionDateFromInput = document.getElementById('renditionDateFrom');
        const renditionDateToInput = document.getElementById('renditionDateTo');
        const renditionDateFromText = document.getElementById('renditionDateFromText');
        const renditionDateToText = document.getElementById('renditionDateToText');
        const renditionPreparedByInput = document.getElementById('renditionPreparedBy');
        const renditionTitleInput = document.getElementById('renditionTitle');
        const renditionIssuingUnitInput = document.getElementById('renditionIssuingUnit');
        const renditionRecipientNameInput = document.getElementById('renditionRecipientName');
        const renditionUseActiveFiltersInput = document.getElementById('renditionUseActiveFilters');
        const renditionIncludeTicketsInput = document.getElementById('renditionIncludeTickets');
        const renditionCustomNotesInput = document.getElementById('renditionCustomNotes');
        const renditionActiveFiltersSummary = document.getElementById('renditionActiveFiltersSummary');
        const renditionMarkdownTextarea = document.getElementById('renditionMarkdownTextarea');
        const renditionEditorPane = document.getElementById('renditionEditorPane');
        const renditionPreviewPane = document.getElementById('renditionPreviewPane');
        const renditionFinalPreview = document.getElementById('renditionFinalPreview');
        const renditionTabEditBtn = document.getElementById('renditionTabEditBtn');
        const renditionTabPreviewBtn = document.getElementById('renditionTabPreviewBtn');
        const renditionRegenerateAiBtn = document.getElementById('renditionRegenerateAiBtn');
        const renditionRegenerateAiIcon = document.getElementById('renditionRegenerateAiIcon');
        const renditionRegenerateAiLabel = document.getElementById('renditionRegenerateAiLabel');
        const renditionConfirmApproval = document.getElementById('renditionConfirmApproval');

        const renditionBackBtn = document.getElementById('renditionWizardBackBtn');
        const renditionGenerateBtn = document.getElementById('renditionWizardGenerateBtn');
        const renditionGenerateIcon = document.getElementById('renditionWizardGenerateIcon');
        const renditionGenerateLabel = document.getElementById('renditionWizardGenerateLabel');
        const renditionSaveDraftBtn = document.getElementById('renditionSaveDraftBtn');
        const renditionToStep3Btn = document.getElementById('renditionToStep3Btn');
        const renditionApproveSaveBtn = document.getElementById('renditionApproveSaveBtn');
        const renditionApproveSaveIcon = document.getElementById('renditionApproveSaveIcon');
        const renditionApproveSaveLabel = document.getElementById('renditionApproveSaveLabel');
        const renditionOpenPrintBtn = document.getElementById('renditionOpenPrintBtn');

        const stepSections = Array.from(renditionModalEl.querySelectorAll('[data-rendition-step]'));
        const stepIndicators = Array.from(renditionModalEl.querySelectorAll('[data-rendition-indicator]'));
        const stepTriggers = Array.from(renditionModalEl.querySelectorAll('[data-rendition-step-trigger]'));

        let currentStep = 1;
        let currentReportMeta = {
            status: 'borrador',
            scope_summary: 'Todos los mantenimientos del período',
            stats: { total_maintenances: 0, total_tickets: 0 }
        };

        const showRenditionAlert = (message, tone = 'danger', htmlContent = '') => {
            if (!renditionAlert) return;
            if (!message && !htmlContent) {
                renditionAlert.className = 'alert d-none mb-3';
                renditionAlert.textContent = '';
                return;
            }
            renditionAlert.className = `alert alert-${tone} small mb-3`;
            if (htmlContent) {
                renditionAlert.innerHTML = htmlContent;
            } else {
                renditionAlert.textContent = message;
            }
        };

        const describeCurrentTableFilters = () => {
            if (!renditionUseActiveFiltersInput.checked) {
                return 'Alcance actual: Todos los mantenimientos registrados en el rango de fechas.';
            }
            const parts = [];
            if (categoryFilter.value) {
                const opt = categoryFilter.selectedOptions[0];
                parts.push(`Categoría: ${opt ? opt.textContent.trim() : categoryFilter.value}`);
            }
            if (adscriptionFilter.value) {
                const opt = adscriptionFilter.selectedOptions[0];
                parts.push(`Unidad: ${opt ? opt.textContent.trim() : adscriptionFilter.value}`);
            }
            if (statusFilterSelect.value) {
                const opt = statusFilterSelect.selectedOptions[0];
                parts.push(`Estado: ${opt ? opt.textContent.trim() : statusFilterSelect.value}`);
            }
            if (typeFilterSelect.value) {
                const opt = typeFilterSelect.selectedOptions[0];
                parts.push(`Tipo: ${opt ? opt.textContent.trim() : typeFilterSelect.value}`);
            }
            if (technicianFilterSelect.value) {
                parts.push(`Técnico: ${technicianFilterSelect.value}`);
            }
            return parts.length
                ? `Alcance filtrado por: ${parts.join(' · ')}`
                : 'Alcance actual: Todos los mantenimientos registrados en el rango de fechas.';
        };

        const setEditorMode = (mode) => {
            const isPreview = mode === 'preview';
            renditionEditorPane.classList.toggle('d-none', isPreview);
            renditionPreviewPane.classList.toggle('d-none', !isPreview);
            renditionTabEditBtn.classList.toggle('active', !isPreview);
            renditionTabEditBtn.setAttribute('aria-pressed', !isPreview ? 'true' : 'false');
            renditionTabPreviewBtn.classList.toggle('active', isPreview);
            renditionTabPreviewBtn.setAttribute('aria-pressed', isPreview ? 'true' : 'false');
            if (isPreview) {
                renditionPreviewPane.innerHTML = renderMarkdownPreviewHtml(renditionMarkdownTextarea.value);
            }
        };

        const formatIsoDateLabel = (isoDate) => {
            const match = String(isoDate ?? '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
            return match ? `${match[3]}/${match[2]}/${match[1]}` : (isoDate || '—');
        };

        const syncRenditionPeriodText = (fromIso, toIso) => {
            renditionDateFromInput.value = fromIso || '';
            renditionDateToInput.value = toIso || '';
            if (renditionDateFromText) {
                renditionDateFromText.textContent = formatIsoDateLabel(fromIso);
            }
            if (renditionDateToText) {
                renditionDateToText.textContent = formatIsoDateLabel(toIso);
            }
        };

        const populateStep3Summary = () => {
            const code = renditionReportCodeInput.value || 'IR-BORRADOR';
            const isApproved = currentReportMeta.status === 'aprobado';
            document.getElementById('renditionSummaryCode').textContent = code;
            document.getElementById('renditionSummaryStatus').innerHTML = isApproved
                ? '<span class="badge rounded-pill text-bg-success"><i class="bi bi-patch-check-fill me-1"></i>Aprobado</span>'
                : '<span class="badge rounded-pill text-bg-warning"><i class="bi bi-pencil-square me-1"></i>Borrador</span>';
            document.getElementById('renditionSummaryPeriod').textContent =
                `${formatIsoDateLabel(renditionDateFromInput.value)} al ${formatIsoDateLabel(renditionDateToInput.value)}`;
            document.getElementById('renditionSummaryScope').textContent =
                currentReportMeta.scope_summary || 'Todos los mantenimientos del período';
            document.getElementById('renditionSummaryMaintenances').textContent =
                String(currentReportMeta.stats?.total_maintenances ?? 0);
            document.getElementById('renditionSummaryTickets').textContent =
                String(currentReportMeta.stats?.total_tickets ?? 0);
            document.getElementById('renditionSummaryPreparedBy').textContent =
                renditionPreparedByInput.value.trim() || '—';
            const hashVal = renditionScopeHashInput.value || '';
            document.getElementById('renditionSummaryHash').textContent = hashVal
                ? `${hashVal.slice(0, 20)}…`
                : '—';
            renditionFinalPreview.innerHTML = renderMarkdownPreviewHtml(renditionMarkdownTextarea.value);
        };

        const setRenditionStep = (step) => {
            currentStep = step;
            stepSections.forEach((sec) => {
                sec.hidden = Number(sec.dataset.renditionStep) !== step;
            });
            stepIndicators.forEach((ind) => {
                const s = Number(ind.dataset.renditionIndicator);
                ind.classList.toggle('is-active', s === step);
                ind.classList.toggle('is-complete', s < step);
                if (s === step) {
                    ind.setAttribute('aria-current', 'step');
                } else {
                    ind.removeAttribute('aria-current');
                }
            });
            const labels = ['Período y parámetros', 'Contenido IA y edición', 'Revisión y aprobación'];
            renditionLiveStatus.textContent = `Paso ${step} de 3: ${labels[step - 1]}`;

            const isApproved = currentReportMeta.status === 'aprobado';
            renditionBackBtn.hidden = step === 1;
            renditionGenerateBtn.hidden = step !== 1;
            renditionSaveDraftBtn.hidden = step === 1 || isApproved;
            renditionToStep3Btn.hidden = step !== 2;
            renditionApproveSaveBtn.hidden = step !== 3 || isApproved;
            renditionMarkdownTextarea.readOnly = isApproved;
            renditionRegenerateAiBtn.disabled = isApproved;
            renditionConfirmApproval.disabled = isApproved;

            if (renditionReportIdInput.value && renditionPrintUrl) {
                renditionOpenPrintBtn.href = `${renditionPrintUrl}?id=${encodeURIComponent(renditionReportIdInput.value)}`;
                renditionOpenPrintBtn.classList.remove('d-none');
            } else {
                renditionOpenPrintBtn.classList.add('d-none');
            }

            if (step === 3) {
                populateStep3Summary();
            }
        };

        const collectFormParameters = (forceRegenerate = false) => {
            const csrfToken = renditionForm.querySelector('input[name="csrf_token"]')?.value || '';
            const adscriptionOpt = adscriptionFilter.selectedOptions[0];
            const adscriptionLabel = adscriptionFilter.value && adscriptionOpt
                ? adscriptionOpt.textContent.trim()
                : '';

            const body = new URLSearchParams();
            body.set('csrf_token', csrfToken);
            if (renditionReportIdInput.value) {
                body.set('report_id', renditionReportIdInput.value);
            }
            body.set('date_from', renditionDateFromInput.value);
            body.set('date_to', renditionDateToInput.value);
            body.set('title', renditionTitleInput.value.trim());
            body.set('issuing_unit', renditionIssuingUnitInput.value.trim());
            body.set('recipient_name', renditionRecipientNameInput.value.trim());
            body.set('prepared_by', renditionPreparedByInput.value.trim());
            body.set('use_active_filters', renditionUseActiveFiltersInput.checked ? '1' : '0');
            body.set('include_tickets', renditionIncludeTicketsInput.checked ? '1' : '0');
            body.set('custom_notes', renditionCustomNotesInput.value.trim());
            body.set('category', categoryFilter.value);
            body.set('adscription', adscriptionFilter.value);
            body.set('adscription_label', adscriptionLabel);
            body.set('status', statusFilterSelect.value);
            body.set('type', typeFilterSelect.value);
            body.set('technician', technicianFilterSelect.value);
            if (forceRegenerate) {
                body.set('force_regenerate', '1');
            }
            return body;
        };

        const validateStep1Inputs = () => {
            if (!renditionDateFromInput.value || !renditionDateToInput.value) {
                showRenditionAlert(
                    'Selecciona el rango "Fecha desde" y "Fecha hasta" en los filtros de la tabla antes de generar el informe.',
                    'warning'
                );
                return false;
            }
            if (!renditionPreparedByInput.reportValidity()
                || !renditionTitleInput.reportValidity()
                || !renditionIssuingUnitInput.reportValidity()
                || !renditionRecipientNameInput.reportValidity()) {
                return false;
            }
            if (renditionDateFromInput.value > renditionDateToInput.value) {
                showRenditionAlert('La "Fecha desde" en el filtro no puede ser mayor que la "Fecha hasta".', 'danger');
                return false;
            }
            return true;
        };

        const requestRenditionGeneration = async (forceRegenerate = false) => {
            if (!validateStep1Inputs()) return;
            showRenditionAlert('');

            const activeBtn = forceRegenerate ? renditionRegenerateAiBtn : renditionGenerateBtn;
            const activeIcon = forceRegenerate ? renditionRegenerateAiIcon : renditionGenerateIcon;
            const activeLabel = forceRegenerate ? renditionRegenerateAiLabel : renditionGenerateLabel;
            const idleText = forceRegenerate ? 'Regenerar con IA' : 'Generar informe con IA';

            activeBtn.disabled = true;
            activeIcon.className = 'spinner-border spinner-border-sm me-1';
            activeLabel.textContent = 'Generando con IA…';

            try {
                const response = await fetch(generateRenditionReportUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                    },
                    body: collectFormParameters(forceRegenerate).toString()
                });
                const data = await response.json().catch(() => ({}));

                if ((response.status === 409 || data.already_approved) && data.existing_report) {
                    const rep = data.existing_report;
                    const printLink = renditionPrintUrl
                        ? `${renditionPrintUrl}?id=${encodeURIComponent(rep.id)}`
                        : '#';
                    renditionReportIdInput.value = rep.id ? String(rep.id) : '';
                    renditionReportCodeInput.value = rep.report_code || '';
                    renditionScopeHashInput.value = rep.scope_hash || '';
                    renditionMarkdownTextarea.value = rep.content_markdown || '';
                    renditionConfirmApproval.checked = true;
                    currentReportMeta = {
                        status: 'aprobado',
                        scope_summary: rep.scope_summary || '',
                        stats: rep.stats || { total_maintenances: 0, total_tickets: 0 }
                    };
                    setEditorMode('preview');
                    setRenditionStep(3);
                    showRenditionAlert(
                        '',
                        'warning',
                        `<i class="bi bi-lock-fill me-1"></i><strong>Período ya rendido y aprobado:</strong> El informe <strong>${rep.report_code}</strong> ya fue aprobado para este período y alcance (modo solo lectura). <a href="${printLink}" target="_blank" rel="noopener" class="alert-link ms-1">Abrir vista imprimible</a>.`
                    );
                    return;
                }

                if (!response.ok) {
                    throw new Error(data.error || 'No se pudo generar el Informe de Rendición.');
                }

                renditionReportIdInput.value = data.report_id ? String(data.report_id) : '';
                renditionReportCodeInput.value = data.report_code || '';
                renditionScopeHashInput.value = data.scope_hash || '';
                renditionAiGeneratedInput.value = '1';
                renditionMarkdownTextarea.value = data.content_markdown || '';
                currentReportMeta = {
                    status: data.status || 'borrador',
                    scope_summary: data.scope_summary || '',
                    stats: data.stats || { total_maintenances: 0, total_tickets: 0 }
                };

                setEditorMode('edit');
                setRenditionStep(2);

                if (data.loaded_existing_draft) {
                    showRenditionAlert(
                        `Se cargó el borrador existente (${data.report_code}) para este período y alcance. Puedes continuar editándolo o pulsar "Regenerar con IA".`,
                        'info'
                    );
                } else if (data.fallback) {
                    showRenditionAlert(
                        'Informe ensamblado con la plantilla Markdown (usando síntesis local estructurada por falta de conexión o clave de IA). Revisa y edita el contenido.',
                        'warning'
                    );
                } else {
                    showRenditionAlert(
                        `Borrador ${data.report_code} generado con IA siguiendo la plantilla Markdown. Puedes modificar el contenido antes de aprobar.`,
                        'success'
                    );
                }
            } catch (error) {
                showRenditionAlert(
                    error instanceof Error ? error.message : 'No se pudo generar el Informe de Rendición.',
                    'danger'
                );
            } finally {
                activeBtn.disabled = currentReportMeta.status === 'aprobado';
                activeIcon.className = 'bi bi-magic me-1';
                activeLabel.textContent = idleText;
            }
        };

        const submitRenditionSave = async (targetStatus) => {
            if (!renditionMarkdownTextarea.value.trim()) {
                showRenditionAlert('El contenido Markdown del informe no puede estar vacío.', 'danger');
                setRenditionStep(2);
                renditionMarkdownTextarea.focus();
                return;
            }
            if (targetStatus === 'aprobado' && !renditionConfirmApproval.checked) {
                showRenditionAlert('Debes marcar la casilla de confirmación para aprobar definitivamente el informe.', 'warning');
                renditionConfirmApproval.focus();
                return;
            }

            const body = collectFormParameters(false);
            body.set('content_markdown', renditionMarkdownTextarea.value);
            body.set('status', targetStatus);
            body.set('ai_generated', renditionAiGeneratedInput.value);
            body.set('confirm_approval', renditionConfirmApproval.checked ? '1' : '0');

            renditionSaveDraftBtn.disabled = true;
            renditionApproveSaveBtn.disabled = true;
            if (targetStatus === 'aprobado') {
                renditionApproveSaveIcon.className = 'spinner-border spinner-border-sm me-1';
                renditionApproveSaveLabel.textContent = 'Aprobando…';
            }

            try {
                const response = await fetch(saveRenditionReportUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                    },
                    body: body.toString()
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(data.error || 'No se pudo guardar el Informe de Rendición.');
                }

                const saved = data.report || {};
                renditionReportIdInput.value = saved.id ? String(saved.id) : '';
                renditionReportCodeInput.value = saved.report_code || '';
                renditionScopeHashInput.value = saved.scope_hash || '';
                renditionMarkdownTextarea.value = saved.content_markdown || renditionMarkdownTextarea.value;
                currentReportMeta = {
                    status: saved.status || targetStatus,
                    scope_summary: saved.scope_summary || currentReportMeta.scope_summary,
                    stats: saved.stats || currentReportMeta.stats
                };

                const savedCountBadge = document.getElementById('renditionSavedCount');
                if (savedCountBadge && typeof data.total_reports === 'number') {
                    savedCountBadge.textContent = String(data.total_reports);
                }

                setRenditionStep(currentStep);
                showRenditionAlert(data.message || 'Informe guardado.', 'success');
            } catch (error) {
                showRenditionAlert(
                    error instanceof Error ? error.message : 'No se pudo guardar el Informe de Rendición.',
                    'danger'
                );
            } finally {
                renditionSaveDraftBtn.disabled = currentReportMeta.status === 'aprobado';
                renditionApproveSaveBtn.disabled = currentReportMeta.status === 'aprobado';
                renditionApproveSaveIcon.className = 'bi bi-patch-check me-1';
                renditionApproveSaveLabel.textContent = 'Aprobar y guardar';
            }
        };

        let skipResetOnNextShow = false;

        renditionModalEl.addEventListener('show.bs.offcanvas', (event) => {
            if (skipResetOnNextShow) {
                skipResetOnNextShow = false;
                return;
            }
            showRenditionAlert('');
            const trigger = event.relatedTarget;
            if (trigger && trigger.dataset && trigger.dataset.loadReport) {
                return;
            }

            renditionReportIdInput.value = '';
            renditionReportCodeInput.value = '';
            renditionScopeHashInput.value = '';
            renditionConfirmApproval.checked = false;
            currentReportMeta = {
                status: 'borrador',
                scope_summary: '',
                stats: { total_maintenances: 0, total_tickets: 0 }
            };

            let resolvedFrom = dateFromFilter.value;
            let resolvedTo = dateToFilter.value;
            if (!resolvedFrom || !resolvedTo) {
                const visibleDates = table.rows({ search: 'applied' }).nodes().toArray()
                    .map((row) => row.dataset.date || '')
                    .filter((d) => /^\d{4}-\d{2}-\d{2}$/.test(d))
                    .sort();
                if (visibleDates.length > 0) {
                    resolvedFrom = resolvedFrom || visibleDates[0];
                    resolvedTo = resolvedTo || visibleDates[visibleDates.length - 1];
                } else {
                    const todayIso = new Date().toISOString().slice(0, 10);
                    resolvedFrom = resolvedFrom || todayIso;
                    resolvedTo = resolvedTo || todayIso;
                }
            }
            syncRenditionPeriodText(resolvedFrom, resolvedTo);

            renditionActiveFiltersSummary.textContent = describeCurrentTableFilters();
            setEditorMode('edit');
            setRenditionStep(1);
        });

        renditionUseActiveFiltersInput.addEventListener('change', () => {
            renditionActiveFiltersSummary.textContent = describeCurrentTableFilters();
        });

        renditionTabEditBtn.addEventListener('click', () => setEditorMode('edit'));
        renditionTabPreviewBtn.addEventListener('click', () => setEditorMode('preview'));

        renditionGenerateBtn.addEventListener('click', () => requestRenditionGeneration(false));
        renditionRegenerateAiBtn.addEventListener('click', () => requestRenditionGeneration(true));

        renditionToStep3Btn.addEventListener('click', () => {
            if (!renditionMarkdownTextarea.value.trim()) {
                showRenditionAlert('El contenido del informe no puede estar vacío.', 'danger');
                return;
            }
            showRenditionAlert('');
            setRenditionStep(3);
        });

        renditionBackBtn.addEventListener('click', () => {
            if (currentStep > 1) {
                showRenditionAlert('');
                setRenditionStep(currentStep - 1);
            }
        });

        stepTriggers.forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = Number(btn.dataset.renditionStepTrigger);
                if (!target || target === currentStep) return;
                if (target < currentStep) {
                    showRenditionAlert('');
                    setRenditionStep(target);
                    return;
                }
                if (target >= 2 && !renditionMarkdownTextarea.value.trim()) {
                    requestRenditionGeneration(false);
                    return;
                }
                showRenditionAlert('');
                setRenditionStep(target);
            });
        });

        renditionSaveDraftBtn.addEventListener('click', () => submitRenditionSave('borrador'));
        renditionApproveSaveBtn.addEventListener('click', () => submitRenditionSave('aprobado'));

        // Historial de Informes de Rendición
        if (renditionHistoryModalEl && renditionReportsUrl) {
            const historyStatus = document.getElementById('renditionHistoryStatus');
            const historyBody = document.getElementById('renditionHistoryTableBody');

            const loadSavedReports = async () => {
                historyStatus.textContent = 'Cargando informes guardados…';
                historyStatus.className = 'small text-body-secondary mb-2';
                historyBody.replaceChildren();

                try {
                    const response = await fetch(renditionReportsUrl, {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' }
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.error || 'No se pudieron cargar los informes.');
                    }

                    const reports = Array.isArray(data.reports) ? data.reports : [];
                    if (!reports.length) {
                        historyStatus.textContent = 'Aún no hay Informes de Rendición guardados.';
                        return;
                    }

                    historyStatus.textContent = `${reports.length} ${reports.length === 1 ? 'informe registrado' : 'informes registrados'}.`;
                    reports.forEach((rep) => {
                        const tr = document.createElement('tr');
                        const isApproved = rep.status === 'aprobado';
                        const mCount = rep.stats?.total_maintenances ?? 0;
                        const tCount = rep.stats?.total_tickets ?? 0;

                        const tdCode = document.createElement('td');
                        tdCode.className = 'fw-semibold text-nowrap';
                        tdCode.textContent = rep.report_code;

                        const tdPeriod = document.createElement('td');
                        tdPeriod.className = 'small text-nowrap';
                        tdPeriod.textContent = `${rep.date_from} a ${rep.date_to}`;

                        const tdScope = document.createElement('td');
                        tdScope.className = 'small text-break';
                        tdScope.textContent = rep.scope_summary || 'Todos';

                        const tdCounts = document.createElement('td');
                        tdCounts.className = 'small text-center text-nowrap';
                        tdCounts.textContent = `${mCount} / ${tCount}`;

                        const tdStatus = document.createElement('td');
                        tdStatus.innerHTML = isApproved
                            ? '<span class="badge rounded-pill text-bg-success">Aprobado</span>'
                            : '<span class="badge rounded-pill text-bg-warning">Borrador</span>';

                        const tdActions = document.createElement('td');
                        tdActions.className = 'text-nowrap';
                        const group = document.createElement('div');
                        group.className = 'btn-group btn-group-sm';

                        const openWizardBtn = document.createElement('button');
                        openWizardBtn.type = 'button';
                        openWizardBtn.className = 'btn btn-outline-primary';
                        openWizardBtn.title = isApproved ? 'Ver en asistente' : 'Editar borrador';
                        openWizardBtn.innerHTML = `<i class="bi ${isApproved ? 'bi-eye' : 'bi-pencil-square'}" aria-hidden="true"></i>`;
                        openWizardBtn.addEventListener('click', () => {
                            renditionReportIdInput.value = String(rep.id);
                            renditionReportCodeInput.value = rep.report_code || '';
                            renditionScopeHashInput.value = rep.scope_hash || '';
                            syncRenditionPeriodText(rep.date_from || '', rep.date_to || '');
                            renditionTitleInput.value = rep.parameters?.title || rep.title || '';
                            renditionIssuingUnitInput.value = rep.parameters?.issuing_unit || '';
                            renditionRecipientNameInput.value = rep.parameters?.recipient_name || '';
                            renditionPreparedByInput.value = rep.parameters?.prepared_by || '';
                            renditionCustomNotesInput.value = rep.parameters?.custom_notes || '';
                            renditionMarkdownTextarea.value = rep.content_markdown || '';
                            renditionConfirmApproval.checked = isApproved;
                            currentReportMeta = {
                                status: rep.status || 'borrador',
                                scope_summary: rep.scope_summary || '',
                                stats: rep.stats || { total_maintenances: 0, total_tickets: 0 }
                            };
                            bootstrap.Offcanvas.getOrCreateInstance(renditionHistoryModalEl).hide();
                            const wizDrawer = bootstrap.Offcanvas.getOrCreateInstance(renditionModalEl);
                            setEditorMode(isApproved ? 'preview' : 'edit');
                            setRenditionStep(isApproved ? 3 : 2);
                            showRenditionAlert(
                                isApproved
                                    ? `Consultando Informe de Rendición aprobado ${rep.report_code} (solo lectura).`
                                    : `Borrador ${rep.report_code} cargado para edición y aprobación.`,
                                isApproved ? 'success' : 'info'
                            );
                            skipResetOnNextShow = true;
                            wizDrawer.show();
                        });

                        const printLink = document.createElement('a');
                        printLink.className = 'btn btn-outline-secondary';
                        printLink.href = `${renditionPrintUrl}?id=${encodeURIComponent(rep.id)}`;
                        printLink.target = '_blank';
                        printLink.rel = 'noopener';
                        printLink.title = 'Abrir vista imprimible';
                        printLink.innerHTML = '<i class="bi bi-printer" aria-hidden="true"></i>';

                        const mdLink = document.createElement('a');
                        mdLink.className = 'btn btn-outline-secondary';
                        mdLink.href = `${renditionPrintUrl}?id=${encodeURIComponent(rep.id)}&download=md`;
                        mdLink.title = 'Descargar Markdown (.md)';
                        mdLink.innerHTML = '<i class="bi bi-markdown" aria-hidden="true"></i>';

                        group.append(openWizardBtn, printLink, mdLink);

                        if (canDeleteRenditionReport && deleteRenditionReportUrl) {
                            const deleteBtn = document.createElement('button');
                            deleteBtn.type = 'button';
                            deleteBtn.className = 'btn btn-outline-danger';
                            deleteBtn.title = `Eliminar informe ${rep.report_code} (solo administradores)`;
                            deleteBtn.setAttribute('aria-label', `Eliminar informe ${rep.report_code}`);
                            deleteBtn.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>';
                            deleteBtn.addEventListener('click', async () => {
                                const confirmed = window.confirm(
                                    `¿Estás seguro de eliminar el Informe de Rendición ${rep.report_code} (${rep.date_from} a ${rep.date_to})? Esta acción no se puede deshacer.`
                                );
                                if (!confirmed) return;

                                const csrfToken = renditionForm.querySelector('input[name="csrf_token"]')?.value || '';
                                const body = new URLSearchParams();
                                body.set('csrf_token', csrfToken);
                                body.set('report_id', String(rep.id));

                                deleteBtn.disabled = true;
                                deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';

                                try {
                                    const delResponse = await fetch(deleteRenditionReportUrl, {
                                        method: 'POST',
                                        credentials: 'same-origin',
                                        headers: {
                                            Accept: 'application/json',
                                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                                        },
                                        body: body.toString()
                                    });
                                    const delData = await delResponse.json().catch(() => ({}));
                                    if (!delResponse.ok) {
                                        throw new Error(delData.error || 'No se pudo eliminar el Informe de Rendición.');
                                    }

                                    const savedCountBadge = document.getElementById('renditionSavedCount');
                                    if (savedCountBadge && typeof delData.total_reports === 'number') {
                                        savedCountBadge.textContent = String(delData.total_reports);
                                    }

                                    if (renditionReportIdInput.value === String(rep.id)) {
                                        renditionReportIdInput.value = '';
                                        renditionReportCodeInput.value = '';
                                        renditionScopeHashInput.value = '';
                                        renditionMarkdownTextarea.value = '';
                                        renditionConfirmApproval.checked = false;
                                        currentReportMeta = {
                                            status: 'borrador',
                                            scope_summary: '',
                                            stats: { total_maintenances: 0, total_tickets: 0 }
                                        };
                                    }

                                    await loadSavedReports();
                                } catch (delError) {
                                    deleteBtn.disabled = false;
                                    deleteBtn.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>';
                                    historyStatus.textContent = delError instanceof Error
                                        ? delError.message
                                        : 'No se pudo eliminar el Informe de Rendición.';
                                    historyStatus.className = 'small text-danger mb-2';
                                }
                            });
                            group.appendChild(deleteBtn);
                        }

                        tdActions.appendChild(group);

                        tr.append(tdCode, tdPeriod, tdScope, tdCounts, tdStatus, tdActions);
                        historyBody.appendChild(tr);
                    });
                } catch (error) {
                    historyStatus.textContent = error instanceof Error
                        ? error.message
                        : 'No se pudieron cargar los informes guardados.';
                    historyStatus.className = 'small text-danger mb-2';
                }
            };

            renditionHistoryModalEl.addEventListener('show.bs.offcanvas', loadSavedReports);
        }
    }

    updateKpiCounts();
    updateActionStates();
})();
