(function() {
    var init = function() {
        var root = document.querySelector('.aegis-portal-root');
        if (!root) {
            return;
        }

        var toggle = root.querySelector('.aegis-portal-menu-toggle');
        var overlay = root.querySelector('.portal-drawer-overlay');
        var sidebar = root.querySelector('#aegis-portal-sidebar');
        if (!toggle || !overlay || !sidebar) {
            return;
        }

        var lockScroll = function(locked) {
            document.body.classList.toggle('aegis-portal-drawer-open', locked);
        };

        var closeDrawer = function() {
            root.classList.remove('is-drawer-open');
            toggle.setAttribute('aria-expanded', 'false');
            lockScroll(false);
        };

        var openDrawer = function() {
            root.classList.add('is-drawer-open');
            toggle.setAttribute('aria-expanded', 'true');
            lockScroll(true);
        };

        toggle.addEventListener('click', function() {
            if (root.classList.contains('is-drawer-open')) {
                closeDrawer();
                return;
            }
            openDrawer();
        });

        overlay.addEventListener('click', closeDrawer);

        sidebar.addEventListener('click', function(event) {
            if (event.target && event.target.closest('a')) {
                closeDrawer();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDrawer();
            }
        });

        root.dataset.drawerReady = '1';
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
