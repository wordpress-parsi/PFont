<?php
/**
 * Help: short answers to common questions.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Help view.
 */
final class HelpView {

	/**
	 * Render.
	 */
	public static function render(): void {
		AdminPage::page_header( __( 'Help', 'universal-custom-fonts' ), __( 'Short answers to common questions.', 'universal-custom-fonts' ) );
		echo '<div class="ucf-card ucf-faq">';
		self::item(
			'loading',
			__( 'How are fonts loaded?', 'universal-custom-fonts' ),
			array(
				__( 'Each page loads only the fonts it uses. Elementor and Astra report exactly which fonts a page needs; for the Classic Editor, Block Editor and theme settings, the font name is found in the page content and saved settings.', 'universal-custom-fonts' ),
				__( 'Fonts on your server are added as one small inline style. CDN fonts add one stylesheet, only on pages that use them.', 'universal-custom-fonts' ),
			)
		);
		self::item( 'format', __( 'Which file format should I upload?', 'universal-custom-fonts' ), array( __( 'WOFF2. It is the smallest format and every current browser supports it.', 'universal-custom-fonts' ) ) );
		self::item(
			'use',
			__( 'Where do I choose a font in my editor or builder?', 'universal-custom-fonts' ),
			array(
				__( 'Classic Editor: the Font Family menu in the toolbar.', 'universal-custom-fonts' ),
				__( 'Block Editor: Typography → Font in the block settings, or Styles → Typography in the Site Editor.', 'universal-custom-fonts' ),
				__( 'Elementor: Style → Typography → Family, in the “PFont” group.', 'universal-custom-fonts' ),
				__( 'Astra: Customizer → Global → Typography.', 'universal-custom-fonts' ),
			),
			'ul'
		);
		self::item( 'privacy', __( 'Why host Google Fonts on my own server?', 'universal-custom-fonts' ), array( __( 'A font loaded from a CDN sends each visitor’s IP address to that service. A German court (LG München I, 20 January 2022, 3 O 17493/20) awarded damages for embedding Google Fonts without consent. Hosting the files on your server avoids that request. This is not legal advice.', 'universal-custom-fonts' ) ) );
		self::item(
			'builtin',
			__( 'What about Divi, WoodMart and other themes with their own font upload?', 'universal-custom-fonts' ),
			array(
				__( 'Divi and WoodMart already include a custom font uploader, so this plugin does not integrate with them. Upload the font in the builder or theme itself: in Divi, open any font menu and choose Upload; in WoodMart, go to Theme Settings → Typography → Custom fonts.', 'universal-custom-fonts' ),
			)
		);
		self::item(
			'admin-font',
			__( 'Can I change the font of the WordPress admin area?', 'universal-custom-fonts' ),
			array(
				__( 'Yes. Under Settings → WordPress admin, choose any enabled font from your library. It is applied to the dashboard screens and the toolbar. Icons, code fields and the content you edit keep their own fonts.', 'universal-custom-fonts' ),
			)
		);
		self::item(
			'missing',
			__( 'A font does not appear. What should I check?', 'universal-custom-fonts' ),
			array(
				__( 'The font is enabled and turned on for that editor under “Where it appears”.', 'universal-custom-fonts' ),
				__( 'Caching and CSS optimization plugins are cleared, then the page is reloaded.', 'universal-custom-fonts' ),
				__( 'Debug mode (under Settings) shows which fonts the last page loaded.', 'universal-custom-fonts' ),
			),
			'ul'
		);
		echo '</div><div class="ucf-help-links">';
		AdminPage::button_link( __( 'Testing checklist', 'universal-custom-fonts' ), PFONT_URL . 'docs/TESTING.md', '', 'ghost' );
		AdminPage::button_link( __( 'Developer guide', 'universal-custom-fonts' ), PFONT_URL . 'docs/DEVELOPER.md', '', 'ghost' );
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
