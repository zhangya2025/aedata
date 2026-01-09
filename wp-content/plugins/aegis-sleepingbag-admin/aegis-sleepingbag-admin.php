<?php
/**
 * Plugin Name: AEGIS Sleepingbag Admin
 * Description: Admin tools for sleeping bag product data and attributes.
 * Version: 1.0.0
 * Author: AEGIS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AEGIS_Sleepingbag_Admin {
    const MENU_SLUG = 'aegis-sleepingbag-admin';

    private $meta_fields = [
        'sleepingbag_limit_c'   => 'Limit (°C)',
        'sleepingbag_comfort_c' => 'Comfort (°C)',
        'sleepingbag_extreme_c' => 'Extreme (°C)',
    ];

    public function __construct() {
        add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_product_tab' ] );
        add_action( 'woocommerce_product_data_panels', [ $this, 'render_product_panel' ] );
        add_action( 'woocommerce_admin_process_product_object', [ $this, 'save_product_meta' ] );

        add_action( 'admin_menu', [ $this, 'register_tools_page' ] );
    }

    public function add_product_tab( $tabs ) {
        $tabs['aegis_sleeping_bag'] = [
            'label'    => __( 'Sleeping Bag', 'aegis-sleepingbag-admin' ),
            'target'   => 'aegis_sleeping_bag_data',
            'class'    => [],
            'priority' => 80,
        ];

        return $tabs;
    }

    public function render_product_panel() {
        echo '<div id="aegis_sleeping_bag_data" class="panel woocommerce_options_panel">';

        foreach ( $this->meta_fields as $key => $label ) {
            woocommerce_wp_text_input(
                [
                    'id'                => $key,
                    'label'             => $label,
                    'type'              => 'number',
                    'custom_attributes' => [
                        'step' => '0.1',
                    ],
                    'description'       => __( '仅填数字，例如 -5 / -5.5', 'aegis-sleepingbag-admin' ),
                    'desc_tip'          => true,
                ]
            );
        }

        echo '</div>';
    }

    public function save_product_meta( $product ) {
        foreach ( array_keys( $this->meta_fields ) as $key ) {
            $value = isset( $_POST[ $key ] ) ? wc_clean( wp_unslash( $_POST[ $key ] ) ) : '';
            $value = is_string( $value ) ? trim( $value ) : $value;

            if ( '' === $value ) {
                $product->delete_meta_data( $key );
                continue;
            }

            $formatted = wc_format_decimal( $value );
            if ( '' === $formatted ) {
                $product->delete_meta_data( $key );
                continue;
            }

            $product->update_meta_data( $key, $formatted );
        }
    }

    public function register_tools_page() {
        add_submenu_page(
            'tools.php',
            __( 'Sleepingbag Attribute Sync', 'aegis-sleepingbag-admin' ),
            __( 'Sleepingbag Attribute Sync', 'aegis-sleepingbag-admin' ),
            'manage_woocommerce',
            self::MENU_SLUG,
            [ $this, 'render_tools_page' ]
        );
    }

    public function render_tools_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'aegis-sleepingbag-admin' ) );
        }

        $result = null;
        if ( isset( $_POST['aegis_sleepingbag_action'] ) && check_admin_referer( 'aegis_sleepingbag_sync', 'aegis_sleepingbag_nonce' ) ) {
            $action = sanitize_text_field( wp_unslash( $_POST['aegis_sleepingbag_action'] ) );
            if ( 'dry_run' === $action ) {
                $result = $this->run_sync( true );
            }
            if ( 'sync' === $action ) {
                $result = $this->run_sync( false );
            }
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Sleepingbag Attribute Sync', 'aegis-sleepingbag-admin' ) . '</h1>';
        echo '<p>' . esc_html__( 'Dry-run will show what would be created. Sync will create only missing attributes/terms.', 'aegis-sleepingbag-admin' ) . '</p>';

        echo '<form method="post">';
        wp_nonce_field( 'aegis_sleepingbag_sync', 'aegis_sleepingbag_nonce' );
        echo '<p>';
        echo '<button class="button" name="aegis_sleepingbag_action" value="dry_run">' . esc_html__( 'Dry-run Preview', 'aegis-sleepingbag-admin' ) . '</button> ';
        echo '<button class="button button-primary" name="aegis_sleepingbag_action" value="sync">' . esc_html__( 'Sync Now', 'aegis-sleepingbag-admin' ) . '</button>';
        echo '</p>';
        echo '</form>';

        if ( null !== $result ) {
            $this->render_result( $result );
        }

        echo '</div>';
    }

    private function render_result( $result ) {
        $mode = $result['dry_run'] ? __( 'Dry-run result', 'aegis-sleepingbag-admin' ) : __( 'Sync result', 'aegis-sleepingbag-admin' );
        echo '<h2>' . esc_html( $mode ) . '</h2>';

        echo '<h3>' . esc_html__( 'Attributes to create', 'aegis-sleepingbag-admin' ) . '</h3>';
        if ( empty( $result['attributes'] ) ) {
            echo '<p>' . esc_html__( 'None.', 'aegis-sleepingbag-admin' ) . '</p>';
        } else {
            echo '<ul>';
            foreach ( $result['attributes'] as $attribute ) {
                echo '<li>' . esc_html( $attribute ) . '</li>';
            }
            echo '</ul>';
        }

        echo '<h3>' . esc_html__( 'Terms to create', 'aegis-sleepingbag-admin' ) . '</h3>';
        if ( empty( $result['terms'] ) ) {
            echo '<p>' . esc_html__( 'None.', 'aegis-sleepingbag-admin' ) . '</p>';
        } else {
            foreach ( $result['terms'] as $taxonomy => $terms ) {
                echo '<h4>' . esc_html( $taxonomy ) . '</h4>';
                echo '<ul>';
                foreach ( $terms as $term ) {
                    echo '<li>' . esc_html( $term ) . '</li>';
                }
                echo '</ul>';
            }
        }

        if ( ! empty( $result['errors'] ) ) {
            echo '<h3>' . esc_html__( 'Errors', 'aegis-sleepingbag-admin' ) . '</h3>';
            echo '<ul>';
            foreach ( $result['errors'] as $error ) {
                echo '<li>' . esc_html( $error ) . '</li>';
            }
            echo '</ul>';
        }
    }

    private function run_sync( $dry_run ) {
        $config = $this->load_config();
        $attributes = $this->get_existing_attributes();
        $result = [
            'dry_run'    => $dry_run,
            'attributes' => [],
            'terms'      => [],
            'errors'     => [],
        ];

        foreach ( $config as $index => $entry ) {
            $slug  = sanitize_title( $entry['slug'] );
            $name  = $entry['name'];
            $terms = isset( $entry['terms'] ) ? $entry['terms'] : [];

            $taxonomy = wc_attribute_taxonomy_name( $slug );

            if ( ! isset( $attributes[ $slug ] ) ) {
                $result['attributes'][] = sprintf( '%s (%s)', $name, $slug );
                if ( ! $dry_run ) {
                    $attribute_id = wc_create_attribute(
                        [
                            'name'         => $name,
                            'slug'         => $slug,
                            'type'         => 'select',
                            'order_by'     => 'menu_order',
                            'has_archives' => false,
                        ]
                    );
                    if ( is_wp_error( $attribute_id ) ) {
                        $result['errors'][] = $attribute_id->get_error_message();
                        continue;
                    }
                    delete_transient( 'wc_attribute_taxonomies' );
                    $attributes = $this->get_existing_attributes();
                }
            }

            if ( ! taxonomy_exists( $taxonomy ) ) {
                register_taxonomy(
                    $taxonomy,
                    [ 'product' ],
                    [
                        'hierarchical' => false,
                        'show_ui'      => false,
                        'show_in_nav_menus' => false,
                        'show_admin_column' => false,
                        'public'       => false,
                        'query_var'    => true,
                        'rewrite'      => false,
                    ]
                );
            }

            foreach ( $terms as $term_index => $term_name ) {
                $term_slug = sanitize_title( $term_name );
                $exists    = term_exists( $term_slug, $taxonomy );

                if ( ! $exists ) {
                    if ( ! isset( $result['terms'][ $taxonomy ] ) ) {
                        $result['terms'][ $taxonomy ] = [];
                    }
                    $result['terms'][ $taxonomy ][] = $term_name;

                    if ( ! $dry_run ) {
                        $inserted = wp_insert_term(
                            $term_name,
                            $taxonomy,
                            [
                                'slug' => $term_slug,
                            ]
                        );

                        if ( is_wp_error( $inserted ) ) {
                            $result['errors'][] = $inserted->get_error_message();
                            continue;
                        }

                        if ( isset( $inserted['term_id'] ) ) {
                            update_term_meta( $inserted['term_id'], 'order', $term_index + 1 );
                        }
                    }
                }
            }
        }

        return $result;
    }

    private function load_config() {
        $path = plugin_dir_path( __FILE__ ) . 'config.php';
        if ( ! file_exists( $path ) ) {
            return [];
        }

        $config = require $path;
        return is_array( $config ) ? $config : [];
    }

    private function get_existing_attributes() {
        if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
            return [];
        }

        $existing = [];
        $attributes = wc_get_attribute_taxonomies();
        if ( empty( $attributes ) ) {
            return [];
        }

        foreach ( $attributes as $attribute ) {
            $existing[ $attribute->attribute_name ] = $attribute;
        }

        return $existing;
    }
}

new AEGIS_Sleepingbag_Admin();
