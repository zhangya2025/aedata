<?php
/*
 * Template Name: AEGIS Info Sidebar
 * Template Post Type: page
 */

get_header();

$current_id = (int) get_queried_object_id();
$nav_id = 'aegis-info-nav-list';
?>
<main class="aegis-info-layout">
    <aside class="aegis-info-nav">
        <button class="aegis-info-nav-toggle" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $nav_id ); ?>">
            <?php esc_html_e( '目录', 'aegis-themes' ); ?>
        </button>
        <?php echo aegis_info_sidebar_render_nav( $current_id, $nav_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
