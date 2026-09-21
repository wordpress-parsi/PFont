<?php
/**
 * Library: searchable specimen list and the Persian/Arabic presets.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Admin;

use UniversalCustomFonts\Core\Font;
use UniversalCustomFonts\Core\FontRegistry;
use UniversalCustomFonts\Fonts\CdnFonts;

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
			'tinymce'      => __( 'Classic Editor', 'universal-custom-fonts' ),
			'block_editor' => __( 'Block Editor', 'universal-custom-fonts' ),
			'elementor'    => 'Elementor',
			'astra'        => 'Astra',
			'customizer'   => __( 'Theme Customizer', 'universal-custom-fonts' ),
		);
	}

	/**
	 * One-line preview sentences per writing system.
	 *
	 * @return array<string,string>
	 */
	public static function samples(): array {
		return array(
			'fa'    => __( 'تمام افراد بشر آزاد به دنیا می‌آیند و از لحاظ حیثیت و حقوق با هم برابرند.', 'universal-custom-fonts' ),
			'latin' => __( 'All human beings are born free and equal in dignity and rights.', 'universal-custom-fonts' ),
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
			return __( 'Uploaded files', 'universal-custom-fonts' );
		}
		if ( '' !== $font->cdn_url() ) {
			return __( 'Custom CDN', 'universal-custom-fonts' );
		}
		if ( $font->is_self_hosted() ) {
			return __( 'Hosted on this server', 'universal-custom-fonts' );
		}
		/* translators: %s: font service name. */
		return sprintf( __( '%s (CDN)', 'universal-custom-fonts' ), CdnFonts::provider()['label'] );
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
		if ( $font->is_cdn() && '' === $font->cdn_url() && is_array( $preset['range'] ?? null ) && count( $weights ) > 1 ) {
			/* translators: 1: lightest weight, 2: boldest weight. */
			return sprintf( __( 'Variable %1$d–%2$d', 'universal-custom-fonts' ), min( $weights ), max( $weights ) );
		}
		/* translators: %s: comma-separated weights. */
		return sprintf( _n( 'Weight %s', 'Weights %s', count( $weights ), 'universal-custom-fonts' ), implode( ', ', $weights ) );
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
		AdminPage::page_header( __( 'Font library', 'universal-custom-fonts' ), __( 'Fonts here appear in the editors and builders you choose. Each page loads only the fonts it uses.', 'universal-custom-fonts' ) );
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
		echo '<div class="ucf-callout">';
		Icons::render( 'shield' );
		printf(
			'<div class="ucf-callout__text"><span class="ucf-callout__label">%1$s</span><span class="ucf-callout__msg">%2$s</span></div>',
			esc_html__( 'Privacy', 'universal-custom-fonts' ),
			/* translators: %d: number of fonts. */
			esc_html( sprintf( _n( '%d font loads from a third-party CDN, which receives your visitors’ IP addresses.', '%d fonts load from a third-party CDN, which receives your visitors’ IP addresses.', count( $remote ), 'universal-custom-fonts' ), count( $remote ) ) )
		);
		AdminPage::button_link(
			__( 'Host on this server', 'universal-custom-fonts' ),
			AdminPage::url(
				array(
					'tab'  => 'edit',
					'font' => $first->id(),
				)
			) . '#ucf-hosting',
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

		echo '<div class="ucf-toolbar" data-ucf-toolbar hidden>';
		printf( '<label class="ucf-search"><span class="screen-reader-text">%s</span>', esc_html__( 'Search fonts', 'universal-custom-fonts' ) );
		Icons::render( 'search' );
		printf( '<input type="search" class="ucf-input" data-ucf-filter placeholder="%s"></label>', esc_attr__( 'Search fonts', 'universal-custom-fonts' ) );
		printf( '<label><span class="screen-reader-text">%1$s</span><input type="text" class="ucf-input" data-ucf-preview-text placeholder="%2$s"></label>', esc_html__( 'Preview text', 'universal-custom-fonts' ), esc_attr__( 'Type your own preview text', 'universal-custom-fonts' ) );
		printf(
			'<label><span class="screen-reader-text">%1$s</span><select class="ucf-select ucf-select--pill" data-ucf-script><option value="auto">%2$s</option><option value="fa">%3$s</option><option value="latin">%4$s</option></select></label>',
			esc_html__( 'Writing system', 'universal-custom-fonts' ),
			esc_html__( 'Automatic script', 'universal-custom-fonts' ),
			esc_html__( 'Persian', 'universal-custom-fonts' ),
			esc_html__( 'Latin', 'universal-custom-fonts' )
		);
		printf( '<label class="ucf-size"><span class="screen-reader-text">%s</span><input type="range" min="16" max="80" value="40" data-ucf-size><output data-ucf-size-out>40px</output></label></div>', esc_html__( 'Preview size', 'universal-custom-fonts' ) );

		printf(
			'<form method="post" action="%1$s" class="ucf-font-list" data-ucf-list data-samples="%2$s" data-one="%3$s" data-many="%4$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( (string) wp_json_encode( $samples ) ),
			/* translators: 1: fonts shown, 2: fonts in the library. */
			esc_attr__( '%1$s of %2$s font', 'universal-custom-fonts' ),
			/* translators: 1: fonts shown, 2: fonts in the library. */
			esc_attr__( '%1$s of %2$s fonts', 'universal-custom-fonts' )
		);
		wp_nonce_field( 'ucf_bulk_update' );
		echo '<input type="hidden" name="action" value="ucf_bulk_update"><input type="hidden" name="ucf_scope" value="enabled"><div class="ucf-list-head">';
		/* translators: 1: fonts shown, 2: fonts in the library. */
		printf( '<span data-ucf-count>%s</span>', esc_html( sprintf( _n( '%1$s of %2$s font', '%1$s of %2$s fonts', $total, 'universal-custom-fonts' ), number_format_i18n( $total ), number_format_i18n( $total ) ) ) );
		printf( '<a href="%s">', esc_url( AdminPage::url( array( 'tab' => 'help' ) ) . '#ucf-help-loading' ) );
		Icons::render( 'info' );
		printf( '<span>%s</span></a></div>', esc_html__( 'How loading works', 'universal-custom-fonts' ) );
		foreach ( $fonts as $font ) {
			self::row( $font, $samples );
		}
		printf( '<p class="ucf-list-empty" data-ucf-empty hidden>%s</p>', esc_html__( 'No fonts match your search.', 'universal-custom-fonts' ) );
		printf( '<div class="ucf-savebar" data-ucf-savebar><button type="submit" class="ucf-btn">%s</button></div></form>', esc_html__( 'Save changes', 'universal-custom-fonts' ) );
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
		printf( '<article class="%1$s" data-ucf-row data-name="%2$s"><div class="ucf-font-row__meta">', esc_attr( $font->is_enabled() ? 'ucf-font-row' : 'ucf-font-row is-disabled' ), esc_attr( strtolower( $font->name() . ' ' . $font->family() ) ) );
		printf( '<a class="ucf-font-row__name" href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $font->name() ) );
		foreach ( array( self::weight_label( $font ), self::source_label( $font ) ) as $part ) {
			printf( '<span class="ucf-font-row__sep" aria-hidden="true">|</span><span>%s</span>', esc_html( $part ) );
		}
		if ( ! $font->is_enabled() ) {
			printf( '<span class="ucf-badge">%s</span>', esc_html__( 'Disabled', 'universal-custom-fonts' ) );
		}
		printf(
			'<span class="ucf-font-row__actions"><input type="hidden" name="ucf_ids[]" value="%1$s"><label><span class="screen-reader-text">%2$s</span><input type="checkbox" class="ucf-switch" role="switch" name="ucf_enabled[%1$s]" value="1"%3$s></label>',
			esc_attr( $id ),
			/* translators: %s: font name. */
			esc_html( sprintf( __( 'Enable %s', 'universal-custom-fonts' ), $font->name() ) ),
			checked( $font->is_enabled(), true, false )
		);
		$edit = AdminPage::url(
			array(
				'tab'  => 'edit',
				'font' => $id,
			)
		);
		printf( '<a class="ucf-icon-btn" href="%1$s" title="%2$s"><span class="screen-reader-text">%2$s</span>', esc_url( $edit ), esc_attr__( 'Edit settings', 'universal-custom-fonts' ) );
		Icons::render( 'edit' );
		printf(
			'</a><a class="ucf-icon-btn ucf-icon-btn--danger" href="%1$s" title="%2$s" data-ucf-confirm="%3$s"><span class="screen-reader-text">%2$s</span>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ucf_delete_font&font=' . rawurlencode( $id ) ), 'ucf_delete_font_' . $id ) ),
			esc_attr__( 'Delete', 'universal-custom-fonts' ),
			/* translators: %s: font name. */
			esc_attr( sprintf( __( 'Delete “%s” and its files?', 'universal-custom-fonts' ), $font->name() ) )
		);
		Icons::render( 'trash' );
		printf(
			'</a></span></div><a class="ucf-font-row__sample" href="%1$s" style="font-family:%2$s" data-ucf-sample data-script="%3$s" dir="%4$s" lang="%5$s" aria-hidden="true" tabindex="-1">%6$s</a></article>',
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
		echo '<div class="ucf-card ucf-empty ucf-mt">';
		Icons::render( 'fonts' );
		$text = $presets_above
			? __( 'Add a Persian font above, or upload your own WOFF2, WOFF, TTF or OTF files.', 'universal-custom-fonts' )
			: __( 'Upload your own WOFF2, WOFF, TTF or OTF files.', 'universal-custom-fonts' );
		printf( '<h2>%1$s</h2><p>%2$s</p>', esc_html__( 'Your library is empty', 'universal-custom-fonts' ), esc_html( $text ) );
		AdminPage::button_link( __( 'Upload a font', 'universal-custom-fonts' ), AdminPage::url( array( 'tab' => 'edit' ) ), 'upload' );
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
			__( 'Persian and Arabic fonts', 'universal-custom-fonts' ),
			/* translators: %s: font service name. */
			sprintf( __( 'Free Google Fonts under the SIL Open Font License. These previews load from %s; nothing is added to your site until you select a font.', 'universal-custom-fonts' ), CdnFonts::provider()['label'] )
		);
		printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( 'ucf_add_presets' );
		echo '<input type="hidden" name="action" value="ucf_add_presets"><div class="ucf-preset-grid">';
		$categories = array(
			'serif'      => __( 'Serif', 'universal-custom-fonts' ),
			'sans-serif' => __( 'Sans-serif', 'universal-custom-fonts' ),
		);
		foreach ( $available as $id => $preset ) {
			$weights = count( $preset['weights'] ) > 1 ? min( $preset['weights'] ) . '–' . max( $preset['weights'] ) : (string) $preset['weights'][0];
			printf(
				'<label class="ucf-preset"><input type="checkbox" name="ucf_presets[]" value="%1$s"><span class="ucf-preset__sample" style="font-family:%2$s" dir="rtl" lang="fa">%3$s</span><span class="ucf-preset__name">%4$s</span><span class="ucf-preset__meta">%5$s</span></label>',
				esc_attr( $id ),
				esc_attr( "'" . $preset['family'] . "', " . $preset['fallback'] ),
				esc_html__( 'خوش آمدید', 'universal-custom-fonts' ),
				esc_html( $preset['family'] ),
				/* translators: 1: font category, 2: weight range. */
				esc_html( sprintf( __( '%1$s | weights %2$s', 'universal-custom-fonts' ), $categories[ $preset['fallback'] ] ?? $preset['fallback'], $weights ) )
			);
		}
		printf( '</div><div class="ucf-preset-actions"><button type="submit" class="ucf-btn">%s</button></div></form>', esc_html__( 'Add selected fonts', 'universal-custom-fonts' ) );
		return true;
	}
}
