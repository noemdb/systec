(function () {
    'use strict';

    const config = window.SystecDashboardConfig || {};
    const search = config.search || '';
    const totalPropertyCount = Number(config.totalPropertyCount || 0);
    const openMaintenanceModalOnLoad = Boolean(config.openMaintenanceModalOnLoad);
    const cspNonce = config.cspNonce || '';

    const dashboardParams = new URLSearchParams(window.location.search);
    const categoryFilter = document.getElementById('filterCategory');
    const statusFilter = document.getElementById('filterStatus');
    const adscriptionFilter = document.getElementById('filterAdscription');
    const responsibleFilter = document.getElementById('filterResponsible');
    const assignedFilter = document.getElementById('filterAssigned');
    if (!categoryFilter || !statusFilter || !adscriptionFilter) return;

    categoryFilter.value = dashboardParams.get('category') ?? '';
    statusFilter.value = dashboardParams.get('status') ?? '';
    adscriptionFilter.value = dashboardParams.get('adscription') ?? '';
    if (responsibleFilter) responsibleFilter.value = dashboardParams.get('responsible') ?? '';
    if (assignedFilter) assignedFilter.value = dashboardParams.get('assigned') ?? '';

    const table = new window.Systec.TablePaginator('#myTable', {
        pageLength: 10,
        search: {
            search: dashboardParams.get('search') ?? search ?? ''
        },
        order: [4, 'asc'],
        language: {
            search: 'Buscar bienes:',
            searchPlaceholder: 'Código, descripción, modelo, color, serial...',
            emptyTable: 'No hay bienes registrados.',
            zeroRecords: 'Ningún bien coincide con los filtros aplicados.',
            unitLabel: 'bienes'
        }
    });

    const inventoryIndicators = document.querySelectorAll('.js-inventory-indicator');
    const activeFiltersContainer = document.getElementById('activeInventoryFilters');
    const resultCount = document.getElementById('inventoryResultCount');

    const updateFilterFeedback = () => {
        const activeFilters = [];
        if (categoryFilter.value) {
            activeFilters.push({
                key: 'category',
                label: `Categoría: ${categoryFilter.selectedOptions[0].textContent}`
            });
        }
        if (statusFilter.value) {
            activeFilters.push({
                key: 'status',
                label: `Estado: ${statusFilter.selectedOptions[0].textContent}`
            });
        }
        if (adscriptionFilter.value) {
            activeFilters.push({
                key: 'adscription',
                label: `Adscripción: ${adscriptionFilter.selectedOptions[0].textContent}`
            });
        }
        if (responsibleFilter && responsibleFilter.value) {
            activeFilters.push({
                key: 'responsible',
                label: `Responsable: ${responsibleFilter.selectedOptions[0].textContent}`
            });
        }
        if (assignedFilter && assignedFilter.value) {
            activeFilters.push({
                key: 'assigned',
                label: `Usuario asignado: ${assignedFilter.selectedOptions[0].textContent}`
            });
        }
        if (table.search().trim()) {
            activeFilters.push({
                key: 'search',
                label: `Búsqueda: ${table.search().trim()}`
            });
        }

        activeFiltersContainer.replaceChildren();
        activeFilters.forEach((filter) => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'btn btn-sm btn-outline-secondary rounded-pill';
            chip.dataset.filterKey = filter.key;
            chip.setAttribute('aria-label', `Quitar filtro ${filter.label}`);
            chip.textContent = `${filter.label} ×`;
            activeFiltersContainer.appendChild(chip);
        });

        const filteredCount = table.rows({ search: 'applied' }).count();
        resultCount.textContent = `Mostrando ${filteredCount} de ${totalPropertyCount} bienes`;
    };

    activeFiltersContainer.addEventListener('click', (event) => {
        const chip = event.target.closest('button[data-filter-key]');
        if (!chip) return;

        if (chip.dataset.filterKey === 'category') categoryFilter.value = '';
        if (chip.dataset.filterKey === 'status') statusFilter.value = '';
        if (chip.dataset.filterKey === 'adscription') adscriptionFilter.value = '';
        if (chip.dataset.filterKey === 'responsible' && responsibleFilter) responsibleFilter.value = '';
        if (chip.dataset.filterKey === 'assigned' && assignedFilter) assignedFilter.value = '';
        if (chip.dataset.filterKey === 'search') table.search('');

        table.draw();
        syncInventoryUrl();
        syncIndicatorState();
    });

    const syncInventoryUrl = () => {
        const url = new URL(window.location.href);
        const filters = {
            category: categoryFilter.value,
            status: statusFilter.value,
            adscription: adscriptionFilter.value,
            responsible: responsibleFilter?.value ?? '',
            assigned: assignedFilter?.value ?? '',
            search: table.search().trim()
        };

        Object.entries(filters).forEach(([key, value]) => {
            if (value) {
                url.searchParams.set(key, value);
            } else {
                url.searchParams.delete(key);
            }
        });

        history.replaceState(null, '', url.pathname + url.search + url.hash);
    };

    const syncIndicatorState = () => {
        inventoryIndicators.forEach((indicator) => {
            const status = indicator.dataset.filterStatus;
            const isActive = status === ''
                ? !categoryFilter.value && !statusFilter.value && !adscriptionFilter.value
                    && !(responsibleFilter?.value) && !(assignedFilter?.value) && !table.search().trim()
                : statusFilter.value === status;
            indicator.setAttribute('aria-pressed', String(isActive));
        });
    };

    table.on('draw', updateFilterFeedback);

    table.setFilterPredicate((row) => {
        if (!row) return true;

        const categoryMatches = !categoryFilter.value || row.dataset.category === categoryFilter.value;
        const adscriptionMatches = !adscriptionFilter.value
            || row.dataset.adscription === adscriptionFilter.value;
        const rowStatus = row.dataset.status;
        const statusMatches = !statusFilter.value
            || (statusFilter.value === '__attention__'
                ? rowStatus === 'MALO' || rowStatus === 'MUY MALO'
                : rowStatus === statusFilter.value);
        const responsibleMatches = !responsibleFilter || !responsibleFilter.value
            || row.dataset.responsible === responsibleFilter.value;
        const assignedMatches = !assignedFilter || !assignedFilter.value
            || row.dataset.assigned === assignedFilter.value;

        return categoryMatches && statusMatches && adscriptionMatches
            && responsibleMatches && assignedMatches;
    });

    [categoryFilter, statusFilter, adscriptionFilter, responsibleFilter, assignedFilter]
        .filter(Boolean)
        .forEach((filter) => {
            filter.addEventListener('change', () => {
                table.draw(true);
                syncInventoryUrl();
                syncIndicatorState();
            });
        });
    table.draw(true);
    syncIndicatorState();

    inventoryIndicators.forEach((indicator) => {
        indicator.addEventListener('click', () => {
            const status = indicator.dataset.filterStatus;
            if (status === '') {
                categoryFilter.value = '';
                statusFilter.value = '';
                adscriptionFilter.value = '';
                if (responsibleFilter) responsibleFilter.value = '';
                if (assignedFilter) assignedFilter.value = '';
                table.search('');
            } else {
                statusFilter.value = (statusFilter.value === status) ? '' : status;
            }

            table.draw();
            syncInventoryUrl();
            syncIndicatorState();
            document.getElementById('inventoryTableSection').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        });
    });

    document.getElementById('resetInventoryFilters').addEventListener('click', () => {
        categoryFilter.value = '';
        statusFilter.value = '';
        adscriptionFilter.value = '';
        if (responsibleFilter) responsibleFilter.value = '';
        if (assignedFilter) assignedFilter.value = '';
        table.search('');
        table.draw();
        syncInventoryUrl();
        syncIndicatorState();
    });

    const registerMaintenanceModal = document.getElementById('registerMaintenanceModal');
    if (registerMaintenanceModal) {
        registerMaintenanceModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (button) {
                document.getElementById('maintenancePropertyId').value = button.dataset.propertyId;
                document.getElementById('maintenancePropertyCode').textContent = button.dataset.propertyCode;
            }
        });
    }

    const maintenanceForm = document.querySelector('.maintenance-modal-form');
    if (maintenanceForm) {
        maintenanceForm.addEventListener('submit', () => {
            const params = new URLSearchParams(window.location.search);
            ['category', 'status', 'adscription', 'responsible', 'assigned', 'search'].forEach((key) => {
                const field = maintenanceForm.elements[`return_${key}`];
                if (field) field.value = params.get(key) ?? '';
            });
        });
    }

    if (openMaintenanceModalOnLoad && registerMaintenanceModal) {
        bootstrap.Modal.getOrCreateInstance(registerMaintenanceModal).show();
    }

    const historyModal = document.getElementById('propertyHistoryModal');
    if (historyModal) {
        const historyContent = document.getElementById('propertyHistoryContent');
        const printHistoryButton = document.getElementById('printPropertyHistory');
        let historyRequest = null;
        let historyTrigger = null;

        historyModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            historyTrigger = button;
            document.getElementById('historyPropertyCode').textContent = button.dataset.propertyCode;
            printHistoryButton.disabled = true;
            historyContent.setAttribute('aria-busy', 'true');
            historyContent.innerHTML = '<div class="d-flex align-items-center gap-2 text-body-secondary" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Cargando historial...</div>';

            if (historyRequest) historyRequest.abort();
            historyRequest = new AbortController();
            const request = historyRequest;

            fetch(button.dataset.historyUrl, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: request.signal
            })
                .then((response) => {
                    if (response.redirected) {
                        throw new Error('La sesión expiró o la solicitud fue redirigida.');
                    }
                    if (!response.ok) {
                        throw new Error(`Error HTTP ${response.status}`);
                    }
                    return response.text();
                })
                .then((html) => {
                    if (!request.signal.aborted) {
                        historyContent.innerHTML = html;
                        printHistoryButton.disabled = false;
                    }
                })
                .catch((error) => {
                    if (error.name !== 'AbortError') {
                        console.error('No se pudo cargar el historial del bien:', error);
                        historyContent.innerHTML = '<div class="alert alert-danger mb-0" role="alert">No se pudo cargar el historial. Cierra el diálogo e inténtalo de nuevo.</div>';
                    }
                })
                .finally(() => {
                    if (!request.signal.aborted) {
                        historyContent.setAttribute('aria-busy', 'false');
                    }
                });
        });

        historyModal.addEventListener('hide.bs.modal', () => {
            if (historyRequest) historyRequest.abort();
            if (historyModal.contains(document.activeElement)) {
                document.activeElement.blur();
            }
        });

        historyModal.addEventListener('hidden.bs.modal', () => {
            if (historyRequest) historyRequest.abort();
            historyRequest = null;
            historyContent.innerHTML = '<p class="text-body-secondary mb-0">Selecciona un bien para consultar su historial.</p>';
            historyContent.setAttribute('aria-busy', 'false');
            printHistoryButton.disabled = true;
            if (historyTrigger && historyTrigger.isConnected) {
                historyTrigger.focus();
            }
            historyTrigger = null;
        });

        printHistoryButton.addEventListener('click', () => {
            const printWindow = window.open('', '_blank');
            if (!printWindow) {
                window.alert('El navegador bloqueó la ventana de impresión. Permite ventanas emergentes e inténtalo de nuevo.');
                return;
            }
            printWindow.opener = null;

            const reportTitle = `Historial de mantenimiento - ${document.getElementById('historyPropertyCode').textContent}`;
            const letterhead = historyContent.querySelector('#historyLetterhead');
            const reportContent = historyContent.cloneNode(true);
            reportContent.querySelector('#historyLetterhead')?.remove();

            const printDocument = printWindow.document;
            printDocument.title = reportTitle;
            const charset = printDocument.createElement('meta');
            charset.setAttribute('charset', 'utf-8');
            printDocument.head.appendChild(charset);
            const viewport = printDocument.createElement('meta');
            viewport.name = 'viewport';
            viewport.content = 'width=device-width, initial-scale=1';
            printDocument.head.appendChild(viewport);

            const printStyle = printDocument.createElement('style');
            printStyle.nonce = cspNonce;
            printStyle.textContent = `
                body { color: #212529; font: 12px Arial, sans-serif; margin: 20px; }
                h1, h2, h3, h4, h5 { margin: 0 0 8px; }
                #historyLetterhead { display: block !important; color: #000 !important; margin: 0 0 16px; }
                #historyLetterhead, #historyLetterhead * { visibility: visible !important; }
                #historyLetterhead h5 { font-size: .9375rem; }
                #historyLetterhead .small { font-size: .75rem; }
                #historyLetterhead dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 16px; margin: 8px 0 0; }
                #historyLetterhead dt { color: #495057; font-size: 10px; text-transform: uppercase; }
                #historyLetterhead dd { margin: 0; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #adb5bd; padding: 6px; text-align: left; vertical-align: top; }
                th { background: #e9ecef; }
                .badge { border: 1px solid #adb5bd; border-radius: 12px; display: inline-block; padding: 2px 6px; }
                .list-group { list-style: none; padding: 0; }
                .list-group-item { border-bottom: 1px solid #dee2e6; padding: 4px 0; }
                @page { margin: 15mm; }
                @media print { body { margin: 0; } }
            `;
            printDocument.head.appendChild(printStyle);
            if (letterhead) printDocument.body.appendChild(letterhead.cloneNode(true));
            printDocument.body.appendChild(reportContent);
            printWindow.addEventListener('load', () => {
                printWindow.focus();
                printWindow.print();
            }, { once: true });
        });
    }

    const statusModal = document.getElementById('updatePropertyStatusModal');
    if (statusModal) {
        statusModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            document.getElementById('statusPropertyId').value = button.dataset.propertyId;
            document.getElementById('statusPropertyCode').textContent = button.dataset.propertyCode;
            document.getElementById('propertyStatus').value = button.dataset.propertyStatus;

            const currentBadge = document.getElementById('statusCurrentBadge');
            if (currentBadge) {
                const rawStatus = (button.dataset.propertyStatus || '').toUpperCase();
                currentBadge.textContent = rawStatus || 'NO REGISTRADO';
                let badgeClass = 'badge bg-secondary';
                if (rawStatus === 'BUENO') {
                    badgeClass = 'badge bg-success';
                } else if (rawStatus === 'REGULAR') {
                    badgeClass = 'badge bg-warning text-dark';
                } else if (rawStatus === 'MALO' || rawStatus === 'MUY MALO') {
                    badgeClass = 'badge bg-danger';
                }
                currentBadge.className = badgeClass;
            }
        });
    }

    table.on('search', () => {
        syncInventoryUrl();
        syncIndicatorState();
    });

    syncIndicatorState();
    updateFilterFeedback();
})();
