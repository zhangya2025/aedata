# Aegis Badges

Aegis Badges replaces or suppresses WooCommerce sale badges and adds preset styles with per-product overrides.

## Modes

Configured at **WooCommerce → Settings → Aegis Badges**:

- **Replace Woo Sale badge** (default): removes WooCommerce sale flashes and outputs the Aegis badge in the same hook positions.
- **Hide all sale badges**: removes WooCommerce sale flashes, strips Blocks sale badges, and adds a CSS fallback to hide any remaining badge markup.
- **Use Woo default**: leaves WooCommerce sale flashes and Blocks output untouched.

## Per-product overrides

In the product editor under **Product data → Badges**:

- **Badge behavior**
  - **inherit**: follow global settings.
  - **off**: never show the badge for this product.
  - **on**: force the badge to show when the product is on sale.
- **Preset**: pick A/B/C or inherit the global preset.
- **Text override**: if empty, the global default text is used.

Override priority:

1. Behavior `off` hides the badge.
2. Behavior `on` forces the badge, still requires the product to be on sale.
3. Behavior `inherit` uses the global settings.

## Presets and extension

Badge markup is always:

```html
<span class="aegis-badge aegis-badge--preset-a" data-preset="a">SALE</span>
```

Presets live in `assets/badges.css`. To add a preset:

1. Add a new CSS block with the class `.aegis-badge--preset-x`.
2. Extend the select options in `includes/admin-settings.php` and `includes/product-meta.php`.
3. Update the default validation lists in `aegis-badges.php` and `includes/product-meta.php`.

## WooCommerce Blocks support

When using WooCommerce Blocks product grids/collections, the plugin filters `woocommerce_blocks_product_grid_item_html` to remove the default sale badge markup and inject the Aegis badge instead. In hide mode, it removes the badge HTML and adds a CSS fallback to hide any remaining sale badge output.
