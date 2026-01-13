document.addEventListener('DOMContentLoaded', () => {
  const layout = document.querySelector('.aegis-info-layout');
  if (!layout) {
    return;
  }

  const toggle = layout.querySelector('.aegis-info-nav-toggle');
  const list = layout.querySelector('.aegis-info-nav-list');
  if (!toggle || !list) {
    return;
  }

  const mediaQuery = window.matchMedia('(max-width: 720px)');

  const setExpanded = (expanded, isMobile) => {
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    if (isMobile) {
      list.hidden = !expanded;
    } else {
      list.hidden = false;
    }
  };

  const syncForViewport = () => {
    if (mediaQuery.matches) {
      setExpanded(false, true);
    } else {
      setExpanded(true, false);
    }
  };

  syncForViewport();

  toggle.addEventListener('click', () => {
    if (!mediaQuery.matches) {
      return;
    }
    const expanded = toggle.getAttribute('aria-expanded') === 'true';
    setExpanded(!expanded, true);
  });

  mediaQuery.addEventListener('change', syncForViewport);
});
