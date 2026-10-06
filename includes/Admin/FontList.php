<?php
/**
 * Library: searchable specimen list and the Persian/Arabic presets.
 *
 * @package PFont
 */

namespace PFont\Admin;

use PFont\Core\Font;
use PFont\Core\FontRegistry;
use PFont\Fonts\CdnFonts;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the library.
 */
final class FontList {

	/**
	 * Labels per integration key.
	 *
	 * @return array<string,string>
	 */
	public static function labels(): array {
		return array(
			'tinymce'      => __( 'Classic Editor', 'pfont' ),
			'block_editor' => __( 'Block Editor', 'pfont' ),
			'elementor'    => 'Elementor',
			'astra'        => 'Astra',
			'customizer'   => __( 'Theme Customizer', 'pfont' ),
		);
	}

	/**
	 * One-line preview sentences per writing system.
	 *
	 * @return array<string,string>
	 */
	public static function samples(): array {
		return array(
			'fa'    => __( 'تمام افراد بشر آزاد به دنیا می‌آیند و از لحاظ حیثیت و حقوق با هم برابرند.', 'pfont' ),
			'latin' => __( 'All human beings are born free and equal in dignity and rights.', 'pfont' ),
		);
	}

	/**
	 * Writing system shown first for a font.
	 *
	 * @param Font $font Font.
	 * @return string fa|latin
	 */
	public static function default_script( Font $font ): string {
		if ( $font->preset_data() && in_array( 'arabic', $font->subsets(), true ) ) {
			return 'fa';
		}
		return preg_match( '/^(fa|ar|ur|ps|ckb)/', get_user_locale() ) ? 'fa' : 'latin';
	}

	/**
	 * Where the font comes from.
	 *
	 * @param Font $font Font.
	 * @return string
	 */
	public static function source_label( Font $font ): string {
		if ( $font->is_upload() ) {
			return __( 'Uploaded files', 'pfont' );
		}
		if ( $font->is_self_hosted() ) {
			return __( 'Hosted on this server', 'pfont' );
		}
		/* translators: %s: font service name. */
		return sprintf( __( '%s (CDN)', 'pfont' ), CdnFonts::provider()['label'] );
	}

	/**
	 * Weight summary.
	 *
	 * @param Font $font Font.
	 * @return string
	 */
	public static function weight_label( Font $font ): string {
		$weights = $font->weights();
		$preset  = $font->preset_data();
		if ( $font->is_cdn() && is_array( $preset['range'] ?? null ) && count( $weights ) > 1 ) {
			/* translators: 1: lightest weight, 2: boldest weight. */
			return sprintf( __( 'Variable %1$d–%2$d', 'pfont' ), min( $weights ), max( $weights ) );
		}
		/* translators: %s: comma-separated weights. */
		return sprintf( _n( 'Weight %s', 'Weights %s', count( $weights ), 'pfont' ), implode( ', ', $weights ) );
	}

	/**
	 * Presets not yet in the library.
	 *
	 * @return array
	 */
	public static function available_presets(): array {
		$available = array();
		foreach ( CdnFonts::presets() as $id => $preset ) {
			if ( ! FontRegistry::find_by_family( $preset['family'] ) ) {
				$available[ $id ] = $preset;
			}
		}
		return $available;
	}

	/**
	 * Render the view.
	 */
	public static function render(): void {
		$fonts = FontRegistry::all();
		AdminPage::page_header( __( 'Font library', 'pfont' ), __( 'Fonts here appear in the editors and builders you choose. Each page loads only the fonts it uses.', 'pfont' ) );
		self::privacy_callout( $fonts );
		if ( $fonts ) {
			self::render_list( $fonts );
			self::render_presets();
			return;
		}
		// Empty library: offer the one-click presets first, then the upload card.
		$has_presets = self::render_presets();
		self::render_empty( $has_presets );
	}

	/**
	 * Privacy note when fonts load from a third-party CDN.
	 *
	 * @param Font[] $fonts Fonts.
	 */
	private static function privacy_callout( array $fonts ): void {
		$remote = array_filter(
			$fonts,
			static function ( Font $font ): bool {
				return $font->is_enabled() && $font->is_cdn() && ! $font->is_self_hosted();
			}
		);
		if ( ! $remote ) {
			return;
		}
		$first = reset( $remote );
		echo '<div class="pfont-callout">';
		Icons::render( 'shield' );
		printf(
			'<div class="pfont-callout__text"><span class="pfont-callout__label">%1$s</span><span class="pfont-callout__msg">%2$s</span></div>',
			esc_html__( 'Privacy', 'pfont' ),
			/* translators: %d: number of fonts. */
			esc_html( sprintf( _n( '%d font loads from a third-party CDN, which receives your visitors’ IP addresses.', '%d fonts load from a third-party CDN, which receives your visitors’ IP addresses.', count( $remote ), 'pfont' ), count( $remote ) ) )
		);
		AdminPage::button_link(
			__( 'Host on this server', 'pfont' ),
			AdminPage::url(
				array(
					'tab'  => 'edit',
					'font' => $first->id(),
				)
			) . '#pfont-hosting',
			'',
			'ghost'
		);
		echo '</div>';
	}

	/**
	 * Toolbar, list head and rows.
	 *
	 * @param Font[] $fonts Fonts.
	 */
	private static function render_list( array $fonts ): void {
		$samples = self::samples();
		$total   = count( $fonts );

		echo '<div class="pfont-toolbar" data-pfont-toolbar hidden>';
		printf( '<label class="pfont-search"><span class="screen-reader-text">%s</span>', esc_html__( 'Search fonts', 'pfont' ) );
		Icons::render( 'search' );
		printf( '<input type="search" class="pfont-input" data-pfont-filter placeholder="%s"></label>', esc_attr__( 'Search fonts', 'pfont' ) );
		printf( '<label><span class="screen-reader-text">%1$s</span><input type="text" class="pfont-input" data-pfont-preview-text placeholder="%2$s"></label>', esc_html__( 'Preview text', 'pfont' ), esc_attr__( 'Type your own preview text', 'pfont' ) );
		printf(
			'<label><span class="screen-reader-text">%1$s</span><select class="pfont-select pfont-select--pill" data-pfont-script><option value="auto">%2$s</option><option value="fa">%3$s</option><option value="latin">%4$s</option></select></label>',
			esc_html__( 'Writing system', 'pfont' ),
			esc_html__( 'Automatic script', 'pfont' ),
			esc_html__( 'Persian', 'pfont' ),
			esc_html__( 'Latin', 'pfont' )
		);
		printf( '<label class="pfont-size"><span class="screen-reader-text">%s</span><input type="range" min="16" max="80" value="40" data-pfont-size><output data-pfont-size-out>40px</output></label></div>', esc_html__( 'Preview size', 'pfont' ) );

		printf(
			'<form method="post" action="%1$s" class="pfont-font-list" data-pfont-list data-samples="%2$s" data-one="%3$s" data-many="%4$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( (string) wp_json_encode( $samples ) ),
			/* translators: 1: fonts shown, 2: fonts in the library. */
			esc_attr__( '%1$s of %2$s font', 'pfont' ),
			/* translators: 1: fonts shown, 2: fonts in the library. */
			esc_attr__( '%1$s of %2$s fonts', 'pfont' )
		);
		wp_nonce_field( 'pfont_bulk_update' );
		echo '<input type="hidden" name="action" value="pfont_bulk_update"><input type="hidden" name="pfont_scope" value="enabled"><div class="pfont-list-head">';
		/* translators: 1: fonts shown, 2: fonts in the library. */
		printf( '<span data-pfont-count>%s</span>', esc_html( sprintf( _n( '%1$s of %2$s font', '%1$s of %2$s fonts', $total, 'pfont' ), number_format_i18n( $total ), number_format_i18n( $total ) ) ) );
		printf( '<a href="%s">', esc_url( AdminPage::url( array( 'tab' => 'help' ) ) . '#pfont-help-loading' ) );
		Icons::render( 'info' );
		printf( '<span>%s</span></a></div>', esc_html__( 'How loading works', 'pfont' ) );
		foreach ( $fonts as $font ) {
			self::row( $font, $samples );
		}
		printf( '<p class="pfont-list-empty" data-pfont-empty hidden>%s</p>', esc_html__( 'No fonts match your search.', 'pfont' ) );
		printf( '<div class="pfont-savebar" data-pfont-savebar><button type="submit" class="pfont-btn">%s</button></div></form>', esc_html__( 'Save changes', 'pfont' ) );
	}

	/**
	 * One font row.
	 *
	 * @param Font  $font    Font.
	 * @param array $samples Samples.
	 */
	private static function row( Font $font, array $samples ): void {
		$id     = $font->id();
		$script = self::default_script( $font );
		$url    = AdminPage::url(
			array(
				'tab'  => 'font',
				'font' => $id,
			)
		);
		printf( '<article class="%1$s" data-pfont-row data-name="%2$s"><div class="pfont-font-row__meta">', esc_attr( $font->is_enabled() ? 'pfont-font-row' : 'pfont-font-row is-disabled' ), esc_attr( strtolower( $font->name() . ' ' . $font->family() ) ) );
		printf( '<a class="pfont-font-row__name" href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $font->name() ) );
		foreach ( array( self::weight_label( $font ), self::source_label( $font ) ) as $part ) {
			printf( '<span class="pfont-font-row__sep" aria-hidden="true">|</span><span>%s</span>', esc_html( $part ) );
		}
		if ( ! $font->is_enabled() ) {
			printf( '<span class="pfont-badge">%s</span>', esc_html__( 'Disabled', 'pfont' ) );
		}
		printf(
			'<span class="pfont-font-row__actions"><input type="hidden" name="pfont_ids[]" value="%1$s"><label><span class="screen-reader-text">%2$s</span><input type="checkbox" class="pfont-switch" role="switch" name="pfont_enabled[%1$s]" value="1"%3$s></label>',
			esc_attr( $id ),
			/* translators: %s: font name. */
			esc_html( sprintf( __( 'Enable %s', 'pfont' ), $font->name() ) ),
			checked( $font->is_enabled(), true, false )
		);
		$edit = AdminPage::url(
			array(
				'tab'  => 'edit',
				'font' => $id,
			)
		);
		printf( '<a class="pfont-icon-btn" href="%1$s" title="%2$s"><span class="screen-reader-text">%2$s</span>', esc_url( $edit ), esc_attr__( 'Edit settings', 'pfont' ) );
		Icons::render( 'edit' );
		printf(
			'</a><a class="pfont-icon-btn pfont-icon-btn--danger" href="%1$s" title="%2$s" data-pfont-confirm="%3$s"><span class="screen-reader-text">%2$s</span>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pfont_delete_font&font=' . rawurlencode( $id ) ), 'pfont_delete_font_' . $id ) ),
			esc_attr__( 'Delete', 'pfont' ),
			/* translators: %s: font name. */
			esc_attr( sprintf( __( 'Delete “%s” and its files?', 'pfont' ), $font->name() ) )
		);
		Icons::render( 'trash' );
		printf(
			'</a></span></div><a class="pfont-font-row__sample" href="%1$s" style="font-family:%2$s" data-pfont-sample data-script="%3$s" dir="%4$s" lang="%5$s" aria-hidden="true" tabindex="-1">%6$s</a></article>',
			esc_url( $url ),
			esc_attr( $font->css_stack() ),
			esc_attr( $script ),
			esc_attr( 'fa' === $script ? 'rtl' : 'ltr' ),
			esc_attr( 'fa' === $script ? 'fa' : 'en' ),
			esc_html( $samples[ $script ] )
		);
	}

	/**
	 * Empty library.
	 *
	 * @param bool $presets_above Whether the preset cards are shown above this card.
	 */
	private static function render_empty( bool $presets_above = false ): void {
		echo '<div class="pfont-card pfont-empty pfont-mt">';
		Icons::render( 'fonts' );
		$text = $presets_above
			? __( 'Add a Persian font above, or upload your own WOFF2, WOFF, TTF or OTF files.', 'pfont' )
			: __( 'Upload your own WOFF2, WOFF, TTF or OTF files.', 'pfont' );
		printf( '<h2>%1$s</h2><p>%2$s</p>', esc_html__( 'Your library is empty', 'pfont' ), esc_html( $text ) );
		AdminPage::button_link( __( 'Upload a font', 'pfont' ), AdminPage::url( array( 'tab' => 'edit' ) ), 'upload' );
		echo '</div>';
	}

	/**
	 * Preset cards. Nothing is added or loaded on the site until selected.
	 *
	 * @return bool Whether any preset was shown.
	 */
	private static function render_presets(): bool {
		$available = self::available_presets();
		if ( ! $available ) {
			return false;
		}
		AdminPage::section_title(
			__( 'Persian and Arabic fonts', 'pfont' ),
			/* translators: %s: font service name. */
			sprintf( __( 'Free Google Fonts under the SIL Open Font License. These previews load from %s; nothing is added to your site until you select a font.', 'pfont' ), CdnFonts::provider()['label'] )
		);
		printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( 'pfont_add_presets' );
		echo '<input type="hidden" name="action" value="pfont_add_presets"><div class="pfont-preset-grid">';
		$categories = array(
			'serif'      => __( 'Serif', 'pfont' ),
			'sans-serif' => __( 'Sans-serif', 'pfont' ),
		);
		foreach ( $available as $id => $preset ) {
			$weights = count( $preset['weights'] ) > 1 ? min( $preset['weights'] ) . '–' . max( $preset['weights'] ) : (string) $preset['weights'][0];
			printf(
				'<label class="pfont-preset"><input type="checkbox" name="pfont_presets[]" value="%1$s"><span class="pfont-preset__sample" style="font-family:%2$s" dir="rtl" lang="fa">%3$s</span><span class="pfont-preset__name">%4$s</span><span class="pfont-preset__meta">%5$s</span></label>',
				esc_attr( $id ),
				esc_attr( "'" . $preset['family'] . "', " . $preset['fallback'] ),
				esc_html__( 'خوش آمدید', 'pfont' ),
				esc_html( $preset['family'] ),
				/* translators: 1: font category, 2: weight range. */
				esc_html( sprintf( __( '%1$s | weights %2$s', 'pfont' ), $categories[ $preset['fallback'] ] ?? $preset['fallback'], $weights ) )
			);
		}
		printf( '</div><div class="pfont-preset-actions"><button type="submit" class="pfont-btn">%s</button></div></form>', esc_html__( 'Add selected fonts', 'pfont' ) );
		return true;
	}
}
