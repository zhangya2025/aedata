<?php
/**
 * Plugin Name: Aegis Mega Header
 * Description: Provides a placeholder mega header block with utility bar, navigation, and mega panel layout.
 * Version: 0.1.0
 * Author: Aegis
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

function aegis_mega_header_register_block() {
register_block_type(
__DIR__,
[
'render_callback' => 'aegis_mega_header_render_block',
]
);
}
add_action( 'init', 'aegis_mega_header_register_block' );

function aegis_mega_header_placeholder_data() {
return [
'menu_items' => [
[ 'key' => 'snow', 'label' => 'Snow' ],
[ 'key' => 'men', 'label' => 'Men' ],
[ 'key' => 'women', 'label' => 'Women' ],
[ 'key' => 'kids', 'label' => 'Kids' ],
[ 'key' => 'equipment', 'label' => 'Equipment' ],
[ 'key' => 'sale', 'label' => 'Sale' ],
[ 'key' => 'discover', 'label' => 'Discover' ],
],
'panels'     => [
'snow'      => [
'left'    => [
'title' => 'Shop by Activity',
'links' => [ 'Backcountry Touring', 'Resort Riding', 'Splitboard Essentials', 'Freeride Favorites', 'Avalanche Ready', 'Base Layers' ],
],
'columns' => [
[ 'title' => 'Outerwear', 'links' => [ 'Shell Jackets', 'Insulated Jackets', 'Snow Pants', 'Bibs', 'Midlayers', 'Gloves & Mitts' ] ],
[ 'title' => 'Footwear', 'links' => [ 'Snow Boots', 'Hiking Boots', 'Approach Shoes', 'Waterproof Boots' ] ],
[ 'title' => 'Packs & Gear', 'links' => [ 'Ski Packs', 'Avalanche Safety', 'Goggles', 'Helmets', 'Tools' ] ],
[ 'title' => 'Featured', 'links' => [ 'Storm Guard Series', 'Lightweight Layers', 'Backcountry Kits', 'Winter Essentials' ] ],
[ 'title' => 'Explore', 'links' => [ 'Size Guide', 'Care & Repair', 'Pro Program', 'Gift Cards' ] ],
],
'promos'  => [
[ 'title' => 'Winter Drops', 'note' => 'Placeholder promo card' ],
[ 'title' => 'Layering Guide', 'note' => 'Placeholder promo card' ],
],
],
'men'       => [
'left'    => [
'title' => 'Collections',
'links' => [ 'New Arrivals', 'Insulation', 'Rain Shells', 'Trail Essentials', 'Puffers', 'Everyday Layers' ],
],
'columns' => [
[ 'title' => 'Jackets', 'links' => [ 'Rain Jackets', 'Softshells', 'Insulated Jackets', 'Windbreakers' ] ],
[ 'title' => 'Tops', 'links' => [ 'Flannels', 'Hoodies', 'Tees', 'Base Layers', 'Polos' ] ],
[ 'title' => 'Bottoms', 'links' => [ 'Hiking Pants', 'Shorts', 'Joggers', 'Denim' ] ],
[ 'title' => 'Accessories', 'links' => [ 'Hats & Beanies', 'Gloves', 'Belts', 'Socks' ] ],
],
'promos'  => [ [ 'title' => 'Urban Trek', 'note' => 'Placeholder promo card' ] ],
],
'women'     => [
'left'    => [
'title' => 'Collections',
'links' => [ 'New Arrivals', 'Insulated Favorites', 'Everyday Layers', 'Trail Ready', 'Rain Essentials', 'Travel Kits' ],
],
'columns' => [
[ 'title' => 'Jackets', 'links' => [ 'Parkas', 'Lightweight Shells', 'Insulated Jackets', 'Vests' ] ],
[ 'title' => 'Tops', 'links' => [ 'Fleece & Knits', 'Sweaters', 'Tees', 'Base Layers' ] ],
[ 'title' => 'Bottoms', 'links' => [ 'Leggings', 'Hiking Pants', 'Skorts', 'Shorts' ] ],
[ 'title' => 'Accessories', 'links' => [ 'Hats & Beanies', 'Scarves', 'Gloves', 'Packs' ] ],
],
'promos'  => [ [ 'title' => 'Cold Weather Edit', 'note' => 'Placeholder promo card' ] ],
],
'kids'      => [
'left'    => [ 'title' => 'Collections', 'links' => [ 'Snow Play', 'School Days', 'Weekend Hikes', 'New Arrivals' ] ],
'columns' => [
[ 'title' => 'Outerwear', 'links' => [ 'Jackets', 'Snow Pants', 'Rain Shells', 'Vests' ] ],
[ 'title' => 'Layers', 'links' => [ 'Hoodies', 'Fleece', 'Tees', 'Base Layers' ] ],
],
'promos'  => [ [ 'title' => 'Mini Explorers', 'note' => 'Placeholder promo card' ] ],
],
'equipment' => [
'left'    => [
'title' => 'Shop by Activity',
'links' => [ 'Climbing', 'Camping', 'Snow', 'Trail Running', 'Travel', 'Training' ],
],
'columns' => [
[ 'title' => 'Packs', 'links' => [ 'Daypacks', 'Overnight Packs', 'Hydration Packs', 'Duffels' ] ],
[ 'title' => 'Shelter', 'links' => [ 'Tents', 'Sleeping Bags', 'Sleeping Pads', 'Camp Furniture' ] ],
[ 'title' => 'Climbing', 'links' => [ 'Harnesses', 'Helmets', 'Protection', 'Ropes', 'Chalk & Bags' ] ],
[ 'title' => 'Accessories', 'links' => [ 'Lights', 'Poles', 'Tools', 'Care & Repair' ] ],
],
'promos'  => [ [ 'title' => 'Gear Lab', 'note' => 'Placeholder promo card' ] ],
],
'sale'      => [
'left'    => [ 'title' => 'Collections', 'links' => [ 'Winter Sale', 'Last Chance', 'Best Sellers' ] ],
'columns' => [ [ 'title' => 'Shop All', 'links' => [ 'Men', 'Women', 'Kids', 'Equipment' ] ] ],
'promos'  => [ [ 'title' => 'Save Now', 'note' => 'Placeholder promo card' ] ],
],
'discover'  => [
'left'    => [ 'title' => 'Discover', 'links' => [ 'Stories', 'Athletes', 'Events', 'About Aegis' ] ],
'columns' => [ [ 'title' => 'More', 'links' => [ 'Sustainability', 'Repairs', 'Newsletter', 'Gift Guides' ] ] ],
'promos'  => [ [ 'title' => 'Field Notes', 'note' => 'Placeholder promo card' ] ],
],
],
];
}

function aegis_mega_header_render_links( $links ) {
if ( empty( $links ) || ! is_array( $links ) ) {
return '';
}

$output = '';
foreach ( $links as $link ) {
$output .= '<li class="aegis-mega-header__link-item"><a href="#" class="aegis-mega-header__link">' . esc_html( $link ) . '</a></li>';
}

return $output;
}

function aegis_mega_header_render_panel( $panel ) {
if ( empty( $panel ) || ! is_array( $panel ) ) {
return '<div class="aegis-mega-header__panel-empty">Panel data not configured.</div>';
}

$left    = isset( $panel['left'] ) ? $panel['left'] : [];
$columns = isset( $panel['columns'] ) ? $panel['columns'] : [];
$promos  = isset( $panel['promos'] ) ? $panel['promos'] : [];

ob_start();
?>
<div class="aegis-mega-header__panel-grid">
<div class="aegis-mega-header__panel-left">
<div class="aegis-mega-header__panel-left-inner">
<?php if ( ! empty( $left['title'] ) ) : ?>
<div class="aegis-mega-header__panel-title"><?php echo esc_html( $left['title'] ); ?></div>
<?php endif; ?>
<?php if ( ! empty( $left['links'] ) && is_array( $left['links'] ) ) : ?>
<ul class="aegis-mega-header__panel-links">
<?php echo aegis_mega_header_render_links( $left['links'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</ul>
<?php endif; ?>
</div>
</div>
<div class="aegis-mega-header__panel-columns">
<?php foreach ( $columns as $column ) : ?>
<div class="aegis-mega-header__column">
<?php if ( ! empty( $column['title'] ) ) : ?>
<div class="aegis-mega-header__column-title"><?php echo esc_html( $column['title'] ); ?></div>
<div class="aegis-mega-header__column-divider" aria-hidden="true"></div>
<?php endif; ?>
<?php if ( ! empty( $column['links'] ) && is_array( $column['links'] ) ) : ?>
<ul class="aegis-mega-header__column-links">
<?php echo aegis_mega_header_render_links( $column['links'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</ul>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<div class="aegis-mega-header__panel-promos">
<?php if ( empty( $promos ) ) : ?>
<div class="aegis-mega-header__panel-empty">Panel data not configured.</div>
<?php else : ?>
<?php foreach ( $promos as $promo ) : ?>
<div class="aegis-mega-header__promo-card">
<div class="aegis-mega-header__promo-label"><?php echo esc_html( isset( $promo['title'] ) ? $promo['title'] : 'Promo' ); ?></div>
<div class="aegis-mega-header__promo-note"><?php echo esc_html( isset( $promo['note'] ) ? $promo['note'] : '' ); ?></div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>
<?php

return ob_get_clean();
}

function aegis_mega_header_render_block( $attributes ) {
$defaults   = [
'placeholder'    => true,
'showUtilityBar' => true,
'showSearch'     => true,
'showCart'       => true,
];
$attributes = wp_parse_args( $attributes, $defaults );

$placeholder = ! empty( $attributes['placeholder'] );

$data       = aegis_mega_header_placeholder_data();
$menu_items = isset( $data['menu_items'] ) ? $data['menu_items'] : [];
$panels     = isset( $data['panels'] ) ? $data['panels'] : [];

$panel_ids = [];
foreach ( $menu_items as $item ) {
$key               = isset( $item['key'] ) ? $item['key'] : uniqid( 'item' );
$panel_ids[ $key ] = wp_unique_id( 'aegis-mega-panel-' );
}

ob_start();
?>
<header class="aegis-mega-header" data-placeholder="<?php echo $placeholder ? 'true' : 'false'; ?>">
<?php if ( ! empty( $attributes['showUtilityBar'] ) ) : ?>
<div class="aegis-mega-header__utility">
<div class="aegis-mega-header__utility-links" aria-label="Utility">
<a class="aegis-mega-header__utility-link" href="#">Marmot Rewards</a>
<a class="aegis-mega-header__utility-link" href="#">Support</a>
<a class="aegis-mega-header__utility-link" href="#">Account</a>
</div>
</div>
<?php endif; ?>

<div class="aegis-mega-header__main">
<div class="aegis-mega-header__logo" aria-label="Site">
<a href="#" class="aegis-mega-header__logo-link">Aegis</a>
</div>
<nav class="aegis-mega-header__nav" aria-label="Primary">
<?php foreach ( $menu_items as $index => $item ) :
$key      = isset( $item['key'] ) ? $item['key'] : 'item-' . $index;
$label    = isset( $item['label'] ) ? $item['label'] : 'Item';
$panel_id = isset( $panel_ids[ $key ] ) ? $panel_ids[ $key ] : wp_unique_id( 'aegis-mega-panel-' );
?>
<button
class="aegis-mega-header__nav-item"
type="button"
data-mega-trigger="<?php echo esc_attr( $key ); ?>"
data-panel-target="<?php echo esc_attr( $panel_id ); ?>"
aria-expanded="false"
aria-controls="<?php echo esc_attr( $panel_id ); ?>"
>
<span><?php echo esc_html( $label ); ?></span>
</button>
<?php endforeach; ?>
</nav>
<div class="aegis-mega-header__actions">
<?php if ( ! empty( $attributes['showSearch'] ) ) : ?>
<form class="aegis-mega-header__search" role="search">
<label class="screen-reader-text" for="aegis-mega-header-search">Search</label>
<input id="aegis-mega-header-search" type="search" placeholder="Search" />
<button type="submit" class="aegis-mega-header__search-btn">Go</button>
</form>
<?php endif; ?>
<?php if ( ! empty( $attributes['showCart'] ) ) : ?>
<div class="aegis-mega-header__cart" aria-label="Cart">Cart</div>
<?php endif; ?>
</div>
</div>

<div class="aegis-mega-header__panel-shell" data-mega-panels>
<?php foreach ( $menu_items as $index => $item ) :
$key      = isset( $item['key'] ) ? $item['key'] : 'item-' . $index;
$label    = isset( $item['label'] ) ? $item['label'] : 'Item';
$panel_id = isset( $panel_ids[ $key ] ) ? $panel_ids[ $key ] : wp_unique_id( 'aegis-mega-panel-' );
$panel    = $placeholder && isset( $panels[ $key ] ) ? $panels[ $key ] : [];
?>
<div
class="aegis-mega-header__panel"
id="<?php echo esc_attr( $panel_id ); ?>"
role="region"
aria-label="<?php echo esc_attr( $label ); ?> menu"
data-panel-key="<?php echo esc_attr( $key ); ?>"
hidden
>
<?php
if ( $placeholder ) {
echo aegis_mega_header_render_panel( $panel ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} else {
echo '<div class="aegis-mega-header__panel-empty">Panel data not configured.</div>';
}
?>
</div>
<?php endforeach; ?>
</div>
</header>
<?php

return ob_get_clean();
}
