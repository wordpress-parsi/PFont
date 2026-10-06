<?php
/**
 * Optional: use a library font for the WordPress admin area.
 *
 * @package PFont
 */

namespace PFont\Admin;

use PFont\Core\Font;
use PFont\Core\FontLoader;
use PFont\Core\FontRegistry;
use PFont\Core\Settings;
use PFont\Helpers\FontHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Applies the font chosen under Settings → WordPress admin to the dashboard screens and toolbar.
 *
 * The rule is deliberately NOT "!important": inline previews and higher-specificity plugin CSS
 * keep working. Icon fonts, code fields and edited content are excluded so they never break.
 */
final class AdminFont {

	/**
	 * WordPress' own admin font stack, used for glyphs the chosen font does not contain.
	 */
	private const SYSTEM_STACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue"';

	/**
	 * Elements that must keep their own font. Verified against the WordPress admin CSS, where
	 * these set an icon or monospace font on the element itself (not on ::before).
	 */
	private const KEEP = array(
		// Icon fonts: core.
		'.dashicons',
		'.ab-icon',
		'.wp-admin-bar-arrow',
		'.blavatar',
		'.mce-ico',
		'.qt-dfw',
		'.star-rating .star',
		'[role="treeitem"] span[aria-hidden]',
		// Icon fonts: common third-party conventions.
		'i',
		'[aria-hidden="true"]',
		'[class^="eicon"]',
		'[class*=" eicon"]',
		'[class^="fa-"]',
		'[class*=" fa-"]',
		'.fa',
		'.fas',
		'.far',
		'.fab',
		'[class^="icon-"]',
		'[class*=" icon-"]',
		'.genericon',
		'.material-icons',
		// Code.
		'code',
		'kbd',
		'pre',
		'samp',
		'tt',
		'.code',
		'.wp-editor-area',
		'.CodeMirror',
		'.CodeMirror *',
		'.cm-editor',
		'.cm-editor *',
		'.ace_editor',
		'.ace_editor *',
		// Content being edited (non-iframed block editor).
		'.editor-styles-wrapper',
		'.editor-styles-wrapper *',
		// This plugin's own font previews.
		'[data-pfont-t-apply]',
		'[data-pfont-t-apply] *',
		'[data-pfont-sample]',
		'.pfont-preset__sample',
	);

	/**
	 * Register hooks.
	 */
	public static function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ), 5 );
	}

	/**
	 * The font to use, or null for the WordPress default.
	 *
	 * @return Font|null
	 */
	public static function font(): ?Font {
		$id = (string) Settings::get( 'admin_font' );
		if ( '' === $id ) {
			return null;
		}
		$font = FontRegistry::get_font( $id );

		/**
		 * Filter the font used for the admin area (null = WordPress default).
		 *
		 * @param Font|null $font Font.
		 */
		$font = apply_filters( 'pfont_admin_font', ( $font && $font->is_enabled() ) ? $font : null );
		return $font instanceof Font ? $font : null;
	}

	/**
	 * Load the font files and print the rule on every admin screen.
	 */
	public static function enqueue(): void {
		$font = self::font();
		if ( ! $font ) {
			return;
		}
		FontLoader::enqueue_for_editor( $font->id() );
		wp_register_style( 'pfont-admin-font', false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Inline-only handle.
		wp_add_inline_style( 'pfont-admin-font', self::css( $font ) );
		wp_enqueue_style( 'pfont-admin-font' );
	}

	/**
	 * The CSS rule.
	 *
	 * @param Font $font Font.
	 * @return string
	 */
	public static function css( Font $font ): string {
		/**
		 * Filter the selectors that keep their own font in the admin area.
		 *
		 * @param string[] $selectors Selectors.
		 */
		$keep = (array) apply_filters( 'pfont_admin_font_keep', self::KEEP );
		$keep = array_filter( array_map( 'strval', $keep ) );
		$keep = str_replace( array( '{', '}', '<', '>', ';' ), '', implode( ',', $keep ) );
		$not  = '' !== $keep ? ':not(' . $keep . ')' : '';

		return 'body.wp-admin,body.wp-admin *' . $not . ',#wpadminbar *' . $not . '{font-family:' . self::stack( $font ) . '}';
	}

	/**
	 * Family, the font's own named fallbacks, WordPress' system stack, then the generic family.
	 *
	 * @param Font $font Font.
	 * @return string
	 */
	public static function stack( Font $font ): string {
		$parts = array( "'" . self::clean( $font->family() ) . "'" );
		foreach ( explode( ',', $font->fallback() ) as $fallback ) {
			$fallback = self::clean( trim( $fallback, " \t\n\r\0\x0B'\"" ) );
			if ( '' !== $fallback && ! in_array( strtolower( $fallback ), FontHelper::GENERIC, true ) ) {
				$parts[] = "'" . $fallback . "'";
			}
		}
		$parts[] = self::SYSTEM_STACK;
		$parts[] = $font->generic();
		return implode( ', ', $parts );
	}

	/**
	 * Keep only characters that are valid in a family name (fonts added by code are not validated).
	 *
	 * @param string $name Family name.
	 * @return string
	 */
	private static function clean( string $name ): string {
		return trim( (string) preg_replace( '/[^\p{L}\p{N}_ \-]/u', '', $name ) );
	}
}
