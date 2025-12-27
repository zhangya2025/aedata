<?php
/**
 * Admin View: Notice - Update
 */

defined( 'ABSPATH' ) || exit;
?>
<p><strong><?php esc_html_e( 'The7 database update', 'the7mk2' ); ?></strong> &#8211; <?php esc_html_e( 'We need to update your site database to match the latest theme version.', 'the7mk2' ); ?></p>
<p><a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'do_update_the7', 'true', admin_url( 'admin.php?page=the7-dashboard' ) ), 'do_update_the7_nonce' ) ); ?>" class="the7-update-now button-primary"><?php esc_html_e( 'Run the updater', 'the7mk2' ); ?></a></p>
<script type="text/javascript">
	jQuery( '.the7-update-now' ).click( 'click', function() {
		return window.confirm( '<?php echo esc_js( __( 'It is strongly recommended that you backup your database before proceeding. Are you sure you wish to run the updater now?', 'the7mk2' ) ); ?>' ); // jshint ignore:line
	});
</script>
