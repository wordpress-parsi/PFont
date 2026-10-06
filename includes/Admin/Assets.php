<?php
/**
 * Admin assets (loaded only on the plugin screen).
 *
 * @package PFont
 */

namespace PFont\Admin;

use PFont\Core\FontLoader;
use PFont\Core\FontRegistry;
use PFont\Fonts\CdnFonts;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues CSS/JS and font previews.
 */
final class Assets {

	/**
	 * Enqueue on our screen only.
	 *
	 * @param string $hook_suffix Current screen.
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( '' === AdminPage::hook_suffix() || AdminPage::hook_suffix() !== $hook_suffix ) {
			return;
		}
		// 'wp-base-styles' defines --wp-admin-theme-color for the user's admin colour scheme.
		$deps = wp_style_is( 'wp-base-styles', 'registered' ) ? array( 'wp-base-styles' ) : array();
		wp_enqueue_style( 'pfont-admin', PFONT_URL . 'assets/css/admin.css', $deps, PFONT_VERSION );
		wp_enqueue_script(
			'pfont-admin',
			PFONT_URL . 'assets/js/admin.js',
			array(),
			PFONT_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		$ids = array_keys( FontRegistry::all() );
		if ( $ids ) {
			FontLoader::enqueue_for_editor( $ids, true );
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'fonts'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only.
		if ( 'fonts' !== $tab ) {
			return;
		}
		$specs = array();
		foreach ( FontList::available_presets() as $preset ) {
			$specs[] = CdnFonts::css2_family( $preset['family'], array( 400 ), $preset['range'] ?? null, array( 'normal' ) );
		}
		if ( $specs ) {
			// Only on this admin screen, so the site owner can compare the presets before adding one.
			wp_enqueue_style( 'pfont-preset-previews', CdnFonts::css2_url( $specs, 'swap' ), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Versioned by the font service.
		}
	}
}
