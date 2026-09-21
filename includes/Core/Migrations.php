<?php
/**
 * Activation and versioned data migrations.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Core;

use UniversalCustomFonts\Fonts\FontStorage;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps stored data in step with PFONT_DB_VERSION.
 */
final class Migrations {

	public const VERSION_OPTION = 'ucf_db_version';

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		self::run();
		FontStorage::ensure_base_dir();
	}

	/**
	 * Run migrations when the stored version is behind.
	 */
	public static function maybe_run(): void {
		if ( get_option( self::VERSION_OPTION ) !== PFONT_DB_VERSION ) {
			self::run();
		}
	}

	/**
	 * Apply every step newer than the stored version, in order.
	 */
	public static function run(): void {
		$current = (string) get_option( self::VERSION_OPTION, '0.0.0' );
		$steps   = array(
			'1.0.0' => array( self::class, 'to_1_0_0' ),
		);
		foreach ( $steps as $version => $callback ) {
			if ( version_compare( $current, $version, '<' ) ) {
				call_user_func( $callback );
			}
		}
		update_option( self::VERSION_OPTION, PFONT_DB_VERSION, true );
	}

	/**
	 * 1.0.0: create options. Fonts are small and needed on the front end, so they autoload.
	 */
	public static function to_1_0_0(): void {
		if ( false === get_option( FontRepository::OPTION, false ) ) {
			add_option( FontRepository::OPTION, array(), '', true );
		}
		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', true );
		}
	}
}
