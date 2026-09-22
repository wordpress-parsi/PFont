<?php
/**
 * Kadence theme.
 *
 * @package PFont
 */

namespace PFont\Integrations\Customizer;

use PFont\Integrations\CustomizerAdapter;
use PFont\Helpers\FontHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Verified in Kadence 1.5.2: kadence_theme_add_custom_fonts takes name => [fallback, weights];
 * Kadence only requests fonts flagged as Google, so custom fonts never hit Google.
 */
final class KadenceProvider extends ThemeProvider {

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Kadence';
	}

	/**
	 * {@inheritDoc}
	 */
	public function detect(): bool {
		return 'kadence' === strtolower( (string) get_template() ) || defined( 'KADENCE_VERSION' );
	}

	/**
	 * Add fonts through Kadence's Beaver-Builder-compatible filter.
	 *
	 * @param CustomizerAdapter $adapter Adapter.
	 */
	public function register( CustomizerAdapter $adapter ): void {
		$adapter->register_hook(
			'filter',
			'kadence_theme_add_custom_fonts',
			static function ( $fonts ) use ( $adapter ) {
				$fonts = is_array( $fonts ) ? $fonts : array();
				$taken = array();
				foreach ( array_keys( $fonts ) as $name ) {
					$taken[ FontHelper::normalize_family_name( (string) $name ) ] = true;
				}
				foreach ( $adapter->fonts() as $font ) {
					if ( ! isset( $taken[ FontHelper::normalize_family_name( $font->family() ) ] ) ) {
						$fonts[ $font->family() ] = array(
							'fallback' => $font->fallback(),
							'weights'  => array_map( 'strval', $font->weights() ),
						);
					}
				}
				return $fonts;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function haystack(): string {
		return (string) wp_json_encode( get_theme_mods() );
	}
}
