(function (global) {
    'use strict';

    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLocaleLowerCase();

    const initPropertyPicker = (picker) => {
        const search = document.getElementById(picker.dataset.searchInput);
        const resultCount = document.getElementById(picker.dataset.resultCount);
        const categoryFilter = document.getElementById(picker.dataset.categoryFilter);
        const directionFilter = picker.dataset.directionFilter
            ? document.getElementById(picker.dataset.directionFilter)
            : null;
        if (!search || !resultCount || !categoryFilter) return;

        const isSelect = picker.tagName === 'SELECT';
        const preserveSelection = picker.hasAttribute('data-preserve-selection');
        const options = isSelect
            ? Array.from(picker.options).filter((option) => option.value !== '')
            : Array.from(picker.querySelectorAll('[data-property-picker-option]'));
        const getInput = (option) => isSelect ? null : option.querySelector('input[type="radio"]');
        const normalizedSearchText = new WeakMap();
        options.forEach((option) => {
            normalizedSearchText.set(option, normalize(option.dataset.search || option.textContent));
        });

        const filterOptions = () => {
            const query = normalize(search.value);
            const selectedCategory = normalize(categoryFilter.value);
            let matches = 0;

            options.forEach((option) => {
                const searchMatches = !query
                    || normalizedSearchText.get(option).includes(query);
                const categoryMatches = !selectedCategory
                    || normalize(option.dataset.category) === selectedCategory;
                const directionMatches = !directionFilter || !directionFilter.value
                    || option.dataset.direction === directionFilter.value;
                const match = searchMatches && categoryMatches && directionMatches;

                if (isSelect) {
                    option.hidden = !match;
                    option.disabled = !match;
                } else {
                    const input = getInput(option);
                    option.classList.toggle('d-none', !match);
                    if (input) {
                        if (!match && !preserveSelection) input.checked = false;
                        input.disabled = !match && !(preserveSelection && input.checked);
                        option.classList.toggle('active', input.checked);
                    }
                }
                if (match) matches++;
            });

            if (isSelect && picker.value) {
                const selectedOption = picker.options[picker.selectedIndex];
                if (selectedOption && selectedOption.value !== '' && selectedOption.hidden) {
                    picker.value = '';
                }
            }

            resultCount.textContent = matches === 0
                ? 'No hay bienes que coincidan. Prueba otra búsqueda o categoría.'
                : matches === 1
                    ? '1 bien disponible'
                    : `${matches} bienes disponibles`;
        };

        let searchTimer = null;
        search.addEventListener('input', () => {
            global.clearTimeout(searchTimer);
            searchTimer = global.setTimeout(filterOptions, 120);
        });
        categoryFilter.addEventListener('input', filterOptions);
        categoryFilter.addEventListener('change', filterOptions);
        directionFilter?.addEventListener('change', filterOptions);
        picker.addEventListener('change', (event) => {
            const radio = isSelect ? null : event.target.closest('input[type="radio"]');
            const hasSelection = isSelect ? Boolean(picker.value) : Boolean(radio && radio.checked);
            if (!isSelect) {
                picker.querySelectorAll('[data-property-picker-option]').forEach((option) => {
                    option.classList.toggle('active', getInput(option)?.checked || false);
                });
                const validationMessage = document.getElementById(picker.dataset.validationMessage);
                if (hasSelection && validationMessage) validationMessage.classList.remove('d-block');
            }

            filterOptions();
        });

        if (!isSelect) {
            picker.closest('form')?.addEventListener('submit', (event) => {
                if (picker.querySelector('input[type="radio"]:checked')) return;

                event.preventDefault();
                const validationMessage = document.getElementById(picker.dataset.validationMessage);
                if (validationMessage) validationMessage.classList.add('d-block');
                search.focus();
            });
        }

        filterOptions();
    };

    global.Systec = global.Systec || {};
    global.Systec.initPropertyPicker = initPropertyPicker;
})(window);
