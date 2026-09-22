<?php
/**
 * Uninstall routine. Deletes nothing unless the administrator opted in under Settings → Uninstall.
 *
 * @package PFont
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove data for the current site according to its own uninstall settings.
 */
function pfont_uninstall_site() {
	$settings = get_option( 'ucf_settings', array() );
	$settings = is_array( $settings ) ? $settings : array();

	if ( ! empty( $settings['delete_files_on_uninstall'] ) ) {
		$uploads = wp_upload_dir( null, false );
		$dir     = trailingslashit( $uploads['basedir'] ) . 'pfont';
		if ( is_dir( $dir ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			if ( WP_Filesystem() ) {
				global $wp_filesystem;
				$wp_filesystem->rmdir( $dir, true );
			}
		}
	}

	if ( ! empty( $settings['delete_settings_on_uninstall'] ) ) {
		delete_option( 'ucf_fonts' );
		delete_option( 'ucf_settings' );
		delete_option( 'ucf_db_version' );
		delete_transient( 'ucf_native_index' );
		delete_transient( 'ucf_debug_last_load' );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $pfont_site_id ) {
		switch_to_blog( $pfont_site_id );
		pfont_uninstall_site();
		restore_current_blog();
	}
} else {
	pfont_uninstall_site();
}
