<?php
/**
 * Block Editor (Gutenberg) via theme.json data.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Integrations;

use UniversalCustomFonts\Core\Font;
use UniversalCustomFonts\Core\FontLoader;
use UniversalCustomFonts\Core\FontRegistry;
use UniversalCustomFonts\Fonts\FontStorage;
use UniversalCustomFonts\Helpers\FontHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Appends fonts to the theme's font families (never replaces them and never edits theme files).
 */
final class BlockEditorAdapter extends AbstractAdapter {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'block_editor';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Block Editor (Gutenberg)', 'universal-custom-fonts' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return IntegrationDetector::block_editor();
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return (string) get_bloginfo( 'version' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$this->hook( 'filter', 'wp_theme_json_data_theme', array( $this, 'inject' ) );
		$this->hook( 'action', 'enqueue_block_assets', array( $this, 'editor_assets' ) );
		if ( ! is_admin() ) {
			$this->hook( 'filter', 'render_block', array( $this, 'detect_block' ), 10, 2 );
		}
	}

	/**
	 * Append our families to the theme origin, keeping every family the theme defines.
	 *
	 * @param mixed $theme_json WP_Theme_JSON_Data.
	 * @return mixed
	 */
	public function inject( mixed $theme_json ): mixed {
		$fonts = $this->fonts();
		if ( ! $fonts || ! is_object( $theme_json ) || ! method_exists( $theme_json, 'get_data' ) || ! method_exists( $theme_json, 'update_with' ) ) {
			return $theme_json;
		}
		$data     = $theme_json->get_data();
		$families = self::theme_families( $data['settings']['typography']['fontFamilies'] ?? array() );
		$known    = array();
		foreach ( $families as $family ) {
			if ( isset( $family['fontFamily'] ) ) {
				$known[ FontHelper::normalize_family_name( (string) $family['fontFamily'] ) ] = true;
			}
			if ( isset( $family['slug'] ) ) {
				$known[ 'slug:' . $family['slug'] ] = true;
			}
		}

		$added = 0;
		foreach ( $fonts as $font ) {
			$key = FontHelper::normalize_family_name( $font->family() );
			if ( isset( $known[ $key ] ) || isset( $known[ 'slug:' . $font->slug() ] ) ) {
				continue;
			}
			$entry = array(
				'name'       => $font->name(),
				'slug'       => $font->slug(),
				'fontFamily' => $font->css_stack(),
			);
			// Core prints fontFace entries on every front-end page, so they are only added for the
			// editors; the front end loads a font only when a block or Global Styles uses it.
			$faces = self::editor_context() ? self::font_faces( $font ) : array();
			if ( $faces ) {
				$entry['fontFace'] = $faces;
			}
			$families[] = $entry;
			++$added;
		}
		if ( ! $added ) {
			return $theme_json;
		}

		return $theme_json->update_with(
			array(
				'version'  => \WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'typography' => array(
						'fontFamilies' => $families,
					),
				),
			)
		);
	}

	/**
	 * The theme's own font families as a flat list.
	 *
	 * WP_Theme_JSON_Data::get_data() returns presets already keyed by origin
	 * ( array( 'theme' => array( ...families ) ) ), not the flat list written in theme.json.
	 * Treating that as a list turned the theme's families into one nameless entry, which hid
	 * the fonts and crashed the Font Library ("name is undefined").
	 *
	 * @param mixed $raw Value of settings.typography.fontFamilies.
	 * @return array
	 */
	private static function theme_families( mixed $raw ): array {
		if ( ! is_array( $raw ) || ! $raw ) {
			return array();
		}
		if ( ! self::is_list( $raw ) ) {
			$raw = ( isset( $raw['theme'] ) && is_array( $raw['theme'] ) ) ? $raw['theme'] : array();
		}
		// The theme's entries are kept exactly as they are; only values that are not a family
		// object (a string, an empty array, a nested list) are left out.
		return array_values(
			array_filter(
				$raw,
				static function ( $family ): bool {
					return is_array( $family ) && $family && ! self::is_list( $family );
				}
			)
		);
	}

	/**
	 * Whether an array is a list (keys 0..n-1 in order).
	 *
	 * Same result as PHP 8.1's array_is_list(). WordPress only polyfills that function from
	 * 6.5, and Plugin Check compares it with "Requires at least: 6.4", so it is not used here.
	 *
	 * @param array $value Array.
	 * @return bool
	 */
	private static function is_list( array $value ): bool {
		return array_values( $value ) === $value;
	}

	/**
	 * Font face entries for local fonts (added for the editors only).
	 *
	 * @param Font $font Font.
	 * @return array
	 */
	private static function font_faces( Font $font ): array {
		$faces = array();
		if ( $font->is_upload() ) {
			foreach ( $font->files() as $weight => $by_style ) {
				foreach ( (array) $by_style as $style => $formats ) {
					$src = array();
					foreach ( array( 'woff2', 'woff', 'ttf', 'otf' ) as $format ) {
						if ( ! empty( $formats[ $format ] ) ) {
							$src[] = FontStorage::url( (string) $formats[ $format ] );
						}
					}
					if ( $src ) {
						$faces[] = array(
							'fontFamily'  => $font->family(),
							'fontWeight'  => (string) $weight,
							'fontStyle'   => (string) $style,
							'fontDisplay' => $font->display(),
							'src'         => $src,
						);
					}
				}
			}
		} elseif ( $font->is_self_hosted() ) {
			foreach ( $font->local_faces() as $face ) {
				$src = array();
				foreach ( (array) ( $face['src'] ?? array() ) as $item ) {
					$src[] = FontStorage::url( (string) ( $item['file'] ?? '' ) );
				}
				$src = array_values( array_filter( $src ) );
				if ( $src ) {
					$entry = array(
						'fontFamily'  => $font->family(),
						'fontWeight'  => (string) ( $face['weight'] ?? '400' ),
						'fontStyle'   => (string) ( $face['style'] ?? 'normal' ),
						'fontDisplay' => $font->display(),
						'src'         => $src,
					);
					if ( ! empty( $face['unicode_range'] ) ) {
						$entry['unicodeRange'] = (string) $face['unicode_range'];
					}
					$faces[] = $entry;
				}
			}
		}
		return $faces;
	}

	/**
	 * Editor canvas: load fonts so the preview matches (local fonts are cheap, CDN fonts need CSS).
	 */
	public function editor_assets(): void {
		if ( is_admin() && $this->fonts() ) {
			FontLoader::enqueue_for_editor( array_keys( $this->fonts() ) );
		}
	}

	/**
	 * Front end: fonts chosen in block attributes.
	 *
	 * @param mixed $content Block HTML.
	 * @param mixed $block   Parsed block.
	 * @return mixed
	 */
	public function detect_block( mixed $content, mixed $block ): mixed {
		if ( ! is_array( $block ) || empty( $block['attrs'] ) ) {
			return $content;
		}
		$slug = (string) ( $block['attrs']['fontFamily'] ?? '' );
		if ( '' === $slug && isset( $block['attrs']['style']['typography']['fontFamily'] ) && preg_match( '/font-family[|-]{1,2}(ucf-[a-z0-9_\-]+)/', (string) $block['attrs']['style']['typography']['fontFamily'], $m ) ) {
			$slug = $m[1];
		}
		if ( str_starts_with( $slug, 'ucf-' ) ) {
			$font = FontRegistry::get_font( substr( $slug, 4 ) );
			if ( $font && $font->enabled_for( $this->id() ) ) {
				FontLoader::enqueue( $font->id() );
			}
		}
		return $content;
	}

	/**
	 * Whether theme.json is being resolved for an editor (admin screens or REST).
	 *
	 * @return bool
	 */
	private static function editor_context(): bool {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_is_json_request() ) {
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return str_contains( $uri, '/' . rest_get_url_prefix() . '/' ) || str_contains( $uri, 'rest_route=' );
	}

	/**
	 * Front end: fonts referenced by Global Styles (Site Editor → Styles).
	 */
	public function enqueue_frontend(): void {
		$fonts = $this->fonts();
		if ( ! $fonts || ! function_exists( 'wp_get_global_styles' ) ) {
			return;
		}
		$styles = (string) wp_json_encode( wp_get_global_styles() );
		foreach ( $fonts as $font ) {
			if ( str_contains( $styles, 'font-family|' . $font->slug() ) || str_contains( $styles, 'font-family--' . $font->slug() ) ) {
				FontLoader::enqueue( $font->id() );
			}
		}
	}
}
