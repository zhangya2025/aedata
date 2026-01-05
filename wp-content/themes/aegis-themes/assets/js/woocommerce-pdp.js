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
    });
})();
