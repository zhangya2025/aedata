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
