<?php
/**
 * Environment detection helpers (safe to call after after_setup_theme).
 *
 * @package PFont
 */

namespace PFont\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Detects platforms without loading any of their code.
 */
final class IntegrationDetector {

	/**
	 * Parent theme folder, lowercase.
	 *
	 * @return string
	 */
	public static function template(): string {
		return strtolower( (string) get_template() );
	}

	/**
	 * Elementor.
	 *
	 * @return bool
	 */
	public static function elementor(): bool {
		return defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Fonts' );
	}

	/**
	 * Astra.
	 *
	 * @return bool
	 */
	public static function astra(): bool {
		return defined( 'ASTRA_THEME_VERSION' ) && class_exists( 'Astra_Font_Families' );
	}

	/**
	 * Block editor with theme.json data filters (WordPress 6.1+).
	 *
	 * @return bool
	 */
	public static function block_editor(): bool {
		return class_exists( 'WP_Theme_JSON_Data' ) && class_exists( 'WP_Theme_JSON' );
	}
}
