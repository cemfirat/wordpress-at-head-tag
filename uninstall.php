<?php
/** Remove At Head Tag options on uninstall. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'at_head_tag_content' );
delete_option( 'at_head_tag_priority' );
delete_option( 'at_head_tag_enabled' );
delete_site_transient( 'at_head_tag_release_v1' );
