<?php
/**
 * Add/edit form, grouped into cards. JavaScript only toggles sections.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Admin;

use UniversalCustomFonts\Core\Font;
use UniversalCustomFonts\Core\FontRegistry;
use UniversalCustomFonts\Core\FontValidator;
use UniversalCustomFonts\Fonts\CdnFonts;
use UniversalCustomFonts\Fonts\FontStorage;
use UniversalCustomFonts\Helpers\FontHelper;
use UniversalCustomFonts\Integrations\IntegrationManager;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the form.
 */
final class FontForm {

	private const ICONS = array(
		'tinymce'      => 'editor',
		'block_editor' => 'blocks',
		'elementor'    => 'layout',
		'astra'        => 'window',
		'customizer'   => 'window',
	);

	/**
	 * Icon for an integration key.
	 *
	 * @param string $key Integration key.
	 * @return string
	 */
	public static function icon_for( string $key ): string {
		return self::ICONS[ $key ] ?? 'layout';
	}

	/**
	 * Render.
	 */
	public static function render(): void {
		$id   = isset( $_GET['font'] ) ? sanitize_key( wp_unslash( $_GET['font'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only.
		$font = '' !== $id ? FontRegistry::get_font( $id ) : null;
		if ( '' !== $id && ! $font ) {
			AdminPage::page_header( __( 'Font not found', 'universal-custom-fonts' ), __( 'It may have been deleted.', 'universal-custom-fonts' ), array(), AdminPage::url(), __( 'Library', 'universal-custom-fonts' ) );
			return;
		}
		$v     = $font ? $font->to_array() : Font::defaults();
		$state = get_transient( 'ucf_form_' . get_current_user_id() );
		if ( is_array( $state ) ) {
			delete_transient( 'ucf_form_' . get_current_user_id() );
			foreach ( array( 'name', 'family', 'fallback', 'source', 'preset', 'cdn_url', 'display' ) as $key ) {
				if ( isset( $state[ $key ] ) ) {
					$v[ $key ] = (string) $state[ $key ];
				}
			}
			$v['weights']      = isset( $state['weights'] ) ? (array) $state['weights'] : $v['weights'];
			$v['styles']       = isset( $state['styles'] ) ? (array) $state['styles'] : $v['styles'];
			$v['integrations'] = isset( $state['integrations'] ) ? (array) $state['integrations'] : array();
			$v['enabled']      = ! empty( $state['enabled'] );
		}
		$weights = array_map( 'intval', (array) $v['weights'] );
		$back    = $font ? AdminPage::url(
			array(
				'tab'  => 'font',
				'font' => $id,
			)
		) : AdminPage::url();

		AdminPage::page_header(
			/* translators: %s: font name. */
			$font ? sprintf( __( 'Edit %s', 'universal-custom-fonts' ), $font->name() ) : __( 'Add new font', 'universal-custom-fonts' ),
			$font ? __( 'Changes apply everywhere this font is used.', 'universal-custom-fonts' ) : __( 'Choose a Google font or upload your own files.', 'universal-custom-fonts' ),
			array(),
			$back,
			$font ? $font->name() : __( 'Library', 'universal-custom-fonts' )
		);

		printf( '<form method="post" action="%s" enctype="multipart/form-data" data-ucf-form>', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( 'ucf_save_font' );
		// The hidden first button makes Enter save the form instead of triggering a hosting button.
		printf( '<input type="hidden" name="action" value="ucf_save_font"><input type="hidden" name="ucf[id]" value="%1$s"><button type="submit" class="screen-reader-text" tabindex="-1" aria-hidden="true">%2$s</button>', esc_attr( $id ), esc_html__( 'Save', 'universal-custom-fonts' ) );

		AdminPage::section_title( __( 'Source', 'universal-custom-fonts' ) );
		echo '<div class="ucf-choice-grid">';
		self::choice( 'cdn', 'cloud', __( 'Google Fonts', 'universal-custom-fonts' ), __( 'Pick a Google font. You can host it on this server later with one click.', 'universal-custom-fonts' ), (string) $v['source'] );
		self::choice( 'upload', 'upload', __( 'Upload custom font', 'universal-custom-fonts' ), __( 'Use your own WOFF2, WOFF, TTF or OTF files.', 'universal-custom-fonts' ), (string) $v['source'] );
		echo '</div>';

		AdminPage::section_title( __( 'Font', 'universal-custom-fonts' ) );
		printf( '<div class="ucf-card"><div class="ucf-grid-2"><div class="ucf-field ucf-field--wide" data-ucf-source="cdn"><label for="ucf-preset">%1$s</label><select id="ucf-preset" class="ucf-select" name="ucf[preset]"><option value="" data-weights="">%2$s</option>', esc_html__( 'Google font', 'universal-custom-fonts' ), esc_html__( 'Another Google font (type its exact name below)', 'universal-custom-fonts' ) );
		foreach ( CdnFonts::presets() as $preset_id => $preset ) {
			printf(
				'<option value="%1$s" data-family="%2$s" data-fallback="%3$s" data-weights="%4$s"%5$s>%2$s</option>',
				esc_attr( $preset_id ),
				esc_attr( $preset['family'] ),
				esc_attr( $preset['fallback'] ),
				esc_attr( implode( ',', $preset['weights'] ) ),
				selected( $v['preset'], $preset_id, false )
			);
		}
		echo '</select></div>';
		self::input_field( 'ucf-name', 'ucf[name]', __( 'Display name', 'universal-custom-fonts' ), (string) $v['name'], __( 'Shown in font menus.', 'universal-custom-fonts' ) );
		self::input_field( 'ucf-family', 'ucf[family]', __( 'CSS font family', 'universal-custom-fonts' ), (string) $v['family'], __( 'The exact name written into CSS. For your own copy of a font that Elementor or Astra already list, use an alias such as “Vazirmatn Local”.', 'universal-custom-fonts' ) );
		self::input_field( 'ucf-fallback', 'ucf[fallback]', __( 'Fallback', 'universal-custom-fonts' ), (string) $v['fallback'], __( 'Shown while the font loads, for example “Tahoma, sans-serif”.', 'universal-custom-fonts' ) );
		self::input_field( 'ucf-cdn-url', 'ucf[cdn_url]', __( 'Custom stylesheet URL', 'universal-custom-fonts' ), (string) $v['cdn_url'], __( 'Optional. Only for fonts from another CDN (https).', 'universal-custom-fonts' ), 'cdn', 'url' );
		// One "Font" section: the fields, then the part that belongs to the chosen source.
		echo '</div><div data-ucf-source="cdn">';

		AdminPage::card_subtitle( __( 'Weights and styles', 'universal-custom-fonts' ), __( 'For variable fonts, two or more weights download the same file as the full range. Unsupported combinations are rejected when you save.', 'universal-custom-fonts' ) );
		printf( '<div class="ucf-chips" role="group" aria-label="%s">', esc_attr__( 'Weights', 'universal-custom-fonts' ) );
		foreach ( FontHelper::WEIGHTS as $weight ) {
			printf( '<label class="ucf-chip"><input type="checkbox" name="ucf[weights][]" value="%1$d"%2$s><span>%1$d</span></label>', (int) $weight, checked( in_array( $weight, $weights, true ), true, false ) );
		}
		printf( '</div><div class="ucf-chips" role="group" aria-label="%s">', esc_attr__( 'Styles', 'universal-custom-fonts' ) );
		foreach ( Font::STYLES as $style ) {
			printf(
				'<label class="ucf-chip"><input type="checkbox" name="ucf[styles][]" value="%1$s"%2$s><span>%3$s</span></label>',
				esc_attr( $style ),
				checked( in_array( $style, (array) $v['styles'], true ), true, false ),
				esc_html( 'italic' === $style ? __( 'Italic', 'universal-custom-fonts' ) : __( 'Normal', 'universal-custom-fonts' ) )
			);
		}
		echo '</div></div><div data-ucf-source="upload">';

		AdminPage::card_subtitle(
			__( 'Files', 'universal-custom-fonts' ),
			/* translators: %s: size limit. */
			sprintf( __( 'One row per weight and style. WOFF2 is best; the real format is read from the file contents, not the name. Limit: %s per file.', 'universal-custom-fonts' ), size_format( FontValidator::max_upload_size() ) )
		);
		self::render_files( $font );
		echo '</div></div>';

		if ( $font && $font->is_cdn() && '' === $font->cdn_url() ) {
			self::render_hosting( $font );
		}

		echo '<div id="ucf-usage">';
		AdminPage::section_title( __( 'Where it appears', 'universal-custom-fonts' ), __( 'Turn a place off to keep this font out of its font menus.', 'universal-custom-fonts' ) );
		echo '<div class="ucf-card"><div class="ucf-toggle-list">';
		foreach ( FontList::labels() as $key => $label ) {
			$adapter  = IntegrationManager::for_key( $key );
			$detected = $adapter && $adapter->is_available();
			$version  = $detected ? $adapter->version() : '';
			if ( ! $detected ) {
				$desc = __( 'Not detected on this site', 'universal-custom-fonts' );
			} elseif ( preg_match( '/^\d+(\.\d+)+/', $version ) ) {
				/* translators: %s: version number. */
				$desc = sprintf( __( 'Detected, version %s', 'universal-custom-fonts' ), $version );
			} else {
				$desc = __( 'Detected', 'universal-custom-fonts' );
			}
			AdminPage::toggle_row( 'ucf[integrations][' . $key . ']', self::icon_for( $key ), $label, $desc, ! empty( $v['integrations'][ $key ] ), ! $detected );
		}
		echo '</div>';
		if ( $font ) {
			foreach ( IntegrationManager::available() as $adapter ) {
				$note = $adapter->native_conflict( $font );
				if ( '' !== $note ) {
					printf( '<p class="ucf-card__note">%s</p>', esc_html( $note ) );
				}
			}
		}
		echo '</div></div>';

		AdminPage::section_title( __( 'Loading', 'universal-custom-fonts' ) );
		printf( '<div class="ucf-card"><div class="ucf-grid-2"><div class="ucf-field"><label for="ucf-display">%s</label><select id="ucf-display" class="ucf-select" name="ucf[display]">', esc_html__( 'Text while the font loads', 'universal-custom-fonts' ) );
		foreach ( Font::DISPLAYS as $display ) {
			printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $display ), selected( $v['display'], $display, false ) );
		}
		printf( '</select><p class="ucf-field__help">%s</p></div></div><div class="ucf-toggle-list ucf-mt">', esc_html__( '“swap” shows text at once in the fallback font, then switches to this font.', 'universal-custom-fonts' ) );
		AdminPage::toggle_row( 'ucf[enabled]', 'check', __( 'Enabled', 'universal-custom-fonts' ), __( 'Turn off to hide the font everywhere without deleting it.', 'universal-custom-fonts' ), ! empty( $v['enabled'] ) );
		printf(
			'</div></div><div class="ucf-savebar"><a class="ucf-btn ucf-btn--ghost" href="%1$s">%2$s</a><button type="submit" class="ucf-btn">%3$s</button></div></form>',
			esc_url( $back ),
			esc_html__( 'Cancel', 'universal-custom-fonts' ),
			esc_html( $font ? __( 'Save changes', 'universal-custom-fonts' ) : __( 'Add font', 'universal-custom-fonts' ) )
		);
	}

	/**
	 * Hosting card for Google fonts.
	 *
	 * @param Font $font Font.
	 */
	private static function render_hosting( Font $font ): void {
		echo '<div data-ucf-source="cdn" id="ucf-hosting">';
		AdminPage::section_title( __( 'Hosting', 'universal-custom-fonts' ) );
		echo '<div class="ucf-card"><div class="ucf-callout ucf-callout--flat">';
		Icons::render( $font->is_self_hosted() ? 'server' : 'cloud' );
		if ( $font->is_self_hosted() ) {
			printf(
				'<div class="ucf-callout__text"><span class="ucf-callout__label">%1$s</span><span class="ucf-callout__msg">%2$s</span></div><button type="submit" class="ucf-btn ucf-btn--ghost" name="ucf[after]" value="unhost">%3$s</button>',
				esc_html__( 'Hosted on this server', 'universal-custom-fonts' ),
				/* translators: %s: date. */
				esc_html( sprintf( __( 'Downloaded %s. Visitors never contact the font service.', 'universal-custom-fonts' ), wp_date( (string) get_option( 'date_format' ), (int) ( $font->get( 'local' )['downloaded'] ?? 0 ) ) ) ),
				esc_html__( 'Switch back to the CDN', 'universal-custom-fonts' )
			);
		} else {
			printf(
				'<div class="ucf-callout__text"><span class="ucf-callout__label">%1$s</span><span class="ucf-callout__msg">%2$s</span></div><button type="submit" class="ucf-btn" name="ucf[after]" value="selfhost">%3$s</button>',
				esc_html__( 'Loaded from the CDN', 'universal-custom-fonts' ),
				esc_html__( 'Hosting it here removes third-party requests (GDPR) and allows an alias name.', 'universal-custom-fonts' ),
				esc_html__( 'Host on this server', 'universal-custom-fonts' )
			);
		}
		echo '</div></div></div>';
	}

	/**
	 * Uploaded files and new weight rows.
	 *
	 * @param Font|null $font Font.
	 */
	private static function render_files( ?Font $font ): void {
		if ( $font && $font->is_upload() && $font->files() ) {
			echo '<div class="ucf-table-wrap"><table class="ucf-table"><thead><tr>';
			$headings = array(
				__( 'Weight', 'universal-custom-fonts' ),
				__( 'Style', 'universal-custom-fonts' ),
				__( 'Files', 'universal-custom-fonts' ),
				__( 'Replace or add formats', 'universal-custom-fonts' ),
				__( 'Delete', 'universal-custom-fonts' ),
			);
			foreach ( $headings as $heading ) {
				printf( '<th scope="col">%s</th>', esc_html( $heading ) );
			}
			echo '</tr></thead><tbody>';
			foreach ( $font->files() as $weight => $by_style ) {
				foreach ( (array) $by_style as $style => $formats ) {
					$key = $weight . '-' . $style;
					printf( '<tr><td>%1$d</td><td>%2$s</td><td>', (int) $weight, esc_html( (string) $style ) );
					foreach ( (array) $formats as $format => $relative ) {
						printf(
							'<span class="ucf-file"><a href="%1$s" target="_blank" rel="noopener">%2$s</a><label><input type="checkbox" name="ucf[existing][%3$s][remove][%4$s]" value="1"> %5$s</label></span>',
							esc_url( FontStorage::url( (string) $relative ) ),
							esc_html( strtoupper( (string) $format ) ),
							esc_attr( $key ),
							esc_attr( (string) $format ),
							esc_html__( 'remove', 'universal-custom-fonts' )
						);
					}
					printf(
						'</td><td><input type="file" name="ucf_existing[%1$s][]" accept=".woff2,.woff,.ttf,.otf" multiple></td><td><input type="checkbox" name="ucf[existing][%1$s][delete]" value="1" aria-label="%2$s"></td></tr>',
						esc_attr( $key ),
						/* translators: %s: weight-style key. */
						esc_attr( sprintf( __( 'Delete %s', 'universal-custom-fonts' ), $key ) )
					);
				}
			}
			echo '</tbody></table></div>';
		}
		printf( '<div class="ucf-files-new"><p class="ucf-field__label">%s</p><div class="ucf-table-wrap"><table class="ucf-table"><tbody data-ucf-rows>', esc_html__( 'Add weights', 'universal-custom-fonts' ) );
		self::new_row( '0' );
		echo '</tbody></table></div><template id="ucf-row-template">';
		self::new_row( '__i__' );
		printf( '</template><p><button type="button" class="ucf-btn ucf-btn--ghost ucf-btn--sm" data-ucf-add-row>%s</button></p></div>', esc_html__( 'Add another weight', 'universal-custom-fonts' ) );
	}

	/**
	 * A new weight row.
	 *
	 * @param string $index Row index or template token.
	 */
	private static function new_row( string $index ): void {
		printf( '<tr><td><select class="ucf-select ucf-select--mini" name="ucf[new][%1$s][weight]" aria-label="%2$s">', esc_attr( $index ), esc_attr__( 'Weight', 'universal-custom-fonts' ) );
		foreach ( FontHelper::WEIGHTS as $weight ) {
			printf( '<option value="%1$d"%2$s>%1$d</option>', (int) $weight, selected( 400, $weight, false ) );
		}
		printf(
			'</select></td><td><select class="ucf-select ucf-select--mini" name="ucf[new][%1$s][style]" aria-label="%2$s"><option value="normal">%3$s</option><option value="italic">%4$s</option></select></td><td><input type="file" name="ucf_new[%1$s][]" accept=".woff2,.woff,.ttf,.otf" multiple></td><td><button type="button" class="ucf-btn ucf-btn--ghost ucf-btn--sm" data-ucf-remove-row>%5$s</button></td></tr>',
			esc_attr( $index ),
			esc_attr__( 'Style', 'universal-custom-fonts' ),
			esc_html__( 'Normal', 'universal-custom-fonts' ),
			esc_html__( 'Italic', 'universal-custom-fonts' ),
			esc_html__( 'Remove row', 'universal-custom-fonts' )
		);
	}

	/**
	 * Source choice card.
	 *
	 * @param string $value   Value.
	 * @param string $icon    Icon.
	 * @param string $title   Title.
	 * @param string $desc    Description.
	 * @param string $current Current value.
	 */
	private static function choice( string $value, string $icon, string $title, string $desc, string $current ): void {
		printf( '<label class="ucf-choice"><input type="radio" name="ucf[source]" value="%1$s"%2$s><span class="ucf-choice__icon">', esc_attr( $value ), checked( $current, $value, false ) );
		Icons::render( $icon );
		printf( '</span><span><span class="ucf-choice__title">%1$s</span><span class="ucf-choice__desc">%2$s</span></span></label>', esc_html( $title ), esc_html( $desc ) );
	}

	/**
	 * Text field.
	 *
	 * @param string $id     Element ID.
	 * @param string $name   Field name.
	 * @param string $label  Label.
	 * @param string $value  Value.
	 * @param string $help   Help text.
	 * @param string $source Section (all|cdn|upload).
	 * @param string $type   Input type.
	 */
	private static function input_field( string $id, string $name, string $label, string $value, string $help, string $source = 'all', string $type = 'text' ): void {
		printf(
			'<div class="ucf-field" data-ucf-source="%1$s"><label for="%2$s">%3$s</label><input type="%4$s" class="ucf-input" id="%2$s" name="%5$s" value="%6$s"><p class="ucf-field__help">%7$s</p></div>',
			esc_attr( $source ),
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( $value ),
			esc_html( $help )
		);
	}
}
