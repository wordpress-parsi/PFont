<?php
/**
 * Immutable font value object.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Core;

use UniversalCustomFonts\Fonts\CdnFonts;
use UniversalCustomFonts\Helpers\FontHelper;

defined( 'ABSPATH' ) || exit;

/**
 * One entry of the central font library.
 */
final class Font {

	public const SOURCE_CDN    = 'cdn';
	public const SOURCE_UPLOAD = 'upload';
	public const INTEGRATIONS  = array( 'tinymce', 'block_editor', 'elementor', 'astra', 'customizer' );
	public const STYLES        = array( 'normal', 'italic' );
	public const FORMATS       = array( 'woff2', 'woff', 'ttf', 'otf' );
	public const DISPLAYS      = array( 'swap', 'fallback', 'optional', 'block', 'auto' );

	/**
	 * Normalized data.
	 *
	 * @var array
	 */
	private array $data;

	/**
	 * Constructor.
	 *
	 * @param array $data Raw font data.
	 */
	public function __construct( array $data ) {
		$this->data = self::normalize( $data );
	}

	/**
	 * Storage schema with defaults.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'id'           => '',
			'name'         => '',
			'family'       => '',
			'fallback'     => 'sans-serif',
			'source'       => self::SOURCE_CDN,
			'preset'       => '',
			'cdn_url'      => '',
			'hosting'      => 'remote',
			'local'        => array(),
			'weights'      => array( 400 ),
			'styles'       => array( 'normal' ),
			'files'        => array(),
			'display'      => 'swap',
			'enabled'      => true,
			'integrations' => array_fill_keys( self::INTEGRATIONS, true ),
			'created'      => 0,
			'updated'      => 0,
		);
	}

	/**
	 * Normalize raw data into the storage schema.
	 *
	 * @param array $data Raw data.
	 * @return array
	 */
	public static function normalize( array $data ): array {
		$font = array_merge( self::defaults(), array_intersect_key( $data, self::defaults() ) );

		$font['id']       = sanitize_key( (string) $font['id'] );
		$font['name']     = (string) $font['name'];
		$font['family']   = (string) $font['family'];
		$font['fallback'] = '' !== (string) $font['fallback'] ? (string) $font['fallback'] : 'sans-serif';
		$font['source']   = self::SOURCE_UPLOAD === $font['source'] ? self::SOURCE_UPLOAD : self::SOURCE_CDN;
		$font['preset']   = sanitize_key( (string) $font['preset'] );
		$font['cdn_url']  = (string) $font['cdn_url'];
		$font['hosting']  = 'local' === $font['hosting'] ? 'local' : 'remote';
		$font['weights']  = FontHelper::sanitize_weights( (array) $font['weights'] );
		$font['styles']   = array_values( array_intersect( self::STYLES, (array) $font['styles'] ) );
		$font['display']  = in_array( $font['display'], self::DISPLAYS, true ) ? $font['display'] : 'swap';
		$font['enabled']  = (bool) $font['enabled'];
		$font['files']    = is_array( $font['files'] ) ? $font['files'] : array();
		$font['local']    = is_array( $font['local'] ) ? $font['local'] : array();
		$font['created']  = (int) $font['created'];
		$font['updated']  = (int) $font['updated'];

		if ( ! $font['weights'] ) {
			$font['weights'] = array( 400 );
		}
		if ( ! $font['styles'] ) {
			$font['styles'] = array( 'normal' );
		}

		$integrations = array();
		foreach ( self::INTEGRATIONS as $key ) {
			$integrations[ $key ] = ! empty( $font['integrations'][ $key ] );
		}
		$font['integrations'] = $integrations;

		return $font;
	}

	/**
	 * Font ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->data['id'];
	}

	/**
	 * Label shown in dropdowns.
	 *
	 * @return string
	 */
	public function name(): string {
		return '' !== $this->data['name'] ? $this->data['name'] : $this->data['family'];
	}

	/**
	 * CSS font-family name.
	 *
	 * @return string
	 */
	public function family(): string {
		return $this->data['family'];
	}

	/**
	 * Fallback stack, e.g. "sans-serif" or "Tahoma, sans-serif".
	 *
	 * @return string
	 */
	public function fallback(): string {
		return $this->data['fallback'];
	}

	/**
	 * Generic family at the end of the fallback.
	 *
	 * @return string
	 */
	public function generic(): string {
		return FontHelper::generic_from_fallback( $this->fallback() );
	}

	/**
	 * Full CSS stack.
	 *
	 * @return string
	 */
	public function css_stack(): string {
		return "'" . $this->family() . "', " . $this->fallback();
	}

	/**
	 * Source type.
	 *
	 * @return string
	 */
	public function source(): string {
		return $this->data['source'];
	}

	/**
	 * Whether this is a CDN font.
	 *
	 * @return bool
	 */
	public function is_cdn(): bool {
		return self::SOURCE_CDN === $this->data['source'];
	}

	/**
	 * Whether this font was uploaded.
	 *
	 * @return bool
	 */
	public function is_upload(): bool {
		return self::SOURCE_UPLOAD === $this->data['source'];
	}

	/**
	 * Whether a CDN font has been downloaded to this server.
	 *
	 * @return bool
	 */
	public function is_self_hosted(): bool {
		return $this->is_cdn() && 'local' === $this->data['hosting'] && ! empty( $this->data['local']['faces'] );
	}

	/**
	 * Whether files are served from this site (no third-party request).
	 *
	 * @return bool
	 */
	public function is_local(): bool {
		return $this->is_upload() || $this->is_self_hosted();
	}

	/**
	 * Master switch.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->data['enabled'];
	}

	/**
	 * Whether the font is enabled for an integration.
	 *
	 * @param string $integration Integration key.
	 * @return bool
	 */
	public function enabled_for( string $integration ): bool {
		return $this->is_enabled() && ! empty( $this->data['integrations'][ $integration ] );
	}

	/**
	 * Integration toggles.
	 *
	 * @return array<string,bool>
	 */
	public function integrations(): array {
		return $this->data['integrations'];
	}

	/**
	 * Available weights (from files for uploads, from settings for CDN fonts).
	 *
	 * @return int[]
	 */
	public function weights(): array {
		if ( $this->is_upload() ) {
			$weights = FontHelper::sanitize_weights( array_keys( $this->data['files'] ) );
			return $weights ? $weights : array( 400 );
		}
		return $this->data['weights'];
	}

	/**
	 * Available styles.
	 *
	 * @return string[]
	 */
	public function styles(): array {
		if ( $this->is_upload() ) {
			$styles = array();
			foreach ( $this->data['files'] as $by_style ) {
				foreach ( array_keys( (array) $by_style ) as $style ) {
					$styles[ $style ] = $style;
				}
			}
			$styles = array_values( array_intersect( self::STYLES, $styles ) );
			return $styles ? $styles : array( 'normal' );
		}
		return $this->data['styles'];
	}

	/**
	 * Whether a weight/style combination exists.
	 *
	 * @param int    $weight Weight.
	 * @param string $style  Style.
	 * @return bool
	 */
	public function has_variant( int $weight, string $style ): bool {
		if ( $this->is_upload() ) {
			return ! empty( $this->data['files'][ $weight ][ $style ] );
		}
		return in_array( $weight, $this->data['weights'], true ) && in_array( $style, $this->data['styles'], true );
	}

	/**
	 * Variant strings such as "400", "700", "400italic".
	 *
	 * @param string $italic_suffix Suffix for italic variants.
	 * @return string[]
	 */
	public function variant_strings( string $italic_suffix = 'italic' ): array {
		$out = array();
		foreach ( $this->weights() as $weight ) {
			if ( $this->has_variant( $weight, 'normal' ) ) {
				$out[] = (string) $weight;
			}
			if ( $this->has_variant( $weight, 'italic' ) ) {
				$out[] = $weight . $italic_suffix;
			}
		}
		return $out;
	}

	/**
	 * Uploaded files: [weight][style][format] => relative path.
	 *
	 * @return array
	 */
	public function files(): array {
		return $this->data['files'];
	}

	/**
	 * Self-hosted faces.
	 *
	 * @return array
	 */
	public function local_faces(): array {
		return isset( $this->data['local']['faces'] ) && is_array( $this->data['local']['faces'] ) ? $this->data['local']['faces'] : array();
	}

	/**
	 * Preset ID.
	 *
	 * @return string
	 */
	public function preset(): string {
		return $this->data['preset'];
	}

	/**
	 * Preset definition, if any.
	 *
	 * @return array|null
	 */
	public function preset_data(): ?array {
		return '' !== $this->data['preset'] ? CdnFonts::preset( $this->data['preset'] ) : null;
	}

	/**
	 * Family name the CDN knows this font by.
	 *
	 * @return string
	 */
	public function remote_family(): string {
		$preset = $this->preset_data();
		return $preset ? $preset['family'] : $this->family();
	}

	/**
	 * Custom stylesheet URL.
	 *
	 * @return string
	 */
	public function cdn_url(): string {
		return $this->data['cdn_url'];
	}

	/**
	 * The font-display value.
	 *
	 * @return string
	 */
	public function display(): string {
		return $this->data['display'];
	}

	/**
	 * Character subsets (presets only; uploads are unknown).
	 *
	 * @return string[]
	 */
	public function subsets(): array {
		$preset = $this->preset_data();
		return $preset ? $preset['subsets'] : array( 'latin' );
	}

	/**
	 * Slug used in theme.json presets.
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'ucf-' . $this->id();
	}

	/**
	 * Raw value.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get( string $key ): mixed {
		return $this->data[ $key ] ?? null;
	}

	/**
	 * Storage array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return $this->data;
	}

	/**
	 * Copy with changes.
	 *
	 * @param array $changes Changes.
	 * @return self
	 */
	public function with( array $changes ): self {
		return new self( array_merge( $this->data, $changes ) );
	}
}
