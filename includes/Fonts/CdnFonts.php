<?php
/**
 * Predefined CDN fonts and css2 URL building.
 *
 * @package PFont
 */

namespace PFont\Fonts;

use PFont\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Preset data was verified against google/fonts METADATA.pb and the live css2 API.
 */
final class CdnFonts {

	/**
	 * Offered presets. None is enabled automatically.
	 *
	 * @return array<string,array>
	 */
	public static function presets(): array {
		$presets = array(
			'parastoo'     => array(
				'family'   => 'Parastoo',
				'fallback' => 'serif',
				'weights'  => array( 400, 500, 600, 700 ),
				'range'    => array( 400, 700 ),
				'styles'   => array( 'normal' ),
				'subsets'  => array( 'arabic', 'latin', 'latin-ext', 'vietnamese' ),
			),
			'vazirmatn'    => array(
				'family'   => 'Vazirmatn',
				'fallback' => 'sans-serif',
				'weights'  => array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ),
				'range'    => array( 100, 900 ),
				'styles'   => array( 'normal' ),
				'subsets'  => array( 'arabic', 'latin', 'latin-ext' ),
			),
			'estedad'      => array(
				'family'   => 'Estedad',
				'fallback' => 'sans-serif',
				'weights'  => array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ),
				'range'    => array( 100, 900 ),
				'styles'   => array( 'normal' ),
				'subsets'  => array( 'arabic', 'latin', 'latin-ext', 'vietnamese' ),
			),
			'lalezar'      => array(
				'family'   => 'Lalezar',
				'fallback' => 'sans-serif',
				'weights'  => array( 400 ),
				'range'    => null,
				'styles'   => array( 'normal' ),
				'subsets'  => array( 'arabic', 'latin', 'latin-ext', 'vietnamese' ),
			),
			'markazi-text' => array(
				'family'   => 'Markazi Text',
				'fallback' => 'serif',
				'weights'  => array( 400, 500, 600, 700 ),
				'range'    => array( 400, 700 ),
				'styles'   => array( 'normal' ),
				'subsets'  => array( 'arabic', 'latin', 'latin-ext', 'vietnamese' ),
			),
		);
		foreach ( $presets as $id => $preset ) {
			$presets[ $id ]['id']      = $id;
			$presets[ $id ]['license'] = 'SIL Open Font License 1.1';
		}

		/**
		 * Filter the preset list shown under "Available fonts".
		 *
		 * @param array $presets Presets keyed by ID.
		 */
		return (array) apply_filters( 'pfont_cdn_presets', $presets );
	}

	/**
	 * One preset.
	 *
	 * @param string $id Preset ID.
	 * @return array|null
	 */
	public static function preset( string $id ): ?array {
		$presets = self::presets();
		return isset( $presets[ $id ] ) ? $presets[ $id ] : null;
	}

	/**
	 * CDN providers that speak the css2 API.
	 *
	 * @return array<string,array>
	 */
	public static function providers(): array {
		return array(
			'google' => array(
				'label' => 'Google Fonts',
				'css2'  => 'https://fonts.googleapis.com/css2',
				'hosts' => array( 'fonts.googleapis.com', 'fonts.gstatic.com' ),
			),
			'bunny'  => array(
				'label' => 'Bunny Fonts',
				'css2'  => 'https://fonts.bunny.net/css2',
				'hosts' => array( 'fonts.bunny.net' ),
			),
		);
	}

	/**
	 * The configured provider.
	 *
	 * @return array
	 */
	public static function provider(): array {
		$providers = self::providers();
		$id        = (string) Settings::get( 'cdn_provider' );
		return $providers[ $id ] ?? $providers['google'];
	}

	/**
	 * One css2 "family=" value.
	 *
	 * Verified API behavior: several weights of a variable family download the same files as the
	 * full range, while a single weight gets a smaller static file; invalid weights or italics
	 * return HTTP 400, so callers pass validated values only.
	 *
	 * @param string     $family  Family as the CDN knows it.
	 * @param int[]      $weights Weights.
	 * @param array|null $range   Variable wght range, or null for static families.
	 * @param string[]   $styles  normal/italic.
	 * @return string
	 */
	public static function css2_family( string $family, array $weights, ?array $range, array $styles ): string {
		$name    = str_replace( '%20', '+', rawurlencode( $family ) );
		$weights = array_values( array_unique( array_map( 'intval', $weights ) ) );
		sort( $weights );
		$weights = $weights ? $weights : array( 400 );
		$italic  = in_array( 'italic', $styles, true );
		$normal  = in_array( 'normal', $styles, true ) || ! $italic;

		if ( ! $italic && array( 400 ) === $weights ) {
			return $name;
		}

		$axis_values = ( $range && count( $weights ) > 1 ) ? array( min( $weights ) . '..' . max( $weights ) ) : array_map( 'strval', $weights );

		if ( ! $italic ) {
			return $name . ':wght@' . implode( ';', $axis_values );
		}

		$tuples = array();
		foreach ( array(
			0 => $normal,
			1 => $italic,
		) as $ital => $wanted ) {
			if ( ! $wanted ) {
				continue;
			}
			foreach ( $axis_values as $value ) {
				$tuples[] = $ital . ',' . $value;
			}
		}
		return $name . ':ital,wght@' . implode( ';', $tuples );
	}

	/**
	 * Full css2 URL for several families (one request instead of many).
	 *
	 * @param string[]   $specs    Family specs.
	 * @param string     $display  font-display.
	 * @param array|null $provider Provider (defaults to the configured one).
	 * @return string
	 */
	public static function css2_url( array $specs, string $display = 'swap', ?array $provider = null ): string {
		$provider = $provider ?? self::provider();
		$query    = array();
		foreach ( array_unique( $specs ) as $spec ) {
			$query[] = 'family=' . $spec;
		}
		$query[] = 'display=' . rawurlencode( $display );
		return $provider['css2'] . '?' . implode( '&', $query );
	}

	/**
	 * Whether a URL is https on one of the allowed hosts.
	 *
	 * @param string   $url   URL.
	 * @param string[] $hosts Allowed hosts.
	 * @return bool
	 */
	public static function is_allowed_host( string $url, array $hosts ): bool {
		$parts = wp_parse_url( $url );
		return is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' ) && in_array( strtolower( $parts['host'] ?? '' ), $hosts, true );
	}
}
