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

function aegis_mega_header_default_nav() {
    return [
        'top'  => [
            'enabled' => true,
            'links'   => [
                [ 'label' => '中文', 'url' => '#' ],
                [ 'label' => 'English', 'url' => '#' ],
            ],
        ],
        'main' => [
            'home'               => [ 'label' => 'HOME', 'type' => 'mega', 'url' => '#' ],
            'cloth'              => [ 'label' => 'CLOTH', 'type' => 'mega', 'url' => '#' ],
            'equipment'          => [ 'label' => 'EQUIPMENT', 'type' => 'mega', 'url' => '#' ],
            'technology'         => [ 'label' => 'TECHNOLOGY', 'type' => 'mega', 'url' => '#' ],
            'contact-us'         => [ 'label' => 'CONTACT US', 'type' => 'mega', 'url' => '#' ],
            'query-verification' => [ 'label' => 'QUERY VERIFICATION', 'type' => 'mega', 'url' => '#' ],
        ],
    ];
}

function aegis_mega_header_default_settings() {
    $nav_defaults = aegis_mega_header_default_nav();

    return [
        'branding' => [
            'logo_source'    => 'wp_site_logo',
            'plugin_logo_id' => 0,
            'logo_url'       => home_url( '/' ),
            'logo_alt'       => '',
            'logo_height_desktop' => 36,
            'logo_height_mobile'  => 32,
            'logo_max_width'      => 220,
        ],
        'ad_slots' => [
            'header_mega_promo_1' => [
                'image_id' => 0,
                'title'    => '',
                'subtitle' => '',
                'url'      => '',
                'new_tab'  => false,
            ],
            'header_mega_promo_2' => [
                'image_id' => 0,
                'title'    => '',
                'subtitle' => '',
                'url'      => '',
                'new_tab'  => false,
            ],
        ],
        'top' => $nav_defaults['top'],
        'nav' => $nav_defaults['main'],
    ];
}

function aegis_mega_header_get_settings() {
    $defaults = aegis_mega_header_default_settings();
    $saved    = get_option( 'aegis_mega_header_settings', [] );

    if ( ! is_array( $saved ) ) {
        $saved = [];
    }

    return array_replace_recursive( $defaults, $saved );
}

function aegis_mega_header_sanitize_settings( $settings ) {
    $defaults     = aegis_mega_header_default_settings();
    $nav_defaults = aegis_mega_header_default_nav();

    if ( ! is_array( $settings ) ) {
        return $defaults;
    }

    $branding          = isset( $settings['branding'] ) && is_array( $settings['branding'] ) ? $settings['branding'] : [];
    $branding_defaults = isset( $defaults['branding'] ) ? $defaults['branding'] : [];
    $top_input         = isset( $settings['top'] ) && is_array( $settings['top'] ) ? $settings['top'] : [];
    $nav_input         = isset( $settings['nav'] ) && is_array( $settings['nav'] ) ? $settings['nav'] : [];
    $logo_source       = isset( $branding['logo_source'] ) && in_array( $branding['logo_source'], [ 'wp_site_logo', 'plugin_logo' ], true ) ? $branding['logo_source'] : 'wp_site_logo';

    $logo_height_desktop = isset( $branding['logo_height_desktop'] ) ? absint( $branding['logo_height_desktop'] ) : 0;
    $logo_height_mobile  = isset( $branding['logo_height_mobile'] ) ? absint( $branding['logo_height_mobile'] ) : 0;
    $logo_max_width      = isset( $branding['logo_max_width'] ) ? absint( $branding['logo_max_width'] ) : 0;

    if ( $logo_height_desktop <= 0 ) {
        $logo_height_desktop = isset( $branding_defaults['logo_height_desktop'] ) ? absint( $branding_defaults['logo_height_desktop'] ) : 36;
    }

    if ( $logo_height_mobile <= 0 ) {
        $logo_height_mobile = isset( $branding_defaults['logo_height_mobile'] ) ? absint( $branding_defaults['logo_height_mobile'] ) : 32;
    }

    if ( $logo_max_width <= 0 ) {
        $logo_max_width = isset( $branding_defaults['logo_max_width'] ) ? absint( $branding_defaults['logo_max_width'] ) : 220;
    }

    $clean = [
        'branding' => [
            'logo_source'    => $logo_source,
            'plugin_logo_id' => isset( $branding['plugin_logo_id'] ) ? absint( $branding['plugin_logo_id'] ) : 0,
            'logo_url'       => ! empty( $branding['logo_url'] ) ? esc_url_raw( $branding['logo_url'] ) : home_url( '/' ),
            'logo_alt'       => isset( $branding['logo_alt'] ) ? sanitize_text_field( $branding['logo_alt'] ) : '',
            'logo_height_desktop' => $logo_height_desktop,
            'logo_height_mobile'  => $logo_height_mobile,
            'logo_max_width'      => $logo_max_width,
        ],
        'ad_slots' => [],
        'top'      => [
            'enabled' => ! empty( $top_input['enabled'] ),
            'links'   => [],
        ],
        'nav'      => [],
    ];

    $top_links_default = isset( $nav_defaults['top']['links'] ) ? $nav_defaults['top']['links'] : [];
    foreach ( $top_links_default as $index => $link_default ) {
        $link_input = isset( $top_input['links'][ $index ] ) ? $top_input['links'][ $index ] : [];
        $clean['top']['links'][ $index ] = [
            'label' => isset( $link_input['label'] ) && '' !== $link_input['label'] ? sanitize_text_field( $link_input['label'] ) : $link_default['label'],
            'url'   => ! empty( $link_input['url'] ) ? esc_url_raw( $link_input['url'] ) : $link_default['url'],
        ];
    }

    $main_defaults = isset( $nav_defaults['main'] ) ? $nav_defaults['main'] : [];
    foreach ( $main_defaults as $slug => $item_default ) {
        $item_input = isset( $nav_input[ $slug ] ) ? $nav_input[ $slug ] : [];
        $type       = isset( $item_input['type'] ) && in_array( $item_input['type'], [ 'mega', 'link' ], true ) ? $item_input['type'] : $item_default['type'];

        $clean['nav'][ $slug ] = [
            'label' => isset( $item_input['label'] ) && '' !== $item_input['label'] ? sanitize_text_field( $item_input['label'] ) : $item_default['label'],
            'type'  => $type,
            'url'   => ! empty( $item_input['url'] ) ? esc_url_raw( $item_input['url'] ) : $item_default['url'],
        ];
    }

    $slot_defaults = $defaults['ad_slots'];

    foreach ( $slot_defaults as $slot_key => $slot_default ) {
        $slot_settings = isset( $settings['ad_slots'][ $slot_key ] ) && is_array( $settings['ad_slots'][ $slot_key ] ) ? $settings['ad_slots'][ $slot_key ] : [];

        $clean['ad_slots'][ $slot_key ] = [
            'image_id' => isset( $slot_settings['image_id'] ) ? absint( $slot_settings['image_id'] ) : 0,
            'title'    => isset( $slot_settings['title'] ) ? sanitize_text_field( $slot_settings['title'] ) : '',
            'subtitle' => isset( $slot_settings['subtitle'] ) ? sanitize_text_field( $slot_settings['subtitle'] ) : '',
            'url'      => ! empty( $slot_settings['url'] ) ? esc_url_raw( $slot_settings['url'] ) : '',
            'new_tab'  => ! empty( $slot_settings['new_tab'] ),
        ];
    }

    return array_replace_recursive( $defaults, $clean );
}

function aegis_mega_header_register_settings_page() {
    add_theme_page(
        'Aegis Mega Header',
        'Aegis Mega Header',
        'manage_options',
        'aegis-mega-header',
        'aegis_mega_header_render_settings_page'
    );
}
add_action( 'admin_menu', 'aegis_mega_header_register_settings_page' );

function aegis_mega_header_admin_init() {
    register_setting(
        'aegis_mega_header_settings_group',
        'aegis_mega_header_settings',
        [
            'type'              => 'array',
            'sanitize_callback' => 'aegis_mega_header_sanitize_settings',
            'default'           => aegis_mega_header_default_settings(),
        ]
    );
}
add_action( 'admin_init', 'aegis_mega_header_admin_init' );

function aegis_mega_header_admin_assets( $hook ) {
    if ( 'appearance_page_aegis-mega-header' !== $hook ) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'aegis-mega-header-admin',
        plugins_url( 'admin.js', __FILE__ ),
        [ 'jquery' ],
        '0.1.0',
        true
    );
}
add_action( 'admin_enqueue_scripts', 'aegis_mega_header_admin_assets' );

function aegis_mega_header_placeholder_data() {
    return [
        'home'              => [
            'left'    => [ 'title' => 'Collections', 'links' => [ 'Featured Stories', 'New Season', 'Editor Picks', 'Community' ] ],
            'columns' => [
                [ 'title' => 'Highlights', 'links' => [ 'Latest Drops', 'Sustainability', 'Lookbook', 'Events' ] ],
                [ 'title' => 'Explore', 'links' => [ 'About Aegis', 'Our Mission', 'Heritage', 'Care & Repair' ] ],
            ],
            'promos'  => [ [ 'title' => 'Welcome Home', 'note' => 'Placeholder promo card' ] ],
        ],
        'cloth'             => [
            'left'    => [ 'title' => 'Collections', 'links' => [ 'Urban Line', 'Outdoor Line', 'Travel Ready', 'Seasonal Picks', 'Basics' ] ],
            'columns' => [
                [ 'title' => 'Categories', 'links' => [ 'Jackets', 'Tops', 'Bottoms', 'Layering', 'Accessories' ] ],
                [ 'title' => 'Shop By', 'links' => [ 'Activity', 'Weather', 'Fabric', 'Fit' ] ],
                [ 'title' => 'Featured', 'links' => [ 'New Arrivals', 'Limited', 'Best Sellers', 'Care Guide' ] ],
            ],
            'promos'  => [ [ 'title' => 'Style Edit', 'note' => 'Placeholder promo card' ] ],
        ],
        'equipment'         => [
            'left'    => [ 'title' => 'Shop by Use', 'links' => [ 'Climbing', 'Camping', 'Snow', 'Travel', 'Trail' ] ],
            'columns' => [
                [ 'title' => 'Packs & Bags', 'links' => [ 'Daypacks', 'Duffels', 'Technical Packs', 'Travel Bags' ] ],
                [ 'title' => 'Shelter & Sleep', 'links' => [ 'Tents', 'Sleeping Bags', 'Pads', 'Camp Furniture' ] ],
                [ 'title' => 'Accessories', 'links' => [ 'Lighting', 'Poles', 'Tools', 'Repair' ] ],
            ],
            'promos'  => [ [ 'title' => 'Gear Spotlight', 'note' => 'Placeholder promo card' ] ],
        ],
        'technology'        => [
            'left'    => [ 'title' => 'Innovations', 'links' => [ 'Fabric Science', 'Weatherproofing', 'Insulation', 'Comfort Systems' ] ],
            'columns' => [
                [ 'title' => 'Learn', 'links' => [ 'Material Guides', 'Performance Labs', 'Testing', 'Design Notes' ] ],
                [ 'title' => 'Programs', 'links' => [ 'Sustainability', 'Repair & Care', 'Warranty', 'Recycling' ] ],
            ],
            'promos'  => [ [ 'title' => 'Tech Preview', 'note' => 'Placeholder promo card' ] ],
        ],
        'contact-us'        => [
            'left'    => [ 'title' => 'Support', 'links' => [ 'Help Center', 'Store Locator', 'Size Guide', 'Warranty' ] ],
            'columns' => [
                [ 'title' => 'Get in Touch', 'links' => [ 'Chat', 'Email', 'Phone', 'Feedback' ] ],
                [ 'title' => 'Resources', 'links' => [ 'Shipping', 'Returns', 'Repairs', 'FAQ' ] ],
            ],
            'promos'  => [ [ 'title' => 'We are here', 'note' => 'Placeholder promo card' ] ],
        ],
        'query-verification' => [
            'left'    => [ 'title' => 'Verification', 'links' => [ 'Order Status', 'Authenticity', 'Warranty Check', 'Service Request' ] ],
            'columns' => [
                [ 'title' => 'Look Up', 'links' => [ 'Order Number', 'Email', 'Serial', 'Support Ticket' ] ],
                [ 'title' => 'More Help', 'links' => [ 'Guides', 'Policies', 'Security', 'Contact Team' ] ],
            ],
            'promos'  => [ [ 'title' => 'Check & Confirm', 'note' => 'Placeholder promo card' ] ],
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

function aegis_mega_header_promo_slots( $settings ) {
    $slots   = isset( $settings['ad_slots'] ) && is_array( $settings['ad_slots'] ) ? $settings['ad_slots'] : [];
    $order   = [ 'header_mega_promo_1', 'header_mega_promo_2' ];
    $results = [];

    foreach ( $order as $slot_key ) {
        if ( empty( $slots[ $slot_key ] ) || ! is_array( $slots[ $slot_key ] ) ) {
            continue;
        }

        $slot = $slots[ $slot_key ];
        $has  = ! empty( $slot['image_id'] ) || ! empty( $slot['title'] ) || ! empty( $slot['subtitle'] ) || ! empty( $slot['url'] );

        if ( ! $has ) {
            continue;
        }

        $results[] = [
            'image_id' => isset( $slot['image_id'] ) ? absint( $slot['image_id'] ) : 0,
            'title'    => isset( $slot['title'] ) ? $slot['title'] : '',
            'subtitle' => isset( $slot['subtitle'] ) ? $slot['subtitle'] : '',
            'url'      => isset( $slot['url'] ) ? $slot['url'] : '',
            'new_tab'  => ! empty( $slot['new_tab'] ),
        ];
    }

    return $results;
}

function aegis_mega_header_render_panel( $panel, $promo_slots, $use_placeholder ) {
if ( empty( $panel ) || ! is_array( $panel ) ) {
    $panel = [];
}

function aegis_mega_header_build_logo_image( $attachment_id, $alt = '' ) {
    if ( ! $attachment_id ) {
        return '';
    }

    $mime = get_post_mime_type( $attachment_id );
    $alt  = $alt ? $alt : get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

    if ( 'image/svg+xml' === $mime ) {
        $src = wp_get_attachment_url( $attachment_id );
        if ( ! $src ) {
            return '';
        }

        return '<img src="' . esc_url( $src ) . '" class="aegis-header__brand-image style-svg" alt="' . esc_attr( $alt ) . '" />';
    }

    return wp_get_attachment_image(
        $attachment_id,
        'full',
        false,
        [
            'class' => 'aegis-header__brand-image',
            'alt'   => $alt,
        ]
    );
}

$left         = isset( $panel['left'] ) ? $panel['left'] : [];
$columns      = isset( $panel['columns'] ) ? $panel['columns'] : [];
$promos       = isset( $panel['promos'] ) ? $panel['promos'] : [];
$has_structure = ! empty( $left ) || ! empty( $columns );

$active_promos = [];

if ( ! empty( $promo_slots ) ) {
    $active_promos = $promo_slots;
} elseif ( $use_placeholder && ! empty( $promos ) ) {
    $active_promos = $promos;
}

if ( ! $has_structure && empty( $active_promos ) ) {
    return '<div class="aegis-mega-header__panel-empty">Panel data not configured.</div>';
}

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
<?php if ( ! empty( $active_promos ) ) : ?>
<div class="aegis-mega__promo">
<?php foreach ( $active_promos as $promo ) :
    $title    = isset( $promo['title'] ) ? $promo['title'] : 'Promo';
    $subtitle = '';
    if ( isset( $promo['subtitle'] ) ) {
        $subtitle = $promo['subtitle'];
    } elseif ( isset( $promo['note'] ) ) {
        $subtitle = $promo['note'];
    }
    $image_id = isset( $promo['image_id'] ) ? absint( $promo['image_id'] ) : 0;
    $url      = isset( $promo['url'] ) ? $promo['url'] : '';
    $new_tab  = ! empty( $promo['new_tab'] );
    $tag      = $url ? 'a' : 'div';
    $attrs    = '';

    if ( $url ) {
        $attrs .= ' href="' . esc_url( $url ) . '"';
        if ( $new_tab ) {
            $attrs .= ' target="_blank" rel="noreferrer noopener"';
        }
    }
?>
<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="aegis-mega-header__promo-card"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
<?php if ( $image_id ) : ?>
<div class="aegis-mega-header__promo-image"><?php echo wp_get_attachment_image( $image_id, 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<?php endif; ?>
<div class="aegis-mega-header__promo-label"><?php echo esc_html( $title ); ?></div>
<?php if ( $subtitle ) : ?>
<div class="aegis-mega-header__promo-note"><?php echo esc_html( $subtitle ); ?></div>
<?php endif; ?>
</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php

return ob_get_clean();
}

function aegis_mega_header_render_brand( $settings ) {
    $branding = isset( $settings['branding'] ) && is_array( $settings['branding'] ) ? $settings['branding'] : [];
    $logo_url = ! empty( $branding['logo_url'] ) ? $branding['logo_url'] : home_url( '/' );
    $logo_alt = isset( $branding['logo_alt'] ) ? $branding['logo_alt'] : '';
    $html     = '';

    $use_site_logo = isset( $branding['logo_source'] ) ? $branding['logo_source'] : 'wp_site_logo';

    if ( 'wp_site_logo' === $use_site_logo ) {
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        if ( $custom_logo_id ) {
            $html = aegis_mega_header_build_logo_image( $custom_logo_id, $logo_alt );
        }
    }

    if ( ! $html && ! empty( $branding['plugin_logo_id'] ) ) {
        $plugin_logo_id = absint( $branding['plugin_logo_id'] );
        $html           = aegis_mega_header_build_logo_image( $plugin_logo_id, $logo_alt );
    }

    if ( ! $html ) {
        $html = '<span class="aegis-header__brand-text">' . esc_html( get_bloginfo( 'name', 'display' ) ?: 'Aegis' ) . '</span>';
    }

    return [
        'url'  => $logo_url,
        'html' => $html,
    ];
}

function aegis_mega_header_logo_dimensions( $settings ) {
    $defaults = aegis_mega_header_default_settings();
    $fallback = isset( $defaults['branding'] ) ? $defaults['branding'] : [];
    $branding = isset( $settings['branding'] ) && is_array( $settings['branding'] ) ? $settings['branding'] : [];

    $desktop = isset( $branding['logo_height_desktop'] ) ? absint( $branding['logo_height_desktop'] ) : 0;
    $mobile  = isset( $branding['logo_height_mobile'] ) ? absint( $branding['logo_height_mobile'] ) : 0;
    $max_w   = isset( $branding['logo_max_width'] ) ? absint( $branding['logo_max_width'] ) : 0;

    if ( $desktop <= 0 ) {
        $desktop = isset( $fallback['logo_height_desktop'] ) ? absint( $fallback['logo_height_desktop'] ) : 36;
    }

    if ( $mobile <= 0 ) {
        $mobile = isset( $fallback['logo_height_mobile'] ) ? absint( $fallback['logo_height_mobile'] ) : 32;
    }

    if ( $max_w <= 0 ) {
        $max_w = isset( $fallback['logo_max_width'] ) ? absint( $fallback['logo_max_width'] ) : 220;
    }

    return [
        'desktop' => $desktop,
        'mobile'  => $mobile,
        'max'     => $max_w,
    ];
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

$panel_data   = aegis_mega_header_placeholder_data();
$settings     = aegis_mega_header_get_settings();
$brand        = aegis_mega_header_render_brand( $settings );
$logo_sizes   = aegis_mega_header_logo_dimensions( $settings );
$promo_slots  = aegis_mega_header_promo_slots( $settings );
$nav_defaults = aegis_mega_header_default_nav();
$nav_settings = isset( $settings['nav'] ) ? $settings['nav'] : $nav_defaults['main'];
$top_settings = isset( $settings['top'] ) ? $settings['top'] : $nav_defaults['top'];

$panel_ids = [];
$menu_items = [];
$order      = array_keys( $nav_defaults['main'] );

foreach ( $order as $slug ) {
    $item_default = $nav_defaults['main'][ $slug ];
    $item_setting = isset( $nav_settings[ $slug ] ) ? $nav_settings[ $slug ] : $item_default;

    $label = isset( $item_setting['label'] ) && '' !== $item_setting['label'] ? $item_setting['label'] : $item_default['label'];
    $type  = isset( $item_setting['type'] ) && in_array( $item_setting['type'], [ 'mega', 'link' ], true ) ? $item_setting['type'] : $item_default['type'];
    $url   = ! empty( $item_setting['url'] ) ? $item_setting['url'] : $item_default['url'];

    $menu_items[] = [
        'key'   => $slug,
        'label' => $label,
        'type'  => $type,
        'url'   => $url,
    ];

    if ( 'mega' === $type ) {
        $panel_ids[ $slug ] = 'aegis-mega-panel-' . sanitize_key( $slug );
    }
}

ob_start();
$header_style = sprintf(
    '--aegis-logo-h:%dpx; --aegis-logo-h-mobile:%dpx; --aegis-logo-max-w:%dpx;',
    isset( $logo_sizes['desktop'] ) ? (int) $logo_sizes['desktop'] : 36,
    isset( $logo_sizes['mobile'] ) ? (int) $logo_sizes['mobile'] : 32,
    isset( $logo_sizes['max'] ) ? (int) $logo_sizes['max'] : 220
);
?>
<header class="aegis-mega-header" data-placeholder="<?php echo $placeholder ? 'true' : 'false'; ?>" style="<?php echo esc_attr( $header_style ); ?>">
<?php if ( ! empty( $attributes['showUtilityBar'] ) && ! empty( $top_settings['enabled'] ) ) :
    $top_links = isset( $top_settings['links'] ) ? $top_settings['links'] : [];
    ?>
<div class="aegis-header__top">
<div class="aegis-header__top-inner">
                <div class="aegis-header__top-links" aria-label="Utility">
<?php foreach ( $top_links as $link ) :
    $label = isset( $link['label'] ) ? $link['label'] : '';
    $url   = isset( $link['url'] ) ? $link['url'] : '#';
    ?>
                    <a class="aegis-header__top-link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
<?php endforeach; ?>
</div>
</div>
</div>
<?php endif; ?>

<div class="aegis-header__main">
<div class="aegis-header__main-inner">
<div class="aegis-header__brand" aria-label="Site">
<a href="<?php echo esc_url( $brand['url'] ); ?>" class="aegis-header__brand-link">
<?php echo $brand['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</a>
</div>
<nav class="aegis-header__nav" aria-label="Primary">
<?php foreach ( $menu_items as $index => $item ) :
$key      = isset( $item['key'] ) ? $item['key'] : 'item-' . $index;
$label    = isset( $item['label'] ) ? $item['label'] : 'Item';
$type     = isset( $item['type'] ) ? $item['type'] : 'mega';
$url      = isset( $item['url'] ) ? $item['url'] : '#';
$panel_id = isset( $panel_ids[ $key ] ) ? $panel_ids[ $key ] : '';
$is_mega  = 'mega' === $type && $panel_id;
?>
<a
class="aegis-header__nav-item"
href="<?php echo esc_url( $url ); ?>"
<?php if ( $is_mega ) : ?>
data-mega-trigger="<?php echo esc_attr( $key ); ?>"
data-panel-target="<?php echo esc_attr( $panel_id ); ?>"
aria-expanded="false"
aria-controls="<?php echo esc_attr( $panel_id ); ?>"
aria-haspopup="true"
<?php endif; ?>
>
<span><?php echo esc_html( $label ); ?></span>
</a>
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
$type     = isset( $item['type'] ) ? $item['type'] : 'link';

if ( 'mega' !== $type || ! isset( $panel_ids[ $key ] ) ) {
    continue;
}

$panel_id = $panel_ids[ $key ];
$panel    = $placeholder && isset( $panel_data[ $key ] ) ? $panel_data[ $key ] : [];
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
echo aegis_mega_header_render_panel( $panel, $promo_slots, $placeholder ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
?>
</div>
<?php endforeach; ?>
</div>
</div>
</header>
<?php

return ob_get_clean();
}

function aegis_mega_header_render_slot_fields( $slot_key, $slot_settings ) {
    $image_id   = isset( $slot_settings['image_id'] ) ? absint( $slot_settings['image_id'] ) : 0;
    $title      = isset( $slot_settings['title'] ) ? $slot_settings['title'] : '';
    $subtitle   = isset( $slot_settings['subtitle'] ) ? $slot_settings['subtitle'] : '';
    $url        = isset( $slot_settings['url'] ) ? $slot_settings['url'] : '';
    $new_tab    = ! empty( $slot_settings['new_tab'] );
    $preview_id = 'aegis-preview-' . esc_attr( $slot_key );
    $input_id   = 'aegis-image-' . esc_attr( $slot_key );
    $image_url  = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
    ?>
    <div class="aegis-slot">
        <p><strong><?php echo esc_html( strtoupper( str_replace( '_', ' ', $slot_key ) ) ); ?></strong></p>
        <div class="aegis-slot__media">
            <input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" name="aegis_mega_header_settings[ad_slots][<?php echo esc_attr( $slot_key ); ?>][image_id]" value="<?php echo esc_attr( $image_id ); ?>" />
            <button type="button" class="button aegis-media-select" data-media-target="<?php echo esc_attr( $input_id ); ?>" data-preview-target="<?php echo esc_attr( $preview_id ); ?>">Select image</button>
            <button type="button" class="button aegis-media-clear" data-clear-target="<?php echo esc_attr( $input_id ); ?>" data-preview-target="<?php echo esc_attr( $preview_id ); ?>">Clear</button>
            <div class="aegis-media-preview">
                <img id="<?php echo esc_attr( $preview_id ); ?>" src="<?php echo esc_url( $image_url ); ?>" style="max-width:200px; height:auto;<?php echo $image_url ? '' : 'display:none;'; ?>" alt="" />
            </div>
        </div>
        <p>
            <label>Title<br />
                <input type="text" class="regular-text" name="aegis_mega_header_settings[ad_slots][<?php echo esc_attr( $slot_key ); ?>][title]" value="<?php echo esc_attr( $title ); ?>" />
            </label>
        </p>
        <p>
            <label>Subtitle<br />
                <input type="text" class="regular-text" name="aegis_mega_header_settings[ad_slots][<?php echo esc_attr( $slot_key ); ?>][subtitle]" value="<?php echo esc_attr( $subtitle ); ?>" />
            </label>
        </p>
        <p>
            <label>URL<br />
                <input type="url" class="regular-text" name="aegis_mega_header_settings[ad_slots][<?php echo esc_attr( $slot_key ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" />
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="aegis_mega_header_settings[ad_slots][<?php echo esc_attr( $slot_key ); ?>][new_tab]" value="1" <?php checked( $new_tab ); ?> />
                Open in new tab
            </label>
        </p>
    </div>
    <?php
}

function aegis_mega_header_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings           = aegis_mega_header_get_settings();
    $defaults           = aegis_mega_header_default_settings();
    $branding_defaults  = isset( $defaults['branding'] ) ? $defaults['branding'] : [];
    $branding           = isset( $settings['branding'] ) ? $settings['branding'] : [];
    $ad_slots           = isset( $settings['ad_slots'] ) ? $settings['ad_slots'] : [];
    $nav_defaults       = aegis_mega_header_default_nav();
    $top_defaults = $nav_defaults['top'];
    $main_default = $nav_defaults['main'];
    $top_settings = isset( $settings['top'] ) ? $settings['top'] : $top_defaults;
    $nav_settings = isset( $settings['nav'] ) ? $settings['nav'] : $main_default;
    ?>
    <div class="wrap">
        <h1>Aegis Mega Header</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'aegis_mega_header_settings_group' ); ?>

            <h2>Top Bar</h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">Enable TOP</th>
                        <td>
                            <label>
                                <input type="checkbox" name="aegis_mega_header_settings[top][enabled]" value="1" <?php checked( ! empty( $top_settings['enabled'] ) ); ?> />
                                Show top utility bar
                            </label>
                        </td>
                    </tr>
                    <?php
                    $top_links = isset( $top_settings['links'] ) ? $top_settings['links'] : $top_defaults['links'];
                    foreach ( $top_links as $index => $link ) {
                        $label = isset( $link['label'] ) ? $link['label'] : $top_defaults['links'][ $index ]['label'];
                        $url   = isset( $link['url'] ) ? $link['url'] : $top_defaults['links'][ $index ]['url'];
                        ?>
                        <tr>
                            <th scope="row">Link <?php echo esc_html( $index + 1 ); ?></th>
                            <td>
                                <label>Label<br />
                                    <input type="text" class="regular-text" name="aegis_mega_header_settings[top][links][<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" />
                                </label>
                                <br />
                                <label>URL<br />
                                    <input type="url" class="regular-text" name="aegis_mega_header_settings[top][links][<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" />
                                </label>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>

            <h2>Main Navigation</h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <?php foreach ( $main_default as $slug => $defaults ) :
                        $item        = isset( $nav_settings[ $slug ] ) ? $nav_settings[ $slug ] : $defaults;
                        $label_value = isset( $item['label'] ) ? $item['label'] : $defaults['label'];
                        $type_value  = isset( $item['type'] ) ? $item['type'] : $defaults['type'];
                        $url_value   = isset( $item['url'] ) ? $item['url'] : $defaults['url'];
                        ?>
                        <tr>
                            <th scope="row"><?php echo esc_html( $defaults['label'] ); ?></th>
                            <td>
                                <p>
                                    <label>Label<br />
                                        <input type="text" class="regular-text" name="aegis_mega_header_settings[nav][<?php echo esc_attr( $slug ); ?>][label]" value="<?php echo esc_attr( $label_value ); ?>" />
                                    </label>
                                </p>
                                <p>
                                    <label><input type="radio" name="aegis_mega_header_settings[nav][<?php echo esc_attr( $slug ); ?>][type]" value="link" <?php checked( $type_value, 'link' ); ?> /> Link</label><br />
                                    <label><input type="radio" name="aegis_mega_header_settings[nav][<?php echo esc_attr( $slug ); ?>][type]" value="mega" <?php checked( $type_value, 'mega' ); ?> /> Mega</label>
                                </p>
                                <p>
                                    <label>URL<br />
                                        <input type="url" class="regular-text" name="aegis_mega_header_settings[nav][<?php echo esc_attr( $slug ); ?>][url]" value="<?php echo esc_attr( $url_value ); ?>" />
                                    </label>
                                </p>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Branding</h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">Logo Source</th>
                        <td>
                            <label><input type="radio" name="aegis_mega_header_settings[branding][logo_source]" value="wp_site_logo" <?php checked( isset( $branding['logo_source'] ) ? $branding['logo_source'] : 'wp_site_logo', 'wp_site_logo' ); ?> /> Use Site Logo</label><br />
                            <label><input type="radio" name="aegis_mega_header_settings[branding][logo_source]" value="plugin_logo" <?php checked( isset( $branding['logo_source'] ) ? $branding['logo_source'] : 'wp_site_logo', 'plugin_logo' ); ?> /> Use Plugin Logo</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Plugin Logo</th>
                        <td>
                            <?php
                            $plugin_logo_id  = isset( $branding['plugin_logo_id'] ) ? absint( $branding['plugin_logo_id'] ) : 0;
                            $plugin_preview  = $plugin_logo_id ? wp_get_attachment_image_url( $plugin_logo_id, 'medium' ) : '';
                            $plugin_input_id = 'aegis-plugin-logo-id';
                            $plugin_preview_id = 'aegis-plugin-logo-preview';
                            ?>
                            <input type="hidden" id="<?php echo esc_attr( $plugin_input_id ); ?>" name="aegis_mega_header_settings[branding][plugin_logo_id]" value="<?php echo esc_attr( $plugin_logo_id ); ?>" />
                            <button type="button" class="button aegis-media-select" data-media-target="<?php echo esc_attr( $plugin_input_id ); ?>" data-preview-target="<?php echo esc_attr( $plugin_preview_id ); ?>">Select image</button>
                            <button type="button" class="button aegis-media-clear" data-clear-target="<?php echo esc_attr( $plugin_input_id ); ?>" data-preview-target="<?php echo esc_attr( $plugin_preview_id ); ?>">Clear</button>
                            <div class="aegis-media-preview">
                                <img id="<?php echo esc_attr( $plugin_preview_id ); ?>" src="<?php echo esc_url( $plugin_preview ); ?>" style="max-width:200px; height:auto;<?php echo $plugin_preview ? '' : 'display:none;'; ?>" alt="" />
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Logo Link URL</th>
                        <td><input type="url" class="regular-text" name="aegis_mega_header_settings[branding][logo_url]" value="<?php echo esc_attr( isset( $branding['logo_url'] ) ? $branding['logo_url'] : home_url( '/' ) ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Logo Alt</th>
                        <td><input type="text" class="regular-text" name="aegis_mega_header_settings[branding][logo_alt]" value="<?php echo esc_attr( isset( $branding['logo_alt'] ) ? $branding['logo_alt'] : '' ); ?>" /></td>
                    </tr>
                    <?php
                    $logo_height_desktop = isset( $branding['logo_height_desktop'] ) ? absint( $branding['logo_height_desktop'] ) : ( isset( $branding_defaults['logo_height_desktop'] ) ? absint( $branding_defaults['logo_height_desktop'] ) : 36 );
                    $logo_height_mobile  = isset( $branding['logo_height_mobile'] ) ? absint( $branding['logo_height_mobile'] ) : ( isset( $branding_defaults['logo_height_mobile'] ) ? absint( $branding_defaults['logo_height_mobile'] ) : 32 );
                    $logo_max_width      = isset( $branding['logo_max_width'] ) ? absint( $branding['logo_max_width'] ) : ( isset( $branding_defaults['logo_max_width'] ) ? absint( $branding_defaults['logo_max_width'] ) : 220 );
                    ?>
                    <tr>
                        <th scope="row">Logo Height (Desktop)</th>
                        <td><input type="number" min="1" class="small-text" name="aegis_mega_header_settings[branding][logo_height_desktop]" value="<?php echo esc_attr( $logo_height_desktop ); ?>" /> px</td>
                    </tr>
                    <tr>
                        <th scope="row">Logo Height (Mobile)</th>
                        <td><input type="number" min="1" class="small-text" name="aegis_mega_header_settings[branding][logo_height_mobile]" value="<?php echo esc_attr( $logo_height_mobile ); ?>" /> px</td>
                    </tr>
                    <tr>
                        <th scope="row">Max Logo Width</th>
                        <td><input type="number" min="1" class="small-text" name="aegis_mega_header_settings[branding][logo_max_width]" value="<?php echo esc_attr( $logo_max_width ); ?>" /> px</td>
                    </tr>
                </tbody>
            </table>

            <h2>Ad Slots</h2>
            <p>Configure global promo cards used in the mega header.</p>
            <?php
            $slot_keys = [ 'header_mega_promo_1', 'header_mega_promo_2' ];
            foreach ( $slot_keys as $slot_key ) {
                $slot_settings = isset( $ad_slots[ $slot_key ] ) ? $ad_slots[ $slot_key ] : [];
                aegis_mega_header_render_slot_fields( $slot_key, $slot_settings );
            }
            ?>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
