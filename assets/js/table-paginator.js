(function (global) {
    'use strict';

    const normalizeText = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();

    const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

    /**
     * Construye la lista de páginas e indicadores de elipsis para el estilo
     * clásico completo con ventana deslizante: [1, '...', 4, 5, 6, '...', 20]
     */
    const buildSlidingPageNumbers = (currentPage, totalPages) => {
        if (totalPages <= 7) {
            return Array.from({ length: totalPages }, (_, idx) => idx + 1);
        }

        const pages = [1];
        const start = Math.max(2, currentPage - 1);
        const end = Math.min(totalPages - 1, currentPage + 1);

        if (start > 2) {
            pages.push('ellipsis-start');
        }

        for (let p = start; p <= end; p++) {
            pages.push(p);
        }

        if (end < totalPages - 1) {
            pages.push('ellipsis-end');
        }

        pages.push(totalPages);
        return pages;
    };

    class TablePaginator {
        constructor(selectorOrElement, options = {}) {
            this.tableEl = typeof selectorOrElement === 'string'
                ? document.querySelector(selectorOrElement)
                : selectorOrElement;

            if (!this.tableEl) {
                throw new Error('No se encontró la tabla para inicializar el paginador.');
            }

            this.tbody = this.tableEl.querySelector('tbody');
            this.thead = this.tableEl.querySelector('thead');
            this.allRows = this.tbody
                ? Array.from(this.tbody.querySelectorAll('tr'))
                : [];

            this.rowSearchCache = new WeakMap();
            this.allRows.forEach((row, index) => {
                row.dataset.initialIndex = String(index);
                this.rowSearchCache.set(row, normalizeText(row.textContent));
            });

            this.pageSizes = Array.isArray(options.pageSizes) ? options.pageSizes : [10, 25, 50, 100];
            this.pageSize = Number(options.pageLength) || 10;
            if (!this.pageSizes.includes(this.pageSize)) {
                this.pageSizes.push(this.pageSize);
                this.pageSizes.sort((a, b) => a - b);
            }

            this.currentPage = 1;
            this.searchTerm = (options.search && typeof options.search.search === 'string')
                ? options.search.search
                : '';

            this.sortColumn = null;
            this.sortDirection = 'asc';
            if (Array.isArray(options.order) && options.order.length >= 2) {
                this.sortColumn = Number(options.order[0]);
                this.sortDirection = String(options.order[1]).toLowerCase() === 'desc' ? 'desc' : 'asc';
            }

            this.language = Object.assign({
                search: 'Buscar:',
                searchPlaceholder: 'Filtrar registros…',
                lengthLabel: 'Mostrar',
                lengthSuffix: 'registros',
                emptyTable: 'No hay registros disponibles.',
                zeroRecords: 'Ningún registro coincide con los filtros aplicados.',
                unitLabel: 'registros'
            }, options.language || {});

            this.filterPredicate = typeof options.filterPredicate === 'function'
                ? options.filterPredicate
                : null;

            this.listeners = { draw: [], search: [] };
            this.filteredRows = [];
            this.currentPageRows = [];

            this._initControls();
            this._initSortableHeaders();
            this.draw(false);
        }

        _initControls() {
            const wrapper = this.tableEl.closest('.table-responsive') || this.tableEl;
            const parent = wrapper.parentNode;

            // Barra superior: Selector de filas por página + Buscador
            this.topBar = document.createElement('div');
            this.topBar.className = 'd-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-2';

            const lengthContainer = document.createElement('div');
            lengthContainer.className = 'd-inline-flex align-items-center gap-2 small text-body-secondary';
            const lengthId = `${this.tableEl.id || 'table'}_length_select`;
            const lengthLabel = document.createElement('label');
            lengthLabel.setAttribute('for', lengthId);
            lengthLabel.textContent = this.language.lengthLabel;

            this.lengthSelect = document.createElement('select');
            this.lengthSelect.id = lengthId;
            this.lengthSelect.className = 'form-select form-select-sm w-auto';
            this.pageSizes.forEach((size) => {
                const opt = new Option(String(size), String(size), size === this.pageSize, size === this.pageSize);
                this.lengthSelect.add(opt);
            });

            const lengthSuffix = document.createElement('span');
            lengthSuffix.textContent = this.language.lengthSuffix;
            lengthContainer.append(lengthLabel, this.lengthSelect, lengthSuffix);

            const searchContainer = document.createElement('div');
            searchContainer.className = 'd-inline-flex align-items-center gap-2 small';
            const searchId = `${this.tableEl.id || 'table'}_search_input`;
            const searchLabelText = String(this.language.search || 'Buscar:').replace('_INPUT_', '').trim() || 'Buscar:';
            const searchLabel = document.createElement('label');
            searchLabel.setAttribute('for', searchId);
            searchLabel.className = 'text-body-secondary text-nowrap mb-0';
            searchLabel.textContent = searchLabelText;

            this.searchInput = document.createElement('input');
            this.searchInput.type = 'search';
            this.searchInput.id = searchId;
            this.searchInput.className = 'form-control form-control-sm';
            this.searchInput.placeholder = this.language.searchPlaceholder;
            this.searchInput.value = this.searchTerm;

            searchContainer.append(searchLabel, this.searchInput);
            this.topBar.append(lengthContainer, searchContainer);
            parent.insertBefore(this.topBar, wrapper);

            // Fila vacía para mensaje de tabla sin resultados
            this.emptyRow = document.createElement('tr');
            this.emptyCell = document.createElement('td');
            const colCount = this.thead
                ? this.thead.querySelectorAll('th').length
                : 1;
            this.emptyCell.colSpan = Math.max(1, colCount);
            this.emptyCell.className = 'text-center text-body-secondary py-4';
            this.emptyRow.appendChild(this.emptyCell);

            // Barra inferior: Contador + Paginación clásica con ventana deslizante
            this.bottomBar = document.createElement('div');
            this.bottomBar.className = 'd-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 pt-3 mt-2 border-top';

            this.infoNode = document.createElement('div');
            this.infoNode.className = 'small text-body-secondary dataTables_info';
            this.infoNode.setAttribute('role', 'status');
            this.infoNode.setAttribute('aria-live', 'polite');

            this.navNode = document.createElement('nav');
            this.navNode.setAttribute('aria-label', 'Paginación de registros');
            this.paginationList = document.createElement('ul');
            this.paginationList.className = 'pagination pagination-sm mb-0 flex-wrap';
            this.navNode.appendChild(this.paginationList);

            this.bottomBar.append(this.infoNode, this.navNode);
            parent.insertBefore(this.bottomBar, wrapper.nextSibling);

            // Eventos de controles
            this.lengthSelect.addEventListener('change', () => {
                this.pageSize = Number(this.lengthSelect.value) || 10;
                this.currentPage = 1;
                this.draw(true);
            });

            this.searchInput.addEventListener('input', () => {
                this.searchTerm = this.searchInput.value;
                this.currentPage = 1;
                this._emit('search');
                this.draw(false);
            });
        }

        _initSortableHeaders() {
            if (!this.thead) return;
            const headers = Array.from(this.thead.querySelectorAll('th'));
            headers.forEach((th, index) => {
                const label = th.textContent.trim().toLowerCase();
                if (th.dataset.orderable === 'false' || label === 'acciones' || label === 'acción') {
                    return;
                }
                th.style.cursor = 'pointer';
                th.setAttribute('aria-sort', 'none');
                th.addEventListener('click', (event) => {
                    if (event.target.closest('input, button, a, select')) return;
                    if (this.sortColumn === index) {
                        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.sortColumn = index;
                        this.sortDirection = 'asc';
                    }
                    this.draw(false);
                });
            });
        }

        _updateHeaderSortState() {
            if (!this.thead) return;
            const headers = Array.from(this.thead.querySelectorAll('th'));
            headers.forEach((th, index) => {
                if (th.dataset.orderable === 'false') return;
                if (this.sortColumn === index) {
                    th.setAttribute('aria-sort', this.sortDirection === 'asc' ? 'ascending' : 'descending');
                } else if (th.hasAttribute('aria-sort')) {
                    th.setAttribute('aria-sort', 'none');
                }
            });
        }

        setFilterPredicate(predicate) {
            this.filterPredicate = typeof predicate === 'function' ? predicate : null;
            return this;
        }

        search(value) {
            if (typeof value === 'undefined') {
                return this.searchTerm;
            }
            this.searchTerm = String(value ?? '');
            if (this.searchInput && this.searchInput.value !== this.searchTerm) {
                this.searchInput.value = this.searchTerm;
            }
            this.currentPage = 1;
            return this;
        }

        columns() {
            return {
                search: () => this
            };
        }

        on(eventName, callback) {
            if (this.listeners[eventName] && typeof callback === 'function') {
                this.listeners[eventName].push(callback);
            }
            return this;
        }

        _emit(eventName) {
            if (!this.listeners[eventName]) return;
            this.listeners[eventName].forEach((fn) => fn());
        }

        row(index) {
            const rowNode = this.allRows[index] || null;
            return {
                node: () => rowNode
            };
        }

        rows(selector = {}) {
            let targetRows = this.allRows;
            if (selector && selector.search === 'applied') {
                targetRows = this.filteredRows;
            } else if (selector && selector.page === 'current') {
                targetRows = this.currentPageRows;
            }
            return {
                count: () => targetRows.length,
                nodes: () => ({
                    toArray: () => targetRows.slice()
                })
            };
        }

        draw(resetPage = false) {
            if (resetPage) {
                this.currentPage = 1;
            }

            const normalizedQuery = normalizeText(this.searchTerm);

            this.filteredRows = this.allRows.filter((row, idx) => {
                if (normalizedQuery) {
                    const cached = this.rowSearchCache.get(row) || normalizeText(row.textContent);
                    if (!cached.includes(normalizedQuery)) {
                        return false;
                    }
                }
                if (this.filterPredicate && !this.filterPredicate(row, idx)) {
                    return false;
                }
                return true;
            });

            if (this.sortColumn !== null) {
                const colIdx = this.sortColumn;
                const factor = this.sortDirection === 'asc' ? 1 : -1;
                this.filteredRows.sort((rowA, rowB) => {
                    const cellA = rowA.cells[colIdx] ? rowA.cells[colIdx].textContent.trim() : '';
                    const cellB = rowB.cells[colIdx] ? rowB.cells[colIdx].textContent.trim() : '';
                    const cmp = collator.compare(cellA, cellB);
                    if (cmp !== 0) return cmp * factor;
                    return Number(rowA.dataset.initialIndex || 0) - Number(rowB.dataset.initialIndex || 0);
                });
            }

            this._updateHeaderSortState();

            const totalMatching = this.filteredRows.length;
            const totalPages = Math.max(1, Math.ceil(totalMatching / this.pageSize));
            if (this.currentPage > totalPages) {
                this.currentPage = totalPages;
            }
            if (this.currentPage < 1) {
                this.currentPage = 1;
            }

            const startIdx = (this.currentPage - 1) * this.pageSize;
            const endIdx = Math.min(startIdx + this.pageSize, totalMatching);
            this.currentPageRows = this.filteredRows.slice(startIdx, endIdx);

            if (this.tbody) {
                this.tbody.replaceChildren();
                if (this.currentPageRows.length === 0) {
                    this.emptyCell.textContent = this.allRows.length === 0
                        ? this.language.emptyTable
                        : this.language.zeroRecords;
                    this.tbody.appendChild(this.emptyRow);
                } else {
                    const fragment = document.createDocumentFragment();
                    this.currentPageRows.forEach((row) => fragment.appendChild(row));
                    this.tbody.appendChild(fragment);
                }
            }

            this._renderPagination(totalMatching, startIdx, endIdx, totalPages);
            this._emit('draw');
            return this;
        }

        _renderPagination(totalMatching, startIdx, endIdx, totalPages) {
            const totalAll = this.allRows.length;
            if (totalMatching === 0) {
                this.infoNode.textContent = totalAll > 0
                    ? `Mostrando 0 de 0 registros (filtrado de ${totalAll} totales)`
                    : 'Sin registros';
            } else {
                const suffix = totalMatching < totalAll
                    ? ` (filtrado de ${totalAll} totales)`
                    : '';
                this.infoNode.textContent = `Mostrando ${startIdx + 1}–${endIdx} de ${totalMatching} ${this.language.unitLabel}${suffix}`;
            }

            this.paginationList.replaceChildren();

            const createPageItem = ({ label, ariaLabel, targetPage, disabled = false, active = false, isEllipsis = false }) => {
                const li = document.createElement('li');
                li.className = `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}`;
                if (active) {
                    li.setAttribute('aria-current', 'page');
                }

                if (isEllipsis) {
                    const span = document.createElement('span');
                    span.className = 'page-link';
                    span.setAttribute('aria-hidden', 'true');
                    span.textContent = '…';
                    li.appendChild(span);
                    return li;
                }

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'page-link';
                btn.textContent = label;
                if (ariaLabel) {
                    btn.setAttribute('aria-label', ariaLabel);
                    btn.title = ariaLabel;
                }
                if (disabled) {
                    btn.disabled = true;
                    btn.setAttribute('aria-disabled', 'true');
                } else if (!active) {
                    btn.addEventListener('click', () => {
                        this.currentPage = targetPage;
                        this.draw(false);
                    });
                }
                li.appendChild(btn);
                return li;
            };

            // « Primera y ‹ Anterior
            this.paginationList.appendChild(createPageItem({
                label: '«',
                ariaLabel: 'Primera página',
                targetPage: 1,
                disabled: this.currentPage <= 1 || totalMatching === 0
            }));
            this.paginationList.appendChild(createPageItem({
                label: '‹',
                ariaLabel: 'Página anterior',
                targetPage: Math.max(1, this.currentPage - 1),
                disabled: this.currentPage <= 1 || totalMatching === 0
            }));

            // Números con ventana deslizante
            const pageTokens = buildSlidingPageNumbers(this.currentPage, totalPages);
            pageTokens.forEach((token) => {
                if (typeof token !== 'number') {
                    this.paginationList.appendChild(createPageItem({
                        isEllipsis: true,
                        disabled: true
                    }));
                    return;
                }
                this.paginationList.appendChild(createPageItem({
                    label: String(token),
                    ariaLabel: `Ir a la página ${token}`,
                    targetPage: token,
                    active: token === this.currentPage,
                    disabled: totalMatching === 0
                }));
            });

            // › Siguiente y » Última
            this.paginationList.appendChild(createPageItem({
                label: '›',
                ariaLabel: 'Página siguiente',
                targetPage: Math.min(totalPages, this.currentPage + 1),
                disabled: this.currentPage >= totalPages || totalMatching === 0
            }));
            this.paginationList.appendChild(createPageItem({
                label: '»',
                ariaLabel: 'Última página',
                targetPage: totalPages,
                disabled: this.currentPage >= totalPages || totalMatching === 0
            }));
        }
    }

    global.Systec = global.Systec || {};
    global.Systec.TablePaginator = TablePaginator;
})(window);
