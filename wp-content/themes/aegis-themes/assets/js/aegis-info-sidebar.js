document.addEventListener('DOMContentLoaded', () => {
  const layout = document.querySelector('.aegis-info-layout');
  if (!layout) {
    return;
  }

  const nav = document.querySelector('.aegis-info-layout .aegis-info-nav');
  const btn = document.querySelector('.aegis-info-layout .aegis-info-nav-toggle');
  const list = document.querySelector('.aegis-info-layout .aegis-info-nav-list');
  if (!nav || !btn || !list) {
    return;
  }

  console.log('[AEGIS INFO SIDEBAR] mounted', {
    hasNav: !!nav,
    hasBtn: !!btn,
    hasList: !!list,
  });

  const mq = window.matchMedia('(max-width: 720px)');

  const applyByViewport = () => {
    if (mq.matches) {
      nav.classList.remove('is-open');
      nav.classList.add('is-collapsed');
      btn.setAttribute('aria-expanded', 'false');
    } else {
      nav.classList.add('is-open');
      nav.classList.remove('is-collapsed');
      btn.setAttribute('aria-expanded', 'true');
    }
  };

  applyByViewport();

  const handleToggle = () => {
    if (!mq.matches) {
      return;
    }
    const isOpen = nav.classList.contains('is-open');
    if (isOpen) {
      nav.classList.remove('is-open');
      nav.classList.add('is-collapsed');
      btn.setAttribute('aria-expanded', 'false');
    } else {
      nav.classList.add('is-open');
      nav.classList.remove('is-collapsed');
      btn.setAttribute('aria-expanded', 'true');
    }
  };

  btn.addEventListener('click', handleToggle);
  mq.addEventListener('change', applyByViewport);
});
