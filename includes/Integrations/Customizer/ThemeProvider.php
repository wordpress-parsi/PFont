<?php
/**
 * Base class for theme Customizer providers.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Integrations\Customizer;

use UniversalCustomFonts\Integrations\CustomizerAdapter;

defined( 'ABSPATH' ) || exit;

/**
 * One theme's public font API.
 */
abstract class ThemeProvider {

	/**
	 * Theme label.
	 *
	 * @return string
	 */
	abstract public function label(): string;

	/**
	 * Whether this theme is active.
	 *
	 * @return bool
	 */
	abstract public function detect(): bool;

	/**
	 * Register hooks through the adapter.
	 *
	 * @param CustomizerAdapter $adapter Adapter.
	 */
	public function register( CustomizerAdapter $adapter ): void {
		unset( $adapter );
	}

	/**
	 * Saved theme settings to scan for used font names.
	 *
	 * @return string
	 */
	public function haystack(): string {
		return '';
	}

	/**
	 * Status.
	 *
	 * @return array{state:string,message:string}
	 */
	public function status(): array {
		return array(
			'state'   => 'active',
			'message' => __( 'Fonts appear in the theme’s Customizer typography controls.', 'universal-custom-fonts' ),
		);
	}
}
