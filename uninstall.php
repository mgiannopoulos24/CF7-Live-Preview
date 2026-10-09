<?php
/**
 * Uninstall handler.
 *
 * Removes the plugin's only stored data, its settings option. The previews
 * write nothing else: no posts, meta, tables or transients.
 *
 * @package CF7_Live_Preview
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	$cf7lp_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $cf7lp_site_ids as $cf7lp_site_id ) {
		switch_to_blog( $cf7lp_site_id );
		delete_option( 'cf7_live_preview_options' );
		restore_current_blog();
	}
} else {
	delete_option( 'cf7_live_preview_options' );
}
