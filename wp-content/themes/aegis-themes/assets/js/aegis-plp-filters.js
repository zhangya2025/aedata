(function () {
    const container = document.querySelector('[data-aegis-plp-filters]');
    if (!container) {
        return;
    }

    const drawer = container.querySelector('[data-aegis-plp-drawer]');
    const overlay = container.querySelector('[data-drawer-overlay]');
    const openButtons = container.querySelectorAll('[data-drawer-open]');
    const closeButton = container.querySelector('[data-drawer-close]');

    const lockBody = () => document.body.classList.add('aegis-plp-filters-lock');
    const unlockBody = () => document.body.classList.remove('aegis-plp-filters-lock');

    const toggleSections = (mode) => {
        const sections = container.querySelectorAll('[data-aegis-plp-section]');
        sections.forEach((section) => {
            if (mode === 'all') {
                section.hidden = false;
                return;
            }
            section.hidden = section.getAttribute('data-aegis-plp-section') !== mode;
        });
    };

    const openDrawer = (mode) => {
        if (!drawer || !overlay) {
            return;
        }
        toggleSections(mode);
        drawer.classList.add('is-open');
        overlay.classList.add('is-open');
        lockBody();
    };

    const closeDrawer = () => {
        if (!drawer || !overlay) {
            return;
        }
        drawer.classList.remove('is-open');
        overlay.classList.remove('is-open');
        unlockBody();
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const mode = button.getAttribute('data-aegis-plp-mode') || 'all';
            openDrawer(mode);
        });
    });

    if (closeButton) {
        closeButton.addEventListener('click', closeDrawer);
    }

    if (overlay) {
        overlay.addEventListener('click', closeDrawer);
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeDrawer();
        }
    });

    const syncFilters = () => {
        const inputs = container.querySelectorAll('[data-filter-input]');
        const groups = {};

        container.querySelectorAll('[data-filter-key]').forEach((checkbox) => {
            if (!checkbox.checked) {
                return;
            }
            const key = checkbox.getAttribute('data-filter-key');
            if (!groups[key]) {
                groups[key] = [];
            }
            groups[key].push(checkbox.value);
        });

        inputs.forEach((hiddenInput) => {
            const key = hiddenInput.getAttribute('data-filter-input');
            const values = groups[key] || [];
            hiddenInput.value = values.join(',');
        });
    };

    container.querySelectorAll('[data-filter-key]').forEach((checkbox) => {
        checkbox.addEventListener('change', syncFilters);
    });

    syncFilters();
})();
