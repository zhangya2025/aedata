<?php
/**
 * Info sidebar helpers for block and PHP templates.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function aegis_info_sidebar_get_context( $current_id ) {
    $current_id = (int) $current_id;
    if ( ! $current_id ) {
        return array(
            'current_id' => 0,
            'root' => null,
            'children' => array(),
        );
    }

    $current = get_post( $current_id );
    $root_id = $current_id;

    if ( $current && $current->post_parent ) {
        $ancestor = $current;
        while ( $ancestor && $ancestor->post_parent ) {
            $ancestor = get_post( $ancestor->post_parent );
            if ( ! $ancestor ) {
                break;
            }
            $root_id = (int) $ancestor->ID;
        }
    }

    $root = get_post( $root_id );
    $children = get_pages(
        array(
            'post_type'   => 'page',
            'post_status' => 'publish',
            'parent'      => $root_id,
            'sort_column' => 'menu_order,post_title',
            'sort_order'  => 'ASC',
        )
    );

    return array(
        'current_id' => $current_id,
        'root' => $root,
        'children' => $children,
    );
}

function aegis_info_sidebar_render_nav( $current_id, $nav_id = 'aegis-info-nav-list' ) {
    $context = aegis_info_sidebar_get_context( $current_id );
    $root = $context['root'];
    $children = $context['children'];
    $current_id = (int) $context['current_id'];

    ob_start();
    ?>
    <?php if ( $root ) : ?>
        <div class="aegis-info-nav-root"><?php echo esc_html( $root->post_title ); ?></div>
    <?php endif; ?>
    <ul id="<?php echo esc_attr( $nav_id ); ?>" class="aegis-info-nav-list">
        <?php if ( ! empty( $children ) ) : ?>
            <?php foreach ( $children as $page ) : ?>
                <?php
                $is_current = (int) $page->ID === $current_id;
                $classes    = 'aegis-info-nav-link';
                $aria       = $is_current ? ' aria-current="page"' : '';
                if ( $is_current ) {
                    $classes .= ' is-current';
                }
                ?>
                <li class="aegis-info-nav-item">
                    <a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( get_permalink( $page ) ); ?>"<?php echo $aria; ?>>
                        <?php echo esc_html( $page->post_title ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php else : ?>
            <li class="aegis-info-nav-empty"><?php esc_html_e( 'No pages yet.', 'aegis-themes' ); ?></li>
        <?php endif; ?>
    </ul>
    <?php
    return ob_get_clean();
}

function aegis_info_sidebar_nav_shortcode() {
    $current_id = (int) get_queried_object_id();
    return aegis_info_sidebar_render_nav( $current_id );
}
