<?php
/**
 * Plugin Name:       PFont
 * Description:       One font library for WordPress. Upload fonts or add Google Fonts once, then pick them from the native font dropdowns of the Classic Editor, Block Editor, Elementor, Astra and supported themes.
 * Version:           1.4.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            PFont contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       universal-custom-fonts
 * Domain Path:       /languages
 *
 * @package UniversalCustomFonts
 */

defined( 'ABSPATH' ) || exit;

define( 'PFONT_VERSION', '1.4.0' );
define( 'PFONT_DB_VERSION', '1.0.0' );
define( 'PFONT_FILE', __FILE__ );
define( 'PFONT_PATH', plugin_dir_path( __FILE__ ) );
define( 'PFONT_URL', plugin_dir_url( __FILE__ ) );
define( 'PFONT_MIN_PHP', '8.1' );
define( 'PFONT_MIN_WP', '6.4' );

/**
 * Whether this server meets the minimum requirements.
 *
 * This file deliberately avoids modern PHP syntax so old servers get a notice instead of a fatal error.
 *
 * @return bool
 */
function pfont_requirements_met() {
	global $wp_version;
	return version_compare( PHP_VERSION, PFONT_MIN_PHP, '>=' ) && version_compare( $wp_version, PFONT_MIN_WP, '>=' );
}

/**
 * Admin notice shown when the requirements are not met.
 */
function pfont_requirements_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: minimum PHP version, 2: minimum WordPress version. */
				__( 'PFont needs PHP %1$s or newer and WordPress %2$s or newer. It stays inactive until the server is updated.', 'universal-custom-fonts' ),
				PFONT_MIN_PHP,
				PFONT_MIN_WP
			)
		)
	);
}

if ( ! pfont_requirements_met() ) {
	add_action( 'admin_notices', 'pfont_requirements_notice' );
	return;
}

require_once PFONT_PATH . 'includes/Autoloader.php';
\UniversalCustomFonts\Autoloader::register();

register_activation_hook( __FILE__, array( 'UniversalCustomFonts\Core\Migrations', 'activate' ) );
add_action( 'plugins_loaded', array( 'UniversalCustomFonts\Plugin', 'boot' ), 5 );
