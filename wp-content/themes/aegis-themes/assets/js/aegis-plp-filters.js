(function () {
    const viewportQuery = window.matchMedia('(max-width: 960px)');
    const setViewportCookie = () => {
        const value = viewportQuery.matches ? 'mobile' : 'desktop';
        document.cookie = `aegis_plp_viewport=${value}; path=/; max-age=300`;
    };

    setViewportCookie();
    if (viewportQuery.addEventListener) {
        viewportQuery.addEventListener('change', setViewportCookie);
    } else if (viewportQuery.addListener) {
        viewportQuery.addListener(setViewportCookie);
    }

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
        hydrateFromHiddenInputs();
        renderSelected();
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

    const form = container.querySelector('form');
    const selectedContainer = container.querySelector('[data-aegis-selected]');
    const clearButton = container.querySelector('[data-aegis-clear]');
    const inputs = Array.from(container.querySelectorAll('[data-filter-input]'));
    const checkboxes = Array.from(container.querySelectorAll('[data-filter-key]'));
    const state = {};
    const labelMap = new Map();

    const parseCsv = (value) => {
        if (value === null || value === undefined) {
            return [];
        }
        const trimmed = String(value).trim();
        if (trimmed === '' || /^,+$/.test(trimmed)) {
            return [];
        }
        return trimmed
            .split(',')
            .map((part) => part.trim())
            .filter((part) => part !== '' && !/^,+$/.test(part));
    };

    const hydrateFromHiddenInputs = () => {
        inputs.forEach((input) => {
            const name = input.getAttribute('data-filter-input') || input.name;
            if (!name) {
                return;
            }
            const values = parseCsv(input.value);
            if (!values.length) {
                delete state[name];
                return;
            }
            const set = ensureSet(name);
            set.clear();
            values.forEach((value) => set.add(value));
        });

        inputs.forEach((input) => {
            const name = input.getAttribute('data-filter-input') || input.name;
            if (!name) {
                return;
            }
            const label = input.getAttribute('data-filter-label');
            if (!label) {
                return;
            }
            const values = parseCsv(input.value);
            values.forEach((value) => {
                labelMap.set(`${name}::${value}`, `${label}: ${value}`);
            });
        });

        checkboxes.forEach((checkbox) => {
            const name = checkbox.getAttribute('data-filter-key');
            const value = checkbox.value;
            checkbox.checked = Boolean(state[name] && state[name].has(value));
        });
    };

    const ensureSet = (name) => {
        if (!state[name]) {
            state[name] = new Set();
        }
        return state[name];
    };

    const syncInputFromState = (name) => {
        const values = state[name] ? Array.from(state[name]) : [];
        inputs.forEach((input) => {
            if (input.getAttribute('data-filter-input') !== name) {
                return;
            }
            if (input.type === 'number' || input.type === 'text') {
                input.value = values[0] || '';
            } else {
                input.value = values.join(',');
            }
        });
    };

    const renderSelected = () => {
        if (!selectedContainer) {
            return;
        }
        selectedContainer.innerHTML = '';
        const fragment = document.createDocumentFragment();
        let hasSelections = false;
        Object.keys(state).forEach((name) => {
            state[name].forEach((value) => {
                hasSelections = true;
                const key = `${name}::${value}`;
                const label = labelMap.get(key) || value;
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'aegis-plp-filters__selected-chip';
                button.setAttribute('data-aegis-remove-name', name);
                button.setAttribute('data-aegis-remove-value', value);
                button.textContent = label;
                fragment.appendChild(button);
            });
        });
        if (!hasSelections) {
            const empty = document.createElement('span');
            empty.className = 'aegis-plp-filters__selected-empty';
            empty.textContent = 'No filters selected';
            fragment.appendChild(empty);
        }
        selectedContainer.appendChild(fragment);
    };

    const updateCheckboxState = (name, value, checked) => {
        container
            .querySelectorAll(`[data-filter-key="${name}"][value="${value}"]`)
            .forEach((checkbox) => {
                checkbox.checked = checked;
            });
    };

    const removeSelection = (name, value) => {
        if (!state[name]) {
            return;
        }
        state[name].delete(value);
        if (state[name].size === 0) {
            delete state[name];
        }
        updateCheckboxState(name, value, false);
        syncInputFromState(name);
        renderSelected();
    };

    checkboxes.forEach((checkbox) => {
        const name = checkbox.getAttribute('data-filter-key');
        const value = checkbox.value;
        const label =
            checkbox.getAttribute('data-filter-label') ||
            (checkbox.closest('label') ? checkbox.closest('label').innerText.trim() : value);
        labelMap.set(`${name}::${value}`, label.replace(/\s+$/u, '').replace(/\s+×$/u, ''));
        checkbox.addEventListener('change', () => {
            const set = ensureSet(name);
            if (checkbox.checked) {
                set.add(value);
            } else {
                set.delete(value);
            }
            if (set.size === 0) {
                delete state[name];
            }
            syncInputFromState(name);
            renderSelected();
        });
    });

    inputs.forEach((input) => {
        const name = input.getAttribute('data-filter-input');
        const label = input.getAttribute('data-filter-label');
        input.addEventListener('input', () => {
            const nextValues = parseCsv(input.value);
            if (!nextValues.length) {
                delete state[name];
            } else {
                const set = ensureSet(name);
                set.clear();
                nextValues.forEach((value) => set.add(value));
            }
            if (label) {
                nextValues.forEach((value) => {
                    labelMap.set(`${name}::${value}`, `${label}: ${value}`);
                });
            }
            renderSelected();
        });
    });

    if (selectedContainer) {
        selectedContainer.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            if (!target.matches('[data-aegis-remove-name]')) {
                return;
            }
            const name = target.getAttribute('data-aegis-remove-name');
            const value = target.getAttribute('data-aegis-remove-value');
            if (!name || !value) {
                return;
            }
            removeSelection(name, value);
        });
    }

    if (clearButton) {
        clearButton.addEventListener('click', (event) => {
            event.preventDefault();
            Object.keys(state).forEach((name) => {
                state[name].forEach((value) => {
                    updateCheckboxState(name, value, false);
                });
                delete state[name];
            });
            inputs.forEach((input) => {
                input.value = '';
            });
            renderSelected();
        });
    }

    const hasMeaningfulValue = (value) => {
        if (value === null || value === undefined) {
            return false;
        }
        const trimmed = String(value).trim();
        if (trimmed === '') {
            return false;
        }
        return /[^,]/.test(trimmed);
    };

    const isEmptyValue = (value) => {
        if (value === null || value === undefined) {
            return true;
        }
        const trimmed = String(value).trim();
        if (trimmed === '') {
            return true;
        }
        return /^,+$/.test(trimmed);
    };

    if (form) {
        form.addEventListener('submit', () => {
            const seenNames = new Set();
            Array.from(form.elements).forEach((element) => {
                if (!element || !element.name) {
                    return;
                }
                if (
                    seenNames.has(element.name) &&
                    element.type === 'hidden' &&
                    (element.name.startsWith('filter_') || element.name.startsWith('af_'))
                ) {
                    element.disabled = true;
                    return;
                }
                seenNames.add(element.name);
                if (element.name === 'af_sleepingbag_fill_type' && hasMeaningfulValue(element.value)) {
                    element.disabled = false;
                    return;
                }
                if (isEmptyValue(element.value)) {
                    element.disabled = true;
                }
            });
        });
    }

    hydrateFromHiddenInputs();
    renderSelected();
})();
