<?php
/**
 * Plugin-level settings (pfont_settings option).
 *
 * @package PFont
 */

namespace PFont\Core;

use PFont\Fonts\CdnFonts;

defined( 'ABSPATH' ) || exit;

/**
 * Typed access to the pfont_settings option.
 */
final class Settings {

	public const OPTION = 'pfont_settings';

	/**
	 * Request cache.
	 *
	 * @var array|null
	 */
	private static ?array $cache = null;

	/**
	 * Register cache invalidation.
	 */
	public static function register_hooks(): void {
		add_action( 'update_option_' . self::OPTION, array( self::class, 'flush_cache' ) );
		add_action( 'add_option_' . self::OPTION, array( self::class, 'flush_cache' ) );
	}

	/**
	 * Default values. Every destructive option defaults to the safe choice.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'remote_strategy'              => 'smart',
			'cdn_provider'                 => 'google',
			'preconnect'                   => true,
			'tinymce_toolbar'              => true,
			'admin_font'                   => '',
			'delete_files_on_uninstall'    => false,
			'delete_settings_on_uninstall' => false,
			'debug'                        => false,
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return self::$cache;
	}

	/**
	 * Read one setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( string $key ): mixed {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Update settings.
	 *
	 * @param array $values Values to merge.
	 */
	public static function update( array $values ): void {
		update_option( self::OPTION, self::sanitize( array_merge( self::all(), $values ) ), true );
		self::flush_cache();
	}

	/**
	 * Settings API sanitize callback.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( mixed $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$strategy = isset( $input['remote_strategy'] ) ? sanitize_key( $input['remote_strategy'] ) : '';
		$provider = isset( $input['cdn_provider'] ) ? sanitize_key( $input['cdn_provider'] ) : '';
		$admin    = isset( $input['admin_font'] ) ? sanitize_key( (string) $input['admin_font'] ) : '';

		return array(
			'remote_strategy'              => in_array( $strategy, array( 'smart', 'always' ), true ) ? $strategy : $defaults['remote_strategy'],
			'cdn_provider'                 => array_key_exists( $provider, CdnFonts::providers() ) ? $provider : $defaults['cdn_provider'],
			'preconnect'                   => ! empty( $input['preconnect'] ),
			'tinymce_toolbar'              => ! empty( $input['tinymce_toolbar'] ),
			// Only a font that exists in the library can be the admin font.
			'admin_font'                   => ( '' !== $admin && FontRegistry::get_font( $admin ) ) ? $admin : '',
			'delete_files_on_uninstall'    => ! empty( $input['delete_files_on_uninstall'] ),
			'delete_settings_on_uninstall' => ! empty( $input['delete_settings_on_uninstall'] ),
			'debug'                        => ! empty( $input['debug'] ),
		);
	}

	/**
	 * Drop the request cache.
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}
}
