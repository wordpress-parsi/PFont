<?php
/**
 * Help: short answers to common questions.
 *
 * @package PFont
 */

namespace PFont\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Help view.
 */
final class HelpView {

	/**
	 * Render.
	 */
	public static function render(): void {
		AdminPage::page_header( __( 'Help', 'pfont' ), __( 'Short answers to common questions.', 'pfont' ) );
		echo '<div class="ucf-card ucf-faq">';
		self::item(
			'loading',
			__( 'How are fonts loaded?', 'pfont' ),
			array(
				__( 'Each page loads only the fonts it uses. Elementor and Astra report exactly which fonts a page needs; for the Classic Editor, Block Editor and theme settings, the font name is found in the page content and saved settings.', 'pfont' ),
				__( 'Fonts on your server are added as one small inline style. CDN fonts add one stylesheet, only on pages that use them.', 'pfont' ),
			)
		);
		self::item( 'format', __( 'Which file format should I upload?', 'pfont' ), array( __( 'WOFF2. It is the smallest format and every current browser supports it.', 'pfont' ) ) );
		self::item(
			'use',
			__( 'Where do I choose a font in my editor or builder?', 'pfont' ),
			array(
				__( 'Classic Editor: the Font Family menu in the toolbar.', 'pfont' ),
				__( 'Block Editor: Typography → Font in the block settings, or Styles → Typography in the Site Editor.', 'pfont' ),
				__( 'Elementor: Style → Typography → Family, in the “PFont” group.', 'pfont' ),
				__( 'Astra: Customizer → Global → Typography.', 'pfont' ),
			),
			'ul'
		);
		self::item( 'privacy', __( 'Why host Google Fonts on my own server?', 'pfont' ), array( __( 'A font loaded from a CDN sends each visitor’s IP address to that service. A German court (LG München I, 20 January 2022, 3 O 17493/20) awarded damages for embedding Google Fonts without consent. Hosting the files on your server avoids that request. This is not legal advice.', 'pfont' ) ) );
		self::item(
			'builtin',
			__( 'What about Divi, WoodMart and other themes with their own font upload?', 'pfont' ),
			array(
				__( 'Divi and WoodMart already include a custom font uploader, so this plugin does not integrate with them. Upload the font in the builder or theme itself: in Divi, open any font menu and choose Upload; in WoodMart, go to Theme Settings → Typography → Custom fonts.', 'pfont' ),
			)
		);
		self::item(
			'admin-font',
			__( 'Can I change the font of the WordPress admin area?', 'pfont' ),
			array(
				__( 'Yes. Under Settings → WordPress admin, choose any enabled font from your library. It is applied to the dashboard screens and the toolbar. Icons, code fields and the content you edit keep their own fonts.', 'pfont' ),
			)
		);
		self::item(
			'missing',
			__( 'A font does not appear. What should I check?', 'pfont' ),
			array(
				__( 'The font is enabled and turned on for that editor under “Where it appears”.', 'pfont' ),
				__( 'Caching and CSS optimization plugins are cleared, then the page is reloaded.', 'pfont' ),
				__( 'Debug mode (under Settings) shows which fonts the last page loaded.', 'pfont' ),
			),
			'ul'
		);
		echo '</div><div class="ucf-help-links">';
		//AdminPage::button_link( __( 'Testing checklist', 'pfont' ), PFONT_URL . 'docs/TESTING.md', '', 'ghost' );
		//AdminPage::button_link( __( 'Developer guide', 'pfont' ), PFONT_URL . 'docs/DEVELOPER.md', '', 'ghost' );
		echo '</div>';
	}

	/**
	 * One question.
	 *
	 * @param string $id       Anchor suffix.
	 * @param string $question Question.
	 * @param array  $lines    Paragraphs or list items.
	 * @param string $format   '' for paragraphs, ul or ol.
	 */
	private static function item( string $id, string $question, array $lines, string $format = '' ): void {
		printf( '<details id="ucf-help-%1$s"><summary>%2$s</summary>', esc_attr( $id ), esc_html( $question ) );
		if ( '' !== $format ) {
			echo 'ol' === $format ? '<ol>' : '<ul>';
		}
		foreach ( $lines as $line ) {
			printf( '' !== $format ? '<li>%s</li>' : '<p>%s</p>', esc_html( $line ) );
		}
		if ( '' !== $format ) {
			echo 'ol' === $format ? '</ol>' : '</ul>';
		}
		echo '</details>';
	}
}
