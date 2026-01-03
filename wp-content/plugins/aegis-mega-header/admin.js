(function () {
    function generateId() {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            return 'item_' + crypto.randomUUID();
        }
        return 'item_' + Date.now() + Math.floor(Math.random() * 10000);
    }

    function bindMediaButtons(context) {
        const selects = context.querySelectorAll('.aegis-media-select');
        const clears = context.querySelectorAll('.aegis-media-clear');

        selects.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const targetId = button.getAttribute('data-media-target');
                const previewId = button.getAttribute('data-preview-target');
                const targetInput = targetId ? document.getElementById(targetId) : null;
                const previewImg = previewId ? document.getElementById(previewId) : null;

                if (!targetInput) {
                    return;
                }

                const frame = wp.media({
                    title: 'Select image',
                    multiple: false,
                    library: { type: 'image' },
                });

                frame.on('select', () => {
                    const attachment = frame.state().get('selection').first().toJSON();
                    targetInput.value = attachment.id;
                    if (previewImg) {
                        previewImg.src = attachment.url;
                        previewImg.style.display = 'block';
                    }
                });

                frame.open();
            });
        });

        clears.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const targetId = button.getAttribute('data-clear-target');
                const previewId = button.getAttribute('data-preview-target');
                const targetInput = targetId ? document.getElementById(targetId) : null;
                const previewImg = previewId ? document.getElementById(previewId) : null;

                if (targetInput) {
                    targetInput.value = '';
                }
                if (previewImg) {
                    previewImg.src = '';
                    previewImg.style.display = 'none';
                }
            });
        });
    }

    function renumberItems(container) {
        const items = container.querySelectorAll('.aegis-main-item');

        items.forEach((item, index) => {
            item.dataset.index = index;
            item.querySelectorAll('[data-indexed-name]').forEach((field) => {
                const templateName = field.getAttribute('data-indexed-name');
                if (!templateName) {
                    return;
                }
                field.name = templateName.replace(/__INDEX__/g, index);
            });
        });
    }

    function closeOtherDetails(current, context) {
        const openDetails = context.querySelectorAll('.aegis-panel-details[open]');

        openDetails.forEach((detail) => {
            if (detail !== current) {
                detail.removeAttribute('open');
            }
        });
    }

    function bindPanelToggles(context) {
        const rows = context.querySelectorAll('.aegis-main-item');

        rows.forEach((row) => {
            const panel = row.querySelector('.aegis-panel-details');
            const radios = row.querySelectorAll('.aegis-nav-type');

            if (!panel || radios.length === 0) {
                return;
            }

            const update = () => {
                const isMega = Array.from(radios).some((radio) => radio.checked && radio.value === 'mega');
                panel.style.display = isMega ? '' : 'none';
                if (!isMega && panel.hasAttribute('open')) {
                    panel.removeAttribute('open');
                }
            };

            panel.addEventListener('toggle', () => {
                if (panel.open) {
                    closeOtherDetails(panel, context);
                }
            });

            radios.forEach((radio) => {
                radio.addEventListener('change', update);
            });

            update();
        });
    }

    function bindPromoModes(context) {
        const rows = context.querySelectorAll('.aegis-main-item');

        rows.forEach((row) => {
            const custom = row.querySelector('.aegis-promo-custom');
            const modes = row.querySelectorAll('.aegis-promo-mode');

            if (!custom || modes.length === 0) {
                return;
            }

            const update = () => {
                const checked = Array.from(modes).find((radio) => radio.checked);
                const mode = checked ? checked.value : 'global';
                custom.style.display = mode === 'custom' ? '' : 'none';
            };

            modes.forEach((radio) => {
                radio.addEventListener('change', update);
            });

            update();
        });
    }

    function addItem(container, template) {
        if (!template) {
            return;
        }
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.trim().replace(/__ID__/g, generateId());
        const newItem = wrapper.firstElementChild;
        if (!newItem) {
            return;
        }
        container.appendChild(newItem);
        renumberItems(container);
        bindPanelToggles(container);
        bindPromoModes(container);
        bindMediaButtons(newItem);

        const labelInput = newItem.querySelector('.aegis-main-item__field-label input');

        if (labelInput) {
            labelInput.focus();
        }

        if (typeof newItem.scrollIntoView === 'function') {
            newItem.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function bindListControls(container) {
        container.addEventListener('click', (event) => {
            const target = event.target;
            const row = target.closest('.aegis-main-item');

            if (!row) {
                return;
            }

            if (target.classList.contains('aegis-delete-item')) {
                event.preventDefault();
                if (window.confirm('Delete this item?')) {
                    row.remove();
                    renumberItems(container);
                    bindPanelToggles(container);
                    bindPromoModes(container);
                }
                return;
            }

            if (target.classList.contains('aegis-move-up')) {
                event.preventDefault();
                const prev = row.previousElementSibling;
                if (prev) {
                    container.insertBefore(row, prev);
                    renumberItems(container);
                    bindPanelToggles(container);
                    bindPromoModes(container);
                }
                return;
            }

            if (target.classList.contains('aegis-move-down')) {
                event.preventDefault();
                const next = row.nextElementSibling ? row.nextElementSibling.nextElementSibling : null;
                container.insertBefore(row, next);
                renumberItems(container);
                bindPanelToggles(container);
                bindPromoModes(container);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const adminWrap = document.querySelector('.wrap');
        if (!adminWrap) {
            return;
        }

        if (typeof wp !== 'undefined' && wp.media) {
            bindMediaButtons(adminWrap);
        }

        const mainContainer = adminWrap.querySelector('#aegis-main-items');
        const template = document.getElementById('aegis-main-item-template');

        if (mainContainer) {
            renumberItems(mainContainer);
            bindPanelToggles(mainContainer);
            bindPromoModes(mainContainer);
            bindListControls(mainContainer);
        }

        document.addEventListener('click', (event) => {
            const addButton = event.target.closest('[data-aegis-add-item]');
            if (!addButton || !mainContainer || !template) {
                return;
            }
            event.preventDefault();
            addItem(mainContainer, template);
        });
    });
})();
