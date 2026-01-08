( function () {
  function initMegaHeader( header ) {
    const panelShell = header.querySelector('[data-mega-panels]');
    const panels = header.querySelectorAll('.aegis-mega-header__panel');
    const nav = header.querySelector('.aegis-header__nav');
    const navItems = header.querySelectorAll('.aegis-header__nav-item');
    const mainBar = header.querySelector('.aegis-header__main');
    const mobileOverlay = header.querySelector('.aegis-mobile-overlay');
    const mobileDrawer = header.querySelector('.aegis-mobile-drawer');
    const mobilePanelsScript = header.querySelector('[data-mobile-panels]');
    const mobileRootView = header.querySelector('.aegis-mobile-view--root');
    const mobileSubView = header.querySelector('.aegis-mobile-view--sub');
    const mobileSubtitle = header.querySelector('[data-subtitle]');
    const mobileSubcontent = header.querySelector('[data-subcontent]');

    if ( ! header || ! mainBar ) {
      return;
    }

    let activeKey = null;
    let mainActive = false;
    let mobilePanelsData = {};
    let ticking = false;
    const scroller = document.scrollingElement || document.documentElement;
    const getY = () => ( scroller ? scroller.scrollTop || 0 : window.scrollY );
    let lastScrollY = getY();
    let megaOpen = false;
    let mainHover = false;
    let mainFocus = false;
    const isHome =
      document.body.classList.contains('home') ||
      document.body.classList.contains('front-page') ||
      document.body.classList.contains('page-id-49966');
    let scrollBehaviorEnabled = isHome && window.innerWidth > 960;
    const debugEnabled = window.location.search.includes('aegisHeaderDebug=1');

    if ( mobilePanelsScript && mobilePanelsScript.textContent ) {
      try {
        mobilePanelsData = JSON.parse( mobilePanelsScript.textContent );
      } catch ( e ) {
        mobilePanelsData = {};
      }
    }

    function isMegaTrigger( item ) {
      if ( ! item ) {
        return false;
      }
      const panelId = item.getAttribute('data-panel-target');
      if ( ! panelId ) {
        return false;
      }
      const panel = header.querySelector('#' + panelId);
      return !! panel;
    }

    function logDebug( reason, payload ) {
      if ( ! debugEnabled ) {
        return;
      }

      const summary = {
        reason,
        y: payload.currentY,
        lastY: lastScrollY,
        direction: payload.direction,
        isHome,
        enabled: scrollBehaviorEnabled,
        megaOpen,
        classes: Array.from( header.classList.values() ).join(' ' ),
      };

      // eslint-disable-next-line no-console
      console.log('[AEGIS HEADER]', summary);
    }

    function computeHeaderState( currentY, direction ) {
      const next = {
        isHomeClass: false,
        isHeaderHidden: false,
        isTopHidden: false,
        modeOverlay: false,
        modeSolid: false,
      };

      if ( ! scrollBehaviorEnabled ) {
        return next;
      }

      const atTop = currentY <= 10;
      next.isHomeClass = true;

      if ( megaOpen ) {
        next.modeSolid = true;
        next.isTopHidden = ! atTop;
        return next;
      }

      const shouldHide =
        direction === 'down' &&
        currentY > 80 &&
        ! mainHover &&
        ! mainFocus;
      next.isHeaderHidden = shouldHide;

      if ( shouldHide ) {
        return next;
      }

      const wasTopHidden = header.classList.contains('is-top-hidden');
      const revealMain = ! atTop && ( direction === 'up' || ( direction === 'none' && wasTopHidden ) );
      next.isTopHidden = revealMain;

      if ( atTop ) {
        next.isTopHidden = false;
      }

      const allowOverlay =
        atTop &&
        ! next.isTopHidden &&
        ! mainHover &&
        ! mainFocus;

      next.modeOverlay = allowOverlay;
      next.modeSolid = ! allowOverlay;

      return next;
    }

    function applyHeaderState( reason ) {
      const currentY = getY();
      const direction = currentY > lastScrollY ? 'down' : currentY < lastScrollY ? 'up' : 'none';
      const next = computeHeaderState( currentY, direction );

      header.classList.remove(
        'is-home',
        'is-header-hidden',
        'is-top-hidden',
        'mode-overlay',
        'mode-solid'
      );

      if ( next.isHomeClass ) {
        header.classList.add('is-home');
      }
      if ( next.isHeaderHidden ) {
        header.classList.add('is-header-hidden');
      }
      if ( next.isTopHidden ) {
        header.classList.add('is-top-hidden');
      }
      if ( next.modeOverlay ) {
        header.classList.add('mode-overlay');
      }
      if ( next.modeSolid ) {
        header.classList.add('mode-solid');
      }

      lastScrollY = currentY;

      logDebug( reason, { currentY, direction } );
    }

    function closePanels() {
      panels.forEach( ( panel ) => {
        panel.hidden = true;
        panel.classList.remove('is-active');
      } );
      navItems.forEach( ( trigger ) => {
        trigger.classList.remove('is-active');
        trigger.setAttribute('aria-expanded', 'false');
      } );
      if ( panelShell ) {
        panelShell.classList.remove('is-open');
      }
      header.classList.remove('is-mega-open');
      megaOpen = false;
      activeKey = null;
      applyHeaderState( 'close-panels' );
    }

    function openPanel( key, panelId, trigger ) {
      const panel = header.querySelector('#' + panelId);
      if ( ! panel || ! panelShell ) {
        closePanels();
        return;
      }

      panels.forEach( ( pane ) => {
        pane.hidden = true;
        pane.classList.remove('is-active');
      } );
      navItems.forEach( ( btn ) => {
        btn.classList.remove('is-active');
        btn.setAttribute('aria-expanded', 'false');
      } );

      panel.hidden = false;
      panel.classList.add('is-active');
      panelShell.classList.add('is-open');
      if ( trigger ) {
        trigger.classList.add('is-active');
        trigger.setAttribute('aria-expanded', 'true');
      }
      header.classList.add('is-mega-open');
      megaOpen = true;
      header.classList.remove('is-header-hidden');
      applyHeaderState( 'open-panel' );
      activeKey = key;
    }

    function handleNavEnter( event ) {
      const trigger = event.target.closest('.aegis-header__nav-item');
      if ( ! trigger || ! header.contains( trigger ) ) {
        return;
      }

      if ( ! isMegaTrigger( trigger ) ) {
        closePanels();
        return;
      }

      const key = trigger.getAttribute('data-mega-trigger');
      const panelId = trigger.getAttribute('data-panel-target');
      openPanel( key, panelId, trigger );
    }

    if ( nav ) {
      nav.addEventListener('mouseover', handleNavEnter);
      nav.addEventListener('focusin', handleNavEnter);
      nav.addEventListener('click', ( event ) => {
        const trigger = event.target.closest('.aegis-header__nav-item');
        if ( ! trigger || ! header.contains( trigger ) ) {
          return;
        }

        if ( ! isMegaTrigger( trigger ) ) {
          closePanels();
          return;
        }

        const key = trigger.getAttribute('data-mega-trigger');
        const panelId = trigger.getAttribute('data-panel-target');
        openPanel( key, panelId, trigger );
      } );
    }

    navItems.forEach( ( trigger ) => {
      trigger.addEventListener('keydown', ( event ) => {
        if ( event.key === 'Escape' ) {
          event.preventDefault();
          closePanels();
          trigger.focus();
        }
      } );
    } );

    if ( panelShell ) {
      panelShell.addEventListener('mouseleave', closePanels );
    }
    header.addEventListener('mouseleave', closePanels);

    header.addEventListener('focusout', () => {
      setTimeout( () => {
        const active = document.activeElement;
        if ( active && ! header.contains( active ) ) {
          closePanels();
        }
      }, 10 );
    } );

    document.addEventListener('keydown', ( event ) => {
      if ( event.key === 'Escape' && activeKey ) {
        event.preventDefault();
        closePanels();
      }
    } );

    function handleScroll() {
      applyHeaderState( 'scroll' );
      ticking = false;
    }

    function onScroll() {
      if ( ! scrollBehaviorEnabled ) {
        lastScrollY = getY();
        return;
      }

      if ( ! ticking ) {
        ticking = true;
        requestAnimationFrame( handleScroll );
      }
    }

    function syncScrollBehaviorEnabled() {
      scrollBehaviorEnabled = isHome && window.innerWidth > 960;
      lastScrollY = getY();
      applyHeaderState( 'sync-enabled' );
    }

    function lockBody( lock ) {
      if ( lock ) {
        document.body.classList.add('aegis-mobile-locked');
        document.body.style.overflow = 'hidden';
      } else {
        document.body.classList.remove('aegis-mobile-locked');
        document.body.style.overflow = '';
      }
    }

    function renderMobileSubcontent( itemId ) {
      if ( ! mobilePanelsData || ! mobileSubView || ! mobileSubcontent || ! mobileSubtitle ) {
        return;
      }

      const data = mobilePanelsData[ itemId ];

      if ( ! data ) {
        return;
      }

      mobileSubcontent.innerHTML = '';
      mobileSubtitle.textContent = data.label || '';

      if ( data.url && data.url !== '#' ) {
        const viewAll = document.createElement('a');
        viewAll.className = 'aegis-mobile-viewall';
        viewAll.href = data.url;
        viewAll.textContent = 'View all';
        mobileSubcontent.appendChild( viewAll );
      }

      ( data.columns || [] ).forEach( ( column ) => {
        const section = document.createElement('div');
        section.className = 'aegis-mobile-section';

        if ( column.title ) {
          const title = document.createElement('div');
          title.className = 'aegis-mobile-section__title';
          title.textContent = column.title;
          section.appendChild( title );
        }

        const list = document.createElement('ul');
        list.className = 'aegis-mobile-section__list';

        ( column.links || [] ).forEach( ( link ) => {
          const item = document.createElement('li');
          const anchor = document.createElement('a');
          anchor.href = link.url || '#';
          anchor.textContent = link.label || '';
          item.appendChild( anchor );
          list.appendChild( item );
        } );

        section.appendChild( list );
        mobileSubcontent.appendChild( section );
      } );
    }

    function openMobileDrawer() {
      if ( ! mobileDrawer || ! mobileOverlay ) {
        return;
      }

      mobileDrawer.hidden = false;
      mobileDrawer.setAttribute('aria-hidden', 'false');
      mobileOverlay.hidden = false;
      lockBody( true );
      if ( mobileRootView ) {
        mobileRootView.hidden = false;
      }
      if ( mobileSubView ) {
        mobileSubView.hidden = true;
      }
    }

    function closeMobileDrawer() {
      if ( mobileDrawer ) {
        mobileDrawer.hidden = true;
        mobileDrawer.setAttribute('aria-hidden', 'true');
      }
      if ( mobileOverlay ) {
        mobileOverlay.hidden = true;
      }
      if ( mobileRootView ) {
        mobileRootView.hidden = false;
      }
      if ( mobileSubView ) {
        mobileSubView.hidden = true;
      }
      lockBody( false );
    }

    function bindMobileNav() {
      const openers = header.querySelectorAll('[data-mobile-open]');
      const closers = header.querySelectorAll('[data-mobile-close]');

      openers.forEach( ( btn ) => {
        btn.addEventListener('click', ( event ) => {
          event.preventDefault();
          openMobileDrawer();
        } );
      } );

      closers.forEach( ( btn ) => {
        btn.addEventListener('click', ( event ) => {
          event.preventDefault();
          closeMobileDrawer();
        } );
      } );

      if ( mobileOverlay ) {
        mobileOverlay.addEventListener('click', ( event ) => {
          event.preventDefault();
          closeMobileDrawer();
        } );
      }

      if ( mobileRootView ) {
        mobileRootView.addEventListener('click', ( event ) => {
          const trigger = event.target.closest('[data-enter]');
          if ( ! trigger ) {
            return;
          }

          const itemId = trigger.getAttribute('data-enter');
          renderMobileSubcontent( itemId );
          if ( mobileRootView ) {
            mobileRootView.hidden = true;
          }
          if ( mobileSubView ) {
            mobileSubView.hidden = false;
          }
        } );
      }

      const backBtn = header.querySelector('[data-back]');
      if ( backBtn ) {
        backBtn.addEventListener('click', ( event ) => {
          event.preventDefault();
          if ( mobileRootView ) {
            mobileRootView.hidden = false;
          }
          if ( mobileSubView ) {
            mobileSubView.hidden = true;
          }
          mobileSubcontent && ( mobileSubcontent.innerHTML = '' );
          mobileSubtitle && ( mobileSubtitle.textContent = '' );
        } );
      }

      document.addEventListener('keydown', ( event ) => {
        if ( event.key === 'Escape' ) {
          closeMobileDrawer();
        }
      } );

      window.addEventListener('resize', () => {
        if ( window.innerWidth > 960 ) {
          closeMobileDrawer();
        }
        syncScrollBehaviorEnabled();
      } );
    }

    bindMobileNav();
    syncScrollBehaviorEnabled();
    applyHeaderState( 'init' );

    mainBar.addEventListener('mouseenter', () => {
      mainHover = true;
      applyHeaderState( 'main-hover' );
    });

    mainBar.addEventListener('mouseleave', () => {
      const activeElement = document.activeElement;
      if ( activeElement && mainBar.contains( activeElement ) ) {
        return;
      }
      mainHover = false;
      applyHeaderState( 'main-leave' );
    });

    mainBar.addEventListener('focusin', () => {
      mainFocus = true;
      applyHeaderState( 'main-focus' );
    });

    mainBar.addEventListener('focusout', () => {
      setTimeout( () => {
        const activeElement = document.activeElement;
        if ( activeElement && mainBar.contains( activeElement ) ) {
          return;
        }
        mainFocus = false;
        applyHeaderState( 'main-blur' );
      }, 10 );
    });
    window.addEventListener('scroll', onScroll, { passive: true } );
  }

  function initMiniCartDrawer() {
    const wrapper = document.querySelector('[data-aegis-mini-cart]');
    if ( ! wrapper ) {
      return;
    }

    const overlay = wrapper.querySelector('.aegis-mini-cart__overlay');
    const drawer = wrapper.querySelector('.aegis-mini-cart__drawer');
    let noticeTimer = null;
    let isOpen = false;

    function showNotice() {
      const notice = wrapper.querySelector('[data-aegis-mini-cart-notice]');
      if ( ! notice ) {
        return;
      }
      notice.classList.add('is-visible');
      if ( noticeTimer ) {
        window.clearTimeout( noticeTimer );
      }
      noticeTimer = window.setTimeout( () => {
        notice.classList.remove('is-visible');
      }, 2400 );
    }

    function refreshFragments() {
      if ( window.jQuery && window.jQuery( document.body ).trigger ) {
        window.jQuery( document.body ).trigger('wc_fragment_refresh');
      }
    }

    function openDrawer( showSuccess ) {
      if ( ! drawer || ! overlay ) {
        return;
      }
      overlay.hidden = false;
      drawer.hidden = false;
      drawer.setAttribute('aria-hidden', 'false');
      document.body.classList.add('aegis-mini-cart--open');
      isOpen = true;

      if ( showSuccess ) {
        showNotice();
      }
    }

    function closeDrawer() {
      if ( ! drawer || ! overlay ) {
        return;
      }
      overlay.hidden = true;
      drawer.hidden = true;
      drawer.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('aegis-mini-cart--open');
      isOpen = false;
    }

    if ( overlay ) {
      overlay.addEventListener('click', ( event ) => {
        event.preventDefault();
        closeDrawer();
      } );
    }

    wrapper.addEventListener('click', ( event ) => {
      const closer = event.target.closest('[data-aegis-mini-cart-close]');
      if ( ! closer ) {
        return;
      }
      event.preventDefault();
      closeDrawer();
    } );

    document.addEventListener('keydown', ( event ) => {
      if ( event.key === 'Escape' && isOpen ) {
        event.preventDefault();
        closeDrawer();
      }
    } );

    if ( window.jQuery ) {
      window.jQuery( document.body ).on('added_to_cart', () => {
        refreshFragments();
        if ( window.__aegisPendingOpenMiniCart ) {
          openDrawer( true );
          window.__aegisPendingOpenMiniCart = false;
        }
      } );

      window.jQuery( document.body ).on('wc_fragments_refreshed', () => {
        if ( window.__aegisPendingOpenMiniCart ) {
          openDrawer( true );
          window.__aegisPendingOpenMiniCart = false;
        }
      } );
    }

    document.addEventListener('click', ( event ) => {
      if ( event.target.closest('.single_add_to_cart_button') ) {
        window.__aegisPendingOpenMiniCart = true;
      }
    } );

    document.addEventListener('submit', ( event ) => {
      if ( event.target && event.target.matches('form.cart') ) {
        window.__aegisPendingOpenMiniCart = true;
      }
    } );

    const params = new URLSearchParams( window.location.search );
    const addToCartParam = params.has('add-to-cart') || params.has('added-to-cart');
    const successNotice = document.querySelector('.woocommerce-message');
    const errorNotice = document.querySelector('.woocommerce-error');
    if ( addToCartParam || ( successNotice && ! errorNotice ) ) {
      if ( successNotice ) {
        successNotice.style.display = 'none';
      }
      refreshFragments();
      openDrawer( true );
    }

    const addToCartParam = new URLSearchParams( window.location.search ).has('add-to-cart');
    const successNotice = document.querySelector('.woocommerce-message');
    if ( addToCartParam || successNotice ) {
      if ( successNotice ) {
        successNotice.style.display = 'none';
      }
      refreshFragments();
      openDrawer( true );
    }
  }

  function init() {
    const headers = document.querySelectorAll('.aegis-mega-header');
    headers.forEach( initMegaHeader );
    initMiniCartDrawer();
  }

  if ( document.readyState === 'loading' ) {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
} )();
