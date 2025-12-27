<?php
/**
 * The7 dashboard screen.
 *
 * @package The7\Admin
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="the7-dashboard" class="wrap">

	<div class="the7-welcome">
		<div class="the7-logo">
			<div class="the7-version"><?php echo esc_html( sprintf( 'v.%s', THE7_VERSION ) ); ?></div>
		</div>
		<?php
		$name = presscore_theme_is_activated() ? 'registered' : '';
		presscore_get_template_part( 'the7_admin', 'partials/the7-dashboard/welcome-header', $name );
		?>
	</div>

	<?php settings_errors( 'the7_theme_registration' ); ?>

	<div class="the7-postbox">
		<h2 class="the7-with-subtitle"><?php esc_html_e( 'Let’s get some work done!', 'the7mk2' ); ?></h2>
		<p class="the7-subtitle"><?php esc_html_e( 'We have assembled useful links to get you started:', 'the7mk2' ); ?></p>

		<div class="the7-column-container">

			<?php if ( is_super_admin() ) : ?>
			<div class="the7-column" style="width: 40%">
				<?php
				if ( presscore_theme_is_activated() ) {
					require __DIR__ . '/partials/the7-dashboard/theme-de-registration-form.php';
				} else {
					require __DIR__ . '/partials/the7-dashboard/theme-registration-form.php';
				}
				?>
			</div>
			<?php endif; ?>

			<div class="the7-column" style="width: 30%">
				<h3><?php esc_html_e( 'Getting Started', 'the7mk2' ); ?></h3>
				<ul class="the7-links">
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=the7-demo-content' ) ); ?>" class="the7-dashboard-icons-cloud-download"><?php esc_html_e( 'Import a pre-made site', 'the7mk2' ); ?></a>
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=the7-plugins' ) ); ?>" class="the7-dashboard-icons-plug"><?php esc_html_e( 'Install or update plugins', 'the7mk2' ); ?></a>
					</li>
					<li>
						<?php
						$customize_your_site_text = __( 'Customize your site', 'the7mk2' );
						if ( the7_is_elementor_theme_style_enabled() ) {
							?>

							<a href="https://guide.the7.io/elementor/user-guide/theme-style/" class="the7-dashboard-icons-paint-brush" rel="nofollow" target="_blank"><?php echo esc_html( $customize_your_site_text ); ?></a>

							<?php
						} else {
							?>

							<a href="<?php echo esc_url( admin_url( 'admin.php?page=options-framework' ) ); ?>" class="the7-dashboard-icons-paint-brush"><?php echo esc_html( $customize_your_site_text ); ?></a>

							<?php
						}
						?>
					</li>
				</ul>
			</div>

			<div class="the7-column" style="width: 30%">
				<h3><?php esc_html_e( 'Guides & Support', 'the7mk2' ); ?></h3>
				<ul class="the7-links">
					<li><a href="https://guide.the7.io/start/" target="_blank" class="the7-dashboard-icons-rocket"><?php esc_html_e( 'Quick start guide', 'the7mk2' ); ?></a></li>
					<li><a href="https://guide.the7.io/" target="_blank" class="the7-dashboard-icons-graduation-cap"><?php esc_html_e( 'Advanced user guide', 'the7mk2' ); ?></a></li>
					<li><a href="https://support.dream-theme.com" target="_blank" class="the7-dashboard-icons-life-bouy"><?php esc_html_e( 'Support portal', 'the7mk2' ); ?></a></li>
				</ul>
			</div>
		</div>
	</div>

	<div class="the7-postbox">
		<h2><?php esc_html_e( 'System Status', 'the7mk2' ); ?></h2>
		<table class="the7-system-status">
			<tr>
				<td><?php esc_html_e( 'Install Location:', 'the7mk2' ); ?></td>
				<td>
					<?php
					$template_name = 'dt-the7';
					if ( get_template() === $template_name ) {
						?>

						<code class="status-good"><?php esc_html_e( 'Standard', 'the7mk2' ); ?></code>

						<?php
					} else {
						?>

						<code class="status-bad"><?php esc_html_e( 'Non-standard', 'the7mk2' ); ?></code>

						<?php
						printf(
							// translators: %s - theme folder name.
							esc_html__( 'Using The7 from non-standard install location or having a different directory name could lead to issues in receiving and installing updates. Please make sure that theme folder name is %s, without spaces.', 'the7mk2' ),
							'<strong>' . esc_html( $template_name ) . '</strong>'
						);
					}
					?>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'File System Accessible:', 'the7mk2' ); ?></td>
				<td>
					<?php
					global $wp_filesystem;

					if ( $wp_filesystem || WP_Filesystem() ) {
						?>

						<code class="status-good"><?php esc_html_e( 'Yes', 'the7mk2' ); ?></code>

						<?php
					} else {
						?>

						<code class="status-bad"><?php esc_html_e( 'No', 'the7mk2' ); ?></code>

						<?php
						printf(
							// translators: %1$s - config file, %2$s - code, %3$s - before text.
							esc_html__( 'Theme has no direct access to the file system. Therefore plugins and pre-made websites installation is not possible. Please try to insert the following code in %1$s: %2$s before %3$s', 'the7mk2' ),
							'<code>wp-config.php</code>',
							'<code>define( "FS_METHOD", "direct" );</code>',
							'<code>/* That\'s all, stop editing! Happy blogging. */</code>.'
						);
					}
					?>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Uploads Folder Writable:', 'the7mk2' ); ?></td>
				<td>
				<?php
				$wp_uploads = wp_get_upload_dir();
				if ( wp_is_writable( $wp_uploads['basedir'] . '/' ) ) {
					?>

					<code class="status-good"><?php esc_html_e( 'Yes', 'the7mk2' ); ?></code>

					<?php
				} else {
					?>

					<code class="status-bad"><?php esc_html_e( 'No', 'the7mk2' ); ?></code><?php esc_html_e( 'Uploads folder must be writable to allow WordPress function properly.', 'the7mk2' ); ?>
					<br>
					<span class="the7-tip">
					<?php
						printf(
							// translators: %s - link to wp codex article.
							esc_html__( 'See %s or contact your hosting provider.', 'the7mk2' ),
							'<a href="https://developer.wordpress.org/advanced-administration/server/file-permissions/" target="_blank" rel="noopener noreferrer">changing file permissions</a>'
						);
					?>
					</span>

					<?php
				}
				?>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'ZipArchive Support:', 'the7mk2' ); ?></td>
				<td>
				<?php
				if ( class_exists( 'ZipArchive' ) ) {
					?>

					<code class="status-good"><?php esc_html_e( 'Yes', 'the7mk2' ); ?></code>

					<?php
				} else {
					?>

					<code class="status-bad"><?php esc_html_e( 'No', 'the7mk2' ); ?></code><?php esc_html_e( 'ZipArchive is required for Icons Manager to work properly.', 'the7mk2' ); ?>
					<br>
					<span class="the7-tip"><?php esc_html_e( 'You may want to contact your hosting provider.', 'the7mk2' ); ?></span>

					<?php
				}
				?>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'PHP Version:', 'the7mk2' ); ?></td>
				<td>
				<?php
				$php_version = PHP_VERSION;
				if ( version_compare( '7.2.0', $php_version, '>' ) ) {
					?>

					<code class="status-okay"><?php echo esc_html( $php_version ); ?></code>

					<?php
					printf(
						// translators: %s - recommended php version.
						esc_html__( 'Current version is sufficient. However %s or greater is recommended to improve the performance.', 'the7mk2' ),
						'<strong>v.7.2.0</strong>'
					);
				} else {
					?>

					<code class="status-good"><?php echo esc_html( $php_version ); ?></code>

					<?php
					esc_html_e( 'Current version is sufficient.', 'the7mk2' );
				}
				?>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'PHP Max Input Vars:', 'the7mk2' ); ?></td>
				<td>
				<?php
				$max_input_vars = ini_get( 'max_input_vars' );
				if ( $max_input_vars < 1000 ) {
					?>

					<code class="status-bad"><?php echo esc_html( $max_input_vars ); ?></code>

					<?php
					printf(
						// translators: %1$s - minimum value, %2$s - recommended value, %3$s - more value.
						esc_html__( 'Minimum value is %1$s. %2$s is recommended. %3$s or more may be required if lots of plugins are in use and/or you have a large amount of menu items.', 'the7mk2' ),
						'<strong>1000</strong>',
						'<strong>2000</strong>',
						'<strong>3000</strong>'
					);
				} elseif ( $max_input_vars < 2000 ) {
					?>

					<code class="status-okay"><?php echo esc_html( $max_input_vars ); ?></code>

					<?php
					printf(
						// translators: %1$s - recommended value, %2$s - more value.
						esc_html__( 'Current limit is sufficient for most tasks. %1$s is recommended. %2$s or more may be required if lots of plugins are in use and/or you have a large amount of menu items.', 'the7mk2' ),
						'<strong>2000</strong>',
						'<strong>3000</strong>'
					);
				} elseif ( $max_input_vars < 3000 ) {
					?>

					<code class="status-good"><?php echo esc_html( $max_input_vars ); ?></code>

					<?php
					printf(
						// translators: %s - more value.
						esc_html__( 'Current limit is sufficient. However, up to %s or more may be required if lots of plugins are in use and/or you have a large amount of menu items.', 'the7mk2' ),
						'<strong>3000</strong>'
					);
				} else {
					?>

					<code class="status-good"><?php echo esc_html( $max_input_vars ); ?></code>

					<?php
					esc_html_e( 'Current limit is sufficient.', 'the7mk2' );
				}
				?>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'WP Memory Limit:', 'the7mk2' ); ?></td>
				<td>
				<?php
				$memory    = presscore_get_wp_memory_limit();
				$hr_memory = size_format( $memory );

				$tip  = '<br><span class="the7-tip">';
				$tip .= sprintf(
					// translators: %s - wp codex article link.
					esc_html__( 'See %s or contact your hosting provider.', 'the7mk2' ),
					'<a href="https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#increasing-memory-allocated-to-php" target="_blank" rel="noopener noreferrer">increasing memory allocated to PHP</a>'
				);
				$tip .= '</span>';

				if ( $memory < 67108864 ) {
					?>

					<code class="status-bad"><?php echo esc_html( $hr_memory ); ?></code>

					<?php
					printf(
						// translators: %1$s - minimum value, %2$s - recommended value, %3$s - more value.
						esc_html__( 'Minimum value is %1$s. %2$s is recommended. %3$s or more may be required if lots of plugins are in use and/or you want to install the Main Demo.', 'the7mk2' ),
						'<strong>64 MB</strong>',
						'<strong>128 MB</strong>',
						'<strong>256 MB</strong>'
					);
					echo $tip; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} elseif ( $memory < 134217728 ) {
					?>

					<code class="status-okay"><?php echo esc_html( $hr_memory ); ?></code>

					<?php
					printf(
						// translators: %1$s - recommended value, %2$s - more value.
						esc_html__( 'Current memory limit is sufficient for most tasks. However, recommended value is %1$s. %2$s or more may be required if lots of plugins are in use and/or you want to install the Main Demo.', 'the7mk2' ),
						'<strong>128 MB</strong>',
						'<strong>256 MB</strong>'
					);
					echo $tip; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} elseif ( $memory < 268435456 ) {
					?>

					<code class="status-good"><?php echo esc_html( $hr_memory ); ?></code>

					<?php
					printf(
						// translators: %s - more value.
						esc_html__( 'Current memory limit is sufficient for most tasks. However, %s or more may be required if lots of plugins are in use and/or you want to install the Main Demo.', 'the7mk2' ),
						'<strong>256 MB</strong>'
					);
					echo $tip; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					?>

					<code class="status-good"><?php echo esc_html( $hr_memory ); ?></code>

					<?php
					esc_html_e( 'Current memory limit is sufficient.', 'the7mk2' );
				}
				?>
				</td>
			</tr>
			<?php if ( function_exists( 'ini_get' ) ) : ?>
				<tr>
					<td><?php esc_html_e( 'PHP Time Limit:', 'the7mk2' ); ?></td>
					<td>
						<?php
						$time_limit = (int) ini_get( 'max_execution_time' );

						$tip  = '<br><span class="the7-tip">';
						$tip .= sprintf(
							// translators: %s - wp codex article link.
							esc_html__( 'See %s or contact your hosting provider.', 'the7mk2' ),
							'<a href="https://developer.wordpress.org/advanced-administration/wordpress/common-errors/#php-errors" target="_blank" rel="noopener noreferrer">increasing max PHP execution time</a>'
						);
						$tip .= '</span>';

						if ( $time_limit === 0 ) {
							?>

							<code class="status-good">unlimited</code>

							<?php
							esc_html_e( 'Current time limit is sufficient.', 'the7mk2' );
						} elseif ( $time_limit < 30 ) {
							?>

							<code class="status-bad"><?php echo esc_html( $time_limit ); ?></code>

							<?php
							printf(
								// translators: %1$s - minimum value, %2$s - recommended value, %3$s - more value.
								esc_html__( 'Minimum value is %1$s. %2$s is recommended. Up to %3$s seconds may be required to install the Main Demo.', 'the7mk2' ),
								'<strong>30</strong>',
								'<strong>60</strong>',
								'<strong>300</strong>'
							);
							echo $tip; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} elseif ( $time_limit < 60 ) {
							?>

							<code class="status-okay"><?php echo esc_html( $time_limit ); ?></code>

							<?php
							printf(
								// translators: %1$s - recommended value, %2$s - more value.
								esc_html__( 'Current time limit is sufficient for most tasks. However, recommended value is %1$s. Up to %2$s seconds may be required to install the Main Demo.', 'the7mk2' ),
								'<strong>60</strong>',
								'<strong>300</strong>'
							);
							echo $tip; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} elseif ( $time_limit < 300 ) {
							?>

							<code class="status-good"><?php echo esc_html( $time_limit ); ?></code>

							<?php
							printf(
								// translators: %s - more value.
								esc_html__( 'Current time limit is sufficient. However, up to %s seconds may be required to install the Main Demo.', 'the7mk2' ),
								'<strong>300</strong>'
							);
							echo $tip; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} elseif ( $time_limit > 300 ) {
							?>

							<code class="status-good"><?php echo esc_html( $time_limit ); ?></code>

							<?php
							esc_html_e( 'Current time limit is sufficient.', 'the7mk2' );
						}
						?>
					</td>
				</tr>
			<?php endif; ?>

			<?php
			if ( class_exists( 'WP_Site_Health' ) && method_exists( 'WP_Site_Health', 'get_test_php_extensions' ) ) {
				$result = WP_Site_Health::get_instance()->get_test_php_extensions();
				if ( isset( $result['status'] ) && $result['status'] !== 'good' ) {
					?>

						<style>
							.the7-php-modules-message p {
								display: none;
							}

							#the7-dashboard .the7-php-modules-message li {
								margin: 7px 0;
							}

							.the7-php-modules-message .warning {
								color: orange;
							}

							.the7-php-modules-message .error {
								color: red;
							}
						</style>

					<tr>
						<td>
							<?php esc_html_e( 'PHP Modules:', 'the7mk2' ); ?>
						</td>
						<td class="the7-php-modules-message">
							<?php
							$class = 'status-okay';
							if ( $result['status'] === 'critical' ) {
								$class = 'status-bad';
							}
							echo '<code class="' . esc_attr( $class ) . '">' . esc_html( $result['status'] ) . '</code> ' . esc_html( $result['label'] . '.' );
							echo wp_kses_post( str_replace( [ 'warning', 'error' ], [ 'warning dashicons-info', 'error dashicons-warning' ], $result['description'] ) );
							?>
						</td>
					</tr>

					<?php
				}
			}
			?>

		</table>
	</div>
	<?php require __DIR__ . '/partials/the7-dashboard/settings.php'; ?>
</div>
