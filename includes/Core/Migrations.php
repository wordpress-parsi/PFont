<?php
/**
 * Activation and versioned data migrations.
 *
 * @package PFont
 */

namespace PFont\Core;

use PFont\Fonts\FontStorage;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps stored data in step with PFONT_DB_VERSION.
 */
final class Migrations {

	public const VERSION_OPTION = 'pfont_db_version';

	/**
	 * Option and transient names used before 1.5.0, when the plugin still used the "ucf" prefix.
	 * They are only ever read and deleted here, never written; see import_legacy_data().
	 */
	private const LEGACY_OPTIONS = array(
		'ucf_fonts'      => FontRepository::OPTION,
		'ucf_settings'   => Settings::OPTION,
		'ucf_db_version' => self::VERSION_OPTION,
	);

	private const LEGACY_TRANSIENTS = array( 'ucf_native_index', 'ucf_debug_last_load' );

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		self::run();
		FontStorage::ensure_base_dir();
	}

	/**
	 * Run migrations when the stored version is behind (or the data still uses the old prefix).
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
		self::import_legacy_data();
		$current = (string) get_option( self::VERSION_OPTION, '0.0.0' );
		$steps   = array(
			'1.0.0' => array( self::class, 'to_1_0_0' ),
			'1.1.0' => array( self::class, 'to_1_1_0' ),
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

	/**
	 * 1.1.0: the "custom stylesheet URL" field was removed. Records that relied on it can no
	 * longer load anything sensible, so they are disabled (not deleted) and the key is dropped.
	 */
	public static function to_1_1_0(): void {
		$fonts = get_option( FontRepository::OPTION, array() );
		if ( ! is_array( $fonts ) ) {
			return;
		}
		$changed = false;
		foreach ( $fonts as $id => $record ) {
			if ( ! is_array( $record ) || ! array_key_exists( 'cdn_url', $record ) ) {
				continue;
			}
			if ( '' !== (string) $record['cdn_url'] ) {
				$fonts[ $id ]['enabled'] = false;
			}
			unset( $fonts[ $id ]['cdn_url'] );
			$changed = true;
		}
		if ( $changed ) {
			update_option( FontRepository::OPTION, $fonts, true );
			FontRepository::flush_cache();
		}
	}

	/**
	 * One-time move of data saved by versions before 1.5.0 to the prefixed option names.
	 *
	 * Runs only while the new version option does not exist yet, copies each old option that is
	 * present, then removes the old options and transients so nothing stays behind.
	 */
	private static function import_legacy_data(): void {
		if ( false !== get_option( self::VERSION_OPTION, false ) || false === get_option( 'ucf_db_version', false ) ) {
			return;
		}
		foreach ( self::LEGACY_OPTIONS as $old => $new ) {
			$value = get_option( $old, null );
			if ( null !== $value && false === get_option( $new, false ) ) {
				add_option( $new, $value, '', true );
			}
			delete_option( $old );
		}
		foreach ( self::LEGACY_TRANSIENTS as $transient ) {
			delete_transient( $transient );
		}
		FontRepository::flush_cache();
		Settings::flush_cache();
	}
}
