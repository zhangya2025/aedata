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
    let reopenAfterRefresh = false;

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

    function clearBlocksErrorDom() {
      const wrapperNode = document.querySelector('.woocommerce-notices-wrapper');
      if ( ! wrapperNode ) {
        return;
      }
      const notices = wrapperNode.querySelectorAll('.wc-block-components-notice-banner.is-error');
      notices.forEach( ( notice ) => notice.remove() );
    }

    function syncBlocksStoresOnSuccess() {
      if ( ! window.wp || ! window.wp.data || ! window.wp.data.dispatch || ! window.wp.data.select ) {
        return false;
      }

      let didSync = false;
      try {
        const noticesStore = 'wc/store/notices';
        const noticesSelector = window.wp.data.select( noticesStore );
        const notices = ( noticesSelector && noticesSelector.getNotices && noticesSelector.getNotices() ) || [];
        const errorNotices = notices.filter( ( notice ) => notice && notice.status === 'error' );
        if ( errorNotices.length ) {
          const noticesDispatch = window.wp.data.dispatch( noticesStore );
          if ( noticesDispatch ) {
            if ( typeof noticesDispatch.removeNotice === 'function' ) {
              errorNotices.forEach( ( notice ) => {
                if ( notice && typeof notice.id !== 'undefined' ) {
                  noticesDispatch.removeNotice( notice.id );
                  didSync = true;
                }
              } );
            } else if ( typeof noticesDispatch.removeNotices === 'function' ) {
              noticesDispatch.removeNotices( errorNotices );
              didSync = true;
            } else if ( typeof noticesDispatch.clearNotices === 'function' ) {
              noticesDispatch.clearNotices();
              didSync = true;
            } else if ( typeof noticesDispatch.removeAllNotices === 'function' ) {
              noticesDispatch.removeAllNotices();
              didSync = true;
            }
          }
        }
      } catch ( error ) {
        // noop
      }

      try {
        const cartStore = 'wc/store/cart';
        const cartDispatch = window.wp.data.dispatch( cartStore );
        if ( cartDispatch ) {
          if ( typeof cartDispatch.invalidateResolutionForStoreSelector === 'function' ) {
            cartDispatch.invalidateResolutionForStoreSelector( 'getCart' );
            didSync = true;
          } else if ( typeof cartDispatch.invalidateResolution === 'function' ) {
            cartDispatch.invalidateResolution( 'getCart' );
            didSync = true;
          }
        }
      } catch ( error ) {
        // noop
      }

      return didSync;
    }

    function setButtonLoading( button, loading ) {
      if ( ! button ) {
        return;
      }
      button.disabled = !! loading;
      button.classList.toggle('is-loading', !! loading);
      button.classList.toggle('loading', !! loading);
    }

    function getAjaxEndpoint() {
      if ( window.wc_add_to_cart_params && window.wc_add_to_cart_params.wc_ajax_url ) {
        return window.wc_add_to_cart_params.wc_ajax_url.replace('%%endpoint%%', 'add_to_cart');
      }
      return '/?wc-ajax=add_to_cart';
    }

    function sendAddToCartRequest( form ) {
      const button = form.querySelector('.single_add_to_cart_button');
      const formData = new FormData( form );
      const variationInput = form.querySelector('input[name="variation_id"]');
      const selects = Array.from( form.querySelectorAll('select[name^="attribute_"]') );
      const pid =
        formData.get('product_id') ||
        form.getAttribute('data-product_id') ||
        form.dataset.product_id ||
        ( form.querySelector('input[name="product_id"]') || {} ).value ||
        formData.get('add-to-cart');
      if ( button && button.value ) {
        formData.set('add-to-cart', button.value);
      }
      if ( ! formData.has('quantity') ) {
        formData.set('quantity', '1');
      }
      if ( pid ) {
        formData.set('product_id', pid);
        formData.set('add-to-cart', pid);
      }
      if ( variationInput ) {
        formData.set('variation_id', variationInput.value || '0');
      }
      const vid =
        ( form.querySelector('input[name="variation_id"]') || {} ).value ||
        formData.get('variation_id');
      if ( vid ) {
        formData.set('variation_id', vid);
      }
      selects.forEach( ( select ) => {
        formData.set( select.name, select.value );
      } );

      clearBlocksErrorDom();
      setButtonLoading( button, true );

      return fetch( getAjaxEndpoint(), {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      } )
        .then( ( response ) => response.json() )
        .then( ( response ) => {
          if ( response && response.error ) {
            if ( window.console && window.console.warn ) {
              window.console.warn('[AEGIS MINI CART] add_to_cart error', response);
            }
            return;
          }

          const synced = syncBlocksStoresOnSuccess();
          if ( ! synced ) {
            clearBlocksErrorDom();
            setTimeout( clearBlocksErrorDom, 0 );
            setTimeout( clearBlocksErrorDom, 250 );
          }
          if ( response && response.fragments && response.fragments['#aegis-mini-cart-fragment'] ) {
            const fragment = wrapper.querySelector('#aegis-mini-cart-fragment');
            if ( fragment ) {
              fragment.innerHTML = response.fragments['#aegis-mini-cart-fragment'];
            }
          } else if ( window.jQuery ) {
            reopenAfterRefresh = true;
            window.jQuery( document.body ).trigger('wc_fragment_refresh');
          }

          if ( window.jQuery ) {
            window.jQuery( document.body ).trigger('added_to_cart', [
              response && response.fragments ? response.fragments : {},
              response && response.cart_hash ? response.cart_hash : '',
              window.jQuery( button ),
            ] );
          }

          openDrawer( true );
        } )
        .catch( () => {
          if ( window.console && window.console.warn ) {
            window.console.warn('[AEGIS MINI CART] add to cart failed');
          }
        } )
        .finally( () => {
          setButtonLoading( button, false );
        } );
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
        if ( reopenAfterRefresh ) {
          openDrawer( true );
          reopenAfterRefresh = false;
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
        const form = event.target;
        const variationInput = form.querySelector('input[name="variation_id"]');
        const selects = Array.from( form.querySelectorAll('select[name^="attribute_"]') );
        const variationId = variationInput
          ? parseInt( variationInput.value || '0', 10 )
          : null;
        const attrsOk = selects.length
          ? selects.every( ( select ) => ( select.value || '' ).trim().length > 0 )
          : true;
        if ( variationInput && ( variationId <= 0 || ! attrsOk ) ) {
          return;
        }
        window.__aegisPendingOpenMiniCart = true;
        event.preventDefault();
        console.log('[AEGIS MINI CART] submit intercepted', { variationId, attrsOk });
        sendAddToCartRequest( form );
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
