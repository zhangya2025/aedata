# Aegis WooCommerce Pages

A minimal WordPress plugin that creates the required WooCommerce pages on activation.

## Pages Created

- `cart` — content: `[woocommerce_cart]`
- `checkout` — content: `[woocommerce_checkout]`
- `my-account` — content: `[woocommerce_my_account]`
- `shop` — empty content
- `terms` — empty content (placeholder)

If any of these pages already exist, the plugin will leave them unchanged. If a page is in the trash, it will be restored and published.

## Manual WooCommerce Assignment

After activation, go to **WooCommerce → Settings → Advanced → Page setup** and manually assign the pages in the dropdowns.
