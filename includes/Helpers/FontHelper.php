<?php
/**
 * Small, dependency-free helpers.
 *
 * @package PFont
 */

namespace PFont\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Name normalization and detection helpers.
 */
final class FontHelper {

	public const WEIGHTS = array( 100, 200, 300, 400, 500, 600, 700, 800, 900 );

	public const GENERIC = array( 'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded', 'math', 'emoji', 'fangsong' );

	/**
	 * Normalize a family name so "'Markazi  Text', serif" and "markazi text" compare equal.
	 *
	 * @param string $name Family name or CSS stack.
	 * @return string
	 */
	public static function normalize_family_name( string $name ): string {
		$name  = html_entity_decode( $name, ENT_QUOTES, 'UTF-8' );
		$first = explode( ',', $name )[0];
		$first = trim( $first, " \t\n\r\0\x0B'\"" );
		$first = (string) preg_replace( '/\s+/u', ' ', $first );
		return mb_strtolower( $first, 'UTF-8' );
	}

	/**
	 * Compare two family names safely.
	 *
	 * @param string $a First name.
	 * @param string $b Second name.
	 * @return bool
	 */
	public static function same_family( string $a, string $b ): bool {
		return '' !== $a && self::normalize_family_name( $a ) === self::normalize_family_name( $b );
	}

	/**
	 * Keep valid CSS weights only, sorted and unique.
	 *
	 * @param array $weights Raw weights.
	 * @return int[]
	 */
	public static function sanitize_weights( array $weights ): array {
		$clean = array();
		foreach ( $weights as $weight ) {
			$weight = self::parse_weight( $weight );
			if ( in_array( $weight, self::WEIGHTS, true ) ) {
				$clean[ $weight ] = $weight;
			}
		}
		ksort( $clean );
		return array_values( $clean );
	}

	/**
	 * Turn "700", "700italic", "700i", "regular" or "bold" into an integer weight.
	 *
	 * @param mixed $value Raw value.
	 * @return int 0 when unknown.
	 */
	public static function parse_weight( mixed $value ): int {
		$value = strtolower( trim( (string) $value ) );
		if ( in_array( $value, array( 'regular', 'normal', 'italic', 'i' ), true ) ) {
			return 400;
		}
		if ( 'bold' === $value ) {
			return 700;
		}
		return preg_match( '/^(\d{3})/', $value, $m ) ? (int) $m[1] : 0;
	}

	/**
	 * Generic family (serif, sans-serif...) at the end of a fallback stack.
	 *
	 * @param string $fallback Fallback stack.
	 * @return string
	 */
	public static function generic_from_fallback( string $fallback ): string {
		$parts = array_reverse( array_map( 'trim', explode( ',', strtolower( $fallback ) ) ) );
		foreach ( $parts as $part ) {
			$part = trim( $part, "'\"" );
			if ( in_array( $part, self::GENERIC, true ) ) {
				return $part;
			}
		}
		return 'sans-serif';
	}

	/**
	 * Whether a family name appears as a whole word (builder settings, shortcodes, JSON).
	 *
	 * @param string $haystack Text to search.
	 * @param string $family   Family name.
	 * @return bool
	 */
	public static function mentions_family( string $haystack, string $family ): bool {
		if ( '' === $haystack || '' === $family || false === stripos( $haystack, $family ) ) {
			return false;
		}
		return (bool) preg_match( '/(?<![\p{L}\p{N}_-])' . preg_quote( $family, '/' ) . '(?![\p{L}\p{N}_-])/iu', $haystack );
	}

	/**
	 * Whether HTML/CSS uses a family inside a font-family declaration.
	 *
	 * @param string $haystack HTML or CSS.
	 * @param string $family   Family name.
	 * @return bool
	 */
	public static function uses_in_css( string $haystack, string $family ): bool {
		if ( '' === $haystack || '' === $family || false === stripos( $haystack, $family ) ) {
			return false;
		}
		$haystack = html_entity_decode( $haystack, ENT_QUOTES, 'UTF-8' );
		$pattern  = '/font-family\s*:[^;}<>]*?(?<![\p{L}\p{N}_-])[\'"]?' . preg_quote( $family, '/' ) . '[\'"]?(?![\p{L}\p{N}_-])/iu';
		return (bool) preg_match( $pattern, $haystack );
	}
}
