<?php
/**
 * Processes the upload part of the add/edit form.
 *
 * @package PFont
 */

namespace PFont\Fonts;

use PFont\Core\Font;
use PFont\Core\FontValidator;
use PFont\Helpers\FontHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Applies file removals, replacements and new weight rows to a font's file map.
 */
final class UploadedFonts {

	/**
	 * Normalize a nested $_FILES field into [row][format] => single-file array.
	 *
	 * @param array $field One entry of $_FILES (with name/type/tmp_name/error/size sub-arrays).
	 * @return array
	 */
	public static function normalize_files_field( array $field ): array {
		$out = array();
		if ( ! isset( $field['name'] ) || ! is_array( $field['name'] ) ) {
			return $out;
		}
		foreach ( $field['name'] as $row => $formats ) {
			if ( ! is_array( $formats ) ) {
				continue;
			}
			foreach ( array_keys( $formats ) as $format ) {
				$error = (int) ( $field['error'][ $row ][ $format ] ?? UPLOAD_ERR_NO_FILE );
				if ( UPLOAD_ERR_NO_FILE === $error ) {
					continue;
				}
				$name = $field['name'][ $row ][ $format ] ?? '';
				$type = $field['type'][ $row ][ $format ] ?? '';
				$tmp  = $field['tmp_name'][ $row ][ $format ] ?? '';
				// tmp_name stays as PHP wrote it: FontValidator::validate_upload() rejects anything is_uploaded_file() does not know.
				$out[ sanitize_key( (string) $row ) ][ sanitize_key( (string) $format ) ] = array(
					'name'     => sanitize_file_name( is_scalar( $name ) ? (string) $name : '' ),
					'type'     => sanitize_mime_type( is_scalar( $type ) ? (string) $type : '' ),
					'tmp_name' => is_scalar( $tmp ) ? (string) $tmp : '',
					'error'    => $error,
					'size'     => absint( $field['size'][ $row ][ $format ] ?? 0 ),
				);
			}
		}
		return $out;
	}

	/**
	 * Apply form changes to a file map.
	 *
	 * @param string $font_id        Font ID.
	 * @param array  $files          Current map [weight][style][format] => relative path.
	 * @param array  $input          Form input (existing/new keys).
	 * @param array  $existing_files Normalized uploads for existing rows, keyed "400-normal".
	 * @param array  $new_files      Normalized uploads for new rows, keyed by row index.
	 * @param bool   $sideload       Use sideload (tests/imports).
	 * @return array{files: array, errors: string[], stored: int}
	 */
	public static function apply( string $font_id, array $files, array $input, array $existing_files, array $new_files, bool $sideload = false ): array {
		$errors = array();
		$stored = 0;

		foreach ( (array) ( $input['existing'] ?? array() ) as $key => $changes ) {
			list( $weight, $style ) = self::split_key( (string) $key );
			if ( ! $weight || ! isset( $files[ $weight ][ $style ] ) ) {
				continue;
			}
			if ( ! empty( $changes['delete'] ) ) {
				foreach ( (array) $files[ $weight ][ $style ] as $relative ) {
					FontStorage::delete_relative( (string) $relative );
				}
				unset( $files[ $weight ][ $style ] );
				continue;
			}
			foreach ( array_keys( (array) ( $changes['remove'] ?? array() ) ) as $format ) {
				if ( isset( $files[ $weight ][ $style ][ $format ] ) ) {
					FontStorage::delete_relative( (string) $files[ $weight ][ $style ][ $format ] );
					unset( $files[ $weight ][ $style ][ $format ] );
				}
			}
		}

		foreach ( $existing_files as $key => $uploads ) {
			list( $weight, $style ) = self::split_key( (string) $key );
			if ( $weight ) {
				$stored += self::store_all( $font_id, $files, $weight, $style, $uploads, $errors, $sideload );
			}
		}

		foreach ( $new_files as $row => $uploads ) {
			$meta   = (array) ( $input['new'][ $row ] ?? array() );
			$weight = FontHelper::parse_weight( $meta['weight'] ?? 400 );
			$style  = ( isset( $meta['style'] ) && 'italic' === $meta['style'] ) ? 'italic' : 'normal';
			if ( ! in_array( $weight, FontHelper::WEIGHTS, true ) ) {
				$errors[] = __( 'A new row had an invalid weight and was skipped.', 'pfont' );
				continue;
			}
			$stored += self::store_all( $font_id, $files, $weight, $style, $uploads, $errors, $sideload );
		}

		foreach ( $files as $weight => $by_style ) {
			foreach ( (array) $by_style as $style => $formats ) {
				if ( ! $formats ) {
					unset( $files[ $weight ][ $style ] );
				}
			}
			if ( empty( $files[ $weight ] ) ) {
				unset( $files[ $weight ] );
			}
		}
		ksort( $files );

		return array(
			'files'  => $files,
			'errors' => $errors,
			'stored' => $stored,
		);
	}

	/**
	 * Validate and store every uploaded format of one weight/style.
	 *
	 * @param string $font_id  Font ID.
	 * @param array  $files    File map (by reference).
	 * @param int    $weight   Weight.
	 * @param string $style    Style.
	 * @param array  $uploads  [slot format] => file array.
	 * @param array  $errors   Errors (by reference).
	 * @param bool   $sideload Sideload mode.
	 * @return int Files stored.
	 */
	private static function store_all( string $font_id, array &$files, int $weight, string $style, array $uploads, array &$errors, bool $sideload ): int {
		$count = 0;
		foreach ( $uploads as $file ) {
			$label     = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
			$validated = FontValidator::validate_upload( (array) $file, $sideload );
			if ( is_wp_error( $validated ) ) {
				/* translators: 1: file name, 2: error. */
				$errors[] = sprintf( __( '%1$s: %2$s', 'pfont' ), $label, $validated->get_error_message() );
				continue;
			}
			$format   = $validated['format'];
			$previous = $files[ $weight ][ $style ][ $format ] ?? '';
			$relative = FontStorage::store_upload( $validated, $font_id, $weight, $style, $sideload );
			if ( is_wp_error( $relative ) ) {
				/* translators: 1: file name, 2: error. */
				$errors[] = sprintf( __( '%1$s: %2$s', 'pfont' ), $label, $relative->get_error_message() );
				continue;
			}
			if ( '' !== $previous && $previous !== $relative ) {
				FontStorage::delete_relative( (string) $previous );
			}
			$files[ $weight ][ $style ][ $format ] = $relative;
			++$count;
		}
		return $count;
	}

	/**
	 * Split "700-italic" into [700, 'italic'].
	 *
	 * @param string $key Row key.
	 * @return array{0:int,1:string}
	 */
	private static function split_key( string $key ): array {
		$parts  = explode( '-', $key, 2 );
		$weight = FontHelper::parse_weight( $parts[0] );
		$style  = ( isset( $parts[1] ) && 'italic' === $parts[1] ) ? 'italic' : 'normal';
		return array( in_array( $weight, FontHelper::WEIGHTS, true ) ? $weight : 0, $style );
	}

	/**
	 * Delete every uploaded file of a font.
	 *
	 * @param Font $font Font.
	 */
	public static function delete_all( Font $font ): void {
		FontStorage::delete_dir( 'uploads/' . $font->id() );
	}
}
