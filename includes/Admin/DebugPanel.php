<?php
/**
 * Debug tab (only when debug mode is on). Shows URLs, never server paths.
 *
 * @package PFont
 */

namespace PFont\Admin;

use PFont\Core\FontLoader;
use PFont\Core\FontRegistry;
use PFont\Core\Settings;
use PFont\Fonts\CdnFonts;
use PFont\Fonts\FontStorage;
use PFont\Integrations\IntegrationManager;

defined( 'ABSPATH' ) || exit;

/**
 * Renders diagnostics.
 */
final class DebugPanel {

	/**
	 * Render.
	 */
	public static function render(): void {
		AdminPage::page_header( __( 'Debug', 'pfont' ), __( 'Technical details for troubleshooting. Server paths are never shown.', 'pfont' ) );
		if ( ! Settings::get( 'debug' ) ) {
			printf( '<div class="pfont-card"><p>%s</p></div>', esc_html__( 'Turn on debug mode under Settings to use this page.', 'pfont' ) );
			return;
		}
		global $wp_version;
		$theme = wp_get_theme();
		self::table(
			__( 'Environment', 'pfont' ),
			array(
				'WordPress' => (string) $wp_version,
				'PHP'       => PHP_VERSION,
				'Theme'     => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
				'Fonts URL' => FontStorage::base_url(),
				'CDN'       => CdnFonts::provider()['label'],
				'Loading'   => (string) Settings::get( 'remote_strategy' ),
			)
		);

		$hooks = array();
		foreach ( IntegrationManager::adapters() as $adapter ) {
			$lines = array();
			foreach ( $adapter->hooks() as $hook ) {
				$lines[] = $hook['type'] . ': ' . $hook['hook'] . ' @' . $hook['priority'];
			}
			$hooks[ $adapter->label() ] = $adapter->is_available()
				? ( $lines ? implode( "\n", $lines ) : __( 'no hooks needed', 'pfont' ) )
				: __( 'not detected', 'pfont' );
		}
		self::table( __( 'Registered hooks', 'pfont' ), $hooks );

		$registry = array();
		$css      = array();
		$urls     = array();
		foreach ( FontRegistry::all() as $font ) {
			$registry[ $font->id() ] = $font->to_array();
			if ( $font->is_local() ) {
				$css[ $font->id() ] = FontLoader::get_css( $font->id() );
			} else {
				$urls[ $font->id() ] = FontLoader::get_remote_urls( array( $font ) );
			}
		}
		self::dump( __( 'Font registry', 'pfont' ), $registry );
		self::dump( __( 'Generated @font-face CSS', 'pfont' ), $css );
		self::dump( __( 'CDN stylesheet URLs', 'pfont' ), $urls );
		$last = get_transient( 'pfont_debug_last_load' );
		self::dump( __( 'Last front-end page with fonts', 'pfont' ), is_array( $last ) ? $last : __( 'Nothing recorded yet. Visit a page that uses a font.', 'pfont' ) );
	}

	/**
	 * Key/value table.
	 *
	 * @param string $title Title.
	 * @param array  $rows  Rows.
	 */
	private static function table( string $title, array $rows ): void {
		printf( '<section class="pfont-card pfont-mt"><h2 class="pfont-card__title">%s</h2><dl class="pfont-dl">', esc_html( $title ) );
		foreach ( $rows as $key => $value ) {
			printf( '<dt>%1$s</dt><dd><pre class="pfont-pre">%2$s</pre></dd>', esc_html( (string) $key ), esc_html( (string) $value ) );
		}
		echo '</dl></section>';
	}

	/**
	 * Pretty JSON dump.
	 *
	 * @param string $title Title.
	 * @param mixed  $data  Data.
	 */
	private static function dump( string $title, mixed $data ): void {
		$text = is_string( $data ) ? $data : (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		printf( '<section class="pfont-card pfont-mt"><h2 class="pfont-card__title">%1$s</h2><pre class="pfont-pre">%2$s</pre></section>', esc_html( $title ), esc_html( $text ) );
	}
}
