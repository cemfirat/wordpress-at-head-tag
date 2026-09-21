<?php
/** Run with wp eval-file tests/wordpress.php after activating the packaged plugin. */
if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

function aht_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	WP_CLI::log( 'PASS: ' . $message );
}

$basename = plugin_basename( AT_HEAD_TAG_FILE );
wp_set_current_user( 1 );
at_head_tag_register_settings();
$snippet = "<meta name=\"at-head-tag-test\" content=\"100%20\">\n<script>window.aht = '$1 \\path';</script>";
update_option( AT_HEAD_TAG_OPTION, $snippet );
update_option( AT_HEAD_TAG_PRIORITY_OPTION, 777 );
update_option( AT_HEAD_TAG_ENABLED_OPTION, 1 );
aht_assert( $snippet === get_option( AT_HEAD_TAG_OPTION ), 'A trusted administrator can save source code exactly.' );
aht_assert( 777 === (int) get_option( AT_HEAD_TAG_PRIORITY_OPTION ), 'WordPress stores the configured hook priority.' );
aht_assert( (bool) get_option( AT_HEAD_TAG_ENABLED_OPTION ), 'WordPress stores the output switch.' );

$editor = wp_insert_user( array( 'user_login' => 'aht_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
aht_assert( ! is_wp_error( $editor ), 'Create a restricted test user.' );
wp_set_current_user( $editor );
update_option( AT_HEAD_TAG_OPTION, '<script>unauthorized</script>' );
aht_assert( $snippet === get_option( AT_HEAD_TAG_OPTION ), 'Restricted users cannot replace trusted raw code.' );
wp_set_current_user( 1 );

$release = array(
	'tag_name' => 'v1.5.0', 'draft' => false, 'prerelease' => false,
	'body' => "Requires WordPress: 5.8\nRequires PHP: 7.4\nIntegration fixture",
	'assets' => array( array(
		'name' => 'at-head-tag.zip', 'state' => 'uploaded', 'size' => 100,
		'browser_download_url' => At_Head_Tag_Updater::REPOSITORY . '/releases/download/v1.5.0/at-head-tag.zip',
	) ),
);
$GLOBALS['aht_release'] = $release;
add_filter( 'pre_http_request', function ( $preempt, $args, $url ) {
	if ( At_Head_Tag_Updater::API_URL === $url ) {
		return array( 'headers' => array(), 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $GLOBALS['aht_release'] ) );
	}
	if ( false !== strpos( $url, 'api.wordpress.org/plugins/update-check/' ) ) {
		return array( 'headers' => array(), 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'plugins' => array(), 'no_update' => array(), 'translations' => array() ) ) );
	}
	return $preempt;
}, 10, 3 );

$refresh = ( new ReflectionClass( 'At_Head_Tag_Updater' ) )->newInstanceWithoutConstructor();
set_site_transient( At_Head_Tag_Updater::CACHE_KEY, array( 'version' => AT_HEAD_TAG_VERSION ), HOUR_IN_SECONDS );
set_site_transient( 'update_plugins', (object) array( 'last_checked' => time(), 'checked' => array() ), HOUR_IN_SECONDS );
$_GET['force-check'] = '1';
$refresh->maybe_force_check();
unset( $_GET['force-check'] );
aht_assert( false === get_site_transient( At_Head_Tag_Updater::CACHE_KEY ), 'Check Again clears the plugin release cache.' );
aht_assert( false === get_site_transient( 'update_plugins' ), 'Check Again clears WordPress plugin-update state.' );

delete_site_transient( At_Head_Tag_Updater::CACHE_KEY );
delete_site_transient( 'update_plugins' );
wp_update_plugins();
$updates = get_site_transient( 'update_plugins' );
aht_assert( isset( $updates->response[ $basename ] ), 'WordPress discovers a newer GitHub release.' );
aht_assert( '1.5.0' === $updates->response[ $basename ]->new_version, 'WordPress receives the advertised release version.' );

$GLOBALS['aht_release']['tag_name'] = 'v' . AT_HEAD_TAG_VERSION;
$GLOBALS['aht_release']['assets'][0]['browser_download_url'] = At_Head_Tag_Updater::REPOSITORY . '/releases/download/v' . AT_HEAD_TAG_VERSION . '/at-head-tag.zip';
delete_site_transient( At_Head_Tag_Updater::CACHE_KEY );
delete_site_transient( 'update_plugins' );
wp_update_plugins();
$updates = get_site_transient( 'update_plugins' );
aht_assert( ! isset( $updates->response[ $basename ] ) && isset( $updates->no_update[ $basename ] ), 'An equal release does not produce an update notification.' );

$updates->response[ $basename ] = (object) array(
	'slug' => 'at-head-tag', 'plugin' => $basename, 'new_version' => '1.5.0',
	'package' => getenv( 'AHT_PACKAGE' ), 'url' => At_Head_Tag_Updater::REPOSITORY,
);
set_site_transient( 'update_plugins', $updates );
$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
$result = $upgrader->bulk_upgrade( array( $basename ), array( 'clear_update_cache' => false ) );
aht_assert( is_array( $result ) && isset( $result[ $basename ] ) && is_array( $result[ $basename ] ), 'The real WordPress ZIP upgrader succeeds.' );
aht_assert( file_exists( WP_PLUGIN_DIR . '/' . $basename ), 'The existing plugin directory is preserved.' );
aht_assert( is_plugin_active( $basename ), 'The plugin remains active after a bulk update.' );
aht_assert( $snippet === get_option( AT_HEAD_TAG_OPTION ), 'The saved head snippet survives an update.' );
aht_assert( 777 === (int) get_option( AT_HEAD_TAG_PRIORITY_OPTION ), 'The hook priority survives an update.' );
aht_assert( (bool) get_option( AT_HEAD_TAG_ENABLED_OPTION ), 'The output switch survives an update.' );
WP_CLI::success( 'At Head Tag WordPress integration checks passed on ' . get_bloginfo( 'version' ) . '.' );
