( function () {
  console.log('[AEGIS MINI CART] loaded');
  window.AEGIS_MINICART_LOADED = true;

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

    const aegisQueryParams = new URLSearchParams( window.location.search );
    const aegisHasAddToCart =
      aegisQueryParams.has('add-to-cart') || aegisQueryParams.has('added-to-cart');
    const successNotice = document.querySelector('.woocommerce-message');
    const errorNotice = document.querySelector('.woocommerce-error');
    if ( aegisHasAddToCart || ( successNotice && ! errorNotice ) ) {
      if ( successNotice ) {
        successNotice.style.display = 'none';
      }
      refreshFragments();
      openDrawer( true );
    }
  }

  if ( document.readyState === 'loading' ) {
    document.addEventListener('DOMContentLoaded', initMiniCartDrawer);
  } else {
    initMiniCartDrawer();
  }
} )();
