<?php
/*
 * Template Name: AEGIS Info Sidebar
 * Template Post Type: page
 */

get_header();

$current_id = (int) get_queried_object_id();
$current    = get_post( $current_id );
$nav_id     = 'aegis-info-nav-list';
$root_id    = $current_id;

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

$root     = get_post( $root_id );
$children = get_pages(
    array(
        'post_type'   => 'page',
        'post_status' => 'publish',
        'parent'      => $root_id,
        'sort_column' => 'menu_order,post_title',
        'sort_order'  => 'ASC',
    )
);
?>
<main class="aegis-info-layout">
    <aside class="aegis-info-nav">
        <button class="aegis-info-nav-toggle" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $nav_id ); ?>">
            <?php esc_html_e( '目录', 'aegis-themes' ); ?>
        </button>
        <?php if ( $root ) : ?>
            <div class="aegis-info-nav-root"><?php echo esc_html( $root->post_title ); ?></div>
        <?php endif; ?>
        <ul id="<?php echo esc_attr( $nav_id ); ?>" class="aegis-info-nav-list">
            <?php if ( ! empty( $children ) ) : ?>
                <?php foreach ( $children as $page ) : ?>
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
            <?php else : ?>
                <li class="aegis-info-nav-empty"><?php esc_html_e( 'No pages yet.', 'aegis-themes' ); ?></li>
            <?php endif; ?>
        </ul>
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
