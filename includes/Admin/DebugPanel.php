<?php
/**
 * Debug tab (only when debug mode is on). Shows URLs, never server paths.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Admin;

use UniversalCustomFonts\Core\FontLoader;
use UniversalCustomFonts\Core\FontRegistry;
use UniversalCustomFonts\Core\Settings;
use UniversalCustomFonts\Fonts\CdnFonts;
use UniversalCustomFonts\Fonts\FontStorage;
use UniversalCustomFonts\Integrations\IntegrationManager;

defined( 'ABSPATH' ) || exit;

/**
 * Renders diagnostics.
 */
final class DebugPanel {

	/**
	 * Render.
	 */
	public static function render(): void {
		AdminPage::page_header( __( 'Debug', 'universal-custom-fonts' ), __( 'Technical details for troubleshooting. Server paths are never shown.', 'universal-custom-fonts' ) );
		if ( ! Settings::get( 'debug' ) ) {
			printf( '<div class="ucf-card"><p>%s</p></div>', esc_html__( 'Turn on debug mode under Settings to use this page.', 'universal-custom-fonts' ) );
			return;
		}
		global $wp_version;
		$theme = wp_get_theme();
		self::table(
			__( 'Environment', 'universal-custom-fonts' ),
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
				? ( $lines ? implode( "\n", $lines ) : __( 'no hooks needed', 'universal-custom-fonts' ) )
				: __( 'not detected', 'universal-custom-fonts' );
		}
		self::table( __( 'Registered hooks', 'universal-custom-fonts' ), $hooks );

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
		self::dump( __( 'Font registry', 'universal-custom-fonts' ), $registry );
		self::dump( __( 'Generated @font-face CSS', 'universal-custom-fonts' ), $css );
		self::dump( __( 'CDN stylesheet URLs', 'universal-custom-fonts' ), $urls );
		$last = get_transient( 'ucf_debug_last_load' );
		self::dump( __( 'Last front-end page with fonts', 'universal-custom-fonts' ), is_array( $last ) ? $last : __( 'Nothing recorded yet. Visit a page that uses a font.', 'universal-custom-fonts' ) );
	}

	/**
	 * Key/value table.
	 *
	 * @param string $title Title.
	 * @param array  $rows  Rows.
	 */
	private static function table( string $title, array $rows ): void {
		printf( '<section class="ucf-card ucf-mt"><h2 class="ucf-card__title">%s</h2><dl class="ucf-dl">', esc_html( $title ) );
		foreach ( $rows as $key => $value ) {
			printf( '<dt>%1$s</dt><dd><pre class="ucf-pre">%2$s</pre></dd>', esc_html( (string) $key ), esc_html( (string) $value ) );
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
		printf( '<section class="ucf-card ucf-mt"><h2 class="ucf-card__title">%1$s</h2><pre class="ucf-pre">%2$s</pre></section>', esc_html( $title ), esc_html( $text ) );
	}
}
