(function (global) {
    'use strict';

    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLocaleLowerCase();

    const initPropertyPicker = (select) => {
        const search = document.getElementById(select.dataset.searchInput);
        const resultCount = document.getElementById(select.dataset.resultCount);
        const categoryFilter = document.getElementById(select.dataset.categoryFilter);
        if (!search || !resultCount || !categoryFilter) return;

        const options = Array.from(select.options).filter((option) => option.value !== '');

        const filterOptions = () => {
            const query = normalize(search.value);
            let matches = 0;

            options.forEach((option) => {
                const searchMatches = !query
                    || normalize(option.dataset.search || option.textContent).includes(query);
                const categoryMatches = !categoryFilter.value
                    || option.dataset.category === categoryFilter.value;
                const match = searchMatches && categoryMatches;
                option.hidden = !match;
                option.disabled = !match;
                if (match) matches++;
            });

            if (select.value) {
                const selectedOption = select.options[select.selectedIndex];
                if (selectedOption && selectedOption.value !== '' && selectedOption.hidden) {
                    select.value = '';
                }
            }

            resultCount.textContent = matches === 1
                ? '1 bien disponible'
                : `${matches} bienes disponibles`;
        };

        search.addEventListener('input', filterOptions);
        categoryFilter.addEventListener('change', filterOptions);
        select.addEventListener('change', () => {
            if (!select.value) return;
            search.value = '';
            filterOptions();
        });

        filterOptions();
    };

    global.Systec = global.Systec || {};
    global.Systec.initPropertyPicker = initPropertyPicker;
})(window);
