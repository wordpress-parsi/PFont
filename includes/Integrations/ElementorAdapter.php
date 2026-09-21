<?php
/**
 * Elementor.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Integrations;

use UniversalCustomFonts\Core\Font;
use UniversalCustomFonts\Core\FontLoader;
use UniversalCustomFonts\Helpers\FontHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Verified against Elementor 4.2.4 includes/fonts.php:
 * - elementor/fonts/groups adds our group;
 * - elementor/fonts/additional_fonts (merged with array_replace, so a same-named key REPLACES
 *   Elementor's own entry) adds our fonts;
 * - elementor/fonts/print_font_links/{type} fires only for fonts a page actually uses.
 */
final class ElementorAdapter extends AbstractAdapter {

	public const TYPE = 'ucf';

	/**
	 * Why fonts were skipped (debug panel).
	 *
	 * @var array<string,string>
	 */
	private array $skipped = array();

	/**
	 * Cache of Elementor's native fonts, normalized name => type.
	 *
	 * @var array|null
	 */
	private ?array $native = null;

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'elementor';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Elementor';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return IntegrationDetector::elementor();
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'ELEMENTOR_VERSION' ) ? (string) ELEMENTOR_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$this->hook( 'filter', 'elementor/fonts/groups', array( $this, 'add_group' ) );
		$this->hook( 'filter', 'elementor/fonts/additional_fonts', array( $this, 'add_fonts' ), 20 );
		$this->hook( 'action', 'elementor/fonts/print_font_links/' . self::TYPE, array( $this, 'print_font' ) );
		$this->hook( 'action', 'elementor/preview/enqueue_styles', array( $this, 'editor_fonts' ) );
		$this->hook( 'action', 'elementor/editor/after_enqueue_styles', array( $this, 'editor_fonts' ) );
	}

	/**
	 * Add the "PFont" group at the top of the dropdown.
	 *
	 * @param mixed $groups Groups.
	 * @return mixed
	 */
	public function add_group( mixed $groups ): mixed {
		if ( ! is_array( $groups ) || ! $this->fonts() ) {
			return $groups;
		}
		return array( self::TYPE => 'PFont' ) + $groups;
	}

	/**
	 * Add fonts. CDN fonts Elementor already ships are left to Elementor; local copies override
	 * the Google version on purpose (that is how you get GDPR-safe loading in Elementor).
	 *
	 * @param mixed $fonts Additional fonts: family => type.
	 * @return mixed
	 */
	public function add_fonts( mixed $fonts ): mixed {
		if ( ! is_array( $fonts ) ) {
			return $fonts;
		}
		$taken = array();
		foreach ( array_keys( $fonts ) as $name ) {
			$taken[ FontHelper::normalize_family_name( (string) $name ) ] = true;
		}
		$native = $this->native_fonts();
		foreach ( $this->fonts() as $font ) {
			$key = FontHelper::normalize_family_name( $font->family() );
			if ( isset( $taken[ $key ] ) ) {
				$this->skipped[ $font->id() ] = __( 'another plugin already registers this family in Elementor', 'universal-custom-fonts' );
				continue;
			}
			if ( isset( $native[ $key ] ) && ! $font->is_local() ) {
				$this->skipped[ $font->id() ] = __( 'Elementor already ships this CDN font; Elementor loads it', 'universal-custom-fonts' );
				continue;
			}
			$fonts[ $font->family() ] = self::TYPE;
		}
		return $fonts;
	}

	/**
	 * A page uses one of our fonts: queue it.
	 *
	 * @param mixed $font_family Family.
	 */
	public function print_font( mixed $font_family ): void {
		if ( is_string( $font_family ) ) {
			FontLoader::enqueue_family( $font_family, $this->id() );
		}
	}

	/**
	 * Editor panel and preview iframe: load every enabled font.
	 */
	public function editor_fonts(): void {
		if ( $this->fonts() ) {
			FontLoader::enqueue_for_editor( array_keys( $this->fonts() ) );
		}
	}

	/**
	 * Elementor's shipped fonts via its own (private) list, read-only and guarded.
	 *
	 * @return array<string,string>
	 */
	private function native_fonts(): array {
		if ( null !== $this->native ) {
			return $this->native;
		}
		$this->native = array();
		try {
			$method = new \ReflectionMethod( '\Elementor\Fonts', 'get_native_fonts' );
			if ( $method->isStatic() ) {
				foreach ( (array) $method->invoke( null ) as $name => $type ) {
					$this->native[ FontHelper::normalize_family_name( (string) $name ) ] = (string) $type;
				}
			}
		} catch ( \Throwable $e ) {
			$this->native = array();
		}
		return $this->native;
	}

	/**
	 * Explain how this font relates to the platform's own font list.
	 *
	 * @param Font $font Font.
	 * @return string
	 */
	public function native_conflict( Font $font ): string {
		$native = $this->native_fonts();
		$key    = FontHelper::normalize_family_name( $font->family() );
		if ( ! isset( $native[ $key ] ) ) {
			return '';
		}
		return $font->is_local()
			/* translators: %s: family. */
			? sprintf( __( 'Elementor also ships “%s” from Google. Your local copy replaces it in Elementor, so no Google request is made.', 'universal-custom-fonts' ), $font->family() )
			/* translators: %s: family. */
			: sprintf( __( 'Elementor already ships “%s”, so PFont leaves it in Elementor’s own list. Host it locally to serve it from your server instead.', 'universal-custom-fonts' ), $font->family() );
	}

	/**
	 * Skipped fonts and reasons.
	 *
	 * @return array<string,string>
	 */
	public function skipped(): array {
		return $this->skipped;
	}

	/**
	 * {@inheritDoc}
	 */
	public function status(): array {
		$status = parent::status();
		if ( $this->is_available() ) {
			$status['message'] = sprintf(
				/* translators: %s: group name. */
				__( 'Fonts appear in the typography font list under “%s” and load only on pages that use them.', 'universal-custom-fonts' ),
				'PFont'
			);
		}
		return $status;
	}
}
