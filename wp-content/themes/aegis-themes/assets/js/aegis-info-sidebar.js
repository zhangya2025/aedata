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

  const setExpanded = (expanded) => {
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  };

  if (!toggle.hasAttribute('aria-expanded')) {
    setExpanded(false);
  }

  toggle.addEventListener('click', () => {
    const expanded = toggle.getAttribute('aria-expanded') === 'true';
    setExpanded(!expanded);
  });
});
