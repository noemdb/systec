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
        if (!search || !resultCount || !categoryFilter) return;

        const isSelect = picker.tagName === 'SELECT';
        const options = isSelect
            ? Array.from(picker.options).filter((option) => option.value !== '')
            : Array.from(picker.querySelectorAll('[data-property-picker-option]'));
        const getInput = (option) => isSelect ? null : option.querySelector('input[type="radio"]');

        const filterOptions = () => {
            const query = normalize(search.value);
            let matches = 0;

            options.forEach((option) => {
                const searchMatches = !query
                    || normalize(option.dataset.search || option.textContent).includes(query);
                const categoryMatches = !categoryFilter.value
                    || option.dataset.category === categoryFilter.value;
                const match = searchMatches && categoryMatches;

                if (isSelect) {
                    option.hidden = !match;
                    option.disabled = !match;
                } else {
                    const input = getInput(option);
                    option.classList.toggle('d-none', !match);
                    if (input) {
                        input.disabled = !match;
                        if (!match) input.checked = false;
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

        search.addEventListener('input', filterOptions);
        categoryFilter.addEventListener('change', filterOptions);
        picker.addEventListener('change', (event) => {
            const radio = isSelect ? null : event.target.closest('input[type="radio"]');
            const hasSelection = isSelect ? Boolean(picker.value) : Boolean(radio && radio.checked);
            if (!hasSelection) return;

            if (!isSelect) {
                picker.querySelectorAll('[data-property-picker-option]').forEach((option) => {
                    option.classList.toggle('active', getInput(option)?.checked || false);
                });
                const validationMessage = document.getElementById(picker.dataset.validationMessage);
                if (validationMessage) validationMessage.classList.remove('d-block');
            }

            search.value = '';
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
