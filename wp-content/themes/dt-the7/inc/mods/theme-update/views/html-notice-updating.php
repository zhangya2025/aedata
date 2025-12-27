<?php
/**
 * Admin View: Notice - Updating
 */

defined( 'ABSPATH' ) || exit;
?>
<p><strong><?php esc_html_e( 'The7 database update', 'the7mk2' ); ?></strong> &#8211; <?php esc_html_e( 'Your database is being updated in the background.', 'the7mk2' ); ?> <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'force_update_the7', 'true', admin_url( 'admin.php?page=the7-dashboard' ) ), 'force_update_the7_nonce' ) ); ?>"><?php esc_html_e( 'Taking a while? Click here to run it now.', 'the7mk2' ); ?></a></p>
