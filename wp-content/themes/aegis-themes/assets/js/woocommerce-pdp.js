(function () {
    const MODULE_IDS = [
        'gallery',
        'buybox',
        'trust',
        'highlights',
        'details',
        'reviews',
        'qa',
        'recommendations',
        'sticky_bar',
    ];

    const getDisabledModules = () => {
        if (!Array.isArray(window.AEGIS_WC_PDP_DISABLED)) {
            return [];
        }

        return window.AEGIS_WC_PDP_DISABLED;
    };

    const ensureModuleDataAttributes = () => {
        if (document.querySelector('[data-aegis-module]')) {
            return;
        }

        MODULE_IDS.forEach((id) => {
            const candidates = document.querySelectorAll(`.aegis-wc-module--${id}`);
            candidates.forEach((node) => {
                if (!node.hasAttribute('data-aegis-module')) {
                    node.setAttribute('data-aegis-module', id);
                }
            });
        });
    };

    const smoothScrollToTarget = (selector) => {
        if (!selector) {
            return;
        }

        let target = document.querySelector(selector);
        if (!target && selector.includes('buybox')) {
            target = document.querySelector('[data-aegis-module="buybox"]') ||
                document.querySelector('.aegis-wc-module--buybox');
        }

        if (!target) {
            return;
        }

        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const hideDisabledModules = () => {
        const disabled = getDisabledModules();

        disabled.forEach((id) => {
            const nodes = document.querySelectorAll(
                `[data-aegis-module="${id}"], .aegis-wc-module--${id}`
            );

            nodes.forEach((node) => {
                if (!node.hasAttribute('data-aegis-module')) {
                    node.setAttribute('data-aegis-module', id);
                }
                node.remove();
            });

            if (id === 'buybox') {
                const stickyNodes = document.querySelectorAll(
                    '[data-aegis-module="sticky_bar"], .aegis-wc-module--sticky_bar'
                );

                stickyNodes.forEach((node) => {
                    node.remove();
                });
            }
        });
    };

    const updateVariantToggleState = (select, toggle) => {
        const current = select.value;
        const buttons = toggle.querySelectorAll('.aegis-variant-toggle__btn');

        buttons.forEach((btn) => {
            const isActive = btn.dataset.value === current && current !== '';
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    };

    const mapLabelText = (select) => {
        const key = `${select.name || ''} ${select.id || ''}`.toLowerCase();

        if (key.includes('color')) {
            return 'COLOR';
        }

        if (key.includes('size')) {
            return 'SIZE';
        }

        const segments = key.split('-').filter(Boolean);
        const fallback = segments.length ? segments[segments.length - 1] : (select.getAttribute('aria-label') || '');
        return (fallback || 'Option').toUpperCase();
    };

    const relabelVariant = (select) => {
        const row = select.closest('tr');
        const labelNode = row && row.querySelector('th.label label, td.label label');

        if (!labelNode) {
            return;
        }

        labelNode.textContent = mapLabelText(select);
    };

    const buildVariantToggle = (select) => {
        if (select.dataset.aegisVariantInit === '1') {
            return null;
        }

        const options = Array.from(select.options).filter((opt) => opt.value);
        if (!options.length) {
            return null;
        }

        select.dataset.aegisVariantInit = '1';
        select.classList.add('aegis-variant-select');

        const toggle = document.createElement('div');
        toggle.className = 'aegis-variant-toggle';

        options.forEach((opt) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'aegis-variant-toggle__btn';
            btn.dataset.value = opt.value;
            btn.textContent = opt.textContent;
            btn.setAttribute('aria-pressed', 'false');

            btn.addEventListener('click', () => {
                select.value = opt.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                updateVariantToggleState(select, toggle);
            });

            toggle.appendChild(btn);
        });

        relabelVariant(select);

        const valueCell = select.closest('td.value');
        if (valueCell) {
            valueCell.appendChild(toggle);
        } else {
            select.insertAdjacentElement('afterend', toggle);
        }

        select.addEventListener('change', () => updateVariantToggleState(select, toggle));
        updateVariantToggleState(select, toggle);

        return toggle;
    };

    const readProductId = (buybox) => {
        const bodyMatch = (document.body.className || '').match(/\bpostid-(\d+)\b/);
        if (bodyMatch && bodyMatch[1]) {
            return bodyMatch[1];
        }

        const form = buybox && buybox.querySelector('form.variations_form');
        if (form) {
            const dataId = form.getAttribute('data-product_id') || form.getAttribute('data-product-id');
            if (dataId) {
                return dataId;
            }

            const inputId = form.querySelector('[name="product_id"]');
            if (inputId && inputId.value) {
                return inputId.value;
            }
        }

        return null;
    };

    const readInteractivityPriceHtml = (productId) => {
        if (!productId) {
            return '';
        }

        const script = document.getElementById('wp-script-module-data-@wordpress/interactivity');
        if (!script || !script.textContent) {
            return '';
        }

        try {
            const payload = JSON.parse(script.textContent);
            return (
                payload &&
                payload.config &&
                payload.config.woocommerce &&
                payload.config.woocommerce.products &&
                payload.config.woocommerce.products[productId] &&
                payload.config.woocommerce.products[productId].price_html
            ) || '';
        } catch (error) {
            return '';
        }
    };

    const initTitlePriceMirror = () => {
        const buybox = document.querySelector('.aegis-wc-module--buybox .aegis-wc-buybox__inner');
        if (!buybox) {
            return null;
        }

        const title = buybox.querySelector('.wp-block-post-title, .product_title, h1');
        if (!title) {
            return null;
        }

        let row = buybox.querySelector('.aegis-buybox-title-row');
        if (!row) {
            row = document.createElement('div');
            row.className = 'aegis-buybox-title-row';
            buybox.insertBefore(row, buybox.firstChild);
        }

        if (title.parentElement !== row) {
            row.appendChild(title);
        }

        let slot = row.querySelector('.aegis-buybox-price-mirror');
        if (!slot) {
            slot = document.createElement('div');
            slot.className = 'aegis-buybox-price-mirror';
            row.appendChild(slot);
        }

        const collectFallbackPriceHtml = () => {
            const selectors = [
                '.single_variation_wrap .price',
                '.wp-block-woocommerce-product-price',
                '.wc-block-components-product-price',
                '.price',
            ];

            for (const selector of selectors) {
                const node = buybox.querySelector(selector) || document.querySelector(selector);
                if (node && node.innerHTML && node.innerHTML.trim()) {
                    return node.innerHTML.trim();
                }
            }

            return '';
        };

        const productId = readProductId(buybox);
        let defaultPriceHtml = readInteractivityPriceHtml(productId) || collectFallbackPriceHtml();

        slot.dataset.defaultPrice = defaultPriceHtml || '';
        slot.innerHTML = defaultPriceHtml || '';

        if (!slot.innerHTML) {
            window.setTimeout(() => {
                const lateHtml = readInteractivityPriceHtml(productId) || collectFallbackPriceHtml();
                if (lateHtml) {
                    defaultPriceHtml = lateHtml;
                    slot.dataset.defaultPrice = lateHtml;
                    slot.innerHTML = lateHtml;
                }
            }, 120);
        }

        const getDefaultPrice = () => slot.dataset.defaultPrice || defaultPriceHtml || '';

        return { slot, getDefaultPrice, row };
    };

    const bindPriceSync = () => {
        const mirror = initTitlePriceMirror();
        if (!mirror) {
            return;
        }

        const { slot, getDefaultPrice } = mirror;
        const applyPrice = (html) => {
            const baseline = getDefaultPrice();
            const nextHtml = typeof html === 'string' && html.trim() ? html : baseline;
            slot.innerHTML = nextHtml;
        };

        const forms = document.querySelectorAll('.aegis-wc-module--buybox form.variations_form');
        forms.forEach((form) => {
            if (form.dataset.aegisPriceSyncBound === '1') {
                return;
            }

            form.dataset.aegisPriceSyncBound = '1';

            if (window.jQuery) {
                const $form = window.jQuery(form);

                $form.on('found_variation', (event, variation) => {
                    applyPrice(variation && variation.price_html);
                });

                $form.on('reset_data', () => {
                    applyPrice(getDefaultPrice());
                });
            }

            form.addEventListener('found_variation', (event) => {
                const variation = event && event.detail && event.detail.variation;
                applyPrice(variation && variation.price_html);
            });

            form.addEventListener('reset_data', () => {
                applyPrice(getDefaultPrice());
            });
        });
    };

    const initVariantToggles = () => {
        const forms = document.querySelectorAll('.aegis-wc-module--buybox form.variations_form');
        const selects = [];
        const toggles = [];

        forms.forEach((form) => {
            form.querySelectorAll('select').forEach((select) => {
                const toggle = buildVariantToggle(select);
                if (toggle) {
                    toggles.push(toggle);
                }
                selects.push(select);
            });

            const reset = form.querySelector('.reset_variations');
            if (reset && !reset.dataset.aegisVariantResetBound) {
                reset.dataset.aegisVariantResetBound = '1';
                reset.addEventListener('click', () => {
                    window.setTimeout(() => {
                        selects.forEach((select) => {
                            select.value = '';
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    }, 0);
                });
            }
        });

        if (toggles.length) {
            document.body.classList.add('aegis-variants-ready');
        }
    };

    const bindScrollButtons = () => {
        const scrollButtons = document.querySelectorAll('[data-scroll-target]');
        scrollButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                const selector = event.currentTarget.getAttribute('data-scroll-target');
                smoothScrollToTarget(selector);
            });
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        ensureModuleDataAttributes();
        hideDisabledModules();
        bindScrollButtons();
        initVariantToggles();
        bindPriceSync();
    });
})();
