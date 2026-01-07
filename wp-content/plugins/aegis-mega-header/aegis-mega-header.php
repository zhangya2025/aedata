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

function aegis_mega_header_has_woocommerce() {
    return function_exists( 'WC' ) && WC();
}

function aegis_mega_header_register_block() {
    $style_path  = plugin_dir_path( __FILE__ ) . 'style.css';
    $script_path = plugin_dir_path( __FILE__ ) . 'view.js';

    wp_register_style(
        'aegis-mega-header',
        plugins_url( 'style.css', __FILE__ ),
        [],
        filemtime( $style_path )
    );

    wp_register_script(
        'aegis-mega-header-view',
        plugins_url( 'view.js', __FILE__ ),
        [],
        filemtime( $script_path ),
        true
    );

    register_block_type(
        __DIR__,
        [
            'render_callback' => 'aegis_mega_header_render_block',
            'style'           => 'aegis-mega-header',
            'script'          => 'aegis-mega-header-view',
        ]
    );
}
add_action( 'init', 'aegis_mega_header_register_block' );

function aegis_mega_header_default_promo_config() {
    return [
        'mode'   => 'global',
        'custom' => [
            'promo_1' => [
                'image_id' => 0,
                'title'    => '',
                'subtitle' => '',
                'url'      => '',
                'new_tab'  => false,
            ],
            'promo_2' => [
                'image_id' => 0,
                'title'    => '',
                'subtitle' => '',
                'url'      => '',
                'new_tab'  => false,
            ],
        ],
    ];
}

function aegis_mega_header_default_nav() {
    $promo_default = aegis_mega_header_default_promo_config();

    $defaults = [
        'top'  => [
            'enabled' => true,
            'links'   => [
                [ 'label' => '中文', 'url' => '#' ],
                [ 'label' => 'English', 'url' => '#' ],
            ],
        ],
        'main' => [
            'items' => [
                [
                    'id'    => 'home',
                    'label' => 'HOME',
                    'type'  => 'mega',
                    'url'   => '#',
                    'panel' => [
                        'sidebar' => [
                            'title' => 'Collections',
                            'links' => [
                                [ 'label' => 'Featured Stories', 'url' => '#' ],
                                [ 'label' => 'New Season', 'url' => '#' ],
                                [ 'label' => 'Editor Picks', 'url' => '#' ],
                                [ 'label' => 'Community', 'url' => '#' ],
                            ],
                        ],
                        'groups'  => [
                            [
                                'title' => 'Highlights',
                                'links' => [
                                    [ 'label' => 'Latest Drops', 'url' => '#' ],
                                    [ 'label' => 'Sustainability', 'url' => '#' ],
                                    [ 'label' => 'Lookbook', 'url' => '#' ],
                                    [ 'label' => 'Events', 'url' => '#' ],
                                ],
                            ],
                            [
                                'title' => 'Explore',
                                'links' => [
                                    [ 'label' => 'About Aegis', 'url' => '#' ],
                                    [ 'label' => 'Our Mission', 'url' => '#' ],
                                    [ 'label' => 'Heritage', 'url' => '#' ],
                                    [ 'label' => 'Care & Repair', 'url' => '#' ],
                                ],
                            ],
                        ],
                        'promo'   => $promo_default,
                    ],
                ],
                [
                    'id'    => 'cloth',
                    'label' => 'CLOTH',
                    'type'  => 'mega',
                    'url'   => '#',
                    'panel' => [
                        'sidebar' => [
                            'title' => 'Collections',
                            'links' => [
                                [ 'label' => 'Urban Line', 'url' => '#' ],
                                [ 'label' => 'Outdoor Line', 'url' => '#' ],
                                [ 'label' => 'Travel Ready', 'url' => '#' ],
                                [ 'label' => 'Seasonal Picks', 'url' => '#' ],
                                [ 'label' => 'Basics', 'url' => '#' ],
                            ],
                        ],
                        'groups'  => [
                            [
                                'title' => 'Categories',
                                'links' => [
                                    [ 'label' => 'Jackets', 'url' => '#' ],
                                    [ 'label' => 'Tops', 'url' => '#' ],
                                    [ 'label' => 'Bottoms', 'url' => '#' ],
                                    [ 'label' => 'Layering', 'url' => '#' ],
                                    [ 'label' => 'Accessories', 'url' => '#' ],
                                ],
                            ],
                            [
                                'title' => 'Shop By',
                                'links' => [
                                    [ 'label' => 'Activity', 'url' => '#' ],
                                    [ 'label' => 'Weather', 'url' => '#' ],
                                    [ 'label' => 'Fabric', 'url' => '#' ],
                                    [ 'label' => 'Fit', 'url' => '#' ],
                                ],
                            ],
                            [
                                'title' => 'Featured',
                                'links' => [
                                    [ 'label' => 'New Arrivals', 'url' => '#' ],
                                    [ 'label' => 'Limited', 'url' => '#' ],
                                    [ 'label' => 'Best Sellers', 'url' => '#' ],
                                    [ 'label' => 'Care Guide', 'url' => '#' ],
                                ],
                            ],
                        ],
                        'promo'   => $promo_default,
                    ],
                ],
                [
                    'id'    => 'equipment',
                    'label' => 'EQUIPMENT',
                    'type'  => 'mega',
                    'url'   => '#',
                    'panel' => [
                        'sidebar' => [
                            'title' => 'Gear Up',
                            'links' => [
                                [ 'label' => 'Packs', 'url' => '#' ],
                                [ 'label' => 'Sleeping', 'url' => '#' ],
                                [ 'label' => 'Cooking', 'url' => '#' ],
                                [ 'label' => 'Lighting', 'url' => '#' ],
                            ],
                        ],
                        'groups'  => [
                            [
                                'title' => 'Outdoor',
                                'links' => [
                                    [ 'label' => 'Backpacking', 'url' => '#' ],
                                    [ 'label' => 'Camping', 'url' => '#' ],
                                    [ 'label' => 'Climbing', 'url' => '#' ],
                                    [ 'label' => 'Hiking', 'url' => '#' ],
                                ],
                            ],
                            [
                                'title' => 'Technical',
                                'links' => [
                                    [ 'label' => 'Insulation', 'url' => '#' ],
                                    [ 'label' => 'Waterproof', 'url' => '#' ],
                                    [ 'label' => 'UL Gear', 'url' => '#' ],
                                    [ 'label' => 'Shelters', 'url' => '#' ],
                                ],
                            ],
                            [
                                'title' => 'Featured',
                                'links' => [
                                    [ 'label' => 'New Gear', 'url' => '#' ],
                                    [ 'label' => 'Pro Picks', 'url' => '#' ],
                                    [ 'label' => 'Essentials', 'url' => '#' ],
                                    [ 'label' => 'Bundles', 'url' => '#' ],
                                ],
                            ],
                        ],
                        'promo'   => $promo_default,
                    ],
                ],
                [
                    'id'    => 'technology',
                    'label' => 'TECHNOLOGY',
                    'type'  => 'mega',
                    'url'   => '#',
                    'panel' => [
                        'sidebar' => [
                            'title' => 'Materials',
                            'links' => [
                                [ 'label' => 'Insulation', 'url' => '#' ],
                                [ 'label' => 'Waterproofing', 'url' => '#' ],
                                [ 'label' => 'Breathability', 'url' => '#' ],
                                [ 'label' => 'Durability', 'url' => '#' ],
                            ],
                        ],
                        'groups'  => [
                            [
                                'title' => 'Research',
                                'links' => [
                                    [ 'label' => 'Labs', 'url' => '#' ],
                                    [ 'label' => 'Field Tests', 'url' => '#' ],
                                    [ 'label' => 'Engineering', 'url' => '#' ],
                                    [ 'label' => 'Design', 'url' => '#' ],
                                ],
                            ],
                            [
                                'title' => 'Innovations',
                                'links' => [
                                    [ 'label' => 'Fabrics', 'url' => '#' ],
                                    [ 'label' => 'Trims', 'url' => '#' ],
                                    [ 'label' => 'Construction', 'url' => '#' ],
                                    [ 'label' => 'Systems', 'url' => '#' ],
                                ],
                            ],
                        ],
                        'promo'   => $promo_default,
                    ],
                ],
                [
                    'id'    => 'contact-us',
                    'label' => 'CONTACT US',
                    'type'  => 'mega',
                    'url'   => '#',
                    'panel' => [
                        'sidebar' => [
                            'title' => 'Support',
                            'links' => [
                                [ 'label' => 'Help Center', 'url' => '#' ],
                                [ 'label' => 'Store Locator', 'url' => '#' ],
                                [ 'label' => 'Size Guide', 'url' => '#' ],
                                [ 'label' => 'Warranty', 'url' => '#' ],
                            ],
                        ],
                        'groups'  => [
                            [
                                'title' => 'Get in Touch',
                                'links' => [
                                    [ 'label' => 'Chat', 'url' => '#' ],
                                    [ 'label' => 'Email', 'url' => '#' ],
                                    [ 'label' => 'Phone', 'url' => '#' ],
                                    [ 'label' => 'Feedback', 'url' => '#' ],
                                ],
                            ],
                            [
                                'title' => 'Resources',
                                'links' => [
                                    [ 'label' => 'Shipping', 'url' => '#' ],
                                    [ 'label' => 'Returns', 'url' => '#' ],
                                    [ 'label' => 'Repairs', 'url' => '#' ],
                                    [ 'label' => 'FAQ', 'url' => '#' ],
                                ],
                            ],
                        ],
                        'promo'   => $promo_default,
                    ],
                ],
                [
                    'id'    => 'query-verification',
                    'label' => 'QUERY VERIFICATION',
                    'type'  => 'mega',
                    'url'   => '#',
                    'panel' => [
                        'sidebar' => [
                            'title' => 'Verification',
                            'links' => [
                                [ 'label' => 'Order Status', 'url' => '#' ],
                                [ 'label' => 'Authenticity', 'url' => '#' ],
                                [ 'label' => 'Warranty Check', 'url' => '#' ],
                                [ 'label' => 'Service Request', 'url' => '#' ],
                            ],
                        ],
                        'groups'  => [
                            [
                                'title' => 'Look Up',
                                'links' => [
                                    [ 'label' => 'Order Number', 'url' => '#' ],
                                    [ 'label' => 'Email', 'url' => '#' ],
                                    [ 'label' => 'Serial', 'url' => '#' ],
                                    [ 'label' => 'Support Ticket', 'url' => '#' ],
                                ],
                            ],
                            [
                                'title' => 'More Help',
                                'links' => [
                                    [ 'label' => 'Guides', 'url' => '#' ],
                                    [ 'label' => 'Policies', 'url' => '#' ],
                                    [ 'label' => 'Security', 'url' => '#' ],
                                    [ 'label' => 'Contact Team', 'url' => '#' ],
                                ],
                            ],
                        ],
                        'promo'   => $promo_default,
                    ],
                ],
            ],
        ],
    ];

    return $defaults;
}

function aegis_mega_header_icon( $name ) {
    $icons = [
        'hamburger'     => '<line x1="3" y1="7" x2="21" y2="7"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="17" x2="21" y2="17"></line>',
        'close'         => '<line x1="5" y1="5" x2="19" y2="19"></line><line x1="19" y1="5" x2="5" y2="19"></line>',
        'chevron-right' => '<polyline points="9 18 15 12 9 6"></polyline>',
        'chevron-left'  => '<polyline points="15 18 9 12 15 6"></polyline>',
        'search'        => '<circle cx="11" cy="11" r="6"></circle><line x1="17" y1="17" x2="21" y2="21"></line>',
        'user'          => '<circle cx="12" cy="7" r="4"></circle><path d="M5.5 20a6.5 6.5 0 0113 0"></path>',
        'cart'          => '<circle cx="9" cy="20" r="1"></circle><circle cx="17" cy="20" r="1"></circle><path d="M3 4h2l1 2h13l-1.2 7.2a2 2 0 01-2 1.8H8.2a2 2 0 01-2-1.6L5 4"></path>',
        'share'         => '<path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"></path><path d="M16 6l-4-4-4 4"></path><line x1="12" y1="2" x2="12" y2="14"></line>',
        'archive'       => '<rect x="3" y="4" width="18" height="4"></rect><path d="M5 8v12h14V8"></path><line x1="10" y1="12" x2="14" y2="12"></line>',
    ];

    if ( ! isset( $icons[ $name ] ) ) {
        return '';
    }

    $svg = sprintf(
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">%s</svg>',
        $icons[ $name ]
    );

    return sprintf(
        '<span class="aegis-icon aegis-icon--%1$s" aria-hidden="true">%2$s</span>',
        esc_attr( $name ),
        $svg
    );
}

function aegis_mega_header_array_is_assoc( $array ) {
    if ( ! is_array( $array ) ) {
        return false;
    }

    return array_keys( $array ) !== range( 0, count( $array ) - 1 );
}

function aegis_mega_header_default_item_map() {
    $nav_defaults = aegis_mega_header_default_nav();
    $items        = isset( $nav_defaults['main']['items'] ) ? $nav_defaults['main']['items'] : [];
    $map          = [];

    foreach ( $items as $item ) {
        if ( isset( $item['id'] ) ) {
            $map[ $item['id'] ] = $item;
        }
    }

    return $map;
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
    static $cached = null;

    if ( null !== $cached ) {
        return $cached;
    }

    $defaults = aegis_mega_header_default_settings();
    $saved    = get_option( 'aegis_mega_header_settings', [] );

    if ( ! is_array( $saved ) ) {
        $saved = [];
    }

    $settings = $defaults;

    if ( isset( $saved['branding'] ) && is_array( $saved['branding'] ) ) {
        $settings['branding'] = array_replace_recursive( $settings['branding'], $saved['branding'] );
    }

    if ( isset( $saved['ad_slots'] ) && is_array( $saved['ad_slots'] ) ) {
        $settings['ad_slots'] = array_replace_recursive( $settings['ad_slots'], $saved['ad_slots'] );
    }

    if ( isset( $saved['top'] ) && is_array( $saved['top'] ) ) {
        $settings['top'] = array_replace_recursive( $settings['top'], $saved['top'] );
    }

    $nav_defaults  = isset( $defaults['nav'] ) ? $defaults['nav'] : [];
    $nav_settings  = isset( $saved['nav'] ) && is_array( $saved['nav'] ) ? $saved['nav'] : [];
    $items_default = isset( $nav_defaults['main']['items'] ) ? $nav_defaults['main']['items'] : [];
    $items_saved   = isset( $nav_settings['items'] ) ? $nav_settings['items'] : $nav_settings;

    if ( aegis_mega_header_array_is_assoc( $items_saved ) ) {
        $items_saved = array_values( $items_saved );
    }

    $settings['nav'] = [
        'items' => ! empty( $items_saved ) && is_array( $items_saved ) ? $items_saved : $items_default,
    ];

    $cached = $settings;

    return $settings;
}

function aegis_mega_header_sanitize_settings( $settings ) {
    $defaults      = aegis_mega_header_default_settings();
    $nav_defaults  = aegis_mega_header_default_nav();
    $default_items = isset( $nav_defaults['main']['items'] ) ? $nav_defaults['main']['items'] : [];
    $default_map   = aegis_mega_header_default_item_map();
    $promo_default = aegis_mega_header_default_promo_config();

    if ( ! is_array( $settings ) ) {
        return $defaults;
    }

    $branding    = isset( $settings['branding'] ) && is_array( $settings['branding'] ) ? $settings['branding'] : [];
    $top_input   = isset( $settings['top'] ) && is_array( $settings['top'] ) ? $settings['top'] : [];
    $nav_input   = isset( $settings['nav'] ) && is_array( $settings['nav'] ) ? $settings['nav'] : [];
    $logo_source = isset( $branding['logo_source'] ) && in_array( $branding['logo_source'], [ 'wp_site_logo', 'plugin_logo' ], true ) ? $branding['logo_source'] : 'wp_site_logo';

    $logo_height_desktop = isset( $branding['logo_height_desktop'] ) ? absint( $branding['logo_height_desktop'] ) : 0;
    $logo_height_mobile  = isset( $branding['logo_height_mobile'] ) ? absint( $branding['logo_height_mobile'] ) : 0;
    $logo_max_width      = isset( $branding['logo_max_width'] ) ? absint( $branding['logo_max_width'] ) : 0;

    if ( $logo_height_desktop <= 0 ) {
        $logo_height_desktop = isset( $defaults['branding']['logo_height_desktop'] ) ? absint( $defaults['branding']['logo_height_desktop'] ) : 36;
    }

    if ( $logo_height_mobile <= 0 ) {
        $logo_height_mobile = isset( $defaults['branding']['logo_height_mobile'] ) ? absint( $defaults['branding']['logo_height_mobile'] ) : 32;
    }

    if ( $logo_max_width <= 0 ) {
        $logo_max_width = isset( $defaults['branding']['logo_max_width'] ) ? absint( $defaults['branding']['logo_max_width'] ) : 220;
    }

    $clean = [
        'branding' => [
            'logo_source'        => $logo_source,
            'plugin_logo_id'     => isset( $branding['plugin_logo_id'] ) ? absint( $branding['plugin_logo_id'] ) : 0,
            'logo_url'           => ! empty( $branding['logo_url'] ) ? esc_url_raw( $branding['logo_url'] ) : home_url( '/' ),
            'logo_alt'           => isset( $branding['logo_alt'] ) ? sanitize_text_field( $branding['logo_alt'] ) : '',
            'logo_height_desktop' => $logo_height_desktop,
            'logo_height_mobile'  => $logo_height_mobile,
            'logo_max_width'      => $logo_max_width,
        ],
        'ad_slots' => [],
        'top'      => [
            'enabled' => ! empty( $top_input['enabled'] ),
            'links'   => [],
        ],
        'nav'      => [ 'items' => [] ],
    ];

    $top_links_input = isset( $top_input['links'] ) && is_array( $top_input['links'] ) ? $top_input['links'] : [];
    if ( empty( $top_links_input ) && isset( $nav_defaults['top']['links'] ) ) {
        $top_links_input = $nav_defaults['top']['links'];
    }

    foreach ( $top_links_input as $index => $link_input ) {
        $default_link = isset( $nav_defaults['top']['links'][ $index ] ) ? $nav_defaults['top']['links'][ $index ] : [ 'label' => '', 'url' => '#' ];
        $label_raw    = is_array( $link_input ) && isset( $link_input['label'] ) ? $link_input['label'] : ( isset( $default_link['label'] ) ? $default_link['label'] : '' );
        $url_raw      = is_array( $link_input ) && isset( $link_input['url'] ) ? $link_input['url'] : ( isset( $default_link['url'] ) ? $default_link['url'] : '#' );
        $clean['top']['links'][] = [
            'label' => '' !== $label_raw ? sanitize_text_field( $label_raw ) : ( isset( $default_link['label'] ) ? $default_link['label'] : '' ),
            'url'   => ( '' === $url_raw || '#' === $url_raw ) ? $url_raw : esc_url_raw( $url_raw ),
        ];
    }

    $items_input = isset( $nav_input['items'] ) && is_array( $nav_input['items'] ) ? $nav_input['items'] : $nav_input;

    if ( aegis_mega_header_array_is_assoc( $items_input ) ) {
        $items_input = array_values( $items_input );
    }

    if ( empty( $items_input ) ) {
        $items_input = $default_items;
    }

    foreach ( $items_input as $maybe_key => $item_input ) {
        if ( ! is_array( $item_input ) ) {
            continue;
        }

        $raw_id  = isset( $item_input['id'] ) && '' !== $item_input['id'] ? $item_input['id'] : '';
        $raw_id  = $raw_id ? $raw_id : ( is_string( $maybe_key ) ? $maybe_key : '' );
        $item_id = $raw_id ? sanitize_text_field( $raw_id ) : 'item_' . wp_generate_uuid4();

        $default_item = isset( $default_map[ $item_id ] ) ? $default_map[ $item_id ] : null;

        $label_raw = isset( $item_input['label'] ) ? $item_input['label'] : ( $default_item['label'] ?? '' );
        $label     = '' !== $label_raw ? sanitize_text_field( $label_raw ) : 'NEW ITEM';

        $type_raw = isset( $item_input['type'] ) ? $item_input['type'] : ( $default_item['type'] ?? 'link' );
        $type     = in_array( $type_raw, [ 'mega', 'link' ], true ) ? $type_raw : 'link';

        $url_raw = isset( $item_input['url'] ) ? $item_input['url'] : ( $default_item['url'] ?? '#' );
        $url     = ( '' === $url_raw || '#' === $url_raw ) ? $url_raw : esc_url_raw( $url_raw );

        $panel_clean = [];

        if ( 'mega' === $type ) {
            $panel_default = ( $default_item && isset( $default_item['panel'] ) ) ? $default_item['panel'] : [];
            $panel_input   = isset( $item_input['panel'] ) && is_array( $item_input['panel'] ) ? $item_input['panel'] : [];
            $sidebar_raw   = isset( $panel_input['sidebar_links'] ) ? $panel_input['sidebar_links'] : '';
            $sidebar       = isset( $panel_input['sidebar'] ) && is_array( $panel_input['sidebar'] ) ? $panel_input['sidebar'] : [];

            $panel_clean = [
                'sidebar' => [
                    'title' => isset( $sidebar['title'] ) ? sanitize_text_field( $sidebar['title'] ) : '',
                    'links' => aegis_mega_header_parse_links_textarea( $sidebar_raw ),
                ],
                'groups'  => [],
                'promo'   => $promo_default,
            ];

            if ( empty( $panel_clean['sidebar']['title'] ) && isset( $panel_default['sidebar']['title'] ) ) {
                $panel_clean['sidebar']['title'] = $panel_default['sidebar']['title'];
            }

            if ( empty( $panel_clean['sidebar']['links'] ) && isset( $panel_default['sidebar']['links'] ) ) {
                $panel_clean['sidebar']['links'] = $panel_default['sidebar']['links'];
            }

            $groups_input = isset( $panel_input['groups'] ) && is_array( $panel_input['groups'] ) ? $panel_input['groups'] : [];

            for ( $i = 0; $i < 4; $i++ ) {
                $group_input   = isset( $groups_input[ $i ] ) && is_array( $groups_input[ $i ] ) ? $groups_input[ $i ] : [];
                $group_title   = isset( $group_input['title'] ) ? sanitize_text_field( $group_input['title'] ) : '';
                $group_links   = isset( $group_input['links'] ) ? aegis_mega_header_parse_links_textarea( $group_input['links'] ) : [];
                $default_group = isset( $panel_default['groups'][ $i ] ) ? $panel_default['groups'][ $i ] : [];

                if ( '' === $group_title && isset( $default_group['title'] ) ) {
                    $group_title = $default_group['title'];
                }

                if ( empty( $group_links ) && isset( $default_group['links'] ) ) {
                    $group_links = $default_group['links'];
                }

                if ( '' === $group_title && empty( $group_links ) ) {
                    continue;
                }

                $panel_clean['groups'][] = [
                    'title' => $group_title,
                    'links' => $group_links,
                ];
            }

            $promo_input      = isset( $panel_input['promo'] ) && is_array( $panel_input['promo'] ) ? $panel_input['promo'] : [];
            $promo_mode_raw   = isset( $promo_input['mode'] ) ? $promo_input['mode'] : ( isset( $promo_input['source'] ) ? $promo_input['source'] : 'global' );
            $promo_mode       = in_array( $promo_mode_raw, [ 'global', 'custom', 'hidden' ], true ) ? $promo_mode_raw : 'global';
            $promo_custom_raw = isset( $promo_input['custom'] ) && is_array( $promo_input['custom'] ) ? $promo_input['custom'] : [];

            $promo_custom_clean = [];

            foreach ( [ 'promo_1', 'promo_2' ] as $promo_key ) {
                $slot_input = isset( $promo_custom_raw[ $promo_key ] ) && is_array( $promo_custom_raw[ $promo_key ] ) ? $promo_custom_raw[ $promo_key ] : [];

                $promo_custom_clean[ $promo_key ] = [
                    'image_id' => isset( $slot_input['image_id'] ) ? absint( $slot_input['image_id'] ) : 0,
                    'title'    => isset( $slot_input['title'] ) ? sanitize_text_field( $slot_input['title'] ) : '',
                    'subtitle' => isset( $slot_input['subtitle'] ) ? sanitize_text_field( $slot_input['subtitle'] ) : '',
                    'url'      => ! empty( $slot_input['url'] ) ? esc_url_raw( $slot_input['url'] ) : '',
                    'new_tab'  => ! empty( $slot_input['new_tab'] ),
                ];
            }

            $panel_clean['promo'] = [
                'mode'   => $promo_mode,
                'custom' => ! empty( $promo_custom_clean ) ? $promo_custom_clean : $promo_default['custom'],
            ];
        }

        $clean['nav']['items'][] = [
            'id'    => $item_id,
            'label' => $label,
            'type'  => $type,
            'url'   => $url,
            'panel' => 'mega' === $type ? $panel_clean : [],
        ];
    }

    if ( empty( $clean['nav']['items'] ) ) {
        $clean['nav']['items'] = $default_items;
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

    $result              = $defaults;
    $result['branding']  = $clean['branding'];
    $result['ad_slots']  = $clean['ad_slots'];
    $result['top']       = $clean['top'];
    $result['nav']       = $clean['nav'];

    return $result;
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
    $is_settings_page = ( 'appearance_page_aegis-mega-header' === $hook ) || ( isset( $_GET['page'] ) && 'aegis-mega-header' === $_GET['page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( ! $is_settings_page ) {
        return;
    }

    $asset_version = filemtime( plugin_dir_path( __FILE__ ) . 'admin.js' );

    wp_enqueue_media();
    wp_enqueue_style(
        'aegis-mega-header-admin',
        plugins_url( 'admin.css', __FILE__ ),
        [],
        $asset_version
    );
    wp_enqueue_script(
        'aegis-mega-header-admin',
        plugins_url( 'admin.js', __FILE__ ),
        [ 'jquery' ],
        $asset_version,
        true
    );
}
add_action( 'admin_enqueue_scripts', 'aegis_mega_header_admin_assets' );

function aegis_mega_header_render_links( $links ) {
    if ( empty( $links ) || ! is_array( $links ) ) {
        return '';
    }

    $output = '';

    foreach ( $links as $link ) {
        if ( is_array( $link ) ) {
            $label = isset( $link['label'] ) ? $link['label'] : '';
            $url   = isset( $link['url'] ) ? $link['url'] : '#';
        } else {
            $label = $link;
            $url   = '#';
        }

        if ( '' === $label ) {
            continue;
        }

        $href = '' !== $url ? $url : '#';

        $output .= '<li class="aegis-mega-header__link-item"><a href="' . esc_url( $href ) . '" class="aegis-mega-header__link">' . esc_html( $label ) . '</a></li>';
    }

    return $output;
}

function aegis_mega_header_parse_links_textarea( $raw ) {
    if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
        return [];
    }

    $lines = preg_split( '/\r?\n/', $raw );
    $links = [];

    foreach ( $lines as $line ) {
        $line = trim( $line );

        if ( '' === $line ) {
            continue;
        }

        $parts = explode( '|', $line, 2 );
        $label = sanitize_text_field( trim( $parts[0] ) );

        if ( '' === $label ) {
            continue;
        }

        $url = isset( $parts[1] ) ? trim( $parts[1] ) : '';

        if ( '' !== $url && '#' !== $url ) {
            $url = esc_url_raw( $url );
        }

        $links[] = [
            'label' => $label,
            'url'   => '' !== $url ? $url : '#',
        ];
    }

    return $links;
}

function aegis_mega_header_links_to_textarea( $links ) {
    if ( empty( $links ) || ! is_array( $links ) ) {
        return '';
    }

    $lines = [];

    foreach ( $links as $link ) {
        if ( is_array( $link ) ) {
            $label = isset( $link['label'] ) ? $link['label'] : '';
            $url   = isset( $link['url'] ) ? $link['url'] : '#';
        } else {
            $label = $link;
            $url   = '#';
        }

        if ( '' === $label ) {
            continue;
        }

        $lines[] = $label . '|' . $url;
    }

    return implode( "\n", $lines );
}

function aegis_mega_header_panel_has_content( $panel, $promo_slots = [] ) {
    if ( empty( $panel ) || ! is_array( $panel ) ) {
        return ! empty( $promo_slots );
    }

    $sidebar = isset( $panel['sidebar'] ) ? $panel['sidebar'] : [];
    $groups  = isset( $panel['groups'] ) && is_array( $panel['groups'] ) ? array_filter( $panel['groups'] ) : [];

    $has_sidebar = ( ! empty( $sidebar['title'] ) ) || ( ! empty( $sidebar['links'] ) );
    $has_groups  = ! empty( $groups );

    return $has_sidebar || $has_groups || ! empty( $promo_slots );
}

function aegis_mega_header_collect_promos( $slots, $order = [] ) {
    if ( empty( $order ) && is_array( $slots ) ) {
        $order = array_keys( $slots );
    }

    $results = [];

    foreach ( $order as $slot_key ) {
        if ( empty( $slots[ $slot_key ] ) || ! is_array( $slots[ $slot_key ] ) ) {
            continue;
        }

        $slot = $slots[ $slot_key ];

        $has = ! empty( $slot['image_id'] ) || ! empty( $slot['title'] ) || ! empty( $slot['subtitle'] ) || ! empty( $slot['url'] );

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

function aegis_mega_header_promo_slots( $settings ) {
    $slots   = isset( $settings['ad_slots'] ) && is_array( $settings['ad_slots'] ) ? $settings['ad_slots'] : [];
    $order   = [ 'header_mega_promo_1', 'header_mega_promo_2' ];

    return aegis_mega_header_collect_promos( $slots, $order );
}

function aegis_mega_header_resolve_promos( $panel, $global_promos ) {
    if ( empty( $panel ) || ! is_array( $panel ) ) {
        return $global_promos;
    }

    $promo_config = isset( $panel['promo'] ) && is_array( $panel['promo'] ) ? $panel['promo'] : [];
    $mode_raw     = isset( $promo_config['mode'] ) ? $promo_config['mode'] : ( isset( $promo_config['source'] ) ? $promo_config['source'] : 'global' );
    $mode         = in_array( $mode_raw, [ 'global', 'custom', 'hidden' ], true ) ? $mode_raw : 'global';

    if ( 'hidden' === $mode ) {
        return [];
    }

    $custom_raw = isset( $promo_config['custom'] ) && is_array( $promo_config['custom'] ) ? $promo_config['custom'] : [];
    $custom     = aegis_mega_header_collect_promos( $custom_raw, [ 'promo_1', 'promo_2' ] );
    $custom_with_images = [];

    foreach ( $custom as $slot ) {
        if ( ! empty( $slot['image_id'] ) ) {
            $custom_with_images[] = $slot;
        }
    }

    if ( 'custom' === $mode && ! empty( $custom_with_images ) ) {
        return $custom_with_images;
    }

    return $global_promos;
}

function aegis_mega_header_build_logo_image( $attachment_id, $alt = '', $class = 'aegis-header__brand-image' ) {
    if ( ! $attachment_id ) {
        return '';
    }

    $mime = get_post_mime_type( $attachment_id );
    $alt  = $alt ? $alt : get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
    $class_attr = trim( $class );

    if ( 'image/svg+xml' === $mime ) {
        $src = wp_get_attachment_url( $attachment_id );
        if ( ! $src ) {
            return '';
        }

        $classes = trim( $class_attr . ' style-svg' );

        return '<img src="' . esc_url( $src ) . '" class="' . esc_attr( $classes ) . '" alt="' . esc_attr( $alt ) . '" />';
    }

    return wp_get_attachment_image(
        $attachment_id,
        'full',
        false,
        [
            'class' => $class_attr ? $class_attr : 'aegis-header__brand-image',
            'alt'   => $alt,
        ]
    );
}

function aegis_mega_header_render_panel( $panel, $promo_slots ) {
    if ( empty( $panel ) || ! is_array( $panel ) ) {
        $panel = [];
    }

    $sidebar = isset( $panel['sidebar'] ) ? $panel['sidebar'] : [];
    $groups  = isset( $panel['groups'] ) && is_array( $panel['groups'] ) ? $panel['groups'] : [];
    $active_promos = ! empty( $promo_slots ) ? $promo_slots : [];

    $has_sidebar = ( ! empty( $sidebar['title'] ) ) || ( ! empty( $sidebar['links'] ) );
    $has_groups  = ! empty( $groups );

    if ( ! $has_sidebar && ! $has_groups && empty( $active_promos ) ) {
        return '';
    }

    ob_start();
    ?>
    <div class="aegis-mega-header__panel-grid">
        <div class="aegis-mega__sidebar">
            <div class="aegis-mega__sidebar-inner">
                <?php if ( ! empty( $sidebar['title'] ) ) : ?>
                    <div class="aegis-mega-header__panel-title"><?php echo esc_html( $sidebar['title'] ); ?></div>
                <?php endif; ?>
                <?php if ( ! empty( $sidebar['links'] ) && is_array( $sidebar['links'] ) ) : ?>
                    <ul class="aegis-mega-header__panel-links">
                        <?php echo aegis_mega_header_render_links( $sidebar['links'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="aegis-mega__columns">
            <?php foreach ( $groups as $column ) : ?>
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
                    $subtitle = isset( $promo['subtitle'] ) ? $promo['subtitle'] : '';
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

    $has_builder = function_exists( 'aegis_mega_header_build_logo_image' );

    $use_site_logo = isset( $branding['logo_source'] ) ? $branding['logo_source'] : 'wp_site_logo';

    if ( $has_builder && 'wp_site_logo' === $use_site_logo ) {
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        if ( $custom_logo_id ) {
            $html = aegis_mega_header_build_logo_image( $custom_logo_id, $logo_alt );
        }
    }

    if ( $has_builder && ! $html && ! empty( $branding['plugin_logo_id'] ) ) {
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

$settings     = aegis_mega_header_get_settings();
$brand        = aegis_mega_header_render_brand( $settings );
$logo_sizes   = aegis_mega_header_logo_dimensions( $settings );
$promo_slots  = aegis_mega_header_promo_slots( $settings );
$nav_defaults = aegis_mega_header_default_nav();
$top_settings = isset( $settings['top'] ) ? $settings['top'] : $nav_defaults['top'];
$nav_items    = isset( $settings['nav']['items'] ) ? $settings['nav']['items'] : ( isset( $settings['nav'] ) ? $settings['nav'] : [] );
$default_map  = aegis_mega_header_default_item_map();
$cart_count   = 0;

if ( aegis_mega_header_has_woocommerce() && WC()->cart ) {
    $cart_count = WC()->cart->get_cart_contents_count();
}

if ( aegis_mega_header_array_is_assoc( $nav_items ) ) {
    $nav_items = array_values( $nav_items );
}

if ( empty( $nav_items ) && isset( $nav_defaults['main']['items'] ) ) {
    $nav_items = $nav_defaults['main']['items'];
}

$panel_ids   = [];
$menu_items  = [];
$mobile_data = [];

foreach ( $nav_items as $index => $item ) {
    if ( ! is_array( $item ) ) {
        continue;
    }

    $item_id      = isset( $item['id'] ) && '' !== $item['id'] ? $item['id'] : 'item-' . $index;
    $default_item = isset( $default_map[ $item_id ] ) ? $default_map[ $item_id ] : null;

    $label = isset( $item['label'] ) && '' !== $item['label'] ? $item['label'] : ( $default_item['label'] ?? 'Item' );
    $type  = isset( $item['type'] ) && in_array( $item['type'], [ 'mega', 'link' ], true ) ? $item['type'] : ( $default_item['type'] ?? 'link' );
    $url   = isset( $item['url'] ) && '' !== $item['url'] ? $item['url'] : ( $default_item['url'] ?? '#' );
    $panel = isset( $item['panel'] ) && is_array( $item['panel'] ) ? $item['panel'] : ( isset( $default_item['panel'] ) ? $default_item['panel'] : [] );

    $panel_promos      = 'mega' === $type ? aegis_mega_header_resolve_promos( $panel, $promo_slots ) : [];
    $has_panel_content = 'mega' === $type && aegis_mega_header_panel_has_content( $panel, $panel_promos );
    $panel_id          = $has_panel_content ? 'aegis-mega-panel-' . sanitize_key( $item_id ) : '';

    $menu_items[] = [
        'key'   => $item_id,
        'label' => $label,
        'type'  => $type,
        'url'   => $url,
        'panel' => $panel,
        'promos' => $panel_promos,
    ];

    if ( $panel_id ) {
        $panel_ids[ $item_id ] = $panel_id;
    }

    if ( 'mega' === $type ) {
        $sidebar_links = [];

        if ( ! empty( $panel['sidebar']['links'] ) && is_array( $panel['sidebar']['links'] ) ) {
            foreach ( $panel['sidebar']['links'] as $plink ) {
                if ( empty( $plink['label'] ) ) {
                    continue;
                }

                $sidebar_links[] = [
                    'label' => sanitize_text_field( $plink['label'] ),
                    'url'   => isset( $plink['url'] ) && '' !== $plink['url'] ? esc_url_raw( $plink['url'] ) : '#',
                ];
            }
        }

        $groups_data = [];

        if ( ! empty( $panel['groups'] ) && is_array( $panel['groups'] ) ) {
            foreach ( $panel['groups'] as $group ) {
                $group_title = isset( $group['title'] ) ? sanitize_text_field( $group['title'] ) : '';
                $group_links = [];

                if ( ! empty( $group['links'] ) && is_array( $group['links'] ) ) {
                    foreach ( $group['links'] as $g_link ) {
                        if ( empty( $g_link['label'] ) ) {
                            continue;
                        }

                        $group_links[] = [
                            'label' => sanitize_text_field( $g_link['label'] ),
                            'url'   => isset( $g_link['url'] ) && '' !== $g_link['url'] ? esc_url_raw( $g_link['url'] ) : '#',
                        ];
                    }
                }

                if ( '' === $group_title && empty( $group_links ) ) {
                    continue;
                }

                $groups_data[] = [
                    'title' => $group_title,
                    'links' => $group_links,
                ];
            }
        }

        $mobile_data[ $item_id ] = [
            'label'   => $label,
            'url'     => $url,
            'sidebar' => [
                'title' => isset( $panel['sidebar']['title'] ) ? sanitize_text_field( $panel['sidebar']['title'] ) : '',
                'links' => $sidebar_links,
            ],
            'groups'  => $groups_data,
        ];
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

<div class="aegis-mobile-topbar" aria-label="Mobile menu bar">
    <button class="aegis-mobile-btn" data-mobile-open aria-label="Open menu">
        <?php echo aegis_mega_header_icon( 'hamburger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <span class="screen-reader-text">Open menu</span>
    </button>
    <div class="aegis-mobile-logo">
        <a href="<?php echo esc_url( $brand['url'] ); ?>" class="aegis-header__brand-link">
            <?php echo $brand['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </a>
    </div>
    <div class="aegis-mobile-icons">
        <a class="aegis-mobile-icon" href="#" aria-label="Account">
            <?php echo aegis_mega_header_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span class="screen-reader-text">Account</span>
        </a>
        <?php if ( ! empty( $attributes['showCart'] ) ) : ?>
            <a class="aegis-mobile-icon" href="#" aria-label="Cart" data-aegis-mini-cart-trigger>
                <?php echo aegis_mega_header_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <span class="aegis-header__cart-count"><?php echo esc_html( $cart_count ); ?></span>
                <span class="screen-reader-text">Cart</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ( ! empty( $attributes['showSearch'] ) ) : ?>
    <form class="aegis-mobile-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
        <label class="screen-reader-text" for="aegis-mobile-search">Search</label>
        <input id="aegis-mobile-search" type="search" name="s" placeholder="What are you looking for?" />
        <button type="submit" class="aegis-mobile-search-btn" aria-label="Search">
            <?php echo aegis_mega_header_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span class="screen-reader-text">Search</span>
        </button>
    </form>
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
<button type="submit" class="aegis-header__search-btn" aria-label="Search">
<?php echo aegis_mega_header_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<span class="screen-reader-text">Search</span>
</button>
</form>
<?php endif; ?>
<?php if ( ! empty( $attributes['showCart'] ) ) : ?>
<button class="aegis-header__cart" type="button" aria-label="Cart" data-aegis-mini-cart-trigger>
Cart
<span class="aegis-header__cart-count"><?php echo esc_html( $cart_count ); ?></span>
</button>
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
$panel    = isset( $item['panel'] ) ? $item['panel'] : [];
$panel_promos = isset( $item['promos'] ) ? $item['promos'] : [];
$panel_html = aegis_mega_header_render_panel( $panel, $panel_promos );

if ( '' === $panel_html ) {
    continue;
}
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
echo $panel_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
?>
</div>
<?php endforeach; ?>
        </div>
    </div>

    <div class="aegis-mobile-overlay" data-mobile-close hidden></div>
    <aside class="aegis-mobile-drawer" hidden aria-hidden="true">
        <div class="aegis-mobile-drawer__header">
            <button class="aegis-mobile-btn" data-mobile-close aria-label="Close">
                <?php echo aegis_mega_header_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <span class="screen-reader-text">Close</span>
            </button>
            <div class="aegis-mobile-drawer__title">Main Menu</div>
            <button class="aegis-mobile-btn" data-mobile-close aria-label="Close">
                <?php echo aegis_mega_header_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <span class="screen-reader-text">Close</span>
            </button>
        </div>

        <div class="aegis-mobile-view aegis-mobile-view--root" data-view="root">
            <ul class="aegis-mobile-list">
                <?php foreach ( $menu_items as $index => $item ) :
                    $key   = isset( $item['key'] ) ? $item['key'] : 'item-' . $index;
                    $label = isset( $item['label'] ) ? $item['label'] : 'Item';
                    $type  = isset( $item['type'] ) ? $item['type'] : 'link';
                    $url   = isset( $item['url'] ) ? $item['url'] : '#';
                    ?>
                    <?php if ( 'mega' === $type ) : ?>
                        <li>
                            <button class="aegis-mobile-row" data-enter="<?php echo esc_attr( $key ); ?>">
                                <span class="aegis-mobile-row__label"><?php echo esc_html( $label ); ?></span>
                                <span class="aegis-mobile-row__chev" aria-hidden="true">
                                    <?php echo aegis_mega_header_icon( 'chevron-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </span>
                            </button>
                        </li>
                    <?php else : ?>
                        <li>
                            <a class="aegis-mobile-row" href="<?php echo esc_url( $url ); ?>">
                                <span class="aegis-mobile-row__label"><?php echo esc_html( $label ); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="aegis-mobile-view aegis-mobile-view--sub" data-view="sub" hidden>
            <div class="aegis-mobile-subheader">
                <button class="aegis-mobile-back" data-back aria-label="Back">
                    <?php echo aegis_mega_header_icon( 'chevron-left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <span>Back</span>
                </button>
                <div class="aegis-mobile-subtitle" data-subtitle></div>
            </div>
            <div class="aegis-mobile-subcontent" data-subcontent></div>
        </div>

        <script type="application/json" class="aegis-mobile-panels" data-mobile-panels>
            <?php echo wp_json_encode( $mobile_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </script>
    </aside>
</header>
<?php

return ob_get_clean();
}

function aegis_mega_header_get_cart_subtotal() {
    if ( ! aegis_mega_header_has_woocommerce() || ! WC()->cart ) {
        return '';
    }

    return WC()->cart->get_cart_subtotal();
}

function aegis_mega_header_get_cart_count() {
    if ( ! aegis_mega_header_has_woocommerce() || ! WC()->cart ) {
        return 0;
    }

    return WC()->cart->get_cart_contents_count();
}

function aegis_mega_header_render_mini_cart_fragment() {
    if ( ! aegis_mega_header_has_woocommerce() ) {
        return '';
    }

    ob_start();
    woocommerce_mini_cart();
    return ob_get_clean();
}

function aegis_mega_header_render_mini_cart_drawer() {
    if ( ! aegis_mega_header_has_woocommerce() ) {
        return;
    }

    $cart_url   = wc_get_cart_url();
    $count      = aegis_mega_header_get_cart_count();
    $subtotal   = aegis_mega_header_get_cart_subtotal();
    $mini_cart  = aegis_mega_header_render_mini_cart_fragment();
    $view_label = sprintf( 'View your cart (%d)', $count );
    ?>
    <div class="aegis-mini-cart" data-aegis-mini-cart>
        <div class="aegis-mini-cart__overlay" data-aegis-mini-cart-close hidden></div>
        <aside class="aegis-mini-cart__drawer" role="dialog" aria-modal="true" aria-hidden="true" hidden>
            <header class="aegis-mini-cart__header">
                <button class="aegis-mini-cart__close" type="button" data-aegis-mini-cart-close aria-label="Close mini cart">
                    <span aria-hidden="true">×</span>
                </button>
                <div class="aegis-mini-cart__title">Cart</div>
            </header>
            <div class="aegis-mini-cart__notice" data-aegis-mini-cart-notice role="status" aria-live="polite">
                <?php echo esc_html__( '已加入购物车', 'aegis-mega-header' ); ?>
            </div>
            <div id="aegis-mini-cart-fragment" class="aegis-mini-cart__items">
                <?php echo $mini_cart; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="aegis-mini-cart__summary">
                <div class="aegis-mini-cart__subtotal">
                    <span class="aegis-mini-cart__subtotal-label"><?php echo esc_html__( 'Subtotal', 'aegis-mega-header' ); ?></span>
                    <span class="aegis-mini-cart__subtotal-amount"><?php echo wp_kses_post( $subtotal ); ?></span>
                </div>
                <a class="aegis-mini-cart__view-cart" href="<?php echo esc_url( $cart_url ); ?>">
                    <span class="aegis-mini-cart__view-cart-text"><?php echo esc_html( $view_label ); ?></span>
                </a>
            </div>
        </aside>
    </div>
    <?php
}
add_action( 'wp_footer', 'aegis_mega_header_render_mini_cart_drawer' );

function aegis_mega_header_add_to_cart_fragments( $fragments ) {
    if ( ! aegis_mega_header_has_woocommerce() ) {
        return $fragments;
    }

    $count      = aegis_mega_header_get_cart_count();
    $subtotal   = aegis_mega_header_get_cart_subtotal();
    $mini_cart  = aegis_mega_header_render_mini_cart_fragment();
    $view_label = sprintf( 'View your cart (%d)', $count );

    $fragments['#aegis-mini-cart-fragment'] = sprintf(
        '<div id="aegis-mini-cart-fragment" class="aegis-mini-cart__items">%s</div>',
        $mini_cart
    );
    $fragments['.aegis-mini-cart__subtotal-amount'] = sprintf(
        '<span class="aegis-mini-cart__subtotal-amount">%s</span>',
        wp_kses_post( $subtotal )
    );
    $fragments['.aegis-mini-cart__view-cart-text'] = sprintf(
        '<span class="aegis-mini-cart__view-cart-text">%s</span>',
        esc_html( $view_label )
    );
    $fragments['.aegis-header__cart-count'] = sprintf(
        '<span class="aegis-header__cart-count">%d</span>',
        absint( $count )
    );

    return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'aegis_mega_header_add_to_cart_fragments' );

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

    $settings          = aegis_mega_header_get_settings();
    $defaults          = aegis_mega_header_default_settings();
    $branding_defaults = isset( $defaults['branding'] ) ? $defaults['branding'] : [];
    $branding          = isset( $settings['branding'] ) ? $settings['branding'] : [];
    $ad_slots          = isset( $settings['ad_slots'] ) ? $settings['ad_slots'] : [];
    $nav_defaults      = aegis_mega_header_default_nav();
    $promo_default     = aegis_mega_header_default_promo_config();
    $top_defaults      = isset( $nav_defaults['top'] ) ? $nav_defaults['top'] : [];
    $main_default      = isset( $nav_defaults['main']['items'] ) ? $nav_defaults['main']['items'] : [];
    $top_settings      = isset( $settings['top'] ) ? $settings['top'] : $top_defaults;
    $nav_items         = isset( $settings['nav']['items'] ) ? $settings['nav']['items'] : ( isset( $settings['nav'] ) ? $settings['nav'] : $main_default );

    if ( aegis_mega_header_array_is_assoc( $nav_items ) ) {
        $nav_items = array_values( $nav_items );
    }

    $default_map = aegis_mega_header_default_item_map();
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
            <p>Manage the top-level menu items. Mega items open panels on hover/focus; link items behave as links only.</p>
            <div id="aegis-main-items" class="aegis-main-items">
                <?php foreach ( $nav_items as $index => $item ) :
                    if ( ! is_array( $item ) ) {
                        continue;
                    }

                    $item_id          = isset( $item['id'] ) && '' !== $item['id'] ? $item['id'] : 'item_' . $index;
                    $default_item     = isset( $default_map[ $item_id ] ) ? $default_map[ $item_id ] : [];
                    $label_value      = isset( $item['label'] ) ? $item['label'] : ( isset( $default_item['label'] ) ? $default_item['label'] : '' );
                    $type_value       = isset( $item['type'] ) ? $item['type'] : ( isset( $default_item['type'] ) ? $default_item['type'] : 'link' );
                    $url_value        = isset( $item['url'] ) ? $item['url'] : ( isset( $default_item['url'] ) ? $default_item['url'] : '#' );
                    $panel_defaults   = isset( $default_item['panel'] ) ? $default_item['panel'] : [];
                    $panel_settings   = isset( $item['panel'] ) ? $item['panel'] : $panel_defaults;
                    $sidebar_title    = isset( $panel_settings['sidebar']['title'] ) ? $panel_settings['sidebar']['title'] : ( isset( $panel_defaults['sidebar']['title'] ) ? $panel_defaults['sidebar']['title'] : '' );
                    $sidebar_links    = isset( $panel_settings['sidebar']['links'] ) ? $panel_settings['sidebar']['links'] : ( isset( $panel_defaults['sidebar']['links'] ) ? $panel_defaults['sidebar']['links'] : [] );
                    $sidebar_links_ui = aegis_mega_header_links_to_textarea( $sidebar_links );
                    $promo_settings   = isset( $panel_settings['promo'] ) ? $panel_settings['promo'] : ( isset( $panel_defaults['promo'] ) ? $panel_defaults['promo'] : $promo_default );
                    $promo_mode       = isset( $promo_settings['mode'] ) ? $promo_settings['mode'] : ( isset( $promo_settings['source'] ) ? $promo_settings['source'] : 'global' );
                    $promo_mode       = in_array( $promo_mode, [ 'global', 'custom', 'hidden' ], true ) ? $promo_mode : 'global';
                    $promo_custom     = isset( $promo_settings['custom'] ) && is_array( $promo_settings['custom'] ) ? $promo_settings['custom'] : $promo_default['custom'];
                    ?>
                    <div class="aegis-main-item" data-index="<?php echo esc_attr( $index ); ?>">
                        <input type="hidden" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][id]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][id]" value="<?php echo esc_attr( $item_id ); ?>" />
                        <div class="aegis-main-item__row">
                            <div class="aegis-main-item__field aegis-main-item__field-label">
                                <label>Label<br />
                                    <input type="text" class="regular-text" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][label]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][label]" value="<?php echo esc_attr( $label_value ); ?>" />
                                </label>
                            </div>
                            <div class="aegis-main-item__field aegis-main-item__field-type">
                                <span class="aegis-main-item__field-title">Type</span>
                                <label><input type="radio" class="aegis-nav-type" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][type]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][type]" value="link" <?php checked( $type_value, 'link' ); ?> /> Link</label>
                                <label><input type="radio" class="aegis-nav-type" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][type]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][type]" value="mega" <?php checked( $type_value, 'mega' ); ?> /> Mega</label>
                            </div>
                            <div class="aegis-main-item__field aegis-main-item__field-url">
                                <label>URL<br />
                                    <input type="url" class="regular-text" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][url]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][url]" value="<?php echo esc_attr( $url_value ); ?>" />
                                </label>
                            </div>
                            <div class="aegis-main-item__controls">
                                <button type="button" class="button aegis-move-up">Move Up</button>
                                <button type="button" class="button aegis-move-down">Move Down</button>
                                <button type="button" class="button aegis-delete-item">Delete</button>
                            </div>
                        </div>
                        <details class="aegis-panel-details aegis-main-item__mega" <?php echo 'mega' === $type_value ? '' : 'style="display:none;"'; ?>>
                            <summary>MEGA Panel</summary>
                            <div class="aegis-mega-panel-settings">
                                <p>
                                    <label>Sidebar Title<br />
                                        <input type="text" class="regular-text" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][panel][sidebar][title]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][sidebar][title]" value="<?php echo esc_attr( $sidebar_title ); ?>" />
                                    </label>
                                </p>
                                <p>
                                    <label>Sidebar Links<br />
                                        <textarea class="large-text code" rows="4" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][panel][sidebar_links]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][sidebar_links]" placeholder="Label|URL per line"><?php echo esc_textarea( $sidebar_links_ui ); ?></textarea>
                                    </label>
                                    <small>One per line: Label|URL</small>
                                </p>
                                <?php
                                for ( $i = 0; $i < 4; $i++ ) {
                                    $group_default  = isset( $panel_defaults['groups'][ $i ] ) ? $panel_defaults['groups'][ $i ] : [];
                                    $group_item     = isset( $panel_settings['groups'][ $i ] ) ? $panel_settings['groups'][ $i ] : $group_default;
                                    $group_title    = isset( $group_item['title'] ) ? $group_item['title'] : ( isset( $group_default['title'] ) ? $group_default['title'] : '' );
                                    $group_links    = isset( $group_item['links'] ) ? $group_item['links'] : ( isset( $group_default['links'] ) ? $group_default['links'] : [] );
                                    $group_links_ui = aegis_mega_header_links_to_textarea( $group_links );
                                    ?>
                                    <div class="aegis-mega-panel-group">
                                        <p><strong>Group <?php echo esc_html( $i + 1 ); ?></strong></p>
                                        <p>
                                            <label>Group <?php echo esc_html( $i + 1 ); ?> Title<br />
                                                <input type="text" class="regular-text" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][panel][groups][<?php echo esc_attr( $i ); ?>][title]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][groups][<?php echo esc_attr( $i ); ?>][title]" value="<?php echo esc_attr( $group_title ); ?>" />
                                            </label>
                                        </p>
                                        <p>
                                            <label>Group <?php echo esc_html( $i + 1 ); ?> Links<br />
                                                <textarea class="large-text code" rows="4" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][panel][groups][<?php echo esc_attr( $i ); ?>][links]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][groups][<?php echo esc_attr( $i ); ?>][links]" placeholder="Label|URL per line"><?php echo esc_textarea( $group_links_ui ); ?></textarea>
                                            </label>
                                        <small>One per line: Label|URL</small>
                                    </p>
                                </div>
                                <?php
                                }
                                ?>
                                <div class="aegis-mega-panel-promo">
                                    <p><strong>Promo Mode</strong></p>
                                    <label><input type="radio" class="aegis-promo-mode" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][panel][promo][mode]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][mode]" value="global" <?php checked( $promo_mode, 'global' ); ?> /> Use Global</label><br />
                                    <label><input type="radio" class="aegis-promo-mode" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][panel][promo][mode]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][mode]" value="custom" <?php checked( $promo_mode, 'custom' ); ?> /> Custom for this menu</label><br />
                                    <label><input type="radio" class="aegis-promo-mode" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][panel][promo][mode]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][mode]" value="hidden" <?php checked( $promo_mode, 'hidden' ); ?> /> Hide promo</label>

                                    <div class="aegis-promo-custom" <?php echo 'custom' === $promo_mode ? '' : 'style="display:none;"'; ?>>
                                        <?php foreach ( [ 'promo_1', 'promo_2' ] as $promo_key ) :
                                            $promo_slot     = isset( $promo_custom[ $promo_key ] ) ? $promo_custom[ $promo_key ] : $promo_default['custom'][ $promo_key ];
                                            $promo_image_id = isset( $promo_slot['image_id'] ) ? absint( $promo_slot['image_id'] ) : 0;
                                            $promo_title    = isset( $promo_slot['title'] ) ? $promo_slot['title'] : '';
                                            $promo_subtitle = isset( $promo_slot['subtitle'] ) ? $promo_slot['subtitle'] : '';
                                            $promo_url      = isset( $promo_slot['url'] ) ? $promo_slot['url'] : '';
                                            $promo_new_tab  = ! empty( $promo_slot['new_tab'] );
                                            $promo_key_safe = sanitize_key( $promo_key );
                                            $preview_id     = 'aegis-promo-preview-' . $promo_key_safe . '-' . (int) $index;
                                            $input_id       = 'aegis-promo-image-' . $promo_key_safe . '-' . (int) $index;
                                            $image_src      = $promo_image_id ? wp_get_attachment_image_url( $promo_image_id, 'medium' ) : '';
                                            ?>
                                            <div class="aegis-promo-slot">
                                                <p><strong><?php echo esc_html( strtoupper( str_replace( '_', ' ', $promo_key ) ) ); ?></strong></p>
                                                <input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][image_id]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][image_id]" value="<?php echo esc_attr( $promo_image_id ); ?>" />
                                                <button type="button" class="button aegis-media-select" data-media-target="<?php echo esc_attr( $input_id ); ?>" data-preview-target="<?php echo esc_attr( $preview_id ); ?>">Select image</button>
                                                <button type="button" class="button aegis-media-clear" data-clear-target="<?php echo esc_attr( $input_id ); ?>" data-preview-target="<?php echo esc_attr( $preview_id ); ?>">Clear</button>
                                                <div class="aegis-media-preview">
                                                    <img id="<?php echo esc_attr( $preview_id ); ?>" src="<?php echo esc_url( $image_src ); ?>" style="max-width:200px; height:auto;<?php echo $image_src ? '' : 'display:none;'; ?>" alt="" />
                                                </div>
                                                <p>
                                                    <label>Title<br />
                                                        <input type="text" class="regular-text" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][title]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][title]" value="<?php echo esc_attr( $promo_title ); ?>" />
                                                    </label>
                                                </p>
                                                <p>
                                                    <label>Subtitle<br />
                                                        <input type="text" class="regular-text" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][subtitle]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][subtitle]" value="<?php echo esc_attr( $promo_subtitle ); ?>" />
                                                    </label>
                                                </p>
                                                <p>
                                                    <label>URL<br />
                                                        <input type="url" class="regular-text" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][url]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][url]" value="<?php echo esc_attr( $promo_url ); ?>" />
                                                    </label>
                                                </p>
                                                <p>
                                                    <label><input type="checkbox" name="aegis_mega_header_settings[nav][items][<?php echo esc_attr( $index ); ?>][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][new_tab]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][new_tab]" value="1" <?php checked( $promo_new_tab ); ?> /> Open in new tab</label>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="aegis-main-actions">
                <button type="button" class="button" data-aegis-add-item>Add Item</button>
            </div>

            <template id="aegis-main-item-template">
                <div class="aegis-main-item" data-index="__INDEX__">
                    <input type="hidden" name="aegis_mega_header_settings[nav][items][__INDEX__][id]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][id]" value="__ID__" />
                    <div class="aegis-main-item__row">
                        <div class="aegis-main-item__field aegis-main-item__field-label">
                            <label>Label<br />
                                <input type="text" class="regular-text" name="aegis_mega_header_settings[nav][items][__INDEX__][label]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][label]" value="NEW ITEM" />
                            </label>
                        </div>
                        <div class="aegis-main-item__field aegis-main-item__field-type">
                            <span class="aegis-main-item__field-title">Type</span>
                            <label><input type="radio" class="aegis-nav-type" name="aegis_mega_header_settings[nav][items][__INDEX__][type]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][type]" value="link" checked="checked" /> Link</label>
                            <label><input type="radio" class="aegis-nav-type" name="aegis_mega_header_settings[nav][items][__INDEX__][type]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][type]" value="mega" /> Mega</label>
                        </div>
                        <div class="aegis-main-item__field aegis-main-item__field-url">
                            <label>URL<br />
                                <input type="url" class="regular-text" name="aegis_mega_header_settings[nav][items][__INDEX__][url]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][url]" value="#" />
                            </label>
                        </div>
                        <div class="aegis-main-item__controls">
                            <button type="button" class="button aegis-move-up">Move Up</button>
                            <button type="button" class="button aegis-move-down">Move Down</button>
                            <button type="button" class="button aegis-delete-item">Delete</button>
                        </div>
                    </div>
                    <details class="aegis-panel-details aegis-main-item__mega" style="display:none;">
                        <summary>MEGA Panel</summary>
                        <div class="aegis-mega-panel-settings">
                            <p>
                                <label>Sidebar Title<br />
                                    <input type="text" class="regular-text" name="aegis_mega_header_settings[nav][items][__INDEX__][panel][sidebar][title]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][sidebar][title]" value="" />
                                </label>
                            </p>
                            <p>
                                <label>Sidebar Links<br />
                                    <textarea class="large-text code" rows="4" name="aegis_mega_header_settings[nav][items][__INDEX__][panel][sidebar_links]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][sidebar_links]" placeholder="Label|URL per line"></textarea>
                                </label>
                                <small>One per line: Label|URL</small>
                            </p>
                            <?php for ( $i = 0; $i < 4; $i++ ) : ?>
                                <div class="aegis-mega-panel-group">
                                    <p><strong>Group <?php echo esc_html( $i + 1 ); ?></strong></p>
                                    <p>
                                        <label>Group <?php echo esc_html( $i + 1 ); ?> Title<br />
                                            <input type="text" class="regular-text" name="aegis_mega_header_settings[nav][items][__INDEX__][panel][groups][<?php echo esc_attr( $i ); ?>][title]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][groups][<?php echo esc_attr( $i ); ?>][title]" value="" />
                                        </label>
                                    </p>
                                    <p>
                                        <label>Group <?php echo esc_html( $i + 1 ); ?> Links<br />
                                            <textarea class="large-text code" rows="4" name="aegis_mega_header_settings[nav][items][__INDEX__][panel][groups][<?php echo esc_attr( $i ); ?>][links]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][groups][<?php echo esc_attr( $i ); ?>][links]" placeholder="Label|URL per line"></textarea>
                                        </label>
                                        <small>One per line: Label|URL</small>
                                    </p>
                                </div>
                            <?php endfor; ?>
                            <div class="aegis-mega-panel-promo">
                                <p><strong>Promo Mode</strong></p>
                                <label><input type="radio" class="aegis-promo-mode" name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][mode]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][mode]" value="global" checked="checked" /> Use Global</label><br />
                                <label><input type="radio" class="aegis-promo-mode" name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][mode]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][mode]" value="custom" /> Custom for this menu</label><br />
                                <label><input type="radio" class="aegis-promo-mode" name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][mode]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][mode]" value="hidden" /> Hide promo</label>

                                <div class="aegis-promo-custom" style="display:none;">
                                    <?php foreach ( [ 'promo_1', 'promo_2' ] as $promo_key ) :
                                        $promo_key_safe = sanitize_key( $promo_key );
                                        $preview_id = 'aegis-promo-preview-' . $promo_key_safe . '-__INDEX__';
                                        $input_id   = 'aegis-promo-image-' . $promo_key_safe . '-__INDEX__';
                                        ?>
                                        <div class="aegis-promo-slot">
                                            <p><strong><?php echo esc_html( strtoupper( str_replace( '_', ' ', $promo_key ) ) ); ?></strong></p>
                                            <input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][image_id]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][image_id]" value="" />
                                            <button type="button" class="button aegis-media-select" data-media-target="<?php echo esc_attr( $input_id ); ?>" data-preview-target="<?php echo esc_attr( $preview_id ); ?>">Select image</button>
                                            <button type="button" class="button aegis-media-clear" data-clear-target="<?php echo esc_attr( $input_id ); ?>" data-preview-target="<?php echo esc_attr( $preview_id ); ?>">Clear</button>
                                            <div class="aegis-media-preview">
                                                <img id="<?php echo esc_attr( $preview_id ); ?>" src="" style="max-width:200px; height:auto; display:none;" alt="" />
                                            </div>
                                            <p>
                                                <label>Title<br />
                                                    <input type="text" class="regular-text" name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][title]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][title]" value="" />
                                                </label>
                                            </p>
                                            <p>
                                                <label>Subtitle<br />
                                                    <input type="text" class="regular-text" name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][subtitle]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][subtitle]" value="" />
                                                </label>
                                            </p>
                                            <p>
                                                <label>URL<br />
                                                    <input type="url" class="regular-text" name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][url]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][url]" value="#" />
                                                </label>
                                            </p>
                                            <p>
                                                <label><input type="checkbox" name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][new_tab]" data-indexed-name="aegis_mega_header_settings[nav][items][__INDEX__][panel][promo][custom][<?php echo esc_attr( $promo_key ); ?>][new_tab]" value="1" /> Open in new tab</label>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </details>
                </div>
            </template>

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
