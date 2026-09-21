<?php
/**
 * Read API for the central font library. Integrations only talk to this class.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Core;

use UniversalCustomFonts\Helpers\FontHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for which fonts exist and where they may be used.
 */
final class FontRegistry {

	/**
	 * Request cache of Font objects keyed by ID.
	 *
	 * @var Font[]|null
	 */
	private static ?array $fonts = null;

	/**
	 * Every font, including disabled ones.
	 *
	 * @return Font[]
	 */
	public static function all(): array {
		if ( null === self::$fonts ) {
			/**
			 * Filter the raw font records before use. Developers may add fonts in code.
			 *
			 * @param array $fonts Raw records keyed by ID.
			 */
			$raw   = apply_filters( 'pfont_registered_fonts', FontRepository::all() );
			$raw   = apply_filters_deprecated( 'ucf_registered_fonts', array( $raw ), '1.4.0', 'pfont_registered_fonts' );
			$fonts = array();
			foreach ( (array) $raw as $id => $data ) {
				if ( ! is_array( $data ) ) {
					continue;
				}
				$data['id'] = $data['id'] ?? (string) $id;
				$font       = new Font( $data );
				if ( '' !== $font->id() && '' !== $font->family() ) {
					$fonts[ $font->id() ] = $font;
				}
			}
			self::$fonts = $fonts;
		}
		return self::$fonts;
	}

	/**
	 * One font.
	 *
	 * @param string $font_id Font ID.
	 * @return Font|null
	 */
	public static function get_font( string $font_id ): ?Font {
		$all = self::all();
		return $all[ $font_id ] ?? null;
	}

	/**
	 * Enabled fonts.
	 *
	 * @return Font[]
	 */
	public static function get_enabled_fonts(): array {
		return array_filter(
			self::all(),
			static function ( Font $font ): bool {
				return $font->is_enabled();
			}
		);
	}

	/**
	 * Enabled fonts switched on for one integration.
	 *
	 * @param string $integration Integration key.
	 * @return Font[]
	 */
	public static function get_fonts_for( string $integration ): array {
		return array_filter(
			self::all(),
			static function ( Font $font ) use ( $integration ): bool {
				return $font->enabled_for( $integration );
			}
		);
	}

	/**
	 * Find a font by CSS family (case/quote/space insensitive).
	 *
	 * @param string $family      Family name or stack.
	 * @param string $integration Optional integration the font must be enabled for.
	 * @return Font|null
	 */
	public static function find_by_family( string $family, string $integration = '' ): ?Font {
		$needle = FontHelper::normalize_family_name( $family );
		if ( '' === $needle ) {
			return null;
		}
		foreach ( self::all() as $font ) {
			if ( FontHelper::normalize_family_name( $font->family() ) !== $needle ) {
				continue;
			}
			if ( '' === $integration || $font->enabled_for( $integration ) ) {
				return $font;
			}
		}
		return null;
	}

	/**
	 * Whether any font is enabled (lets integrations skip all work).
	 *
	 * @return bool
	 */
	public static function has_enabled_fonts(): bool {
		return (bool) self::get_enabled_fonts();
	}

	/**
	 * Clear the request cache.
	 */
	public static function reset(): void {
		self::$fonts = null;
	}
}
