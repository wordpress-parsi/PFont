<?php
/**
 * Plugin bootstrap.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts;

use UniversalCustomFonts\Admin\AdminPage;
use UniversalCustomFonts\Core\FontLoader;
use UniversalCustomFonts\Core\Migrations;
use UniversalCustomFonts\Core\Settings;
use UniversalCustomFonts\Integrations\IntegrationManager;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the services together. Everything else is hook-driven.
 *
 * Translations need no code: WordPress loads language packs by itself (4.6+) and, from 6.8,
 * also the files in this plugin's /languages folder through the "Domain Path" header.
 */
final class Plugin {

	/**
	 * Guard against double booting.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Boot the plugin on plugins_loaded.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		Migrations::maybe_run();
		Settings::register_hooks();
		FontLoader::register_hooks();
		IntegrationManager::register_hooks();

		if ( is_admin() ) {
			AdminPage::register_hooks();
		}

		/**
		 * Fires after PFont has registered its hooks.
		 */
		do_action( 'pfont_loaded' );
		do_action_deprecated( 'ucf_loaded', array(), '1.4.0', 'pfont_loaded' );
	}
}
