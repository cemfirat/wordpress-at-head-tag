<?php
/**
 * Plugin Name: At Head Tag
 * Plugin URI: https://github.com/cemfirat/wordpress-at-head-tag
 * Description: Adds trusted HTML, CSS, JavaScript, meta and link snippets to the front-end <head> in WordPress.
 * Version: 1.4.2
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Cem Firat
 * Author URI: https://cemfirat.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/cemfirat/wordpress-at-head-tag
 * Text Domain: at-head-tag
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AT_HEAD_TAG_VERSION', '1.4.2' );
define( 'AT_HEAD_TAG_FILE', __FILE__ );
define( 'AT_HEAD_TAG_OPTION', 'at_head_tag_content' );
define( 'AT_HEAD_TAG_PRIORITY_OPTION', 'at_head_tag_priority' );
define( 'AT_HEAD_TAG_ENABLED_OPTION', 'at_head_tag_enabled' );

require_once __DIR__ . '/includes/class-at-head-tag-updater.php';

new At_Head_Tag_Updater( __FILE__ );

register_activation_hook( __FILE__, 'at_head_tag_activate' );
add_action( 'admin_init', 'at_head_tag_register_settings' );
add_action( 'admin_menu', 'at_head_tag_menu' );
add_action( 'admin_enqueue_scripts', 'at_head_tag_admin_assets' );
add_action( 'init', 'at_head_tag_bootstrap_head_hook' );
add_action( 'admin_post_at_head_tag_check_output', 'at_head_tag_handle_output_check' );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'at_head_tag_action_links' );

/** Add defaults without overwriting existing installations. */
function at_head_tag_activate() {
	add_option( AT_HEAD_TAG_OPTION, '', '', 'no' );
	add_option( AT_HEAD_TAG_PRIORITY_OPTION, 999, '', 'no' );
	add_option( AT_HEAD_TAG_ENABLED_OPTION, 1, '', 'no' );
}

/** Raw head snippets require both site management and unfiltered HTML. */
function at_head_tag_can_edit_code() {
	return current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' );
}

/** Analyze head code and return non-blocking errors, warnings and notices. */
function at_head_tag_lint( $code ) {
	$result = array(
		'errors'   => array(),
		'warnings' => array(),
		'notices'  => array(),
	);
	$trim = trim( (string) $code );
	if ( '' === $trim ) {
		return $result;
	}

	// DOM is optional. Hosts without ext-dom can still run the plugin safely.
	if ( class_exists( 'DOMDocument' ) ) {
		$previous = libxml_use_internal_errors( true );
		$dom      = new DOMDocument( '1.0', 'UTF-8' );
		$html     = "<!doctype html><html><head>\n" . $trim . "\n</head><body></body></html>";
		$loaded   = $dom->loadHTML( $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		$errors   = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			$result['errors'][] = 'The snippet could not be parsed as HTML.';
		} elseif ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				$message = trim( $error->message );
				$line    = max( 1, (int) $error->line - 1 );
				if ( LIBXML_ERR_FATAL === $error->level ) {
					$result['errors'][] = sprintf( 'Parser error near line %d: %s', $line, $message );
				} elseif ( LIBXML_ERR_ERROR === $error->level ) {
					$result['warnings'][] = sprintf( 'Parser warning near line %d: %s', $line, $message );
				}
			}
		}
	} else {
		$result['notices'][] = 'PHP DOM is unavailable, so structural HTML parser checks were skipped.';
	}

	$foreign = '(?:body|div|span|p|h[1-6]|img|section|article|header|footer|nav|main|form|input|button|canvas|svg|video|audio)';
	if ( preg_match_all( '/<\s*(' . $foreign . ')\b/i', $trim, $matches ) ) {
		$tags = array_unique( array_map( 'strtolower', $matches[1] ) );
		$result['warnings'][] = 'Elements that normally do not belong in <head>: ' . implode( ', ', $tags ) . '.';
	}
	if ( preg_match_all( '/<\s*meta\b[^>]*\bcharset\s*=/i', $trim, $matches ) && count( $matches[0] ) > 1 ) {
		$result['warnings'][] = 'More than one <meta charset> was found.';
	}
	if ( preg_match_all( '/<\s*title\b/i', $trim, $matches ) && count( $matches[0] ) > 1 ) {
		$result['warnings'][] = 'More than one <title> element was found.';
	}
	if ( preg_match( '~(?:src|href)\s*=\s*["\']http://~i', $trim ) ) {
		$result['warnings'][] = 'An http:// resource was found. Prefer https:// on HTTPS sites.';
	}
	$lines = preg_split( '/\R/u', $trim );
	foreach ( $lines as $index => $line ) {
		if ( preg_match( '/<\s*link\b[^>]*\brel\s*=\s*["\'](?:preload|modulepreload)["\']/i', $line ) && ! preg_match( '/\bas\s*=/i', $line ) ) {
			$result['warnings'][] = 'A preload link has no "as" attribute on line ' . ( $index + 1 ) . '.';
		}
		if ( preg_match( '/<\s*script\b[^>]*\bsrc\s*=/i', $line ) && ! preg_match( '/\b(?:async|defer)\b/i', $line ) ) {
			$result['notices'][] = 'External script without async/defer on line ' . ( $index + 1 ) . ' (performance note).';
		}
	}
	return $result;
}

/** Add lint feedback to the Settings API without blocking trusted code. */
function at_head_tag_add_lint_messages( $code ) {
	$lint = at_head_tag_lint( $code );
	foreach ( array( 'errors' => 'error', 'warnings' => 'warning', 'notices' => 'info' ) as $group => $type ) {
		foreach ( $lint[ $group ] as $message ) {
			add_settings_error( 'at_head_tag_options_group', 'at_head_tag_' . md5( $message ), $message, $type );
		}
	}
}

/** Preserve trusted source exactly; reject unauthorized or malformed submissions. */
function at_head_tag_sanitize_code( $input ) {
	if ( ! at_head_tag_can_edit_code() || ! is_string( $input ) ) {
		add_settings_error( 'at_head_tag_options_group', 'at_head_tag_permission', 'The head snippet was not saved. You need permission to manage options and save unfiltered HTML.' );
		$previous = get_option( AT_HEAD_TAG_OPTION, '' );
		return is_string( $previous ) ? $previous : '';
	}
	$checked = wp_check_invalid_utf8( $input );
	if ( '' === $checked && '' !== $input ) {
		add_settings_error( 'at_head_tag_options_group', 'at_head_tag_encoding', 'The head snippet was not saved because it contains invalid text encoding.' );
		$previous = get_option( AT_HEAD_TAG_OPTION, '' );
		return is_string( $previous ) ? $previous : '';
	}
	$checked = str_replace( "\0", '', $checked );
	at_head_tag_add_lint_messages( $checked );
	return $checked;
}

function at_head_tag_sanitize_priority( $value ) {
	$value = (int) $value;
	return ( $value >= 0 && $value <= 9999 ) ? $value : 999;
}

function at_head_tag_sanitize_enabled( $value ) {
	return ! empty( $value );
}

function at_head_tag_register_settings() {
	register_setting(
		'at_head_tag_options_group',
		AT_HEAD_TAG_OPTION,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'at_head_tag_sanitize_code',
			'show_in_rest'      => false,
		)
	);
	register_setting(
		'at_head_tag_options_group',
		AT_HEAD_TAG_PRIORITY_OPTION,
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'at_head_tag_sanitize_priority',
			'show_in_rest'      => false,
		)
	);
	register_setting(
		'at_head_tag_options_group',
		AT_HEAD_TAG_ENABLED_OPTION,
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'at_head_tag_sanitize_enabled',
			'show_in_rest'      => false,
		)
	);
}

function at_head_tag_menu() {
	add_options_page( 'At Head Tag', 'At Head Tag', 'manage_options', 'at-head-tag', 'at_head_tag_options_page' );
}

/** Use WordPress's code editor rather than TinyMCE so source is not rewritten. */
function at_head_tag_admin_assets( $hook ) {
	if ( 'settings_page_at-head-tag' !== $hook || ! at_head_tag_can_edit_code() ) {
		return;
	}
	$settings = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
	if ( false === $settings ) {
		return;
	}
	wp_add_inline_script(
		'code-editor',
		'jQuery(function(){ if (window.wp && wp.codeEditor) { wp.codeEditor.initialize("at_head_tag_content", ' . wp_json_encode( $settings ) . '); } });'
	);
}

function at_head_tag_render_preview_block( $code ) {
	return "<!-- at-head-tag START -->\n" . $code . "\n<!-- at-head-tag END -->";
}

/**
 * Check the real home-page HTML for the saved head snippet.
 *
 * Returns a short status code that can safely be carried through an admin redirect.
 */
function at_head_tag_check_frontend_output() {
	if ( ! (bool) get_option( AT_HEAD_TAG_ENABLED_OPTION, 1 ) ) {
		return 'disabled';
	}
	$code = get_option( AT_HEAD_TAG_OPTION, '' );
	if ( ! is_string( $code ) || '' === $code ) {
		return 'empty';
	}

	$url = add_query_arg( 'at-head-tag-check', (string) time(), home_url( '/' ) );
	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => 10,
			'redirection' => 3,
			'headers'     => array(
				'Cache-Control' => 'no-cache',
				'Pragma'        => 'no-cache',
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return 'fetch-error';
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	if ( $status < 200 || $status >= 400 ) {
		return 'http-error';
	}
	$body = wp_remote_retrieve_body( $response );
	if ( ! is_string( $body ) || ! preg_match( '/<head\\b[^>]*>(.*?)<\\/head>/is', $body, $match ) ) {
		return 'no-head';
	}

	$head = $match[1];
	if ( false !== strpos( $head, at_head_tag_render_preview_block( $code ) ) ) {
		return 'success';
	}
	if ( false !== strpos( $head, $code ) ) {
		return 'markers-changed';
	}
	return 'missing';
}

/** Human-readable output-check notice for a known status code. */
function at_head_tag_output_check_notice( $status ) {
	$messages = array(
		'success'         => array( 'success', 'The saved snippet was found exactly inside the home page <head>.' ),
		'markers-changed' => array( 'warning', 'The saved snippet is present in the home page <head>, but its source markers were changed or removed. A cache or optimization layer may be rewriting HTML.' ),
		'disabled'        => array( 'warning', 'Output is currently disabled. Enable the head snippet before checking the front end.' ),
		'empty'           => array( 'warning', 'There is no saved head snippet to check.' ),
		'fetch-error'     => array( 'error', 'WordPress could not request the home page. A firewall, authentication layer or local HTTP configuration may be blocking the self-request.' ),
		'http-error'      => array( 'error', 'The home page returned an HTTP error during the output check.' ),
		'no-head'         => array( 'error', 'The fetched home page did not contain a readable <head> section.' ),
		'missing'         => array( 'error', 'The saved snippet was not found exactly inside the fetched home page <head>. Check page caching, optimization/minification and theme output.' ),
	);
	return isset( $messages[ $status ] ) ? $messages[ $status ] : false;
}

/** Run the front-end check from wp-admin and return to the settings page. */
function at_head_tag_handle_output_check() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You are not allowed to check this output.' );
	}
	check_admin_referer( 'at_head_tag_check_output' );
	$status = at_head_tag_check_frontend_output();
	wp_safe_redirect(
		add_query_arg(
			array(
				'page'              => 'at-head-tag',
				'at_head_tag_check' => $status,
			),
			admin_url( 'options-general.php' )
		)
	);
	exit;
}

function at_head_tag_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$current  = get_option( AT_HEAD_TAG_OPTION, '' );
	$current  = is_string( $current ) ? $current : '';
	$priority = at_head_tag_sanitize_priority( get_option( AT_HEAD_TAG_PRIORITY_OPTION, 999 ) );
	$enabled  = (bool) get_option( AT_HEAD_TAG_ENABLED_OPTION, 1 );
	?>
	<div class="wrap">
		<h1>At Head Tag</h1>
		<p>Add trusted HTML, CSS, JavaScript, meta or link snippets to the front-end <code>&lt;head&gt;</code>.</p>
		<?php
		settings_errors( 'at_head_tag_options_group' );
		if ( isset( $_GET['at_head_tag_check'] ) ) {
			$check_status = sanitize_key( wp_unslash( $_GET['at_head_tag_check'] ) );
			$check_notice = at_head_tag_output_check_notice( $check_status );
			if ( $check_notice ) {
				add_settings_error( 'at_head_tag_output_check', 'at_head_tag_output_check_' . $check_status, $check_notice[1], $check_notice[0] );
				settings_errors( 'at_head_tag_output_check' );
			}
		}
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'at_head_tag_options_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Output</th>
					<td>
						<input type="hidden" name="<?php echo esc_attr( AT_HEAD_TAG_ENABLED_OPTION ); ?>" value="0">
						<label><input type="checkbox" name="<?php echo esc_attr( AT_HEAD_TAG_ENABLED_OPTION ); ?>" value="1" <?php checked( $enabled ); ?>> Enable head snippet</label>
						<p class="description">Pause output without deleting the saved snippet.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="at_head_tag_content">Head code</label></th>
					<td>
						<?php if ( at_head_tag_can_edit_code() ) : ?>
							<textarea id="at_head_tag_content" name="<?php echo esc_attr( AT_HEAD_TAG_OPTION ); ?>" rows="18" class="large-text code" spellcheck="false"><?php echo esc_textarea( $current ); ?></textarea>
							<p class="description">Saved and printed exactly as entered, except invalid UTF-8 and NUL bytes are rejected/removed. Only save code you trust.</p>
						<?php else : ?>
							<p>You need permission to save unfiltered HTML to edit this snippet. On Multisite, this normally requires a Super Admin.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="at_head_tag_priority">Hook priority</label></th>
					<td>
						<input type="number" min="0" max="9999" id="at_head_tag_priority" name="<?php echo esc_attr( AT_HEAD_TAG_PRIORITY_OPTION ); ?>" value="<?php echo esc_attr( $priority ); ?>">
						<p class="description">Higher values run later in <code>wp_head</code>. Default: 999.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Output preview</th>
					<td>
						<pre style="max-width:900px;overflow:auto;max-height:360px;padding:12px;background:#fff;border:1px solid #c3c4c7"><?php echo esc_html( at_head_tag_render_preview_block( $current ) ); ?></pre>
						<p class="description">Text preview only. Code is not executed here.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>
		<h2>Diagnostics</h2>
		<p>Installed version: <strong><?php echo esc_html( AT_HEAD_TAG_VERSION ); ?></strong></p>
		<p>Update source: <a href="https://github.com/cemfirat/wordpress-at-head-tag/releases" target="_blank" rel="noopener noreferrer">GitHub Releases</a> · <a href="<?php echo esc_url( admin_url( 'update-core.php?force-check=1' ) ); ?>">Check updates now</a></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0 18px">
			<input type="hidden" name="action" value="at_head_tag_check_output">
			<?php wp_nonce_field( 'at_head_tag_check_output' ); ?>
			<?php submit_button( 'Check front-end output', 'secondary', 'submit', false ); ?>
			<p class="description">Fetches the home page with a cache-busting query and checks whether the saved snippet is actually present inside <code>&lt;head&gt;</code>.</p>
		</form>
		<details>
			<summary>Tips for clean head code</summary>
			<ul style="list-style:disc;margin-left:1.25em">
				<li>Avoid content elements such as div, img or section in the head.</li>
				<li>Use only one meta charset and normally one title element.</li>
				<li>Prefer HTTPS resources.</li>
				<li>Use async/defer for external scripts when appropriate.</li>
				<li>Set the as attribute on preload links.</li>
			</ul>
		</details>
	</div>
	<?php
}

/** Print the trusted snippet inside clear source comments. */
function at_head_tag_inject_code() {
	if ( ! (bool) get_option( AT_HEAD_TAG_ENABLED_OPTION, 1 ) ) {
		return;
	}
	$code = get_option( AT_HEAD_TAG_OPTION, '' );
	if ( ! is_string( $code ) || '' === $code ) {
		return;
	}
	echo "\n<!-- at-head-tag START -->\n";
	echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentionally trusted raw head code.
	echo "\n<!-- at-head-tag END -->\n";
}

function at_head_tag_bootstrap_head_hook() {
	$priority = at_head_tag_sanitize_priority( get_option( AT_HEAD_TAG_PRIORITY_OPTION, 999 ) );
	add_action( 'wp_head', 'at_head_tag_inject_code', $priority );
}

function at_head_tag_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=at-head-tag' ) ) . '">Settings</a>' );
	return $links;
}
