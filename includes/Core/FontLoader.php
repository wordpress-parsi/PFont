<?php
/**
 * Loads only the fonts a request actually needs.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Core;

use UniversalCustomFonts\Fonts\CdnFonts;
use UniversalCustomFonts\Fonts\FontFaceGenerator;

defined( 'ABSPATH' ) || exit;

/**
 * Integrations queue fonts; the loader prints one inline @font-face block for local fonts and
 * one combined css2 request for CDN fonts. Fonts queued after the head are printed in the footer.
 */
final class FontLoader {

	/**
	 * Pending fonts: id => weights.
	 *
	 * @var array<string,int[]>
	 */
	private static array $queue = array();

	/**
	 * Already printed, per WP_Styles instance: id => weights.
	 *
	 * The block editor resolves the assets of its canvas iframe with a temporary WP_Styles
	 * object before the admin page itself enqueues anything. Remembering printed fonts globally
	 * made the admin page skip them, so they are remembered per styles registry instead.
	 *
	 * @var \WeakMap<\WP_Styles,array<string,int[]>>|null
	 */
	private static ?\WeakMap $printed = null;

	/**
	 * Flush counter for unique handles.
	 *
	 * @var int
	 */
	private static int $flushes = 0;

	/**
	 * Whether preconnect hints were printed.
	 *
	 * @var bool
	 */
	private static bool $preconnected = false;

	/**
	 * Debug log of what was loaded.
	 *
	 * @var array
	 */
	private static array $log = array();

	/**
	 * Register output hooks.
	 */
	public static function register_hooks(): void {
		add_action( 'wp_print_styles', array( self::class, 'flush' ), 1 );
		add_action( 'admin_print_styles', array( self::class, 'flush' ), 1 );
		add_action( 'wp_print_footer_scripts', array( self::class, 'flush' ), 1 );
		add_action( 'admin_print_footer_scripts', array( self::class, 'flush' ), 1 );
		add_action( 'shutdown', array( self::class, 'store_debug_log' ) );
	}

	/**
	 * Queue a font for this request.
	 *
	 * @param string $font_id Font ID.
	 * @param int[]  $weights Weights needed (empty = all enabled weights).
	 */
	public static function enqueue( string $font_id, array $weights = array() ): void {
		$font = FontRegistry::get_font( $font_id );
		if ( ! $font || ! $font->is_enabled() ) {
			return;
		}
		self::add_to_queue( $font, $weights );
		if ( self::head_done() ) {
			self::flush();
		}
	}

	/**
	 * Queue by CSS family name, e.g. from a builder's "font used" event.
	 *
	 * @param string $family      Family or CSS stack.
	 * @param string $integration Integration the font must be enabled for.
	 * @param int[]  $weights     Weights needed.
	 * @return Font|null The font that was queued.
	 */
	public static function enqueue_family( string $family, string $integration, array $weights = array() ): ?Font {
		$font = FontRegistry::find_by_family( $family, $integration );
		if ( $font ) {
			self::enqueue( $font->id(), $weights );
		}
		return $font;
	}

	/**
	 * Load fonts right now in an editor/preview context (all weights).
	 *
	 * @param string|string[] $font_ids         Font ID(s).
	 * @param bool            $include_disabled Also load disabled fonts (admin previews).
	 */
	public static function enqueue_for_editor( string|array $font_ids, bool $include_disabled = false ): void {
		foreach ( (array) $font_ids as $font_id ) {
			$font = FontRegistry::get_font( (string) $font_id );
			if ( $font && ( $include_disabled || $font->is_enabled() ) ) {
				self::add_to_queue( $font, array() );
			}
		}
		self::flush();
	}

	/**
	 * The @font-face CSS for one local font ('' for CDN fonts).
	 *
	 * @param string $font_id Font ID.
	 * @param int[]  $weights Weights.
	 * @return string
	 */
	public static function get_css( string $font_id, array $weights = array() ): string {
		$font = FontRegistry::get_font( $font_id );
		return $font ? FontFaceGenerator::for_font( $font, $weights ) : '';
	}

	/**
	 * Combined @font-face CSS for several local fonts.
	 *
	 * @param Font[] $fonts Fonts.
	 * @return string
	 */
	public static function get_inline_css( array $fonts ): string {
		$css = '';
		foreach ( $fonts as $font ) {
			if ( $font->is_local() ) {
				$css .= FontFaceGenerator::for_font( $font );
			}
		}
		return $css;
	}

	/**
	 * Stylesheet URLs for CDN fonts (one css2 request for all provider fonts).
	 *
	 * @param Font[] $fonts Fonts.
	 * @return string[]
	 */
	public static function get_remote_urls( array $fonts ): array {
		$items = array();
		foreach ( $fonts as $font ) {
			if ( ! $font->is_local() ) {
				$items[] = array( $font, $font->weights() );
			}
		}
		return self::remote_urls( $items );
	}

	/**
	 * Print queued fonts. Runs on the style-printing hooks and immediately for late fonts.
	 */
	public static function flush(): void {
		if ( ! self::$queue ) {
			return;
		}
		$queue       = self::$queue;
		self::$queue = array();
		$local_css   = '';
		$local_ids   = array();
		$remote      = array();

		$registry = wp_styles();
		if ( null === self::$printed ) {
			self::$printed = new \WeakMap();
		}
		$printed = self::$printed[ $registry ] ?? array();

		foreach ( $queue as $id => $weights ) {
			$font = FontRegistry::get_font( $id );
			if ( ! $font ) {
				continue;
			}
			if ( isset( $printed[ $id ] ) ) {
				$weights = array_values( array_diff( $weights, $printed[ $id ] ) );
				if ( ! $weights ) {
					continue;
				}
			}
			$printed[ $id ] = array_values( array_unique( array_merge( $printed[ $id ] ?? array(), $weights ) ) );

			if ( $font->is_local() ) {
				$local_css  .= FontFaceGenerator::for_font( $font, $weights );
				$local_ids[] = $id;
			} else {
				$remote[] = array( $font, $weights );
			}
		}

		self::$printed[ $registry ] = $printed;

		$suffix = self::$flushes ? '-' . self::$flushes : '';
		++self::$flushes;

		if ( '' !== $local_css ) {
			$handle = 'ucf-fonts' . $suffix;
			wp_register_style( $handle, false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Inline-only handle.
			wp_add_inline_style( $handle, $local_css );
			wp_enqueue_style( $handle );
		}

		$urls = self::remote_urls( $remote );
		foreach ( $urls as $i => $url ) {
			// No ?ver= on third-party font URLs: it would only break shared caching.
			wp_enqueue_style( 'ucf-cdn' . $suffix . ( $i ? '-' . $i : '' ), $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
		if ( $urls ) {
			self::maybe_preconnect();
		}

		self::$log[] = array(
			'hook'   => (string) current_action(),
			'late'   => self::head_done(),
			'local'  => $local_ids,
			'remote' => $urls,
		);
	}

	/**
	 * Fonts printed in this request (for debugging and tests).
	 *
	 * @return array
	 */
	public static function loaded(): array {
		return self::$log;
	}

	/**
	 * Keep the last front-end load in a transient when debug mode is on.
	 */
	public static function store_debug_log(): void {
		if ( ! self::$log || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! Settings::get( 'debug' ) ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		set_transient(
			'ucf_debug_last_load',
			array(
				'url'  => $uri,
				'time' => time(),
				'log'  => self::$log,
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Reset state (tests).
	 */
	public static function reset(): void {
		self::$queue        = array();
		self::$printed      = null;
		self::$flushes      = 0;
		self::$preconnected = false;
		self::$log          = array();
	}

	/**
	 * Merge a font into the queue.
	 *
	 * @param Font  $font    Font.
	 * @param int[] $weights Weights.
	 */
	private static function add_to_queue( Font $font, array $weights ): void {
		$available = $font->weights();
		$weights   = array_values( array_intersect( array_map( 'intval', $weights ), $available ) );
		$weights   = $weights ? $weights : $available;
		$current   = self::$queue[ $font->id() ] ?? array();
		$merged    = array_values( array_unique( array_merge( $current, $weights ) ) );
		sort( $merged );
		self::$queue[ $font->id() ] = $merged;
	}

	/**
	 * Build CDN URLs.
	 *
	 * @param array $items List of [Font, weights].
	 * @return string[]
	 */
	private static function remote_urls( array $items ): array {
		$urls    = array();
		$specs   = array();
		$display = 'swap';
		foreach ( $items as $item ) {
			list( $font, $weights ) = $item;
			if ( '' !== $font->cdn_url() ) {
				$urls[] = $font->cdn_url();
				continue;
			}
			$preset  = $font->preset_data();
			$specs[] = CdnFonts::css2_family( $font->remote_family(), $weights ? $weights : $font->weights(), $preset['range'] ?? null, $font->styles() );
			$display = $font->display();
		}
		if ( $specs ) {
			array_unshift( $urls, CdnFonts::css2_url( $specs, $display ) );
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * Print preconnect hints once, only while the head is being printed.
	 */
	private static function maybe_preconnect(): void {
		if ( self::$preconnected || ! doing_action( 'wp_print_styles' ) || ! Settings::get( 'preconnect' ) ) {
			return;
		}
		self::$preconnected = true;
		$hosts              = CdnFonts::provider()['hosts'];
		foreach ( $hosts as $i => $host ) {
			printf(
				'<link rel="preconnect" href="%s"%s>' . "\n",
				esc_url( 'https://' . $host ),
				( count( $hosts ) > 1 && 0 === $i ) ? '' : ' crossorigin'
			);
		}
	}

	/**
	 * Whether head styles were already printed (late fonts go to the footer).
	 *
	 * @return bool
	 */
	private static function head_done(): bool {
		if ( is_admin() ) {
			return (bool) did_action( 'admin_print_styles' ) && ! doing_action( 'admin_print_styles' );
		}
		return (bool) did_action( 'wp_print_styles' ) && ! doing_action( 'wp_print_styles' );
	}
}
