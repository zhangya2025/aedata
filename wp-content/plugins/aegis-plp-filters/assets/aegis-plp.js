(function () {
  function init(root) {
    var overlay = root.querySelector('[data-aegis-overlay]');
    var drawer = document.getElementById('aegis-plp-drawer');
    var closeBtn = root.querySelector('[data-aegis-close]');
    var openBtns = root.querySelectorAll('[data-aegis-open]');

    if (!overlay || !drawer) return;

    function lockBody(lock) {
      if (lock) {
        document.body.style.overflow = 'hidden';
      } else {
        document.body.style.overflow = '';
      }
    }

    function openDrawer(targetId) {
      overlay.hidden = false;
      drawer.hidden = false;
      drawer.setAttribute('aria-hidden', 'false');
      lockBody(true);

      if (targetId) {
        var el = document.getElementById(targetId);
        if (el) {
          // If target is a <details>, open it.
          if (el.tagName && el.tagName.toLowerCase() === 'details') {
            el.open = true;
          }
          // Smooth scroll within drawer
          try {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
          } catch (e) {
            el.scrollIntoView(true);
          }
        }
      }
    }

    function closeDrawer() {
      overlay.hidden = true;
      drawer.hidden = true;
      drawer.setAttribute('aria-hidden', 'true');
      lockBody(false);
    }

    openBtns.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var targetId = btn.getAttribute('data-aegis-open');
        openDrawer(targetId);
      });
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', function (e) {
        e.preventDefault();
        closeDrawer();
      });
    }

    overlay.addEventListener('click', function (e) {
      e.preventDefault();
      closeDrawer();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeDrawer();
      }
    });

    // Keep hidden inputs synced (clean URL CSV format)
    function updateHidden(name) {
      var hidden = root.querySelector('[data-aegis-hidden="' + name + '"]');
      if (!hidden) return;

      var checked = root.querySelectorAll('input[type="checkbox"][data-aegis-filter="' + name + '"]:checked');
      var values = Array.prototype.map.call(checked, function (i) { return i.value; });
      hidden.value = values.join(',');
    }

    var checkboxes = root.querySelectorAll('input[type="checkbox"][data-aegis-filter]');
    var names = {};
    checkboxes.forEach(function (cb) {
      var name = cb.getAttribute('data-aegis-filter');
      names[name] = true;

      cb.addEventListener('change', function () {
        updateHidden(name);
      });
    });

    Object.keys(names).forEach(function (name) {
      updateHidden(name);
    });
  }

  function boot() {
    var root = document.querySelector('[data-aegis-plp]');
    if (root) init(root);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
