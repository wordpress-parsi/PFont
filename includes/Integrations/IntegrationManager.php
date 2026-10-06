<?php
/**
 * Creates adapters, registers the available ones and coordinates front-end loading.
 *
 * @package PFont
 */

namespace PFont\Integrations;

use PFont\Core\FontRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Adapter registry.
 */
final class IntegrationManager {

	/**
	 * Adapters.
	 *
	 * @var AbstractAdapter[]|null
	 */
	private static ?array $adapters = null;

	/**
	 * Hook into WordPress. Adapters register after the theme loads so theme APIs can be detected.
	 */
	public static function register_hooks(): void {
		add_action( 'after_setup_theme', array( self::class, 'boot' ), 20 );
	}

	/**
	 * All adapters (available or not).
	 *
	 * @return AbstractAdapter[]
	 */
	public static function adapters(): array {
		if ( null === self::$adapters ) {
			$adapters = array(
				new TinyMceAdapter(),
				new BlockEditorAdapter(),
				new ElementorAdapter(),
				new AstraAdapter(),
				new CustomizerAdapter(),
			);

			/**
			 * Add or replace integration adapters.
			 *
			 * @param AbstractAdapter[] $adapters Adapters.
			 */
			$adapters       = apply_filters( 'pfont_integrations', $adapters );
			self::$adapters = array_values(
				array_filter(
					(array) $adapters,
					static function ( $adapter ): bool {
						return $adapter instanceof AbstractAdapter;
					}
				)
			);
		}
		return self::$adapters;
	}

	/**
	 * Available adapters.
	 *
	 * @return AbstractAdapter[]
	 */
	public static function available(): array {
		return array_values(
			array_filter(
				self::adapters(),
				static function ( AbstractAdapter $adapter ): bool {
					return $adapter->is_available();
				}
			)
		);
	}

	/**
	 * The adapter for an integration key (the available one wins when several share a key).
	 *
	 * @param string $key Integration key.
	 * @return AbstractAdapter|null
	 */
	public static function for_key( string $key ): ?AbstractAdapter {
		$fallback = null;
		foreach ( self::adapters() as $adapter ) {
			if ( $adapter->id() !== $key ) {
				continue;
			}
			if ( $adapter->is_available() ) {
				return $adapter;
			}
			$fallback = $fallback ?? $adapter;
		}
		return $fallback;
	}

	/**
	 * Register available adapters.
	 */
	public static function boot(): void {
		foreach ( self::available() as $adapter ) {
			$adapter->register();
		}
		add_action( 'wp_enqueue_scripts', array( self::class, 'frontend' ), 20 );
	}

	/**
	 * Front-end: let each adapter queue what the request needs.
	 */
	public static function frontend(): void {
		if ( ! FontRegistry::has_enabled_fonts() ) {
			return;
		}
		$always = 'always' === \PFont\Core\Settings::get( 'remote_strategy' );
		foreach ( self::available() as $adapter ) {
			if ( $always ) {
				$adapter->enqueue_all();
			} else {
				$adapter->enqueue_frontend();
			}
		}
	}

	/**
	 * Reset (tests).
	 */
	public static function reset(): void {
		self::$adapters = null;
	}
}
