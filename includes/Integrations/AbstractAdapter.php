<?php
/**
 * Base class for integrations.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Integrations;

use UniversalCustomFonts\Core\Font;
use UniversalCustomFonts\Core\FontLoader;
use UniversalCustomFonts\Core\FontRegistry;
use UniversalCustomFonts\Core\Settings;
use UniversalCustomFonts\Helpers\FontHelper;

defined( 'ABSPATH' ) || exit;

/**
 * An adapter translates the central registry into one platform's native API.
 * To add a platform: extend this class and return it from the ucf_integrations filter.
 */
abstract class AbstractAdapter {

	/**
	 * Hooks registered by this adapter (shown in the debug panel).
	 *
	 * @var array
	 */
	protected array $hooks = array();

	/**
	 * Integration key used by the per-font toggles (see Font::INTEGRATIONS).
	 *
	 * @return string
	 */
	abstract public function id(): string;

	/**
	 * Human label.
	 *
	 * @return string
	 */
	abstract public function label(): string;

	/**
	 * Whether the platform is present on this site.
	 *
	 * @return bool
	 */
	abstract public function is_available(): bool;

	/**
	 * Detected platform version.
	 *
	 * @return string
	 */
	public function version(): string {
		return '';
	}

	/**
	 * Register hooks. Only called when is_available() is true.
	 */
	public function register(): void {}

	/**
	 * Queue fonts on a front-end request (wp_enqueue_scripts).
	 */
	public function enqueue_frontend(): void {}

	/**
	 * Status for the Integrations screen. Never claims success it cannot verify.
	 *
	 * @return array{state:string,message:string}
	 */
	public function status(): array {
		if ( ! $this->is_available() ) {
			return array(
				'state'   => 'missing',
				'message' => __( 'Not detected on this site.', 'universal-custom-fonts' ),
			);
		}
		return array(
			'state'   => 'active',
			'message' => __( 'Detected. Enabled fonts appear in its native font list.', 'universal-custom-fonts' ),
		);
	}

	/**
	 * Explain a clash between a library font and a font the platform already ships.
	 *
	 * @param Font $font Font.
	 * @return string
	 */
	public function native_conflict( Font $font ): string {
		unset( $font );
		return '';
	}

	/**
	 * Registered hooks.
	 *
	 * @return array
	 */
	public function hooks(): array {
		return $this->hooks;
	}

	/**
	 * Fonts enabled for this integration.
	 *
	 * @return Font[]
	 */
	public function fonts(): array {
		return FontRegistry::get_fonts_for( $this->id() );
	}

	/**
	 * Queue every enabled font of this integration.
	 *
	 * @param bool $local_only Only fonts served from this site.
	 */
	public function enqueue_all( bool $local_only = false ): void {
		foreach ( $this->fonts() as $font ) {
			if ( ! $local_only || $font->is_local() ) {
				FontLoader::enqueue( $font->id() );
			}
		}
	}

	/**
	 * Add a hook and remember it.
	 *
	 * @param string   $type     filter|action.
	 * @param string   $name     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Accepted args.
	 */
	protected function hook( string $type, string $name, callable $callback, int $priority = 10, int $args = 1 ): void {
		if ( 'filter' === $type ) {
			add_filter( $name, $callback, $priority, $args );
		} else {
			add_action( $name, $callback, $priority, $args );
		}
		$this->hooks[] = array(
			'type'     => $type,
			'hook'     => $name,
			'priority' => $priority,
		);
	}

	/**
	 * Queue CDN fonts whose family appears in a haystack (builder settings, content).
	 *
	 * @param string $haystack Text to scan.
	 * @param bool   $include_local Also detect local fonts.
	 */
	protected function enqueue_mentioned( string $haystack, bool $include_local = false ): void {
		if ( '' === $haystack ) {
			return;
		}
		foreach ( $this->fonts() as $font ) {
			if ( ( $include_local || ! $font->is_local() ) && FontHelper::mentions_family( $haystack, $font->family() ) ) {
				FontLoader::enqueue( $font->id() );
			}
		}
	}

	/**
	 * Content of the main queried post, if any.
	 *
	 * @return string
	 */
	protected static function queried_content(): string {
		if ( ! is_singular() ) {
			return '';
		}
		$post = get_queried_object();
		return $post instanceof \WP_Post ? (string) $post->post_content : '';
	}

	/**
	 * Whether the admin chose to load every enabled font everywhere.
	 *
	 * @return bool
	 */
	protected static function always_load(): bool {
		return 'always' === Settings::get( 'remote_strategy' );
	}
}
