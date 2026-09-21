<?php
/**
 * Persistence for the font library (ucf_fonts option).
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes font records. No custom tables: one autoloaded option.
 */
final class FontRepository {

	public const OPTION = 'ucf_fonts';

	/**
	 * Request cache.
	 *
	 * @var array|null
	 */
	private static ?array $cache = null;

	/**
	 * All raw records keyed by ID.
	 *
	 * @return array
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$raw         = get_option( self::OPTION, array() );
			self::$cache = is_array( $raw ) ? $raw : array();
		}
		return self::$cache;
	}

	/**
	 * One raw record.
	 *
	 * @param string $id Font ID.
	 * @return array|null
	 */
	public static function get( string $id ): ?array {
		$all = self::all();
		return isset( $all[ $id ] ) && is_array( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Whether an ID exists.
	 *
	 * @param string $id Font ID.
	 * @return bool
	 */
	public static function exists( string $id ): bool {
		return null !== self::get( $id );
	}

	/**
	 * Insert or update a font.
	 *
	 * @param array $font Font data (must contain an id).
	 * @return bool
	 */
	public static function save( array $font ): bool {
		$font = Font::normalize( $font );
		if ( '' === $font['id'] || '' === $font['family'] ) {
			return false;
		}
		$all                = self::all();
		$now                = time();
		$font['created']    = isset( $all[ $font['id'] ]['created'] ) ? (int) $all[ $font['id'] ]['created'] : $now;
		$font['updated']    = $now;
		$all[ $font['id'] ] = $font;
		return self::write( $all );
	}

	/**
	 * Delete a font record (files are handled by the caller).
	 *
	 * @param string $id Font ID.
	 * @return bool
	 */
	public static function delete( string $id ): bool {
		$all = self::all();
		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}
		unset( $all[ $id ] );
		return self::write( $all );
	}

	/**
	 * A free ID based on a name.
	 *
	 * @param string $base Base string.
	 * @return string
	 */
	public static function unique_id( string $base ): string {
		$base = sanitize_key( str_replace( ' ', '-', strtolower( remove_accents( $base ) ) ) );
		$base = '' !== $base ? substr( $base, 0, 40 ) : 'font';
		$id   = $base;
		$i    = 2;
		while ( self::exists( $id ) ) {
			$id = $base . '-' . $i;
			++$i;
		}
		return $id;
	}

	/**
	 * Drop the request cache.
	 */
	public static function flush_cache(): void {
		self::$cache = null;
		FontRegistry::reset();
	}

	/**
	 * Persist and notify.
	 *
	 * @param array $all All records.
	 * @return bool
	 */
	private static function write( array $all ): bool {
		update_option( self::OPTION, $all, true );
		self::$cache = $all;
		FontRegistry::reset();

		/**
		 * Fires after the font library changed.
		 */
		do_action( 'pfont_fonts_changed' );
		do_action_deprecated( 'ucf_fonts_changed', array(), '1.4.0', 'pfont_fonts_changed' );
		return true;
	}
}
