# Aegis Badges

Aegis Badges replaces or suppresses WooCommerce sale badges and adds preset styles with per-product overrides, rule-based application, and WooCommerce Blocks support.

## Modes

Configured at **WooCommerce → Settings → Aegis Badges**:

- **Replace Woo Sale badge** (default): removes WooCommerce sale flashes and outputs the Aegis badge in the same hook positions and Blocks grid items.
- **Hide all sale badges**: removes WooCommerce sale flashes, strips Blocks sale badges, and adds a CSS fallback to hide any remaining badge markup.
- **Use Woo default**: leaves WooCommerce sale flashes and Blocks output untouched.

## Display strategy

Set **Display strategy** in the General tab:

- **Show for all on-sale products** (`sale_all`): current behavior, any on-sale product can show a badge unless explicitly turned off.
- **Opt-in only: show only when matched** (`opt_in_only`): only products explicitly opted in via per-product settings or rules will show badges, and only when on sale.

## Presets

Presets are editable in **WooCommerce → Settings → Aegis Badges → Presets**. Each preset stores:

- `label`: display name.
- `template`: `pill`, `ribbon`, or `corner`.
- `text`: default badge text (falls back to global default text when empty).
- `vars`: CSS variable values (`bg`, `fg`, `px`, `py`, `radius`, `font_size`, `font_weight`, `top`, `right`).

Preset data is stored in the `aegis_badges_presets` option.

## Apply rules

Rules are created under **Apply rules** on the preset page. Each rule can target:

- Categories (`product_cat`)
- Tags (`product_tag`)
- Attribute terms (`pa_` taxonomies)
- Specific products

Rules are stored in the `aegis_badges_rules` option. Higher priority rules win. When a product matches, its preset is selected unless a per-product preset override is set.

## Per-product overrides

In the product editor under **Product data → Badges**:

- **Badge behavior**
  - **inherit**: follow global settings.
  - **off**: never show the badge for this product.
  - **on**: force the badge to show when the product is on sale.
- **Preset**: pick preset A/B/C or inherit the global preset.
- **Text override**: if empty, the preset text is used, then the global default text.

Override priority:

1. Behavior `off` hides the badge.
2. Behavior `on` forces the badge (still requires the product to be on sale).
3. Per-product preset overrides rule-based and global presets.
4. Rules apply before the global default preset.

## WooCommerce Blocks support

When using WooCommerce Blocks product grids/collections, the plugin filters `woocommerce_blocks_product_grid_item_html` to remove the default sale badge markup and inject the Aegis badge instead. In hide mode, it removes the badge HTML and adds a CSS fallback to hide any remaining sale badge output.

## Preset extension

Badge markup is always:

```html
<span class="aegis-badge aegis-badge--pill" style="--bg:#e02424;">SALE</span>
```

Presets live in `assets/badges.css`. To add a preset:

1. Add a new CSS block with the class `.aegis-badge--your-template`.
2. Extend the select options in `includes/admin-settings.php` and `includes/product-meta.php`.
3. Update validation lists in `aegis-badges.php`, `includes/admin-presets.php`, and `includes/product-meta.php`.
