<?php
/** Dependency-free regression tests. Run: php tests/run.php */
error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new RuntimeException( "$message in $file:$line" );
} );
define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['caps'] = array( 'manage_options' => true, 'unfiltered_html' => true, 'update_plugins' => true );
$GLOBALS['options'] = array(
	'at_head_tag_content'  => '<meta name="existing" content="1">',
	'at_head_tag_priority' => 999,
	'at_head_tag_enabled'  => 1,
);
$GLOBALS['cache'] = array();
$GLOBALS['actions'] = array();
$GLOBALS['settings_errors'] = array();
function add_action( ...$args ) { $GLOBALS['actions'][] = $args; }
function add_filter( ...$args ) {}
function register_activation_hook( ...$args ) {}
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function current_user_can( $cap ) { return ! empty( $GLOBALS['caps'][ $cap ] ); }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function add_option( $key, $value ) { if ( ! array_key_exists( $key, $GLOBALS['options'] ) ) { $GLOBALS['options'][ $key ] = $value; } return true; }
function wp_check_invalid_utf8( $value ) { return $value; }
function add_settings_error( ...$args ) { $GLOBALS['settings_errors'][] = $args; }
function get_site_transient( $key ) { return array_key_exists( $key, $GLOBALS['cache'] ) ? $GLOBALS['cache'][ $key ] : false; }
function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['cache'][ $key ] = $value; return true; }
function delete_site_transient( $key ) { unset( $GLOBALS['cache'][ $key ] ); return true; }
function wp_remote_get( $url, $args ) { return $GLOBALS['response']; }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
class WP_Error { public function __construct( ...$args ) {} }
require dirname( __DIR__ ) . '/at-head-tag.php';

$count = 0;
function same( $expected, $actual, $label ) {
	global $count;
	++$count;
	if ( $expected !== $actual ) {
		throw new RuntimeException( $label . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) );
	}
}
function yes( $actual, $label ) { same( true, (bool) $actual, $label ); }

same( 999, at_head_tag_sanitize_priority( 999 ), 'Keep valid priority' );
same( 0, at_head_tag_sanitize_priority( 0 ), 'Allow priority zero' );
same( 999, at_head_tag_sanitize_priority( 10000 ), 'Reject excessive priority' );
same( true, at_head_tag_sanitize_enabled( '1' ), 'Enable boolean option' );
same( false, at_head_tag_sanitize_enabled( '0' ), 'Disable boolean option' );

$raw = "<script>const value = '$1 \\path';</script>\n<meta name=\"x\" content=\"100%20\">";
same( $raw, at_head_tag_sanitize_code( $raw ), 'Authorized administrators keep raw head code exactly' );
same( 'ab', at_head_tag_sanitize_code( "a\0b" ), 'NUL bytes are removed' );
$GLOBALS['caps']['unfiltered_html'] = false;
same( $GLOBALS['options']['at_head_tag_content'], at_head_tag_sanitize_code( '<script>blocked</script>' ), 'Restricted administrators cannot replace raw code' );
$GLOBALS['caps']['unfiltered_html'] = true;
$GLOBALS['caps']['manage_options'] = false;
same( $GLOBALS['options']['at_head_tag_content'], at_head_tag_sanitize_code( '<style>blocked</style>' ), 'Site management capability is also required' );
$GLOBALS['caps']['manage_options'] = true;

$lint = at_head_tag_lint( '<div>bad</div><link rel="preload" href="http://example.com/x.css"><script src="https://example.com/x.js"></script>' );
yes( ! empty( $lint['warnings'] ), 'Lint reports head structure or resource warnings' );
yes( ! empty( $lint['notices'] ), 'Lint reports performance notes' );
same( "<!-- at-head-tag START -->\n<meta name=\"x\">\n<!-- at-head-tag END -->", at_head_tag_render_preview_block( '<meta name="x">' ), 'Preview uses clear source markers' );

$GLOBALS['options']['at_head_tag_content'] = '<meta name="integration" content="yes">';
$GLOBALS['options']['at_head_tag_enabled'] = 1;
ob_start();
at_head_tag_inject_code();
$out = ob_get_clean();
yes( false !== strpos( $out, $GLOBALS['options']['at_head_tag_content'] ), 'Enabled output prints the exact saved snippet' );
$GLOBALS['options']['at_head_tag_enabled'] = 0;
ob_start();
at_head_tag_inject_code();
same( '', ob_get_clean(), 'Disabled output prints nothing' );

$release = array(
	'tag_name' => 'v1.5.0',
	'draft' => false,
	'prerelease' => false,
	'body' => "Requires WordPress: 5.8\nRequires PHP: 7.4\n\nRelease notes",
	'assets' => array(
		array(
			'name' => 'at-head-tag.zip',
			'browser_download_url' => At_Head_Tag_Updater::REPOSITORY . '/releases/download/v1.5.0/at-head-tag.zip',
			'state' => 'uploaded',
			'size' => 1234,
		),
	),
);
$parsed = At_Head_Tag_Updater::parse_release( $release );
same( '1.5.0', $parsed['version'], 'Parse a complete stable GitHub release' );
same( '5.8', $parsed['requires'], 'Parse minimum WordPress version' );
same( '7.4', $parsed['requires_php'], 'Parse minimum PHP version' );
$release['draft'] = true;
same( false, At_Head_Tag_Updater::parse_release( $release ), 'Reject draft releases' );
$release['draft'] = false;
$release['assets'][0]['name'] = 'wrong.zip';
same( false, At_Head_Tag_Updater::parse_release( $release ), 'Reject releases without the exact package asset' );

$updater = new At_Head_Tag_Updater( dirname( __DIR__ ) . '/at-head-tag.php' );
$GLOBALS['cache'][ At_Head_Tag_Updater::CACHE_KEY ] = array( 'version' => 'old' );
$GLOBALS['cache']['update_plugins'] = (object) array( 'last_checked' => time() );
$_GET['force-check'] = '1';
$updater->maybe_force_check();
unset( $_GET['force-check'] );
same( false, get_site_transient( At_Head_Tag_Updater::CACHE_KEY ), 'Check Again clears release metadata cache' );
same( false, get_site_transient( 'update_plugins' ), 'Check Again clears WordPress update state' );

echo "PASS: $count regression assertions on PHP " . PHP_VERSION . ".\n";
