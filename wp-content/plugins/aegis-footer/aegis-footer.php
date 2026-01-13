<?php
/**
 * Plugin Name: Aegis Footer
 * Description: Configurable responsive footer with columns and accordion layout.
 * Author: Aegis
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function aegis_footer_default_columns() {
    return array(
        array(
            'id'           => 'col_' . wp_generate_uuid4(),
            'title'        => 'AEGISMAX',
            'links_text'   => '',
            'content_text' => '',
        ),
        array(
            'id'           => 'col_' . wp_generate_uuid4(),
            'title'        => 'Resources',
            'links_text'   => '',
            'content_text' => '',
        ),
        array(
            'id'           => 'col_' . wp_generate_uuid4(),
            'title'        => 'Legal',
            'links_text'   => '',
            'content_text' => '',
        ),
        array(
            'id'           => 'col_' . wp_generate_uuid4(),
            'title'        => 'Customer Service',
            'links_text'   => '',
            'content_text' => '',
        ),
    );
}

function aegis_footer_get_defaults() {
    $year = (int) wp_date( 'Y' );
    return array(
        'enabled' => true,
        'columns' => aegis_footer_default_columns(),
        'bottom'  => array(
            'copyright_text' => '© ' . $year . ' Aegis. All rights reserved.',
        ),
    );
}

function aegis_footer_get_settings() {
    $defaults = aegis_footer_get_defaults();
    $options  = get_option( 'aegis_footer_settings', array() );
    if ( ! is_array( $options ) ) {
        $options = array();
    }
    $settings = wp_parse_args( $options, $defaults );

    if ( empty( $settings['columns'] ) || ! is_array( $settings['columns'] ) ) {
        $settings['columns'] = aegis_footer_default_columns();
    } else {
        $clean = array();
        foreach ( $settings['columns'] as $col ) {
            if ( empty( $col['id'] ) ) {
                $col['id'] = 'col_' . wp_generate_uuid4();
            }
            $clean[] = array(
                'id'           => $col['id'],
                'title'        => isset( $col['title'] ) ? $col['title'] : '',
                'links_text'   => isset( $col['links_text'] ) ? $col['links_text'] : '',
                'content_text' => isset( $col['content_text'] ) ? $col['content_text'] : '',
            );
        }
        $settings['columns'] = $clean;
    }

    if ( empty( $settings['bottom'] ) || ! is_array( $settings['bottom'] ) ) {
        $settings['bottom'] = $defaults['bottom'];
    }

    if ( ! isset( $settings['enabled'] ) ) {
        $settings['enabled'] = true;
    }

    return $settings;
}

function aegis_footer_parse_links_text( $text ) {
    $lines  = preg_split( '/\r?\n/', (string) $text );
    $links  = array();

    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( '' === $line ) {
            continue;
        }
        $parts = explode( '|', $line, 2 );
        $label = sanitize_text_field( $parts[0] );
        if ( '' === $label ) {
            continue;
        }
        $url = isset( $parts[1] ) ? trim( $parts[1] ) : '';
        if ( '' !== $url && '#' !== $url ) {
            $url = esc_url( $url );
        }
        $links[] = array(
            'label' => $label,
            'url'   => $url,
        );
    }

    return $links;
}

function aegis_footer_get_section_pages( $parent_slug ) {
    static $cache = array();

    if ( isset( $cache[ $parent_slug ] ) ) {
        return $cache[ $parent_slug ];
    }

    $empty = array(
        'parent'   => array(),
        'children' => array(),
    );

    if ( '' === (string) $parent_slug ) {
        $cache[ $parent_slug ] = $empty;
        return $empty;
    }

    $parent = get_page_by_path( $parent_slug, OBJECT, 'page' );
    if ( ! $parent instanceof WP_Post || 'publish' !== $parent->post_status ) {
        $cache[ $parent_slug ] = $empty;
        return $empty;
    }

    $children = get_pages(
        array(
            'parent'      => $parent->ID,
            'sort_column' => 'menu_order,post_title',
            'sort_order'  => 'ASC',
            'post_status' => 'publish',
        )
    );

    $items = array();
    foreach ( $children as $child ) {
        $items[] = array(
            'title' => $child->post_title,
            'url'   => get_permalink( $child->ID ),
        );
    }

    $cache[ $parent_slug ] = array(
        'parent'   => array(
            'title' => $parent->post_title,
            'url'   => get_permalink( $parent->ID ),
        ),
        'children' => $items,
    );

    return $cache[ $parent_slug ];
}

function aegis_footer_dynamic_sections_map() {
    return array(
        'aegismax' => array(
            'label'       => 'AEGISMAX',
            'parent_slug' => 'about',
        ),
        'resources' => array(
            'label'       => 'Resources',
            'parent_slug' => 'resources',
        ),
        'legal' => array(
            'label'       => 'Legal',
            'parent_slug' => 'legal',
        ),
        'customer_service' => array(
            'label'       => 'Customer Service',
            'parent_slug' => 'support',
        ),
    );
}

function aegis_footer_apply_dynamic_links( $columns ) {
    if ( empty( $columns ) || ! is_array( $columns ) ) {
        return $columns;
    }

    $map = aegis_footer_dynamic_sections_map();

    foreach ( $columns as $index => $column ) {
        $title = isset( $column['title'] ) ? trim( $column['title'] ) : '';
        if ( '' === $title ) {
            continue;
        }

        foreach ( $map as $section ) {
            if ( 0 !== strcasecmp( $title, $section['label'] ) ) {
                continue;
            }

            $links_text = isset( $column['links_text'] ) ? trim( (string) $column['links_text'] ) : '';
            if ( '' !== $links_text ) {
                break;
            }

            $pages = aegis_footer_get_section_pages( $section['parent_slug'] );
            if ( empty( $pages['children'] ) ) {
                break;
            }

            $columns[ $index ]['links'] = $pages['children'];
            break;
        }
    }

    return $columns;
}

function aegis_footer_render_links_list( $links ) {
    if ( empty( $links ) ) {
        return '';
    }
    $items = '';
    foreach ( $links as $link ) {
        $url   = isset( $link['url'] ) ? $link['url'] : '';
        $label = isset( $link['label'] ) ? $link['label'] : '';
        if ( '' === $label && isset( $link['title'] ) ) {
            $label = $link['title'];
        }
        if ( '' === $label ) {
            continue;
        }
        $href = ( '' !== $url ) ? esc_url( $url ) : '#';
        $items .= '<li class="aegis-footer__link-item"><a href="' . $href . '" class="aegis-footer__link">' . esc_html( $label ) . '</a></li>';
    }
    if ( '' === $items ) {
        return '';
    }
    return '<ul class="aegis-footer__links">' . $items . '</ul>';
}

function aegis_footer_render_content( $text ) {
    $lines = preg_split( '/\r?\n/', (string) $text );
    $html  = '';
    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( '' === $line ) {
            continue;
        }
        $html .= '<p class="aegis-footer__content-line">' . esc_html( $line ) . '</p>';
    }
    if ( '' === $html ) {
        return '';
    }
    return '<div class="aegis-footer__content">' . $html . '</div>';
}

function aegis_footer_render_column( $column ) {
    $title   = isset( $column['title'] ) ? $column['title'] : '';
    $links   = isset( $column['links'] ) && is_array( $column['links'] )
        ? $column['links']
        : aegis_footer_parse_links_text( isset( $column['links_text'] ) ? $column['links_text'] : '' );
    $content = aegis_footer_render_content( isset( $column['content_text'] ) ? $column['content_text'] : '' );

    $html  = '<section class="aegis-footer__col">';
    if ( '' !== $title ) {
        $html .= '<h3 class="aegis-footer__title">' . esc_html( $title ) . '</h3>';
    }
    $html .= aegis_footer_render_links_list( $links );
    $html .= $content;
    $html .= '</section>';

    return $html;
}

function aegis_footer_render_accordion_column( $column ) {
    $title   = isset( $column['title'] ) ? $column['title'] : '';
    $links   = isset( $column['links'] ) && is_array( $column['links'] )
        ? $column['links']
        : aegis_footer_parse_links_text( isset( $column['links_text'] ) ? $column['links_text'] : '' );
    $content = aegis_footer_render_content( isset( $column['content_text'] ) ? $column['content_text'] : '' );
    $body    = aegis_footer_render_links_list( $links ) . $content;

    if ( '' === $body ) {
        $body = '<div class="aegis-footer__content"></div>';
    }

    $html  = '<details class="aegis-footer__acc-item">';
    $html .= '<summary class="aegis-footer__acc-title"><span>' . esc_html( $title ) . '</span><span class="aegis-footer__acc-icon" aria-hidden="true">▾</span></summary>';
    $html .= '<div class="aegis-footer__acc-body">' . $body . '</div>';
    $html .= '</details>';
    return $html;
}

function aegis_footer_render_block( $attributes ) {
    $settings = aegis_footer_get_settings();
    if ( empty( $settings['enabled'] ) ) {
        return '';
    }

    $columns = isset( $settings['columns'] ) && is_array( $settings['columns'] ) ? $settings['columns'] : array();
    $columns = aegis_footer_apply_dynamic_links( $columns );
    $bottom  = isset( $settings['bottom'] ) ? $settings['bottom'] : array();

    $grid_html = '';
    foreach ( $columns as $column ) {
        $grid_html .= aegis_footer_render_column( $column );
    }

    $accordion_html = '';
    foreach ( $columns as $column ) {
        $accordion_html .= aegis_footer_render_accordion_column( $column );
    }

    $copyright = isset( $bottom['copyright_text'] ) ? trim( $bottom['copyright_text'] ) : '';
    if ( '' === $copyright ) {
        $copyright = '© ' . wp_date( 'Y' ) . ' Aegis. All rights reserved.';
    }

    ob_start();
    ?>
    <footer class="aegis-footer">
        <div class="aegis-footer__inner">
            <div class="aegis-footer__grid" aria-label="Footer links">
                <?php echo $grid_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="aegis-footer__accordion" aria-label="Footer accordion">
                <?php echo $accordion_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="aegis-footer__bottom">
                <div class="aegis-footer__copyright"><?php echo esc_html( $copyright ); ?></div>
            </div>
        </div>
    </footer>
    <?php
    return ob_get_clean();
}

function aegis_footer_register_block() {
    $dir      = plugin_dir_path( __FILE__ );
    $url      = plugin_dir_url( __FILE__ );
    $style    = 'aegis-footer-style';
    $view     = 'aegis-footer-view';

    wp_register_style( $style, $url . 'style.css', array(), filemtime( $dir . 'style.css' ) );
    wp_register_script( $view, $url . 'view.js', array(), filemtime( $dir . 'view.js' ), true );

    register_block_type( 'aegis/footer', array(
        'api_version'     => 2,
        'title'           => __( 'Aegis Footer', 'aegis-footer' ),
        'description'     => __( 'Configurable responsive footer with grid and accordion views.', 'aegis-footer' ),
        'category'        => 'widgets',
        'icon'            => 'admin-site',
        'supports'        => array( 'html' => false ),
        'attributes'      => array(
            'placeholder' => array(
                'type'    => 'boolean',
                'default' => true,
            ),
        ),
        'render_callback' => 'aegis_footer_render_block',
        'style'           => $style,
        'view_script'     => $view,
    ) );
}
add_action( 'init', 'aegis_footer_register_block' );

function aegis_footer_admin_menu() {
    add_theme_page(
        __( 'Aegis Footer', 'aegis-footer' ),
        __( 'Aegis Footer', 'aegis-footer' ),
        'manage_options',
        'aegis-footer',
        'aegis_footer_render_settings_page'
    );
}
add_action( 'admin_menu', 'aegis_footer_admin_menu' );

function aegis_footer_handle_save() {
    if ( ! isset( $_POST['aegis_footer_settings_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['aegis_footer_settings_nonce'], 'aegis_footer_save_settings' ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $input = isset( $_POST['aegis_footer_settings'] ) ? (array) $_POST['aegis_footer_settings'] : array();

    $settings            = aegis_footer_get_defaults();
    $settings['enabled'] = ! empty( $input['enabled'] );

    $columns = array();
    if ( isset( $input['columns'] ) && is_array( $input['columns'] ) ) {
        foreach ( $input['columns'] as $col ) {
            if ( empty( $col['id'] ) && empty( $col['title'] ) && empty( $col['links_text'] ) && empty( $col['content_text'] ) ) {
                continue;
            }
            $columns[] = array(
                'id'           => ! empty( $col['id'] ) ? sanitize_text_field( $col['id'] ) : 'col_' . wp_generate_uuid4(),
                'title'        => isset( $col['title'] ) ? sanitize_text_field( $col['title'] ) : '',
                'links_text'   => isset( $col['links_text'] ) ? sanitize_textarea_field( $col['links_text'] ) : '',
                'content_text' => isset( $col['content_text'] ) ? sanitize_textarea_field( $col['content_text'] ) : '',
            );
        }
    }
    if ( ! empty( $columns ) ) {
        $settings['columns'] = $columns;
    }

    if ( isset( $input['bottom']['copyright_text'] ) ) {
        $settings['bottom']['copyright_text'] = sanitize_textarea_field( $input['bottom']['copyright_text'] );
    }

    update_option( 'aegis_footer_settings', $settings );
}
add_action( 'admin_init', 'aegis_footer_handle_save' );

function aegis_footer_admin_inline_styles() {
    ?>
    <style>
        .aegis-footer-admin .aegis-footer-columns { display: flex; flex-direction: column; gap: 12px; }
        .aegis-footer-admin .aegis-footer-column { border: 1px solid #e5e7eb; padding: 12px; background: #fff; }
        .aegis-footer-admin .aegis-footer-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .aegis-footer-admin .aegis-footer-row label { font-weight: 600; }
        .aegis-footer-admin .aegis-footer-actions { margin-left: auto; display: flex; gap: 6px; }
        .aegis-footer-admin .aegis-footer-actions .button { min-width: auto; }
        .aegis-footer-admin .aegis-footer-text { width: 200px; }
        .aegis-footer-admin .aegis-footer-url { flex: 1; min-width: 240px; }
        .aegis-footer-admin .aegis-footer-textarea { width: 100%; min-height: 80px; }
        .aegis-footer-admin .aegis-footer-columns-wrap { margin-top: 12px; }
        .aegis-footer-admin .aegis-footer-add { margin-top: 12px; }
    </style>
    <?php
}

function aegis_footer_admin_enqueue( $hook ) {
    $page_match = ( isset( $_GET['page'] ) && 'aegis-footer' === $_GET['page'] );
    if ( 'appearance_page_aegis-footer' !== $hook && ! $page_match ) {
        return;
    }
    $dir = plugin_dir_path( __FILE__ );
    $url = plugin_dir_url( __FILE__ );
    wp_enqueue_script( 'aegis-footer-admin', $url . 'admin.js', array(), filemtime( $dir . 'admin.js' ), true );
    aegis_footer_admin_inline_styles();
}
add_action( 'admin_enqueue_scripts', 'aegis_footer_admin_enqueue' );

function aegis_footer_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $settings = aegis_footer_get_settings();
    ?>
    <div class="wrap aegis-footer-admin">
        <h1><?php esc_html_e( 'Aegis Footer', 'aegis-footer' ); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'aegis_footer_save_settings', 'aegis_footer_settings_nonce' ); ?>

            <h2><?php esc_html_e( 'General', 'aegis-footer' ); ?></h2>
            <p>
                <label>
                    <input type="checkbox" name="aegis_footer_settings[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
                    <?php esc_html_e( 'Enable Footer', 'aegis-footer' ); ?>
                </label>
            </p>

            <h2><?php esc_html_e( 'Columns', 'aegis-footer' ); ?></h2>
            <div id="aegis-footer-columns" class="aegis-footer-columns">
                <?php
                $index = 0;
                foreach ( $settings['columns'] as $column ) :
                    $col_id       = ! empty( $column['id'] ) ? $column['id'] : 'col_' . wp_generate_uuid4();
                    $title        = isset( $column['title'] ) ? $column['title'] : '';
                    $links_text   = isset( $column['links_text'] ) ? $column['links_text'] : '';
                    $content_text = isset( $column['content_text'] ) ? $column['content_text'] : '';
                    ?>
                    <div class="aegis-footer-column" data-index="<?php echo esc_attr( $index ); ?>">
                        <div class="aegis-footer-row">
                            <label><?php esc_html_e( 'Title', 'aegis-footer' ); ?><br />
                                <input class="aegis-footer-text" type="text" name="aegis_footer_settings[columns][<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $title ); ?>" />
                            </label>
                            <label><?php esc_html_e( 'Links (Label|URL per line)', 'aegis-footer' ); ?><br />
                                <textarea class="aegis-footer-textarea" name="aegis_footer_settings[columns][<?php echo esc_attr( $index ); ?>][links_text]" rows="4"><?php echo esc_textarea( $links_text ); ?></textarea>
                            </label>
                            <label><?php esc_html_e( 'Content (optional)', 'aegis-footer' ); ?><br />
                                <textarea class="aegis-footer-textarea" name="aegis_footer_settings[columns][<?php echo esc_attr( $index ); ?>][content_text]" rows="4"><?php echo esc_textarea( $content_text ); ?></textarea>
                            </label>
                            <div class="aegis-footer-actions">
                                <button type="button" class="button" data-move-up><?php esc_html_e( 'Move Up', 'aegis-footer' ); ?></button>
                                <button type="button" class="button" data-move-down><?php esc_html_e( 'Move Down', 'aegis-footer' ); ?></button>
                                <button type="button" class="button" data-delete><?php esc_html_e( 'Delete', 'aegis-footer' ); ?></button>
                            </div>
                        </div>
                        <input type="hidden" name="aegis_footer_settings[columns][<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $col_id ); ?>" />
                    </div>
                    <?php
                    $index++;
                endforeach;
                ?>
            </div>
            <div class="aegis-footer-add">
                <button type="button" class="button" id="aegis-footer-add-column"><?php esc_html_e( 'Add Column', 'aegis-footer' ); ?></button>
            </div>

            <h2><?php esc_html_e( 'Bottom Bar', 'aegis-footer' ); ?></h2>
            <p>
                <label><?php esc_html_e( 'Copyright', 'aegis-footer' ); ?><br />
                    <textarea name="aegis_footer_settings[bottom][copyright_text]" rows="2" style="width: 100%; max-width: 520px;"><?php echo esc_textarea( $settings['bottom']['copyright_text'] ); ?></textarea>
                </label>
            </p>

            <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'aegis-footer' ); ?></button></p>
        </form>

        <template id="aegis-footer-column-template">
            <div class="aegis-footer-column" data-index="__index__">
                <div class="aegis-footer-row">
                    <label><?php esc_html_e( 'Title', 'aegis-footer' ); ?><br />
                        <input class="aegis-footer-text" type="text" name="aegis_footer_settings[columns][__index__][title]" value="" />
                    </label>
                    <label><?php esc_html_e( 'Links (Label|URL per line)', 'aegis-footer' ); ?><br />
                        <textarea class="aegis-footer-textarea" name="aegis_footer_settings[columns][__index__][links_text]" rows="4"></textarea>
                    </label>
                    <label><?php esc_html_e( 'Content (optional)', 'aegis-footer' ); ?><br />
                        <textarea class="aegis-footer-textarea" name="aegis_footer_settings[columns][__index__][content_text]" rows="4"></textarea>
                    </label>
                    <div class="aegis-footer-actions">
                        <button type="button" class="button" data-move-up><?php esc_html_e( 'Move Up', 'aegis-footer' ); ?></button>
                        <button type="button" class="button" data-move-down><?php esc_html_e( 'Move Down', 'aegis-footer' ); ?></button>
                        <button type="button" class="button" data-delete><?php esc_html_e( 'Delete', 'aegis-footer' ); ?></button>
                    </div>
                </div>
                <input type="hidden" name="aegis_footer_settings[columns][__index__][id]" value="__id__" />
            </div>
        </template>
    </div>
    <?php
}

// Styles are enqueued via the registered block style handle.
