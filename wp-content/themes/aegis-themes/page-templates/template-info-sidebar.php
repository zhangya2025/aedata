<?php
/*
 * Template Name: AEGIS Info Sidebar
 * Template Post Type: page
 */

get_header();

$current_id = get_queried_object_id();
$root_id    = $current_id;
$ancestors  = get_post_ancestors( $current_id );
if ( ! empty( $ancestors ) ) {
    $root_id = (int) end( $ancestors );
}

$root_page = get_post( $root_id );
$children  = array();
if ( $root_page && 'publish' === $root_page->post_status ) {
    $children = get_pages(
        array(
            'parent'      => $root_id,
            'sort_column' => 'menu_order,post_title',
            'sort_order'  => 'ASC',
            'post_status' => 'publish',
        )
    );
}

$is_root_self = (int) $current_id === (int) $root_id;
$has_children = ! empty( $children );
$nav_id       = 'aegis-info-nav-list';
?>
<main class="aegis-info-layout">
    <aside class="aegis-info-nav">
        <button class="aegis-info-nav-toggle" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $nav_id ); ?>">
            <?php esc_html_e( '目录', 'aegis-themes' ); ?>
        </button>
        <?php if ( ! empty( $items ) ) : ?>
            <?php
            $root_item  = $items[0] ?? null;
            $child_items = array_slice( $items, 1 );
            ?>
            <?php if ( $root_item ) : ?>
                <div class="aegis-info-nav-root"><?php echo esc_html( $root_item->post_title ); ?></div>
            <?php endif; ?>
            <ul id="<?php echo esc_attr( $nav_id ); ?>" class="aegis-info-nav-list">
                <?php foreach ( $child_items as $page ) : ?>
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
        <?php if ( $is_root_self && $has_children ) : ?>
            <div class="aegis-info-empty" aria-hidden="true"></div>
        <?php else : ?>
            <h1 class="aegis-info-title"><?php the_title(); ?></h1>
            <div class="aegis-info-body">
                <?php the_content(); ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php
get_footer();
