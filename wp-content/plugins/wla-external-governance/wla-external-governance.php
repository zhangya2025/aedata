<?php
/**
 * Plugin Name: WLA External Governance
 * Description: External link governance with whitelisting, logging, and optional mirroring/localization.
 * Version: 1.0.0
 * Author: WLA
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class WLA_External_Governance {
const OPTION_KEY = 'wla_external_governance_settings';
const LOG_FILE   = 'external-governance.log';
const CACHE_DIR  = 'wla-external-governance';

/**
 * @var WLA_External_Governance
 */
private static $instance;

public static function instance() {
if ( ! self::$instance ) {
self::$instance = new self();
}
return self::$instance;
}

private function __construct() {
add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
add_action( 'admin_init', [ $this, 'handle_admin_actions' ] );
add_filter( 'script_loader_src', [ $this, 'filter_asset_src' ], 10, 2 );
add_filter( 'style_loader_src', [ $this, 'filter_asset_src' ], 10, 2 );
}

public function get_settings() {
$defaults = [
'mode'      => 'log',
'whitelist' => [],
'mirrors'   => [],
];
$settings = get_option( self::OPTION_KEY, [] );
if ( empty( $settings['whitelist'] ) ) {
$host                 = wp_parse_url( home_url(), PHP_URL_HOST );
$settings['whitelist'] = $host ? [ $host ] : [];
}
return wp_parse_args( $settings, $defaults );
}

public function save_settings( $settings ) {
update_option( self::OPTION_KEY, $settings );
}

public function register_admin_page() {
add_management_page(
__( 'External Governance', 'wla' ),
__( 'External Governance', 'wla' ),
'manage_options',
'wla-external-governance',
[ $this, 'render_admin_page' ]
);
}

public function render_admin_page() {
if ( ! current_user_can( 'manage_options' ) ) {
return;
}
$settings   = $this->get_settings();
$whitelist  = implode( "\n", $settings['whitelist'] );
$mode       = $settings['mode'];
$mirrors    = $settings['mirrors'];
$observed   = $this->get_observed_domains();
$mirror_log = isset( $_GET['wla_mirror_status'] ) ? sanitize_text_field( wp_unslash( $_GET['wla_mirror_status'] ) ) : '';
?>
<div class="wrap">
<h1><?php esc_html_e( 'External Governance', 'wla' ); ?></h1>
<p><?php esc_html_e( 'Whitelist external domains, choose handling mode, and optionally mirror JS/CSS assets locally.', 'wla' ); ?></p>
<form method="post">
<?php wp_nonce_field( 'wla_external_governance_save', 'wla_external_governance_nonce' ); ?>
<table class="form-table" role="presentation">
<tr>
<th scope="row"><label for="wla_mode"><?php esc_html_e( 'Mode', 'wla' ); ?></label></th>
<td>
<select id="wla_mode" name="wla_mode">
<option value="off" <?php selected( $mode, 'off' ); ?>><?php esc_html_e( 'Off', 'wla' ); ?></option>
<option value="log" <?php selected( $mode, 'log' ); ?>><?php esc_html_e( 'Log only', 'wla' ); ?></option>
<option value="enforce" <?php selected( $mode, 'enforce' ); ?>><?php esc_html_e( 'Enforce with safe fallbacks', 'wla' ); ?></option>
</select>
<p class="description"><?php esc_html_e( 'Enforce mode replaces known non-critical external assets or mirrored files with local copies; otherwise it logs only to remain fail-safe.', 'wla' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><label for="wla_whitelist"><?php esc_html_e( 'Whitelisted domains', 'wla' ); ?></label></th>
<td>
<textarea id="wla_whitelist" name="wla_whitelist" rows="5" cols="50" class="large-text code"><?php echo esc_textarea( $whitelist ); ?></textarea>
<p class="description"><?php esc_html_e( 'One domain per line. Site host is included by default.', 'wla' ); ?></p>
</td>
</tr>
</table>
<?php submit_button( __( 'Save settings', 'wla' ) ); ?>
</form>

<h2><?php esc_html_e( 'Mirrored assets (JS/CSS)', 'wla' ); ?></h2>
<form method="post">
<?php wp_nonce_field( 'wla_external_governance_mirror', 'wla_external_governance_mirror_nonce' ); ?>
<table class="form-table" role="presentation">
<tr>
<th scope="row"><label for="wla_new_mirror"><?php esc_html_e( 'Add remote asset URL', 'wla' ); ?></label></th>
<td>
<input type="url" class="regular-text" name="wla_new_mirror" id="wla_new_mirror" placeholder="https://example.com/script.js" />
<?php submit_button( __( 'Add & Sync', 'wla' ), 'secondary', 'wla_add_mirror', false ); ?>
<?php submit_button( __( 'Resync all', 'wla' ), 'secondary', 'wla_resync', false ); ?>
<?php submit_button( __( 'Clear cache', 'wla' ), 'delete', 'wla_clear_cache', false ); ?>
<p class="description"><?php esc_html_e( 'Only JS/CSS are supported. Downloads are stored under uploads/wla-external-governance/.', 'wla' ); ?></p>
</td>
</tr>
</table>
</form>
<?php if ( $mirror_log ) : ?>
<div class="notice notice-success"><p><?php echo esc_html( $mirror_log ); ?></p></div>
<?php endif; ?>
<?php $this->render_mirror_table( $mirrors ); ?>

<h2><?php esc_html_e( 'Observed external domains (from wla-observer)', 'wla' ); ?></h2>
<?php if ( ! empty( $observed ) ) : ?>
<table class="widefat">
<thead><tr><th><?php esc_html_e( 'Domain', 'wla' ); ?></th><th><?php esc_html_e( 'Hits', 'wla' ); ?></th></tr></thead>
<tbody>
<?php foreach ( $observed as $domain => $count ) : ?>
<tr><td><?php echo esc_html( $domain ); ?></td><td><?php echo esc_html( $count ); ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else : ?>
<p><?php esc_html_e( 'No observer log entries found.', 'wla' ); ?></p>
<?php endif; ?>
</div>
<?php
}

private function render_mirror_table( $mirrors ) {
if ( empty( $mirrors ) ) {
echo '<p>' . esc_html__( 'No mirrored assets yet.', 'wla' ) . '</p>';
return;
}
$uploads = wp_upload_dir();
?>
<table class="widefat">
<thead><tr><th><?php esc_html_e( 'Remote URL', 'wla' ); ?></th><th><?php esc_html_e( 'Local file', 'wla' ); ?></th><th><?php esc_html_e( 'Type', 'wla' ); ?></th><th><?php esc_html_e( 'Last sync', 'wla' ); ?></th></tr></thead>
<tbody>
<?php foreach ( $mirrors as $remote => $data ) :
$filename = isset( $data['file'] ) ? $data['file'] : '';
$type     = isset( $data['type'] ) ? $data['type'] : '';
$time     = isset( $data['synced'] ) ? gmdate( 'Y-m-d H:i:s', (int) $data['synced'] ) : '—';
?>
<tr>
<td><?php echo esc_url( $remote ); ?></td>
<td><?php echo esc_html( $filename ? trailingslashit( $uploads['basedir'] ) . self::CACHE_DIR . '/' . $filename : '' ); ?></td>
<td><?php echo esc_html( strtoupper( $type ) ); ?></td>
<td><?php echo esc_html( $time ); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php
}

public function handle_admin_actions() {
if ( ! current_user_can( 'manage_options' ) ) {
return;
}

if ( isset( $_POST['wla_external_governance_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['wla_external_governance_nonce'] ), 'wla_external_governance_save' ) ) {
$settings              = $this->get_settings();
$settings['mode']      = isset( $_POST['wla_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['wla_mode'] ) ) : 'log';
$whitelist_raw         = isset( $_POST['wla_whitelist'] ) ? wp_unslash( $_POST['wla_whitelist'] ) : '';
$settings['whitelist'] = array_filter( array_map( [ $this, 'sanitize_domain' ], explode( "\n", $whitelist_raw ) ) );
$settings['mode']      = in_array( $settings['mode'], [ 'off', 'log', 'enforce' ], true ) ? $settings['mode'] : 'log';
$this->save_settings( $settings );
add_settings_error( 'wla_external_governance', 'updated', __( 'Settings saved.', 'wla' ), 'updated' );
}

if ( isset( $_POST['wla_external_governance_mirror_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['wla_external_governance_mirror_nonce'] ), 'wla_external_governance_mirror' ) ) {
$settings = $this->get_settings();
if ( isset( $_POST['wla_add_mirror'] ) && ! empty( $_POST['wla_new_mirror'] ) ) {
$remote = esc_url_raw( wp_unslash( $_POST['wla_new_mirror'] ) );
if ( $remote && $this->is_supported_asset( $remote ) ) {
$settings['mirrors'][ $remote ] = [ 'type' => $this->guess_type( $remote ) ];
$this->save_settings( $settings );
$this->sync_asset( $remote, $settings['mirrors'][ $remote ] );
wp_safe_redirect( add_query_arg( 'wla_mirror_status', rawurlencode( __( 'Asset added and synced.', 'wla' ) ), admin_url( 'tools.php?page=wla-external-governance' ) ) );
exit;
}
}
if ( isset( $_POST['wla_resync'] ) ) {
foreach ( $settings['mirrors'] as $remote => $data ) {
$this->sync_asset( $remote, $data );
}
wp_safe_redirect( add_query_arg( 'wla_mirror_status', rawurlencode( __( 'Resync triggered.', 'wla' ) ), admin_url( 'tools.php?page=wla-external-governance' ) ) );
exit;
}
if ( isset( $_POST['wla_clear_cache'] ) ) {
$this->clear_cache();
wp_safe_redirect( add_query_arg( 'wla_mirror_status', rawurlencode( __( 'Cache cleared.', 'wla' ) ), admin_url( 'tools.php?page=wla-external-governance' ) ) );
exit;
}
}
}

private function sanitize_domain( $domain ) {
$domain = trim( $domain );
$domain = preg_replace( '/^https?:\/\//i', '', $domain );
return $domain;
}

private function is_supported_asset( $url ) {
$ext = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
return in_array( $ext, [ 'js', 'css' ], true );
}

private function guess_type( $url ) {
$ext = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
return in_array( $ext, [ 'js', 'css' ], true ) ? $ext : 'js';
}

private function ensure_cache_dir() {
$uploads = wp_upload_dir();
$dir     = trailingslashit( $uploads['basedir'] ) . self::CACHE_DIR;
if ( ! wp_mkdir_p( $dir ) ) {
return false;
}
return $dir;
}

private function sync_asset( $remote, $data ) {
$dir = $this->ensure_cache_dir();
if ( ! $dir || ! is_writable( $dir ) ) {
return false;
}
$hash     = sha1( $remote );
$ext      = isset( $data['type'] ) ? $data['type'] : $this->guess_type( $remote );
$filename = $hash . '.' . $ext;

$response = wp_remote_get( $remote, [ 'timeout' => 8, 'redirection' => 2 ] );
if ( is_wp_error( $response ) ) {
return false;
}
$code = wp_remote_retrieve_response_code( $response );
$body = wp_remote_retrieve_body( $response );
if ( 200 !== (int) $code || empty( $body ) ) {
return false;
}

$filesystem = $this->get_filesystem();
$written    = false;
$path       = trailingslashit( $dir ) . $filename;
if ( $filesystem ) {
$written = $filesystem->put_contents( $path, $body, FS_CHMOD_FILE );
}
if ( ! $written ) {
$written = (bool) file_put_contents( $path, $body );
}
if ( $written ) {
$settings                         = $this->get_settings();
$settings['mirrors'][ $remote ]['file']   = $filename;
$settings['mirrors'][ $remote ]['type']   = $ext;
$settings['mirrors'][ $remote ]['synced'] = time();
$this->save_settings( $settings );
return true;
}
return false;
}

private function clear_cache() {
$dir = $this->ensure_cache_dir();
if ( ! $dir || ! is_dir( $dir ) ) {
return;
}
foreach ( glob( trailingslashit( $dir ) . '*' ) as $file ) {
if ( is_file( $file ) ) {
unlink( $file );
}
}
}

private function get_filesystem() {
global $wp_filesystem;
if ( ! $wp_filesystem ) {
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();
}
return $wp_filesystem;
}

public function filter_asset_src( $src, $handle ) {
$settings = $this->get_settings();
if ( 'off' === $settings['mode'] ) {
return $src;
}
$host = wp_parse_url( $src, PHP_URL_HOST );
if ( ! $host ) {
return $src;
}

if ( $this->is_whitelisted( $host, $settings['whitelist'] ) ) {
return $src;
}

$this->log_non_whitelisted( $src, $handle, $settings['mode'] );

if ( 'enforce' !== $settings['mode'] ) {
return $src;
}

$mirrors = isset( $settings['mirrors'] ) ? $settings['mirrors'] : [];
$local   = $this->get_mirrored_url( $src, $mirrors );
if ( $local ) {
return $local;
}

if ( $this->is_non_critical( $host ) ) {
return $this->get_blank_data_url( $src );
}

return $src;
}

private function is_whitelisted( $host, $whitelist ) {
return in_array( $host, $whitelist, true );
}

private function get_mirrored_url( $src, $mirrors ) {
$uploads = wp_upload_dir();
foreach ( $mirrors as $remote => $data ) {
if ( 0 === strpos( $src, $remote ) && ! empty( $data['file'] ) ) {
return trailingslashit( $uploads['baseurl'] ) . self::CACHE_DIR . '/' . $data['file'];
}
}
return '';
}

private function is_non_critical( $host ) {
$noncritical = [
'www.google-analytics.com',
'googletagmanager.com',
'connect.facebook.net',
];
foreach ( $noncritical as $domain ) {
if ( false !== stripos( $host, $domain ) ) {
return true;
}
}
return false;
}

private function get_blank_data_url( $src ) {
$ext = strtolower( pathinfo( wp_parse_url( $src, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
if ( 'css' === $ext ) {
return 'data:text/css,' . rawurlencode( '/* blocked by WLA External Governance */' );
}
return 'data:text/javascript,' . rawurlencode( '/* blocked by WLA External Governance */' );
}

private function log_non_whitelisted( $src, $handle, $mode ) {
$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'wla-logs/';
if ( ! wp_mkdir_p( $dir ) ) {
return;
}
$line = sprintf(
"[%s] mode=%s handle=%s src=%s\n",
gmdate( 'c' ),
$mode,
$handle,
$src
);
$path = $dir . self::LOG_FILE;
file_put_contents( $path, $line, FILE_APPEND );
}

private function get_observed_domains() {
$uploads = wp_upload_dir();
$path    = trailingslashit( $uploads['basedir'] ) . 'wla-logs/observe.log';
if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
return [];
}
$contents = file_get_contents( $path );
if ( ! $contents ) {
return [];
}
preg_match_all( '#https?://([^/\s]+)#i', $contents, $matches );
if ( empty( $matches[1] ) ) {
return [];
}
$counts = [];
foreach ( $matches[1] as $host ) {
$counts[ $host ] = isset( $counts[ $host ] ) ? $counts[ $host ] + 1 : 1;
}
arsort( $counts );
return array_slice( $counts, 0, 20, true );
}
}

WLA_External_Governance::instance();
