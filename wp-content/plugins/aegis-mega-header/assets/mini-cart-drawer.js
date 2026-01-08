( function () {
  console.log('[AEGIS MINI CART] loaded');
  window.AEGIS_MINICART_LOADED = true;
  const aegisSettings = window.AegisMiniCartSettings || {};
  const aegisDebugEnabled = !! aegisSettings.debug;
  const miniCartAjaxUrl = aegisSettings.ajaxUrl || '';
  const miniCartNonce = aegisSettings.nonce || '';

  function debugLog( ...args ) {
    if ( ! aegisDebugEnabled || ! window.console || ! window.console.log ) {
      return;
    }
    window.console.log( ...args );
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

    function setItemLoading( item, loading ) {
      if ( ! item ) {
        return;
      }
      item.classList.toggle('is-loading', !! loading);
      const inputs = item.querySelectorAll('input, button, a');
      inputs.forEach( ( input ) => {
        if ( input.classList.contains('remove_from_cart_button') ) {
          return;
        }
        if ( input.tagName === 'A' ) {
          input.setAttribute('aria-disabled', loading ? 'true' : 'false');
          if ( loading ) {
            input.setAttribute('data-disabled', 'true');
          } else {
            input.removeAttribute('data-disabled');
          }
          return;
        }
        input.disabled = !! loading;
      } );
    }

    function showErrorNotice( message ) {
      const noticeMessage = message || '无法加入购物车，请检查所选属性或库存。';
      if ( window.wp && window.wp.data && window.wp.data.dispatch ) {
        try {
          const noticesDispatch = window.wp.data.dispatch( 'wc/store/notices' );
          if ( noticesDispatch && typeof noticesDispatch.addNotice === 'function' ) {
            noticesDispatch.addNotice( {
              status: 'error',
              content: noticeMessage,
              isDismissible: true,
            } );
            return;
          }
        } catch ( error ) {
          // noop
        }
      }

      const wrapperNode = document.querySelector('.woocommerce-notices-wrapper') || document.querySelector('.woocommerce-notices');
      if ( ! wrapperNode ) {
        return;
      }

      const notice = document.createElement('div');
      notice.className = 'woocommerce-error';
      notice.setAttribute('role', 'alert');
      notice.textContent = noticeMessage;
      wrapperNode.appendChild( notice );
    }

    function serializeFormData( formData ) {
      const payload = {};
      for ( const [ key, value ] of formData.entries() ) {
        if ( Object.prototype.hasOwnProperty.call( payload, key ) ) {
          if ( Array.isArray( payload[ key ] ) ) {
            payload[ key ].push( value );
          } else {
            payload[ key ] = [ payload[ key ], value ];
          }
        } else {
          payload[ key ] = value;
        }
      }
      return payload;
    }

    function getAjaxEndpoint() {
      if ( window.wc_add_to_cart_params && window.wc_add_to_cart_params.wc_ajax_url ) {
        return window.wc_add_to_cart_params.wc_ajax_url.replace('%%endpoint%%', 'add_to_cart');
      }
      return '/?wc-ajax=add_to_cart';
    }

    function updateMiniCartFragments( response ) {
      if ( response && response.fragments && response.fragments['#aegis-mini-cart-fragment'] ) {
        const fragment = wrapper.querySelector('#aegis-mini-cart-fragment');
        if ( fragment ) {
          fragment.innerHTML = response.fragments['#aegis-mini-cart-fragment'];
        }
        return true;
      }
      return false;
    }

    function sendAddToCartRequest( form ) {
      const button = form.querySelector('.single_add_to_cart_button');
      const formData = new FormData( form );
      const selects = Array.from( form.querySelectorAll('select[name^="attribute_"]') );
      const parentId =
        ( form.querySelector('input[name="product_id"]') || {} ).value ||
        ( form.querySelector('input[name="add-to-cart"]') || {} ).value ||
        form.dataset.product_id ||
        form.getAttribute('data-product_id');
      if ( button && button.value ) {
        formData.set('add-to-cart', button.value);
      }
      if ( ! formData.has('quantity') ) {
        formData.set('quantity', '1');
      }
      if ( parentId ) {
        formData.set('product_id', parentId);
        formData.set('add-to-cart', parentId);
      }
      const vid = ( form.querySelector('input[name="variation_id"]') || {} ).value || '';
      formData.set('variation_id', vid );
      selects.forEach( ( select ) => {
        formData.set( select.name, select.value );
      } );
      const variationId = parseInt( vid || '0', 10 );
      if ( variationId > 0 ) {
        formData.set('product_id', String( variationId ) );
        formData.set('add-to-cart', String( variationId ) );
      }

      clearBlocksErrorDom();
      setButtonLoading( button, true );
      const ajaxEndpoint = getAjaxEndpoint();
      debugLog('[AEGIS MINI CART] add_to_cart payload', serializeFormData( formData ) );
      debugLog('[AEGIS MINI CART] add_to_cart url', ajaxEndpoint );

      return fetch( ajaxEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      } )
        .then( ( response ) => response.json() )
        .then( ( response ) => {
          debugLog('[AEGIS MINI CART] add_to_cart response', response );
          if ( response && response.error === true ) {
            if ( window.console && window.console.warn ) {
              window.console.warn('[AEGIS MINI CART] add_to_cart error payload', {
                product_id: formData.get('product_id'),
                add_to_cart: formData.get('add-to-cart'),
                variation_id: formData.get('variation_id'),
                attrs: Array.from( form.querySelectorAll('select[name^="attribute_"]') ).map(
                  ( select ) => [ select.name, select.value ]
                ),
                response,
              } );
            }
            showErrorNotice();
            return;
          }

          const synced = syncBlocksStoresOnSuccess();
          if ( ! synced ) {
            clearBlocksErrorDom();
            setTimeout( clearBlocksErrorDom, 0 );
            setTimeout( clearBlocksErrorDom, 250 );
          }
          if ( ! updateMiniCartFragments( response ) && window.jQuery ) {
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
          showErrorNotice('加入购物车失败，请稍后再试。');
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

    function sendUpdateQuantityRequest( cartItemKey, quantity, itemNode ) {
      if ( ! miniCartAjaxUrl || ! miniCartNonce ) {
        refreshFragments();
        return Promise.resolve();
      }
      const payload = new FormData();
      payload.set('action', 'aegis_update_cart_item');
      payload.set('cart_item_key', cartItemKey );
      payload.set('quantity', String( quantity ) );
      payload.set('security', miniCartNonce );

      setItemLoading( itemNode, true );

      return fetch( miniCartAjaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: payload,
      } )
        .then( ( response ) => response.json() )
        .then( ( response ) => {
          debugLog('[AEGIS MINI CART] update cart item response', response );
          if ( response && response.success === false ) {
            showErrorNotice( response.data && response.data.message ? response.data.message : undefined );
            return;
          }

          syncBlocksStoresOnSuccess();
          if ( ! updateMiniCartFragments( response ) ) {
            refreshFragments();
          }
        } )
        .catch( () => {
          showErrorNotice('更新购物车失败，请稍后再试。');
        } )
        .finally( () => {
          setItemLoading( itemNode, false );
        } );
    }

    wrapper.addEventListener('click', ( event ) => {
      const closer = event.target.closest('[data-aegis-mini-cart-close]');
      if ( ! closer ) {
        const qtyButton = event.target.closest('[data-aegis-mini-cart-qty]');
        if ( ! qtyButton ) {
          return;
        }
        event.preventDefault();
        const cartItemKey = qtyButton.getAttribute('data-cart-item-key');
        const direction = qtyButton.getAttribute('data-aegis-mini-cart-qty');
        if ( ! cartItemKey || ! direction ) {
          return;
        }
        const itemNode = qtyButton.closest('.aegis-mini-cart__item');
        const input = itemNode ? itemNode.querySelector('.aegis-mini-cart__qty-input') : null;
        const currentQty = input ? parseInt( input.value || '1', 10 ) : 1;
        const delta = direction === 'increase' ? 1 : -1;
        const nextQty = Math.max( 1, currentQty + delta );
        if ( input ) {
          input.value = String( nextQty );
        }
        sendUpdateQuantityRequest( cartItemKey, nextQty, itemNode );
        return;
      }
      event.preventDefault();
      closeDrawer();
    } );

    wrapper.addEventListener('change', ( event ) => {
      const input = event.target.closest('.aegis-mini-cart__qty-input');
      if ( ! input ) {
        return;
      }
      const cartItemKey = input.getAttribute('data-cart-item-key');
      if ( ! cartItemKey ) {
        return;
      }
      const itemNode = input.closest('.aegis-mini-cart__item');
      const parsedQty = parseInt( input.value || '1', 10 );
      const nextQty = Number.isFinite( parsedQty ) && parsedQty > 0 ? parsedQty : 1;
      input.value = String( nextQty );
      sendUpdateQuantityRequest( cartItemKey, nextQty, itemNode );
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
        debugLog('[AEGIS MINI CART] submit intercepted', { variationId, attrsOk });
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
