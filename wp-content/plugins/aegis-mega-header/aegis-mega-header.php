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
    $items = [
        'home'              => [
            'label' => 'HOME',
            'panel' => [
                'left'    => [ 'title' => 'Collections', 'links' => [ 'Featured Stories', 'New Season', 'Editor Picks', 'Community' ] ],
                'columns' => [
                    [ 'title' => 'Highlights', 'links' => [ 'Latest Drops', 'Sustainability', 'Lookbook', 'Events' ] ],
                    [ 'title' => 'Explore', 'links' => [ 'About Aegis', 'Our Mission', 'Heritage', 'Care & Repair' ] ],
                ],
                'promos'  => [ [ 'title' => 'Welcome Home', 'note' => 'Placeholder promo card' ] ],
            ],
        ],
        'cloth'             => [
            'label' => 'CLOTH',
            'panel' => [
                'left'    => [ 'title' => 'Collections', 'links' => [ 'Urban Line', 'Outdoor Line', 'Travel Ready', 'Seasonal Picks', 'Basics' ] ],
                'columns' => [
                    [ 'title' => 'Categories', 'links' => [ 'Jackets', 'Tops', 'Bottoms', 'Layering', 'Accessories' ] ],
                    [ 'title' => 'Shop By', 'links' => [ 'Activity', 'Weather', 'Fabric', 'Fit' ] ],
                    [ 'title' => 'Featured', 'links' => [ 'New Arrivals', 'Limited', 'Best Sellers', 'Care Guide' ] ],
                ],
                'promos'  => [ [ 'title' => 'Style Edit', 'note' => 'Placeholder promo card' ] ],
            ],
        ],
        'equipment'         => [
            'label' => 'EQUIPMENT',
            'panel' => [
                'left'    => [ 'title' => 'Shop by Use', 'links' => [ 'Climbing', 'Camping', 'Snow', 'Travel', 'Trail' ] ],
                'columns' => [
                    [ 'title' => 'Packs & Bags', 'links' => [ 'Daypacks', 'Duffels', 'Technical Packs', 'Travel Bags' ] ],
                    [ 'title' => 'Shelter & Sleep', 'links' => [ 'Tents', 'Sleeping Bags', 'Pads', 'Camp Furniture' ] ],
                    [ 'title' => 'Accessories', 'links' => [ 'Lighting', 'Poles', 'Tools', 'Repair' ] ],
                ],
                'promos'  => [ [ 'title' => 'Gear Spotlight', 'note' => 'Placeholder promo card' ] ],
            ],
        ],
        'technology'        => [
            'label' => 'TECHNOLOGY',
            'panel' => [
                'left'    => [ 'title' => 'Innovations', 'links' => [ 'Fabric Science', 'Weatherproofing', 'Insulation', 'Comfort Systems' ] ],
                'columns' => [
                    [ 'title' => 'Learn', 'links' => [ 'Material Guides', 'Performance Labs', 'Testing', 'Design Notes' ] ],
                    [ 'title' => 'Programs', 'links' => [ 'Sustainability', 'Repair & Care', 'Warranty', 'Recycling' ] ],
                ],
                'promos'  => [ [ 'title' => 'Tech Preview', 'note' => 'Placeholder promo card' ] ],
            ],
        ],
        'contact-us'        => [
            'label' => 'CONTACT US',
            'panel' => [
                'left'    => [ 'title' => 'Support', 'links' => [ 'Help Center', 'Store Locator', 'Size Guide', 'Warranty' ] ],
                'columns' => [
                    [ 'title' => 'Get in Touch', 'links' => [ 'Chat', 'Email', 'Phone', 'Feedback' ] ],
                    [ 'title' => 'Resources', 'links' => [ 'Shipping', 'Returns', 'Repairs', 'FAQ' ] ],
                ],
                'promos'  => [ [ 'title' => 'We are here', 'note' => 'Placeholder promo card' ] ],
            ],
        ],
        'query-verification' => [
            'label' => 'QUERY VERIFICATION',
            'panel' => [
                'left'    => [ 'title' => 'Verification', 'links' => [ 'Order Status', 'Authenticity', 'Warranty Check', 'Service Request' ] ],
                'columns' => [
                    [ 'title' => 'Look Up', 'links' => [ 'Order Number', 'Email', 'Serial', 'Support Ticket' ] ],
                    [ 'title' => 'More Help', 'links' => [ 'Guides', 'Policies', 'Security', 'Contact Team' ] ],
                ],
                'promos'  => [ [ 'title' => 'Check & Confirm', 'note' => 'Placeholder promo card' ] ],
            ],
        ],
    ];

    $menu_items = [];
    $panels     = [];

    foreach ( $items as $key => $data ) {
        $menu_items[] = [
            'key'   => $key,
            'label' => isset( $data['label'] ) ? $data['label'] : strtoupper( $key ),
        ];

        if ( isset( $data['panel'] ) ) {
            $panels[ $key ] = $data['panel'];
        }
    }

    return [
        'menu_items' => $menu_items,
        'panels'     => $panels,
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
<div class="aegis-mega__sidebar">
<div class="aegis-mega__sidebar-inner">
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
<div class="aegis-mega__columns">
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
<div class="aegis-mega__promo">
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
        $panel_ids[ $key ] = 'aegis-mega-panel-' . sanitize_key( $key );
    }

ob_start();
?>
<header class="aegis-mega-header" data-placeholder="<?php echo $placeholder ? 'true' : 'false'; ?>">
<?php if ( ! empty( $attributes['showUtilityBar'] ) ) : ?>
<div class="aegis-header__top">
<div class="aegis-header__top-inner">
                <div class="aegis-header__top-links" aria-label="Utility">
                    <a class="aegis-header__top-link" href="#">中文</a>
                    <a class="aegis-header__top-link" href="#">English</a>
</div>
</div>
</div>
<?php endif; ?>

<div class="aegis-header__main">
<div class="aegis-header__main-inner">
<div class="aegis-header__brand" aria-label="Site">
<a href="#" class="aegis-header__brand-link">Aegis</a>
</div>
<nav class="aegis-header__nav" aria-label="Primary">
<?php foreach ( $menu_items as $index => $item ) :
$key      = isset( $item['key'] ) ? $item['key'] : 'item-' . $index;
$label    = isset( $item['label'] ) ? $item['label'] : 'Item';
$panel_id = isset( $panel_ids[ $key ] ) ? $panel_ids[ $key ] : wp_unique_id( 'aegis-mega-panel-' );
?>
<button
class="aegis-header__nav-item"
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
<div class="aegis-header__tools">
<?php if ( ! empty( $attributes['showSearch'] ) ) : ?>
<form class="aegis-header__search" role="search">
<label class="screen-reader-text" for="aegis-mega-header-search">Search</label>
<input id="aegis-mega-header-search" type="search" placeholder="Search" />
<button type="submit" class="aegis-header__search-btn">Go</button>
</form>
<?php endif; ?>
<?php if ( ! empty( $attributes['showCart'] ) ) : ?>
<div class="aegis-header__cart" aria-label="Cart">Cart</div>
<?php endif; ?>
</div>
</div>

</div>

<div class="aegis-header__mega" data-mega-panels>
<div class="aegis-header__mega-inner">
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
</div>
</header>
<?php

return ob_get_clean();
}
