<?php
/**
 * Builds @font-face CSS for fonts served from this site.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Fonts;

use UniversalCustomFonts\Core\Font;

defined( 'ABSPATH' ) || exit;

/**
 * Generates compact, valid @font-face rules (WOFF2 first, font-display from settings).
 */
final class FontFaceGenerator {

	/**
	 * Preferred source order.
	 */
	private const ORDER = array( 'woff2', 'woff', 'ttf', 'otf' );

	/**
	 * CSS for a font, optionally limited to some weights. Remote CDN fonts return ''.
	 *
	 * @param Font  $font    Font.
	 * @param int[] $weights Weights to include (empty = all).
	 * @return string
	 */
	public static function for_font( Font $font, array $weights = array() ): string {
		if ( $font->is_upload() ) {
			return self::from_uploads( $font, $weights );
		}
		if ( $font->is_self_hosted() ) {
			return self::from_local_faces( $font, $weights );
		}
		return '';
	}

	/**
	 * Rules from uploaded files.
	 *
	 * @param Font  $font    Font.
	 * @param int[] $weights Weights filter.
	 * @return string
	 */
	private static function from_uploads( Font $font, array $weights ): string {
		$css = '';
		foreach ( $font->files() as $weight => $by_style ) {
			$weight = (int) $weight;
			if ( $weights && ! in_array( $weight, $weights, true ) ) {
				continue;
			}
			foreach ( (array) $by_style as $style => $formats ) {
				if ( ! in_array( $style, Font::STYLES, true ) ) {
					continue;
				}
				$src = array();
				foreach ( self::ORDER as $format ) {
					if ( empty( $formats[ $format ] ) ) {
						continue;
					}
					$url = FontStorage::url( (string) $formats[ $format ] );
					if ( '' !== $url ) {
						$src[] = "url('" . self::css_url( $url ) . "') format('" . self::format_hint( $format ) . "')";
					}
				}
				if ( $src ) {
					$css .= self::rule( $font->family(), (string) $weight, $style, $src, $font->display() );
				}
			}
		}
		return $css;
	}

	/**
	 * Rules from self-hosted (downloaded) faces, keeping their unicode-range subsets.
	 *
	 * @param Font  $font    Font.
	 * @param int[] $weights Weights filter.
	 * @return string
	 */
	private static function from_local_faces( Font $font, array $weights ): string {
		$css = '';
		foreach ( $font->local_faces() as $face ) {
			$weight = isset( $face['weight'] ) ? (string) $face['weight'] : '400';
			if ( $weights && ! self::weight_matches( $weight, $weights ) ) {
				continue;
			}
			$src = array();
			foreach ( (array) ( $face['src'] ?? array() ) as $item ) {
				$format = (string) ( $item['format'] ?? '' );
				$url    = FontStorage::url( (string) ( $item['file'] ?? '' ) );
				if ( '' !== $url && in_array( $format, self::ORDER, true ) ) {
					$src[] = "url('" . self::css_url( $url ) . "') format('" . self::format_hint( $format ) . "')";
				}
			}
			if ( $src ) {
				$style = ( isset( $face['style'] ) && 'italic' === $face['style'] ) ? 'italic' : 'normal';
				$css  .= self::rule( $font->family(), $weight, $style, $src, $font->display(), (string) ( $face['unicode_range'] ?? '' ) );
			}
		}
		return $css;
	}

	/**
	 * One @font-face rule. Single quotes only, so the CSS is also safe inside TinyMCE settings.
	 *
	 * @param string   $family        Family.
	 * @param string   $weight        "400" or "100 900".
	 * @param string   $style         normal|italic.
	 * @param string[] $src           Source list.
	 * @param string   $display       font-display.
	 * @param string   $unicode_range Optional unicode-range.
	 * @return string
	 */
	private static function rule( string $family, string $weight, string $style, array $src, string $display, string $unicode_range = '' ): string {
		$weight        = preg_match( '/^\d{3}( \d{3})?$/', $weight ) ? $weight : '400';
		$unicode_range = (string) preg_replace( '/[^Uu+0-9A-Fa-f?,\s-]/', '', $unicode_range );
		$unicode_range = trim( (string) preg_replace( '/\s+/', ' ', $unicode_range ) );

		return '@font-face{font-family:\'' . str_replace( array( "'", '"', '\\', ';', '{', '}' ), '', $family ) . '\';'
			. 'font-style:' . $style . ';'
			. 'font-weight:' . $weight . ';'
			. 'font-display:' . ( in_array( $display, Font::DISPLAYS, true ) ? $display : 'swap' ) . ';'
			. 'src:' . implode( ',', $src )
			. ( '' !== $unicode_range ? ';unicode-range:' . $unicode_range : '' )
			. "}\n";
	}

	/**
	 * Whether "400" or a range like "100 900" covers any requested weight.
	 *
	 * @param string $weight  Face weight.
	 * @param int[]  $weights Requested weights.
	 * @return bool
	 */
	private static function weight_matches( string $weight, array $weights ): bool {
		$bounds = array_map( 'intval', explode( ' ', $weight ) );
		$low    = $bounds[0];
		$high   = $bounds[1] ?? $bounds[0];
		foreach ( $weights as $requested ) {
			if ( $requested >= $low && $requested <= $high ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The CSS format() hint for a file type.
	 *
	 * @param string $format File type.
	 * @return string
	 */
	public static function format_hint( string $format ): string {
		return match ( $format ) {
			'ttf'   => 'truetype',
			'otf'   => 'opentype',
			'woff'  => 'woff',
			default => 'woff2',
		};
	}

	/**
	 * Make a URL safe inside url('...').
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function css_url( string $url ): string {
		return strtr(
			esc_url_raw( $url ),
			array(
				"'"  => '%27',
				'"'  => '%22',
				'('  => '%28',
				')'  => '%29',
				' '  => '%20',
				'\\' => '%5C',
				"\n" => '',
				"\r" => '',
			)
		);
	}
}
