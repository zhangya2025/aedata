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

    const formatBytes = (value) => {
        const size = Number(value);
        if (!size || Number.isNaN(size)) {
            return '';
        }
        const units = ['B', 'KB', 'MB', 'GB'];
        let index = 0;
        let num = size;
        while (num >= 1024 && index < units.length - 1) {
            num /= 1024;
            index += 1;
        }
        return `${num.toFixed(num >= 10 || index === 0 ? 0 : 1)} ${units[index]}`;
    };

    const renderPaymentPreview = (button) => {
        if (!drawerContent) {
            return;
        }
        const preview = drawerContent.querySelector('#aegis-payment-preview');
        if (!preview) {
            return;
        }
        const previewUrl = button.dataset.previewUrl || '';
        const downloadUrl = button.dataset.downloadUrl || previewUrl;
        const filename = button.dataset.filename || '';
        const size = button.dataset.size || '';
        const isImage = button.dataset.isImage === '1';
        const isPdf = button.dataset.isPdf === '1';
        const body = preview.querySelector('.aegis-payment-preview-body');
        const note = preview.querySelector('.aegis-payment-preview-note');
        const openBtn = preview.querySelector('.aegis-payment-preview-open');
        const downloadBtn = preview.querySelector('.aegis-payment-preview-download');
        const filenameEl = preview.querySelector('.aegis-payment-preview-filename');
        const sizeEl = preview.querySelector('.aegis-payment-preview-size');

        if (filenameEl) {
            filenameEl.textContent = filename ? `文件：${filename}` : '';
        }
        if (sizeEl) {
            const formatted = formatBytes(size);
            sizeEl.textContent = formatted ? `大小：${formatted}` : '';
        }
        if (openBtn) {
            openBtn.href = previewUrl;
        }
        if (downloadBtn) {
            downloadBtn.href = downloadUrl;
        }

        if (body) {
            body.innerHTML = '';
            if (isImage) {
                const link = document.createElement('a');
                link.href = previewUrl;
                link.target = '_blank';
                link.rel = 'noopener';
                const img = document.createElement('img');
                img.src = previewUrl;
                img.alt = filename || '付款凭证';
                link.appendChild(img);
                body.appendChild(link);
                if (note) {
                    note.hidden = true;
                }
            } else if (isPdf) {
                const frame = document.createElement('iframe');
                frame.src = previewUrl;
                frame.title = filename || '付款凭证';
                frame.loading = 'lazy';
                body.appendChild(frame);
                if (note) {
                    note.hidden = true;
                }
            } else if (note) {
                note.hidden = false;
            }
        }

        preview.hidden = false;
        preview.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }
        const previewButton = target.closest('.aegis-payment-preview-btn');
        if (previewButton) {
            event.preventDefault();
            renderPaymentPreview(previewButton);
            return;
        }
        const closeButton = target.closest('.aegis-payment-preview-close');
        if (closeButton && drawerContent) {
            const preview = drawerContent.querySelector('#aegis-payment-preview');
            if (preview) {
                const body = preview.querySelector('.aegis-payment-preview-body');
                if (body) {
                    body.innerHTML = '';
                }
                preview.hidden = true;
            }
        }
    });

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
