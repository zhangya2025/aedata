<?php
/*
 * Template Name: AEGIS Info Sidebar
 * Template Post Type: page
 */

get_header();

$current_id = get_the_ID();
$items      = aegis_info_sidebar_get_nav_items( $current_id );
$nav_id     = 'aegis-info-nav-list';
?>
<main class="aegis-info-layout">
    <aside class="aegis-info-nav">
        <button class="aegis-info-nav-toggle" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $nav_id ); ?>">
            <?php esc_html_e( '目录', 'aegis-themes' ); ?>
        </button>
        <?php if ( ! empty( $items ) ) : ?>
            <ul id="<?php echo esc_attr( $nav_id ); ?>" class="aegis-info-nav-list">
                <?php foreach ( $items as $page ) : ?>
                    <?php
                    $is_current = (int) $page->ID === (int) $current_id;
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
            </ul>
        <?php endif; ?>
    </aside>
    <section class="aegis-info-content">
        <h1 class="aegis-info-title"><?php the_title(); ?></h1>
        <div class="aegis-info-body">
            <?php the_content(); ?>
        </div>
    </section>
</main>
<?php
get_footer();
