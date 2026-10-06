<?php
/**
 * Validation for font data and uploaded files.
 *
 * @package PFont
 */

namespace PFont\Core;

use PFont\Fonts\CdnFonts;
use PFont\Helpers\FontHelper;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Never trusts extensions or browser MIME types: file contents decide.
 */
final class FontValidator {

	/**
	 * Values that can never be a font family name.
	 */
	private const RESERVED = array( 'inherit', 'initial', 'unset', 'revert', 'revert-layer', 'default', 'none' );

	/**
	 * Extension => MIME type we register.
	 *
	 * @return array<string,string>
	 */
	public static function mime_types(): array {
		return array(
			'woff2' => 'font/woff2',
			'woff'  => 'font/woff',
			'ttf'   => 'font/ttf',
			'otf'   => 'font/otf',
		);
	}

	/**
	 * MIME types that fileinfo may legitimately report for real font files.
	 *
	 * @return string[]
	 */
	private static function acceptable_real_mimes(): array {
		return array(
			'font/woff2',
			'font/woff',
			'font/ttf',
			'font/otf',
			'font/sfnt',
			'application/font-woff',
			'application/font-woff2',
			'application/x-font-woff',
			'application/x-woff',
			'application/x-font-ttf',
			'application/x-font-truetype',
			'application/x-font-otf',
			'application/x-font-opentype',
			'application/font-sfnt',
			'application/vnd.ms-opentype',
			'application/octet-stream',
		);
	}

	/**
	 * Largest accepted font file in bytes.
	 *
	 * @return int
	 */
	public static function max_upload_size(): int {
		/**
		 * Filter the maximum font file size in bytes (default 10 MB, never above the server limit).
		 *
		 * @param int $bytes Size.
		 */
		$limit = (int) apply_filters( 'pfont_max_upload_size', 10 * MB_IN_BYTES );
		return (int) min( $limit, wp_max_upload_size() );
	}

	/**
	 * Validate a CSS family name.
	 *
	 * @param string $family Raw family.
	 * @return string|WP_Error
	 */
	public static function validate_family( string $family ): string|WP_Error {
		$family = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $family ) ) );
		$family = trim( $family, "'\" " );

		if ( '' === $family ) {
			return new WP_Error( 'pfont_family_empty', __( 'Enter a CSS font family name.', 'pfont' ) );
		}
		if ( mb_strlen( $family ) > 80 ) {
			return new WP_Error( 'pfont_family_long', __( 'The font family name must be 80 characters or fewer.', 'pfont' ) );
		}
		if ( ! preg_match( '/^[\p{L}_][\p{L}\p{N}_ \-]*$/u', $family ) ) {
			return new WP_Error( 'pfont_family_chars', __( 'Use only letters, numbers, spaces, hyphens and underscores in the font family name, starting with a letter.', 'pfont' ) );
		}
		foreach ( explode( ' ', $family ) as $word ) {
			if ( preg_match( '/^-?\d/', $word ) ) {
				return new WP_Error( 'pfont_family_digit', __( 'Each word of the font family name must start with a letter (for example "Font 2" is not a valid unquoted CSS name).', 'pfont' ) );
			}
		}
		$lower = strtolower( $family );
		if ( in_array( $lower, self::RESERVED, true ) || in_array( $lower, FontHelper::GENERIC, true ) ) {
			return new WP_Error( 'pfont_family_reserved', __( 'That name is reserved by CSS. Choose a real font name.', 'pfont' ) );
		}
		return $family;
	}

	/**
	 * Sanitize a fallback stack to known-safe tokens, always ending in a generic family.
	 *
	 * @param string $fallback Raw fallback.
	 * @return string
	 */
	public static function sanitize_fallback( string $fallback ): string {
		$clean = array();
		foreach ( explode( ',', $fallback ) as $part ) {
			$part = trim( trim( $part ), "'\"" );
			if ( '' === $part ) {
				continue;
			}
			if ( in_array( strtolower( $part ), FontHelper::GENERIC, true ) ) {
				$clean[] = strtolower( $part );
				continue;
			}
			$valid = self::validate_family( $part );
			if ( ! is_wp_error( $valid ) ) {
				$clean[] = str_contains( $valid, ' ' ) ? "'" . $valid . "'" : $valid;
			}
		}
		$clean = array_values( array_unique( $clean ) );
		if ( ! $clean || ! in_array( strtolower( trim( (string) end( $clean ), "'" ) ), FontHelper::GENERIC, true ) ) {
			$clean[] = 'sans-serif';
		}
		return implode( ', ', array_slice( $clean, -6 ) );
	}

	/**
	 * Sanitize the raw add/edit form array as soon as it arrives.
	 *
	 * Only known keys survive, every value gets the most restrictive sanitizer for its type, and
	 * nesting is limited to the shapes the form produces. The result is safe to keep in a transient
	 * and to pass on to sanitize_font_input(), which validates it.
	 *
	 * @param array $raw Unslashed $_POST['pfont'] array.
	 * @return array
	 */
	public static function sanitize_raw_input( array $raw ): array {
		$clean = array();

		foreach ( array( 'id', 'after', 'source', 'preset', 'display' ) as $key ) {
			if ( isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ) {
				$clean[ $key ] = sanitize_key( (string) $raw[ $key ] );
			}
		}
		foreach ( array( 'name', 'family', 'fallback' ) as $key ) {
			if ( isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $raw[ $key ] );
			}
		}
		if ( isset( $raw['weights'] ) ) {
			$clean['weights'] = array_values( array_map( 'absint', array_filter( (array) $raw['weights'], 'is_scalar' ) ) );
		}
		if ( isset( $raw['styles'] ) ) {
			$clean['styles'] = array_values( array_map( 'sanitize_key', array_filter( (array) $raw['styles'], 'is_scalar' ) ) );
		}
		if ( isset( $raw['enabled'] ) ) {
			$clean['enabled'] = self::flag( $raw['enabled'] );
		}
		if ( isset( $raw['integrations'] ) && is_array( $raw['integrations'] ) ) {
			$clean['integrations'] = array();
			foreach ( $raw['integrations'] as $key => $value ) {
				$key = sanitize_key( (string) $key );
				if ( in_array( $key, Font::INTEGRATIONS, true ) ) {
					$clean['integrations'][ $key ] = self::flag( $value );
				}
			}
		}
		if ( isset( $raw['existing'] ) && is_array( $raw['existing'] ) ) {
			$clean['existing'] = array();
			foreach ( $raw['existing'] as $row => $changes ) {
				$row = sanitize_key( (string) $row );
				if ( '' === $row || ! is_array( $changes ) ) {
					continue;
				}
				$entry = array();
				if ( isset( $changes['delete'] ) ) {
					$entry['delete'] = self::flag( $changes['delete'] );
				}
				if ( isset( $changes['remove'] ) && is_array( $changes['remove'] ) ) {
					$entry['remove'] = array();
					foreach ( $changes['remove'] as $format => $value ) {
						$format = sanitize_key( (string) $format );
						if ( in_array( $format, Font::FORMATS, true ) ) {
							$entry['remove'][ $format ] = self::flag( $value );
						}
					}
				}
				$clean['existing'][ $row ] = $entry;
			}
		}
		if ( isset( $raw['new'] ) && is_array( $raw['new'] ) ) {
			$clean['new'] = array();
			foreach ( $raw['new'] as $row => $meta ) {
				if ( ! is_array( $meta ) ) {
					continue;
				}
				$clean['new'][ absint( $row ) ] = array(
					'weight' => isset( $meta['weight'] ) && is_scalar( $meta['weight'] ) ? absint( $meta['weight'] ) : 400,
					'style'  => isset( $meta['style'] ) && is_scalar( $meta['style'] ) ? sanitize_key( (string) $meta['style'] ) : 'normal',
				);
			}
		}

		return $clean;
	}

	/**
	 * A checkbox value as a boolean (arrays and empty strings are false).
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function flag( mixed $value ): bool {
		return is_scalar( $value ) && ! empty( $value );
	}

	/**
	 * Validate the add/edit form (file handling happens separately).
	 *
	 * @param array     $input    Output of sanitize_raw_input().
	 * @param Font|null $existing Font being edited.
	 * @return array|WP_Error Normalized partial record.
	 */
	public static function sanitize_font_input( array $input, ?Font $existing = null ): array|WP_Error {
		$errors = new WP_Error();
		$source = ( isset( $input['source'] ) && Font::SOURCE_UPLOAD === $input['source'] ) ? Font::SOURCE_UPLOAD : Font::SOURCE_CDN;
		$preset = null;
		if ( Font::SOURCE_CDN === $source && ! empty( $input['preset'] ) ) {
			$preset = CdnFonts::preset( sanitize_key( (string) $input['preset'] ) );
		}

		$family_raw = isset( $input['family'] ) ? (string) $input['family'] : '';
		if ( '' === trim( $family_raw ) && $preset ) {
			$family_raw = $preset['family'];
		}
		$family = self::validate_family( $family_raw );
		if ( is_wp_error( $family ) ) {
			$errors->merge_from( $family );
			$family = '';
		}

		if ( '' !== $family ) {
			$other = FontRegistry::find_by_family( $family );
			if ( $other && ( ! $existing || $other->id() !== $existing->id() ) ) {
				$errors->add(
					'pfont_family_duplicate',
					sprintf(
						/* translators: 1: family name, 2: suggested alias. */
						__( 'Another font in your library already uses the CSS family “%1$s”. Use a different alias, for example “%2$s”.', 'pfont' ),
						$family,
						$family . ' Local'
					)
				);
			}
		}

		$weights = FontHelper::sanitize_weights( isset( $input['weights'] ) ? (array) $input['weights'] : array() );
		$styles  = array_values( array_intersect( Font::STYLES, isset( $input['styles'] ) ? (array) $input['styles'] : array( 'normal' ) ) );
		$styles  = $styles ? $styles : array( 'normal' );
		$hosting = $existing ? (string) $existing->get( 'hosting' ) : 'remote';

		if ( Font::SOURCE_CDN === $source ) {
			if ( $preset ) {
				$weights = array_values( array_intersect( $weights, $preset['weights'] ) );
				$weights = $weights ? $weights : $preset['weights'];
				$styles  = array_values( array_intersect( $styles, $preset['styles'] ) );
				$styles  = $styles ? $styles : array( 'normal' );
			}
			$weights = $weights ? $weights : array( 400 );

			// A remote Google stylesheet declares the Google family name, so an alias would never match.
			if ( $preset && 'local' !== $hosting && '' !== $family && ! FontHelper::same_family( $family, $preset['family'] ) ) {
				$errors->add(
					'pfont_alias_remote',
					sprintf(
						/* translators: 1: Google family name. */
						__( 'Fonts loaded from the CDN must keep their original name (“%1$s”). To use an alias, save with the original name first, then click “Host on this server”.', 'pfont' ),
						$preset['family']
					)
				);
			}
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		$display = isset( $input['display'] ) ? sanitize_key( (string) $input['display'] ) : 'swap';
		$name    = isset( $input['name'] ) ? sanitize_text_field( (string) $input['name'] ) : '';

		$integrations = array();
		foreach ( Font::INTEGRATIONS as $key ) {
			$integrations[ $key ] = ! empty( $input['integrations'][ $key ] );
		}

		return array(
			'name'         => '' !== $name ? $name : $family,
			'family'       => $family,
			'fallback'     => self::sanitize_fallback( isset( $input['fallback'] ) ? (string) $input['fallback'] : ( $preset['fallback'] ?? 'sans-serif' ) ),
			'source'       => $source,
			'preset'       => $preset ? $preset['id'] : '',
			'weights'      => $weights,
			'styles'       => $styles,
			'display'      => in_array( $display, Font::DISPLAYS, true ) ? $display : 'swap',
			'enabled'      => ! empty( $input['enabled'] ),
			'integrations' => $integrations,
		);
	}

	/**
	 * Detect the real font format from a file's first bytes.
	 *
	 * @param string $path Absolute path.
	 * @return string|null woff2|woff|ttf|otf or null.
	 */
	public static function detect_format( string $path ): ?string {
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$head = file_get_contents( $path, false, null, 0, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading 4 bytes of a local temp file.
		return false === $head ? null : self::detect_format_from_bytes( $head );
	}

	/**
	 * Detect the font format from the signature bytes.
	 *
	 * @param string $bytes At least the first four bytes.
	 * @return string|null
	 */
	public static function detect_format_from_bytes( string $bytes ): ?string {
		$signature = substr( $bytes, 0, 4 );
		return match ( true ) {
			'wOF2' === $signature => 'woff2',
			'wOFF' === $signature => 'woff',
			"\x00\x01\x00\x00" === $signature, 'true' === $signature => 'ttf',
			'OTTO' === $signature => 'otf',
			default => null,
		};
	}

	/**
	 * Validate one uploaded file array from $_FILES.
	 *
	 * @param array $file     Single-file array.
	 * @param bool  $sideload True when the file did not arrive via HTTP POST (tests, imports).
	 * @return array|WP_Error Validated file info.
	 */
	public static function validate_upload( array $file, bool $sideload = false ): array|WP_Error {
		$error = isset( $file['error'] ) && ! is_array( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $error ) {
			$messages = array(
				UPLOAD_ERR_INI_SIZE   => __( 'The file is larger than the server allows.', 'pfont' ),
				UPLOAD_ERR_FORM_SIZE  => __( 'The file is larger than the form allows.', 'pfont' ),
				UPLOAD_ERR_PARTIAL    => __( 'The file was only partially uploaded.', 'pfont' ),
				UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'pfont' ),
				UPLOAD_ERR_NO_TMP_DIR => __( 'The server has no temporary folder.', 'pfont' ),
				UPLOAD_ERR_CANT_WRITE => __( 'The server could not write the file.', 'pfont' ),
				UPLOAD_ERR_EXTENSION  => __( 'A PHP extension stopped the upload.', 'pfont' ),
			);
			return new WP_Error( 'pfont_upload_error', $messages[ $error ] ?? __( 'Upload failed.', 'pfont' ) );
		}

		$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		if ( '' === $tmp || ( ! $sideload && ! is_uploaded_file( $tmp ) ) || ! is_file( $tmp ) ) {
			return new WP_Error( 'pfont_upload_invalid', __( 'The uploaded file could not be verified.', 'pfont' ) );
		}

		$size = (int) filesize( $tmp );
		if ( $size <= 0 ) {
			return new WP_Error( 'pfont_upload_empty', __( 'The file is empty.', 'pfont' ) );
		}
		if ( $size > self::max_upload_size() ) {
			return new WP_Error(
				'pfont_upload_size',
				sprintf(
					/* translators: %s: size limit, e.g. "10 MB". */
					__( 'The file is too large. The limit is %s.', 'pfont' ),
					size_format( self::max_upload_size() )
				)
			);
		}

		$name = sanitize_file_name( isset( $file['name'] ) ? (string) $file['name'] : '' );
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! isset( self::mime_types()[ $ext ] ) ) {
			return new WP_Error( 'pfont_upload_ext', __( 'Only .woff2, .woff, .ttf and .otf files are accepted.', 'pfont' ) );
		}

		$format = self::detect_format( $tmp );
		if ( null === $format ) {
			return new WP_Error( 'pfont_upload_signature', __( 'This file is not a valid font: its contents do not match any supported font format.', 'pfont' ) );
		}
		$sfnt = array( 'ttf', 'otf' );
		if ( $ext !== $format && ! ( in_array( $ext, $sfnt, true ) && in_array( $format, $sfnt, true ) ) ) {
			return new WP_Error(
				'pfont_upload_mismatch',
				sprintf(
					/* translators: 1: file extension, 2: detected format. */
					__( 'The file is named .%1$s but its contents are %2$s. Rename or re-export it.', 'pfont' ),
					$ext,
					strtoupper( $format )
				)
			);
		}

		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$real  = $finfo ? finfo_file( $finfo, $tmp ) : false;
			if ( $finfo ) {
				finfo_close( $finfo );
			}
			if ( is_string( $real ) && '' !== $real && ! in_array( $real, self::acceptable_real_mimes(), true ) ) {
				return new WP_Error(
					'pfont_upload_mime',
					sprintf(
						/* translators: %s: MIME type reported by the server. */
						__( 'The server identified this file as %s, which is not a font.', 'pfont' ),
						$real
					)
				);
			}
		}

		return array(
			'tmp_name' => $tmp,
			'format'   => $format,
			'ext'      => $format,
			'mime'     => self::mime_types()[ $format ],
			'size'     => $size,
		);
	}
}
