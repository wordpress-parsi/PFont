<?php
/**
 * GeneratePress.
 *
 * @package PFont
 */

namespace PFont\Integrations\Customizer;

defined( 'ABSPATH' ) || exit;

/**
 * GeneratePress 3.x replaced its font-list filter with a Font Manager where any family name can
 * be typed, so there is nothing to inject; PFont loads fonts named in its saved settings.
 */
final class GeneratePressProvider extends ThemeProvider {

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'GeneratePress';
	}

	/**
	 * {@inheritDoc}
	 */
	public function detect(): bool {
		return 'generatepress' === strtolower( (string) get_template() ) || defined( 'GENERATE_VERSION' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function haystack(): string {
		return (string) wp_json_encode( get_option( 'generate_settings', array() ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function status(): array {
		return array(
			'state'   => 'limited',
			'message' => __( 'GeneratePress 3 has no font-list hook. Add the font in Customizer → Typography → Font Manager by typing its exact CSS family name; PFont then loads it wherever it is used.', 'pfont' ),
		);
	}
}
