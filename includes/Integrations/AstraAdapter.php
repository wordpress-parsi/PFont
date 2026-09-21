<?php
/**
 * Astra theme.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Integrations;

use UniversalCustomFonts\Core\Font;
use UniversalCustomFonts\Core\FontLoader;
use UniversalCustomFonts\Core\FontRegistry;
use UniversalCustomFonts\Helpers\FontHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Verified against Astra 4.13.12:
 * - astra_system_fonts feeds the Customizer dropdown ("Other System Fonts");
 * - astra_render_fonts receives every font the page uses; Astra sends anything that is not a
 *   system font to Google, so local fonts are removed here and served by UCF instead.
 */
final class AstraAdapter extends AbstractAdapter {

	/**
	 * Normalized families of ours that Astra also lists as Google fonts.
	 *
	 * @var array|null
	 */
	private ?array $google = null;

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'astra';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Astra';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return IntegrationDetector::astra();
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'ASTRA_THEME_VERSION' ) ? (string) ASTRA_THEME_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$this->hook( 'filter', 'astra_system_fonts', array( $this, 'system_fonts' ), 20 );
		$this->hook( 'filter', 'astra_render_fonts', array( $this, 'render_fonts' ), 20 );
		$this->hook( 'action', 'enqueue_block_assets', array( $this, 'editor_assets' ) );
	}

	/**
	 * Add fonts to the Customizer list, without duplicating Astra's own Google entries.
	 *
	 * @param mixed $fonts name => [fallback, weights].
	 * @return mixed
	 */
	public function system_fonts( mixed $fonts ): mixed {
		if ( ! is_array( $fonts ) ) {
			return $fonts;
		}
		$taken = array();
		foreach ( array_keys( $fonts ) as $name ) {
			$taken[ FontHelper::normalize_family_name( (string) $name ) ] = true;
		}
		foreach ( $this->fonts() as $font ) {
			$key = FontHelper::normalize_family_name( $font->family() );
			if ( isset( $taken[ $key ] ) || $this->in_astra_google( $font ) ) {
				continue;
			}
			$fonts[ $font->family() ] = array(
				'fallback' => $font->fallback(),
				'weights'  => $font->variant_strings(),
			);
		}
		return $fonts;
	}

	/**
	 * Route fonts a page uses: local ones are served by UCF (never Google).
	 *
	 * @param mixed $fonts name => ['variants' => [...]].
	 * @return mixed
	 */
	public function render_fonts( mixed $fonts ): mixed {
		if ( ! is_array( $fonts ) ) {
			return $fonts;
		}
		foreach ( $fonts as $name => $data ) {
			$font = FontRegistry::find_by_family( (string) $name, $this->id() );
			if ( ! $font ) {
				continue;
			}
			$variants = is_array( $data ) && isset( $data['variants'] ) ? (array) $data['variants'] : (array) $data;
			$weights  = FontHelper::sanitize_weights( $variants );
			if ( $font->is_local() ) {
				unset( $fonts[ $name ] );
				FontLoader::enqueue( $font->id(), $weights );
			} elseif ( ! $this->in_astra_google( $font ) ) {
				FontLoader::enqueue( $font->id(), $weights );
			}
		}
		return $fonts;
	}

	/**
	 * Customizer preview: fonts must be ready before they are picked.
	 */
	public function enqueue_frontend(): void {
		if ( is_customize_preview() ) {
			$this->enqueue_all();
		}
	}

	/**
	 * Block editor canvas.
	 */
	public function editor_assets(): void {
		if ( is_admin() && $this->fonts() ) {
			FontLoader::enqueue_for_editor( array_keys( $this->fonts() ) );
		}
	}

	/**
	 * Whether Astra's Google list has this family (cached per Astra version and library).
	 *
	 * @param Font $font Font.
	 * @return bool
	 */
	private function in_astra_google( Font $font ): bool {
		if ( null === $this->google ) {
			$ours = array();
			foreach ( $this->fonts() as $item ) {
				$ours[] = FontHelper::normalize_family_name( $item->family() );
			}
			sort( $ours );
			$key    = md5( $this->version() . '|' . implode( ',', $ours ) );
			$cache  = get_transient( 'ucf_native_index' );
			$cache  = is_array( $cache ) ? $cache : array();
			$cached = $cache['astra'] ?? null;
			if ( is_array( $cached ) && ( $cached['key'] ?? '' ) === $key ) {
				$this->google = (array) $cached['names'];
			} else {
				$this->google = array();
				if ( method_exists( 'Astra_Font_Families', 'get_google_fonts' ) ) {
					foreach ( (array) \Astra_Font_Families::get_google_fonts() as $name => $value ) {
						// Astra 4.x stores a list of single-entry arrays: [ [ 'Family' => [variants, category] ], ... ].
						if ( is_string( $name ) ) {
							$family = $name;
						} elseif ( is_array( $value ) && isset( $value['family'] ) ) {
							$family = (string) $value['family'];
						} elseif ( is_array( $value ) && is_string( array_key_first( $value ) ) ) {
							$family = (string) array_key_first( $value );
						} else {
							$family = is_string( $value ) ? $value : '';
						}
						$family = FontHelper::normalize_family_name( $family );
						if ( in_array( $family, $ours, true ) ) {
							$this->google[ $family ] = true;
						}
					}
				}
				$cache['astra'] = array(
					'key'   => $key,
					'names' => $this->google,
				);
				set_transient( 'ucf_native_index', $cache, WEEK_IN_SECONDS );
			}
		}
		return isset( $this->google[ FontHelper::normalize_family_name( $font->family() ) ] );
	}

	/**
	 * Explain how this font relates to the platform's own font list.
	 *
	 * @param Font $font Font.
	 * @return string
	 */
	public function native_conflict( Font $font ): string {
		if ( ! $font->enabled_for( $this->id() ) || ! $this->in_astra_google( $font ) ) {
			return '';
		}
		return $font->is_local()
			/* translators: %s: family. */
			? sprintf( __( 'Astra lists “%s” under Google Fonts. Pick it there: PFont serves your local copy and Astra makes no Google request.', 'universal-custom-fonts' ), $font->family() )
			/* translators: %s: family. */
			: sprintf( __( 'Astra already offers “%s” under Google Fonts and loads it itself.', 'universal-custom-fonts' ), $font->family() );
	}
}
