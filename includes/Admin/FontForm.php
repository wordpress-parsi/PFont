<?php
/**
 * Add/edit form, grouped into cards. JavaScript only toggles sections.
 *
 * @package PFont
 */

namespace PFont\Admin;

use PFont\Core\Font;
use PFont\Core\FontRegistry;
use PFont\Core\FontValidator;
use PFont\Fonts\CdnFonts;
use PFont\Fonts\FontStorage;
use PFont\Helpers\FontHelper;
use PFont\Integrations\IntegrationManager;

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
			AdminPage::page_header( __( 'Font not found', 'pfont' ), __( 'It may have been deleted.', 'pfont' ), array(), AdminPage::url(), __( 'Library', 'pfont' ) );
			return;
		}
		$v = $font ? $font->to_array() : Font::defaults();
		// Written by Actions::fail() after FontValidator::sanitize_raw_input(), so every value is already clean.
		$state = get_transient( 'pfont_form_' . get_current_user_id() );
		if ( is_array( $state ) ) {
			delete_transient( 'pfont_form_' . get_current_user_id() );
			foreach ( array( 'name', 'family', 'fallback', 'source', 'preset', 'display' ) as $key ) {
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
			$font ? sprintf( __( 'Edit %s', 'pfont' ), $font->name() ) : __( 'Add new font', 'pfont' ),
			$font ? __( 'Changes apply everywhere this font is used.', 'pfont' ) : __( 'Choose a Google font or upload your own files.', 'pfont' ),
			array(),
			$back,
			$font ? $font->name() : __( 'Library', 'pfont' )
		);

		printf( '<form method="post" action="%s" enctype="multipart/form-data" data-pfont-form>', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( 'pfont_save_font' );
		// The hidden first button makes Enter save the form instead of triggering a hosting button.
		printf( '<input type="hidden" name="action" value="pfont_save_font"><input type="hidden" name="pfont[id]" value="%1$s"><button type="submit" class="screen-reader-text" tabindex="-1" aria-hidden="true">%2$s</button>', esc_attr( $id ), esc_html__( 'Save', 'pfont' ) );

		AdminPage::section_title( __( 'Source', 'pfont' ) );
		echo '<div class="pfont-choice-grid">';
		self::choice( 'cdn', 'cloud', __( 'Google Fonts', 'pfont' ), __( 'Pick a Google font. You can host it on this server later with one click.', 'pfont' ), (string) $v['source'] );
		self::choice( 'upload', 'upload', __( 'Upload custom font', 'pfont' ), __( 'Use your own WOFF2, WOFF, TTF or OTF files.', 'pfont' ), (string) $v['source'] );
		echo '</div>';

		AdminPage::section_title( __( 'Font', 'pfont' ) );
		printf( '<div class="pfont-card"><div class="pfont-grid-2"><div class="pfont-field pfont-field--wide" data-pfont-source="cdn"><label for="pfont-preset">%1$s</label><select id="pfont-preset" class="pfont-select" name="pfont[preset]"><option value="" data-weights="">%2$s</option>', esc_html__( 'Google font', 'pfont' ), esc_html__( 'Another Google font (type its exact name below)', 'pfont' ) );
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
		self::input_field( 'pfont-name', 'pfont[name]', __( 'Display name', 'pfont' ), (string) $v['name'], __( 'Shown in font menus.', 'pfont' ) );
		self::input_field( 'pfont-family', 'pfont[family]', __( 'CSS font family', 'pfont' ), (string) $v['family'], __( 'The exact name written into CSS. For your own copy of a font that Elementor or Astra already list, use an alias such as “Vazirmatn Local”.', 'pfont' ) );
		self::input_field( 'pfont-fallback', 'pfont[fallback]', __( 'Fallback', 'pfont' ), (string) $v['fallback'], __( 'Shown while the font loads, for example “Tahoma, sans-serif”.', 'pfont' ) );
		// One "Font" section: the fields, then the part that belongs to the chosen source.
		echo '</div><div data-pfont-source="cdn">';

		AdminPage::card_subtitle( __( 'Weights and styles', 'pfont' ), __( 'For variable fonts, two or more weights download the same file as the full range. Unsupported combinations are rejected when you save.', 'pfont' ) );
		printf( '<div class="pfont-chips" role="group" aria-label="%s">', esc_attr__( 'Weights', 'pfont' ) );
		foreach ( FontHelper::WEIGHTS as $weight ) {
			printf( '<label class="pfont-chip"><input type="checkbox" name="pfont[weights][]" value="%1$d"%2$s><span>%1$d</span></label>', (int) $weight, checked( in_array( $weight, $weights, true ), true, false ) );
		}
		printf( '</div><div class="pfont-chips" role="group" aria-label="%s">', esc_attr__( 'Styles', 'pfont' ) );
		foreach ( Font::STYLES as $style ) {
			printf(
				'<label class="pfont-chip"><input type="checkbox" name="pfont[styles][]" value="%1$s"%2$s><span>%3$s</span></label>',
				esc_attr( $style ),
				checked( in_array( $style, (array) $v['styles'], true ), true, false ),
				esc_html( 'italic' === $style ? __( 'Italic', 'pfont' ) : __( 'Normal', 'pfont' ) )
			);
		}
		echo '</div></div><div data-pfont-source="upload">';

		AdminPage::card_subtitle(
			__( 'Files', 'pfont' ),
			/* translators: %s: size limit. */
			sprintf( __( 'One row per weight and style. WOFF2 is best; the real format is read from the file contents, not the name. Limit: %s per file.', 'pfont' ), size_format( FontValidator::max_upload_size() ) )
		);
		self::render_files( $font );
		echo '</div></div>';

		if ( $font && $font->is_cdn() ) {
			self::render_hosting( $font );
		}

		echo '<div id="pfont-usage">';
		AdminPage::section_title( __( 'Where it appears', 'pfont' ), __( 'Turn a place off to keep this font out of its font menus.', 'pfont' ) );
		echo '<div class="pfont-card"><div class="pfont-toggle-list">';
		foreach ( FontList::labels() as $key => $label ) {
			$adapter  = IntegrationManager::for_key( $key );
			$detected = $adapter && $adapter->is_available();
			$version  = $detected ? $adapter->version() : '';
			if ( ! $detected ) {
				$desc = __( 'Not detected on this site', 'pfont' );
			} elseif ( preg_match( '/^\d+(\.\d+)+/', $version ) ) {
				/* translators: %s: version number. */
				$desc = sprintf( __( 'Detected, version %s', 'pfont' ), $version );
			} else {
				$desc = __( 'Detected', 'pfont' );
			}
			AdminPage::toggle_row( 'pfont[integrations][' . $key . ']', self::icon_for( $key ), $label, $desc, ! empty( $v['integrations'][ $key ] ), ! $detected );
		}
		echo '</div>';
		if ( $font ) {
			foreach ( IntegrationManager::available() as $adapter ) {
				$note = $adapter->native_conflict( $font );
				if ( '' !== $note ) {
					printf( '<p class="pfont-card__note">%s</p>', esc_html( $note ) );
				}
			}
		}
		echo '</div></div>';

		AdminPage::section_title( __( 'Loading', 'pfont' ) );
		printf( '<div class="pfont-card"><div class="pfont-grid-2"><div class="pfont-field"><label for="pfont-display">%s</label><select id="pfont-display" class="pfont-select" name="pfont[display]">', esc_html__( 'Text while the font loads', 'pfont' ) );
		foreach ( Font::DISPLAYS as $display ) {
			printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $display ), selected( $v['display'], $display, false ) );
		}
		printf( '</select><p class="pfont-field__help">%s</p></div></div><div class="pfont-toggle-list pfont-mt">', esc_html__( '“swap” shows text at once in the fallback font, then switches to this font.', 'pfont' ) );
		AdminPage::toggle_row( 'pfont[enabled]', 'check', __( 'Enabled', 'pfont' ), __( 'Turn off to hide the font everywhere without deleting it.', 'pfont' ), ! empty( $v['enabled'] ) );
		printf(
			'</div></div><div class="pfont-savebar"><a class="pfont-btn pfont-btn--ghost" href="%1$s">%2$s</a><button type="submit" class="pfont-btn">%3$s</button></div></form>',
			esc_url( $back ),
			esc_html__( 'Cancel', 'pfont' ),
			esc_html( $font ? __( 'Save changes', 'pfont' ) : __( 'Add font', 'pfont' ) )
		);
	}

	/**
	 * Hosting card for Google fonts.
	 *
	 * @param Font $font Font.
	 */
	private static function render_hosting( Font $font ): void {
		echo '<div data-pfont-source="cdn" id="pfont-hosting">';
		AdminPage::section_title( __( 'Hosting', 'pfont' ) );
		echo '<div class="pfont-card"><div class="pfont-callout pfont-callout--flat">';
		Icons::render( $font->is_self_hosted() ? 'server' : 'cloud' );
		if ( $font->is_self_hosted() ) {
			printf(
				'<div class="pfont-callout__text"><span class="pfont-callout__label">%1$s</span><span class="pfont-callout__msg">%2$s</span></div><button type="submit" class="pfont-btn pfont-btn--ghost" name="pfont[after]" value="unhost">%3$s</button>',
				esc_html__( 'Hosted on this server', 'pfont' ),
				/* translators: %s: date. */
				esc_html( sprintf( __( 'Downloaded %s. Visitors never contact the font service.', 'pfont' ), wp_date( (string) get_option( 'date_format' ), (int) ( $font->get( 'local' )['downloaded'] ?? 0 ) ) ) ),
				esc_html__( 'Switch back to the CDN', 'pfont' )
			);
		} else {
			printf(
				'<div class="pfont-callout__text"><span class="pfont-callout__label">%1$s</span><span class="pfont-callout__msg">%2$s</span></div><button type="submit" class="pfont-btn" name="pfont[after]" value="selfhost">%3$s</button>',
				esc_html__( 'Loaded from the CDN', 'pfont' ),
				esc_html__( 'Hosting it here removes third-party requests (GDPR) and allows an alias name.', 'pfont' ),
				esc_html__( 'Host on this server', 'pfont' )
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
			echo '<div class="pfont-table-wrap"><table class="pfont-table"><thead><tr>';
			$headings = array(
				__( 'Weight', 'pfont' ),
				__( 'Style', 'pfont' ),
				__( 'Files', 'pfont' ),
				__( 'Replace or add formats', 'pfont' ),
				__( 'Delete', 'pfont' ),
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
							'<span class="pfont-file"><a href="%1$s" target="_blank" rel="noopener">%2$s</a><label><input type="checkbox" name="pfont[existing][%3$s][remove][%4$s]" value="1"> %5$s</label></span>',
							esc_url( FontStorage::url( (string) $relative ) ),
							esc_html( strtoupper( (string) $format ) ),
							esc_attr( $key ),
							esc_attr( (string) $format ),
							esc_html__( 'remove', 'pfont' )
						);
					}
					printf(
						'</td><td><input type="file" name="pfont_existing[%1$s][]" accept=".woff2,.woff,.ttf,.otf" multiple></td><td><input type="checkbox" name="pfont[existing][%1$s][delete]" value="1" aria-label="%2$s"></td></tr>',
						esc_attr( $key ),
						/* translators: %s: weight-style key. */
						esc_attr( sprintf( __( 'Delete %s', 'pfont' ), $key ) )
					);
				}
			}
			echo '</tbody></table></div>';
		}
		printf( '<div class="pfont-files-new"><p class="pfont-field__label">%s</p><div class="pfont-table-wrap"><table class="pfont-table"><tbody data-pfont-rows>', esc_html__( 'Add weights', 'pfont' ) );
		self::new_row( '0' );
		echo '</tbody></table></div><template id="pfont-row-template">';
		self::new_row( '__i__' );
		printf( '</template><p><button type="button" class="pfont-btn pfont-btn--ghost pfont-btn--sm" data-pfont-add-row>%s</button></p></div>', esc_html__( 'Add another weight', 'pfont' ) );
	}

	/**
	 * A new weight row.
	 *
	 * @param string $index Row index or template token.
	 */
	private static function new_row( string $index ): void {
		printf( '<tr><td><select class="pfont-select pfont-select--mini" name="pfont[new][%1$s][weight]" aria-label="%2$s">', esc_attr( $index ), esc_attr__( 'Weight', 'pfont' ) );
		foreach ( FontHelper::WEIGHTS as $weight ) {
			printf( '<option value="%1$d"%2$s>%1$d</option>', (int) $weight, selected( 400, $weight, false ) );
		}
		printf(
			'</select></td><td><select class="pfont-select pfont-select--mini" name="pfont[new][%1$s][style]" aria-label="%2$s"><option value="normal">%3$s</option><option value="italic">%4$s</option></select></td><td><input type="file" name="pfont_new[%1$s][]" accept=".woff2,.woff,.ttf,.otf" multiple></td><td><button type="button" class="pfont-btn pfont-btn--ghost pfont-btn--sm" data-pfont-remove-row>%5$s</button></td></tr>',
			esc_attr( $index ),
			esc_attr__( 'Style', 'pfont' ),
			esc_html__( 'Normal', 'pfont' ),
			esc_html__( 'Italic', 'pfont' ),
			esc_html__( 'Remove row', 'pfont' )
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
		printf( '<label class="pfont-choice"><input type="radio" name="pfont[source]" value="%1$s"%2$s><span class="pfont-choice__icon">', esc_attr( $value ), checked( $current, $value, false ) );
		Icons::render( $icon );
		printf( '</span><span><span class="pfont-choice__title">%1$s</span><span class="pfont-choice__desc">%2$s</span></span></label>', esc_html( $title ), esc_html( $desc ) );
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
			'<div class="pfont-field" data-pfont-source="%1$s"><label for="%2$s">%3$s</label><input type="%4$s" class="pfont-input" id="%2$s" name="%5$s" value="%6$s"><p class="pfont-field__help">%7$s</p></div>',
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
