(() => {
    const root = document.querySelector('.aegis-portal-root.is-warehouse-mode');
    if (!root) {
        return;
    }

    const body = root.querySelector('.aegis-portal-body');
    const toggle = root.querySelector('.aegis-portal-menu-toggle');
    const backdrop = root.querySelector('.portal-sidebar-backdrop');
    const sidebar = root.querySelector('#aegis-portal-sidebar');

    if (!body || !toggle || !backdrop || !sidebar) {
        return;
    }

    const closeMenu = () => {
        body.classList.remove('is-menu-open');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', () => {
        const isOpen = body.classList.contains('is-menu-open');
        body.classList.toggle('is-menu-open', !isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    });

    backdrop.addEventListener('click', closeMenu);

    sidebar.addEventListener('click', (event) => {
        if (event.target && event.target.closest('a')) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });
})();

(() => {
    const ordersPage = document.querySelector('.aegis-orders-page');
    if (!ordersPage) {
        return;
    }

    const drawer = document.getElementById('aegis-orders-drawer');
    const drawerContent = document.getElementById('aegis-orders-drawer-content');

    const canonicalizeUrl = (inputUrl) => {
        try {
            const url = new URL(inputUrl, window.location.href);
            if (url.pathname.includes('/index.php/')) {
                url.pathname = url.pathname.replace('/index.php/', '/');
            }
            return url.toString();
        } catch (error) {
            return inputUrl;
        }
    };

    const refreshFromHtml = (html) => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const nextContent = doc.querySelector('#aegis-orders-drawer-content');
        if (nextContent && drawerContent) {
            drawerContent.innerHTML = nextContent.innerHTML;
            if (window.AegisOrders && typeof window.AegisOrders.initPriceMap === 'function') {
                window.AegisOrders.initPriceMap(drawerContent);
            }
            if (window.AegisOrders && typeof window.AegisOrders.initReview === 'function') {
                window.AegisOrders.initReview(drawerContent);
            }
            if (window.AegisOrders && typeof window.AegisOrders.initNotes === 'function') {
                window.AegisOrders.initNotes(drawerContent);
            }
        }

        const urlOrderId = new URL(window.location.href).searchParams.get('order_id');
        const orderId = drawerContent ? (drawerContent.querySelector('input[name="order_id"]')?.value || urlOrderId) : urlOrderId;
        if (orderId) {
            const nextRowMatch = doc.querySelector(`tr[data-order-id="${orderId}"]`);
            const currentRow = ordersPage.querySelector(`tr[data-order-id="${orderId}"]`);
            if (nextRowMatch && currentRow) {
                currentRow.replaceWith(nextRowMatch);
            }
        }

        if (drawer) {
            drawer.hidden = false;
            drawer.setAttribute('aria-hidden', 'false');
        }
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (!form.classList.contains('aegis-cancel-form')) {
            return;
        }

        event.preventDefault();

        const submitButton = form.querySelector('button[type="submit"]');
        const originalLabel = submitButton ? submitButton.textContent : '';
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = '提交中…';
        }

        const actionUrl = canonicalizeUrl(form.getAttribute('action') || window.location.href);

        fetch(actionUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: new FormData(form),
        })
            .then((response) => response.text().then((html) => ({ response, html })))
            .then(({ response, html }) => {
                if (response.url) {
                    window.history.replaceState({}, '', canonicalizeUrl(response.url));
                }
                refreshFromHtml(html);
            })
            .catch(() => {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalLabel;
                }
            });
    });
})();
