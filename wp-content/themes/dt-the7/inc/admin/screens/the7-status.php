<?php
/**
 * The7 status screen.
 *
 * @package The7
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'in_admin_footer',
	static function () {
		?>
		<p style="position: absolute">
			<?php echo esc_html_x( 'Open The7', 'admin', 'the7mk2' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=the7-dev' ) ); ?>"><?php echo esc_html_x( 'debug tools', 'admin', 'the7mk2' ); ?></a>
		</p>
		<br>
		<?php
	}
);

global $wp_filesystem;
global $wpdb;
?>
<div class="updated the7-dashboard-notice">
	<p><?php esc_html_e( 'Please copy and paste this information in your ticket when contacting support:', 'the7mk2' ); ?> </p>
	<p class="submit"><a href="#" class="button-primary debug-report"><?php esc_html_e( 'Get system report', 'the7mk2' ); ?></a>
	</p>
	<div id="the7-debug-report">
		<textarea readonly="readonly"></textarea>
		<p class="copy-error"><?php esc_html_e( 'Please press Ctrl/Cmd+C to copy.', 'the7mk2' ); ?></p>
	</div>
</div>
<div id="the7-dashboard" class="wrap the7-status">
	<h1><?php esc_html_e( 'Service Information', 'the7mk2' ); ?></h1>
	<div class="the7-column-container">
		<div class="the7-column the7-column-double">
			<table class="the7-status-table widefat" cellspacing="0">
				<thead>
				<tr>
					<th colspan="3" data-export-label="WordPress Environment"><?php esc_html_e( 'WordPress Environment', 'the7mk2' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
					<td data-export-label="Home URL"><?php esc_html_e( 'Home URL:', 'the7mk2' ); ?></td>
					<td><?php echo esc_url( home_url() ); ?></td>
				</tr>
				<tr>
					<td data-export-label="Site URL"><?php esc_html_e( 'Site URL:', 'the7mk2' ); ?></td>
					<td><?php echo esc_url( site_url() ); ?></td>
				</tr>
				<tr>
					<td data-export-label="WP Version"><?php esc_html_e( 'WP Version:', 'the7mk2' ); ?></td>
					<td><?php bloginfo( 'version' ); ?></td>
				</tr>
				<tr>
					<td data-export-label="WP Multisite"><?php esc_html_e( 'WP Multisite:', 'the7mk2' ); ?></td>
					<td>
						<?php if ( is_multisite() ) : ?>
							<span class="yes">&#10004;</span>
						<?php else : ?>
							&ndash;
						<?php endif ?>
					</td>
				</tr>
				<tr>
					<td data-export-label="Path to uploads folder"><?php esc_html_e( 'Path to uploads folder:', 'the7mk2' ); ?></td>
					<td>
						<code>
							<?php
							$wp_uplodas = wp_get_upload_dir();
							echo esc_html( $wp_uplodas['basedir'] );
							?>
						</code>
					</td>
				</tr>
				<tr>
					<td data-export-label="WP Memory Limit"><?php esc_html_e( 'WP Memory Limit:', 'the7mk2' ); ?></td>
					<td>
						<?php echo esc_html( size_format( presscore_get_wp_memory_limit() ) ); ?>
					</td>
				</tr>
				<tr>
					<td data-export-label="WP Cron"><?php esc_html_e( 'WP Cron:', 'the7mk2' ); ?></td>
					<td>
						<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
							<span class="no">&ndash;</span>
						<?php else : ?>
							<span class="yes">&#10004;</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td data-export-label="FS Accessible"><?php esc_html_e( 'FS Accessible:', 'the7mk2' ); ?></td>
					<td>
						<?php if ( $wp_filesystem || WP_Filesystem() ) : ?>
							<span class="yes">&#10004;</span>
						<?php else : ?>
							<span class="error">No.</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td data-export-label="WP Debug Mode"><?php esc_html_e( 'WP Debug Mode:', 'the7mk2' ); ?></td>
					<td>
						<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
							<span class="yes">&#10004;</span>
						<?php else : ?>
							<span class="no">&ndash;</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td data-export-label="home option in DB"><?php esc_html_e( 'Home URL stored in DB:', 'the7mk2' ); ?></td>
					<td>
						<?php
						$home_url = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'home'" );
						echo esc_url( $home_url );
						?>
					</td>
				</tr>
				<tr>
					<td data-export-label="WP_HOME constant"><?php esc_html_e( 'WP_HOME:', 'the7mk2' ); ?></td>
					<td>
						<?php if ( defined( 'WP_HOME' ) && WP_HOME ) : ?>
							<?php echo esc_url( WP_HOME ); ?>
						<?php else : ?>
							<span class="no">&ndash;</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td data-export-label="siteurl option in DB"><?php esc_html_e( 'Site URL stored in DB:', 'the7mk2' ); ?></td>
					<td>
						<?php
						$siteurl = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl'" );
						echo esc_url( $siteurl );
						?>
					</td>
				</tr>
				<tr>
					<td data-export-label="WP_SITEURL constant"><?php esc_html_e( 'WP_SITEURL:', 'the7mk2' ); ?></td>
					<td>
						<?php if ( defined( 'WP_SITEURL' ) && WP_SITEURL ) : ?>
							<?php echo esc_url( WP_SITEURL ); ?>
						<?php else : ?>
							<span class="no">&ndash;</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td data-export-label="Language"><?php esc_html_e( 'Language:', 'the7mk2' ); ?></td>
					<td><?php echo esc_html( get_locale() ); ?></td>
				</tr>
				</tbody>
			</table>
			<table class="the7-status-table widefat" cellspacing="0">
				<thead>
				<tr>
					<th colspan="3" data-export-label="Server Environment"><?php esc_html_e( 'Server Environment', 'the7mk2' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
					<td data-export-label="Server Info"><?php esc_html_e( 'Server Info:', 'the7mk2' ); ?></td>
					<td>
						<?php
						if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
							echo esc_html( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) );
						} else {
							echo 'None';
						}
						?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Apache "mod_security" module:', 'the7mk2' ); ?></td>
					<td>
						<?php
						if ( function_exists( 'apache_get_modules' ) ) {
							$apache_get_modules = (array) apache_get_modules();
							if ( in_array( 'mod_security', $apache_get_modules, true ) || in_array( 'security2_module', $apache_get_modules, true ) ) {
								echo '<span class="yes">&#10004;</span>';
							} else {
								echo '<span class="no" style="color: green">&ndash;</span>';
							}
						} else {
							echo '<span class="no" style="color: green">?</span>';
						}
						?>
					</td>
				</tr>
				<tr>
					<td data-export-label="PHP Version"><?php esc_html_e( 'PHP Version:', 'the7mk2' ); ?></td>
					<td>
						<?php
						if ( function_exists( 'phpversion' ) ) {
								echo esc_html( phpversion() );
						}
						?>
					</td>
				</tr>
				<?php if ( function_exists( 'ini_get' ) ) : ?>
					<tr>
						<td data-export-label="PHP Post Max Size"><?php esc_html_e( 'PHP Post Max Size:', 'the7mk2' ); ?></td>
						<td><?php echo esc_html( size_format( wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) ) ) ); ?></td>
					</tr>
					<tr>
						<td data-export-label="PHP Time Limit"><?php esc_html_e( 'PHP Time Limit:', 'the7mk2' ); ?></td>
						<td>
							<?php echo esc_html( ini_get( 'max_execution_time' ) ); ?>
						</td>
					</tr>
					<tr>
						<td data-export-label="PHP Max Input Vars"><?php esc_html_e( 'PHP Max Input Vars:', 'the7mk2' ); ?></td>
						<?php
						$registered_navs  = get_nav_menu_locations();
						$menu_items_count = [ '0' => '0' ];
						foreach ( $registered_navs as $handle => $registered_nav ) {
							$menu = wp_get_nav_menu_object( $registered_nav );
							if ( $menu ) {
								$menu_items_count[] = $menu->count;
							}
						}

						$max_items           = max( $menu_items_count );
						$required_input_vars = $max_items * 20;
						?>
						<td>
							<?php
							$max_input_vars       = ini_get( 'max_input_vars' );
							$required_input_vars += ( 500 + 1000 );

							echo esc_html( $max_input_vars );
							?>
						</td>
					</tr>
					<tr>
						<td data-export-label="SUHOSIN Installed"><?php esc_html_e( 'SUHOSIN Installed:', 'the7mk2' ); ?></td>
						<td><?php echo extension_loaded( 'suhosin' ) ? '&#10004;' : '&ndash;'; ?></td>
					</tr>
					<?php if ( extension_loaded( 'suhosin' ) ) : ?>
						<tr>
							<td data-export-label="Suhosin Post Max Vars"><?php esc_html_e( 'Suhosin Post Max Vars:', 'the7mk2' ); ?></td>
							<?php
							$registered_navs  = get_nav_menu_locations();
							$menu_items_count = [ '0' => '0' ];
							foreach ( $registered_navs as $handle => $registered_nav ) {
								$menu = wp_get_nav_menu_object( $registered_nav );
								if ( $menu ) {
									$menu_items_count[] = $menu->count;
								}
							}

							$max_items           = max( $menu_items_count );
							$required_input_vars = $max_items * 20;
							?>
							<td>
								<?php
								$max_input_vars      = ini_get( 'suhosin.post.max_vars' );
								$required_input_vars = $required_input_vars + ( 500 + 1000 );

								echo esc_html( $max_input_vars );
								?>
							</td>
						</tr>
						<tr>
							<td data-export-label="Suhosin Request Max Vars"><?php esc_html_e( 'Suhosin Request Max Vars:', 'the7mk2' ); ?></td>
							<?php
							$registered_navs  = get_nav_menu_locations();
							$menu_items_count = [ '0' => '0' ];
							foreach ( $registered_navs as $handle => $registered_nav ) {
								$menu = wp_get_nav_menu_object( $registered_nav );
								if ( $menu ) {
									$menu_items_count[] = $menu->count;
								}
							}

							$max_items           = max( $menu_items_count );
							$required_input_vars = $max_items * 20;
							?>
							<td>
								<?php
								$max_input_vars      = ini_get( 'suhosin.request.max_vars' );
								$required_input_vars = $required_input_vars + ( 500 + 1000 );
								echo esc_html( $max_input_vars );
								?>
							</td>
						</tr>
						<tr>
							<td data-export-label="Suhosin Post Max Value Length"><?php esc_html_e( 'Suhosin Post Max Value Length:', 'the7mk2' ); ?></td>
							<td>
								<?php
									$suhosin_max_value_length     = ini_get( 'suhosin.post.max_value_length' );
									$recommended_max_value_length = 2000000;
									echo esc_html( $suhosin_max_value_length );
								?>
							</td>
						</tr>
					<?php endif; ?>
				<?php endif; ?>
				<tr>
					<td data-export-label="ZipArchive"><?php esc_html_e( 'ZipArchive:', 'the7mk2' ); ?></td>
					<td><?php echo class_exists( 'ZipArchive' ) ? '<span class="yes">&#10004;</span>' : '<span class="error">No.</span>'; ?></td>
				</tr>
				<tr>
					<td data-export-label="MySQL Version"><?php esc_html_e( 'MySQL Version:', 'the7mk2' ); ?></td>
					<td>
						<?php echo esc_html( $wpdb->db_version() ); ?>
					</td>
				</tr>
				<tr>
					<td data-export-label="Max Upload Size"><?php esc_html_e( 'Max Upload Size:', 'the7mk2' ); ?></td>
					<td><?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></td>
				</tr>
				<tr>
					<td data-export-label="GD Library"><?php esc_html_e( 'GD Library:', 'the7mk2' ); ?></td>
					<td>
						<?php
						$info = esc_attr__( 'Not Installed', 'the7mk2' );
						if ( extension_loaded( 'gd' ) && function_exists( 'gd_info' ) ) {
							$info    = esc_attr__( 'Installed', 'the7mk2' );
							$gd_info = gd_info();
							if ( isset( $gd_info['GD Version'] ) ) {
								$info = $gd_info['GD Version'];
							}
						}
						echo esc_html( $info );
						?>
					</td>
				</tr>
				<tr>
					<td data-export-label="cURL"><?php esc_html_e( 'cURL:', 'the7mk2' ); ?></td>
					<td>
						<?php
						$info = esc_attr__( 'Not Enabled', 'the7mk2' );
						if ( function_exists( 'curl_version' ) ) {
							$curl_info = curl_version();
							if ( $curl_info && isset( $curl_info['version'] ) ) {
								$info = $curl_info['version'];
							}
						}
						echo esc_html( $info );
						?>
					</td>
				</tr>
				</tbody>
			</table>
		</div>
		<div class="the7-column the7-column-double">
			<table class="the7-status-table widefat" cellspacing="0">
				<thead>
				<tr>
					<th colspan="3" data-export-label="The7 Information"><?php esc_html_e( 'The7 Information', 'the7mk2' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
					<td data-export-label="Current Theme Version"><?php esc_html_e( 'Current Theme Version:', 'the7mk2' ); ?></td>
					<td><?php echo esc_html( THE7_VERSION ); ?></td>
				</tr>
				<tr>
					<td data-export-label="Current DB Version"><?php esc_html_e( 'Current DB Version:', 'the7mk2' ); ?></td>
					<td>
						<?php
						if ( version_compare( The7_Install::get_db_version(), PRESSCORE_DB_VERSION, '<' ) ) {
							/* translators: 1: current db version, 2: max db version, */
							echo esc_html( sprintf( __( '%1$s, can be upgraded to %2$s', 'the7mk2' ), The7_Install::get_db_version(), PRESSCORE_DB_VERSION ) );
						} else {
							echo esc_html( The7_Install::get_db_version() );
						}
						?>
					</td>
				</tr>
				<?php if ( dt_the7_core_is_enabled() ) : ?>
				<tr>
					<td data-export-label="Current The7 Core DB Version"><?php esc_html_e( 'Current The7 Core DB Version:', 'the7mk2' ); ?></td>
					<td>
						<?php
						if ( class_exists( 'The7PT_Install' ) && class_exists( 'The7PT_Core' ) ) {
							if ( The7PT_Install::db_update_is_needed() ) {
								/* translators: 1: current the7 core db version, 2: max the7 core db version, */
								echo esc_html( sprintf( __( '%1$s, can be upgraded to %2$s', 'the7mk2' ), The7PT_Install::get_db_version(), The7PT_Core::PLUGIN_DB_VERSION ) );
							} else {
								echo esc_html( The7PT_Install::get_db_version() );
							}
						} else {
							echo esc_html__( 'Unknown', 'the7mk2' );
						}
						?>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<td data-export-label="Installation Path"><?php esc_html_e( 'Installation Path:', 'the7mk2' ); ?></td>
					<td><code><?php echo esc_html( get_template_directory() ); ?></code></td>
				</tr>
				<tr>
					<td data-export-label="The7 Server Available"><?php esc_html_e( 'The7 Server Available:', 'the7mk2' ); ?></td>
					<td>
						<?php
						$the7_server_code = wp_remote_retrieve_response_code( wp_safe_remote_get( 'https://repo.the7.io/theme/info.json', [ 'decompress' => false ] ) );
						if ( $the7_server_code >= 200 && $the7_server_code < 300 ) {
							echo '<span class="yes">&#10004;</span>';
						} else {
							echo '<span class="error">No</span> ';
							echo esc_html(
								sprintf(
									// translators: %s - remote server url.
									__( 'Service is temporary unavailable. Please check back later. If the issue persists, contact your hosting provider and make sure that %s is not blocked.', 'the7mk2' ),
									'https://repo.the7.io/'
								)
							);
						}
						?>
					</td>
				</tr>
				<tr>
					<td data-export-label="Ajax calls with wp_remote_post"><?php esc_html_e( 'Ajax calls with wp_remote_post:', 'the7mk2' ); ?></td>
					<td>
						<?php
						$ajax_url         = esc_url_raw( admin_url( 'admin-ajax.php' ) );
						$the7_server_code = wp_remote_retrieve_response_code( wp_remote_post( $ajax_url, [ 'decompress' => false ] ) );
						if ( $the7_server_code === 400 ) {
							echo '<span class="yes">&#10004;</span>';
						} else {
							echo '<span class="error">&#10006;</span><br> ';
							echo esc_html(
								sprintf(
									// translators: %1$s - response code, %2$s - url.
									__(
										'Seems that your server is blocking connections to your own site (responded with %1$s code). It may break theme db update process and lead to style corruption. Please, make sure that remote requests to %2$s are not blocked.',
										'the7mk2'
									),
									$the7_server_code,
									$ajax_url
								)
							);
						}
						?>
					</td>
				</tr>

				</tbody>
			</table>
			<table class="the7-status-table widefat" cellspacing="0" id="status">
				<thead>
				<tr>
					<th colspan="3" data-export-label="Active Plugins (<?php echo count( (array) get_option( 'active_plugins' ) ); ?>)"><?php esc_html_e( 'Active Plugins', 'the7mk2' ); ?> (<?php echo count( (array) get_option( 'active_plugins' ) ); ?>)</th>
				</tr>
				</thead>
				<tbody>
				<?php
				$act_plugins = (array) get_option( 'active_plugins', [] );
				if ( is_multisite() ) {
					$active_plugins = array_merge( $act_plugins, get_site_option( 'active_sitewide_plugins', [] ) );
				}

				foreach ( $act_plugins as $act_plugin ) {
					$plugin_data = @get_plugin_data( WP_PLUGIN_DIR . '/' . $act_plugin );

					if ( empty( $plugin_data['Name'] ) ) {
						continue;
					}
					?>
					<tr>
						<td>
							<?php
							// Link the plugin name to the plugin url if available.
							if ( empty( $plugin_data['PluginURI'] ) ) {
								echo esc_html( $plugin_data['Name'] );
							} else {
								?>
									<a href="<?php echo esc_url( $plugin_data['PluginURI'] ); ?>" title="<?php esc_html__( 'Visit plugin homepage', 'the7mk2' ); ?>"><?php echo esc_html( $plugin_data['Name'] ); ?></a>
								<?php
							}
							?>
						</td>
						<td>
							<?php
								echo esc_html_x( 'by', 'admin status', 'the7mk2' ) . ' ' . wp_kses_post( $plugin_data['Author'] );
								echo ' &ndash; ' . esc_html( $plugin_data['Version'] );
							?>
						</td>
					</tr>
					<?php
				}
				?>
				</tbody>
			</table>
		</div>
	</div>
</div>
