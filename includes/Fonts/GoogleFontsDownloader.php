<?php
/**
 * Self-hosting: downloads a CDN font once, on an explicit admin action.
 *
 * @package PFont
 */

namespace PFont\Fonts;

use PFont\Core\Font;
use PFont\Core\FontValidator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Fetches css2 CSS with a modern user agent (to receive WOFF2), downloads the files and
 * keeps the unicode-range subsets so browsers still download only what a page needs.
 */
final class GoogleFontsDownloader {

	private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

	/**
	 * Download a font and return the "local" record.
	 *
	 * @param Font $font CDN font.
	 * @return array|WP_Error
	 */
	public static function download( Font $font ): array|WP_Error {
		if ( ! $font->is_cdn() ) {
			return new WP_Error( 'pfont_selfhost_type', __( 'Only fonts added from Google Fonts or Bunny Fonts can be hosted locally.', 'pfont' ) );
		}
		$provider = CdnFonts::provider();
		$preset   = $font->preset_data();
		$spec     = CdnFonts::css2_family( $font->remote_family(), $font->weights(), $preset['range'] ?? null, $font->styles() );
		$css_url  = CdnFonts::css2_url( array( $spec ), $font->display(), $provider );

		$response = wp_safe_remote_get(
			$css_url,
			array(
				'timeout'             => 20,
				'user-agent'          => self::USER_AGENT,
				'limit_response_size' => MB_IN_BYTES,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'pfont_selfhost_http',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'The font service answered with HTTP %d. Check the family name and the selected weights.', 'pfont' ),
					$code
				)
			);
		}

		$faces = self::parse_css( (string) wp_remote_retrieve_body( $response ) );
		if ( ! $faces ) {
			return new WP_Error( 'pfont_selfhost_empty', __( 'The font service returned no font files.', 'pfont' ) );
		}

		$dir        = 'google/' . $font->id() . '-' . substr( md5( $css_url . wp_rand() ), 0, 8 );
		$downloaded = array();
		$local      = array();

		foreach ( $faces as $face ) {
			$sources = $face['src'];
			$woff2   = array_values(
				array_filter(
					$sources,
					static function ( array $src ): bool {
						return 'woff2' === $src['format'];
					}
				)
			);
			$sources = $woff2 ? $woff2 : $sources;
			$src_out = array();

			foreach ( $sources as $src ) {
				if ( ! in_array( $src['format'], array( 'woff2', 'woff' ), true ) || ! CdnFonts::is_allowed_host( $src['url'], $provider['hosts'] ) ) {
					continue;
				}
				if ( isset( $downloaded[ $src['url'] ] ) ) {
					$src_out[] = $downloaded[ $src['url'] ];
					continue;
				}
				$file = wp_safe_remote_get(
					$src['url'],
					array(
						'timeout'             => 30,
						'user-agent'          => self::USER_AGENT,
						'limit_response_size' => 5 * MB_IN_BYTES,
					)
				);
				$body = is_wp_error( $file ) ? '' : (string) wp_remote_retrieve_body( $file );
				if ( is_wp_error( $file ) || 200 !== (int) wp_remote_retrieve_response_code( $file ) || FontValidator::detect_format_from_bytes( $body ) !== $src['format'] ) {
					FontStorage::delete_dir( $dir );
					return new WP_Error( 'pfont_selfhost_file', __( 'A font file could not be downloaded or failed verification. Nothing was changed.', 'pfont' ) );
				}
				$name = sanitize_file_name( (string) basename( (string) wp_parse_url( $src['url'], PHP_URL_PATH ) ) );
				if ( ! str_ends_with( strtolower( $name ), '.' . $src['format'] ) ) {
					$name = substr( md5( $src['url'] ), 0, 12 ) . '.' . $src['format'];
				}
				$relative = FontStorage::store_bytes( $body, $dir, $name, $src['format'] );
				if ( is_wp_error( $relative ) ) {
					FontStorage::delete_dir( $dir );
					return $relative;
				}
				$entry                     = array(
					'file'   => $relative,
					'format' => $src['format'],
				);
				$downloaded[ $src['url'] ] = $entry;
				$src_out[]                 = $entry;
			}

			if ( $src_out ) {
				$local[] = array(
					'weight'        => $face['weight'],
					'style'         => $face['style'],
					'unicode_range' => $face['unicode_range'],
					'src'           => $src_out,
				);
			}
		}

		if ( ! $local ) {
			FontStorage::delete_dir( $dir );
			return new WP_Error( 'pfont_selfhost_none', __( 'No usable WOFF2/WOFF files were found.', 'pfont' ) );
		}

		return array(
			'dir'        => $dir,
			'faces'      => $local,
			'provider'   => $provider['label'],
			'downloaded' => time(),
		);
	}

	/**
	 * Parse @font-face blocks from css2 output.
	 *
	 * @param string $css CSS.
	 * @return array
	 */
	public static function parse_css( string $css ): array {
		$faces = array();
		if ( ! preg_match_all( '/@font-face\s*\{([^}]*)\}/i', $css, $blocks ) ) {
			return $faces;
		}
		foreach ( $blocks[1] as $body ) {
			$props = array();
			foreach ( explode( ';', $body ) as $declaration ) {
				$pos = strpos( $declaration, ':' );
				if ( false !== $pos ) {
					$props[ strtolower( trim( substr( $declaration, 0, $pos ) ) ) ] = trim( substr( $declaration, $pos + 1 ) );
				}
			}
			preg_match_all( '/url\(\s*[\'"]?([^\'")\s]+)[\'"]?\s*\)\s*format\(\s*[\'"]?([a-z0-9\-]+)[\'"]?\s*\)/i', $props['src'] ?? '', $sources, PREG_SET_ORDER );
			$src = array();
			foreach ( $sources as $source ) {
				$src[] = array(
					'url'    => esc_url_raw( $source[1] ),
					'format' => strtolower( $source[2] ),
				);
			}
			if ( ! $src ) {
				continue;
			}
			$weight  = preg_match( '/^(\d{3})(?:\s+(\d{3}))?$/', trim( $props['font-weight'] ?? '400' ), $m ) ? $m[1] . ( isset( $m[2] ) ? ' ' . $m[2] : '' ) : '400';
			$faces[] = array(
				'weight'        => $weight,
				'style'         => ( 'italic' === strtolower( trim( $props['font-style'] ?? '' ) ) ) ? 'italic' : 'normal',
				'unicode_range' => trim( (string) preg_replace( '/[^Uu+0-9A-Fa-f?,\s-]/', '', $props['unicode-range'] ?? '' ) ),
				'src'           => $src,
			);
		}
		return $faces;
	}
}
