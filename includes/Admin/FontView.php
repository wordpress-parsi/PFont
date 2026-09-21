<?php
/**
 * Font page: live type tester, sizes, characters, details and where the font appears.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Admin;

use UniversalCustomFonts\Core\Font;
use UniversalCustomFonts\Core\FontRegistry;
use UniversalCustomFonts\Integrations\IntegrationManager;

defined( 'ABSPATH' ) || exit;

/**
 * Renders one font.
 */
final class FontView {

	/**
	 * Tester texts per writing system.
	 *
	 * @return array
	 */
	public static function samples(): array {
		return array(
			'fa'    => array(
				'heading' => __( 'تمام افراد بشر آزاد به دنیا می‌آیند', 'universal-custom-fonts' ),
				'body'    => __( 'تمام افراد بشر آزاد به دنیا می‌آیند و از لحاظ حیثیت و حقوق با هم برابرند. همه دارای عقل و وجدان هستند و باید نسبت به یکدیگر با روح برادری رفتار کنند.', 'universal-custom-fonts' ),
				'small'   => __( 'اعداد: ۰۱۲۳۴۵۶۷۸۹ | قیمت: ۲٬۴۵۰٬۰۰۰ تومان | تخفیف ۳۰٪', 'universal-custom-fonts' ),
				'line'    => __( 'زیبایی در سادگی است', 'universal-custom-fonts' ),
			),
			'latin' => array(
				'heading' => __( 'Sphinx of black quartz, judge my vow', 'universal-custom-fonts' ),
				'body'    => __( 'All human beings are born free and equal in dignity and rights. They are endowed with reason and conscience and should act towards one another in a spirit of brotherhood.', 'universal-custom-fonts' ),
				'small'   => __( 'Numbers: 0123456789 | Price: $1,249.00 | Save 30 percent', 'universal-custom-fonts' ),
				'line'    => __( 'Beauty lives in simplicity', 'universal-custom-fonts' ),
			),
		);
	}

	/**
	 * Standard weight names.
	 *
	 * @return array<int,string>
	 */
	private static function weight_names(): array {
		return array(
			100 => __( 'Thin', 'universal-custom-fonts' ),
			200 => __( 'Extra Light', 'universal-custom-fonts' ),
			300 => __( 'Light', 'universal-custom-fonts' ),
			400 => __( 'Regular', 'universal-custom-fonts' ),
			500 => __( 'Medium', 'universal-custom-fonts' ),
			600 => __( 'Semibold', 'universal-custom-fonts' ),
			700 => __( 'Bold', 'universal-custom-fonts' ),
			800 => __( 'Extra Bold', 'universal-custom-fonts' ),
			900 => __( 'Black', 'universal-custom-fonts' ),
		);
	}

	/**
	 * Whether any weight in the range can be shown (variable CDN or self-hosted preset).
	 *
	 * @param Font $font Font.
	 * @return bool
	 */
	private static function is_variable( Font $font ): bool {
		$preset = $font->preset_data();
		return $font->is_cdn() && '' === $font->cdn_url() && is_array( $preset['range'] ?? null ) && count( $font->weights() ) > 1;
	}

	/**
	 * Weights the slider can reach.
	 *
	 * @param Font $font Font.
	 * @return int[]
	 */
	private static function weight_stops( Font $font ): array {
		$weights = array_map( 'intval', $font->weights() );
		sort( $weights );
		return self::is_variable( $font ) ? range( min( $weights ), max( $weights ), 100 ) : array_values( array_unique( $weights ) );
	}

	/**
	 * Render the view.
	 */
	public static function render(): void {
		$id   = isset( $_GET['font'] ) ? sanitize_key( wp_unslash( $_GET['font'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only.
		$font = '' !== $id ? FontRegistry::get_font( $id ) : null;
		if ( ! $font ) {
			AdminPage::page_header( __( 'Font not found', 'universal-custom-fonts' ), __( 'It may have been deleted.', 'universal-custom-fonts' ), array(), AdminPage::url(), __( 'Library', 'universal-custom-fonts' ) );
			return;
		}
		$stops   = self::weight_stops( $font );
		$initial = in_array( 400, $stops, true ) ? 400 : $stops[0];
		$italic  = in_array( 'italic', $font->styles(), true );
		$script  = FontList::default_script( $font );
		$samples = self::samples();
		$stack   = $font->css_stack();
		$dir     = 'fa' === $script ? 'rtl' : 'ltr';
		$lang    = 'fa' === $script ? 'fa' : 'en';

		self::render_header( $font );
		printf( '<div data-ucf-tester data-family="%1$s" data-samples="%2$s" data-weight="%3$d">', esc_attr( $stack ), esc_attr( (string) wp_json_encode( $samples ) ), (int) $initial );
		printf(
			'<div class="ucf-preview-bar"><label for="ucf-t-script">%1$s</label><select id="ucf-t-script" class="ucf-select ucf-select--pill" data-ucf-t-script><option value="fa"%2$s>%3$s</option><option value="latin"%4$s>%5$s</option></select></div>',
			esc_html__( 'Preview text', 'universal-custom-fonts' ),
			selected( $script, 'fa', false ),
			esc_html__( 'Persian', 'universal-custom-fonts' ),
			selected( $script, 'latin', false ),
			esc_html__( 'Latin', 'universal-custom-fonts' )
		);
		echo '<div class="ucf-tester">';
		self::render_panel( $stops, self::is_variable( $font ), $initial, $italic );
		self::render_stage( $samples[ $script ], $stack, $dir, $lang, $italic, $initial );
		echo '</div><div class="ucf-font-sections">';

		printf( '<section class="ucf-card"><h2 class="ucf-card__title">%1$s</h2><div class="ucf-waterfall" data-ucf-t-apply style="font-family:%2$s">', esc_html__( 'Sizes', 'universal-custom-fonts' ), esc_attr( $stack ) );
		foreach ( array( 14, 18, 24, 32, 48, 64 ) as $size ) {
			printf(
				'<div class="ucf-waterfall__row"><span class="ucf-waterfall__size">%1$d</span><span class="ucf-waterfall__text" style="font-size:%1$dpx" data-ucf-t-text="line" dir="%2$s" lang="%3$s">%4$s</span></div>',
				(int) $size,
				esc_attr( $dir ),
				esc_attr( $lang ),
				esc_html( $samples[ $script ]['line'] )
			);
		}
		printf( '</div></section><section class="ucf-card"><h2 class="ucf-card__title">%1$s</h2><div class="ucf-glyphs" data-ucf-t-apply style="font-family:%2$s">', esc_html__( 'Characters', 'universal-custom-fonts' ), esc_attr( $stack ) );
		foreach ( self::glyph_lines() as $line ) {
			printf( '<p dir="%1$s" lang="%2$s">%3$s</p>', esc_attr( 'fa' === $line[0] ? 'rtl' : 'ltr' ), esc_attr( 'fa' === $line[0] ? 'fa' : 'en' ), esc_html( $line[1] ) );
		}
		echo '</div></section></div></div><div class="ucf-detail-grid ucf-mt">';
		self::render_details( $font );
		self::render_usage( $font );
		echo '</div>';
	}

	/**
	 * Header with the CSS family, summary and actions.
	 *
	 * @param Font $font Font.
	 */
	private static function render_header( Font $font ): void {
		printf( '<header class="ucf-page-head"><div class="ucf-page-head__main"><a class="ucf-back" href="%s">', esc_url( AdminPage::url() ) );
		Icons::render( 'arrow-left' );
		printf(
			'<span>%1$s</span></a><h1 class="ucf-page-head__title">%2$s</h1><div class="ucf-font-head__meta"><code class="ucf-code">%3$s</code><span>%4$s</span><span class="ucf-font-row__sep" aria-hidden="true">|</span><span>%5$s</span><span class="%6$s">%7$s</span></div></div><div class="ucf-page-head__actions">',
			esc_html__( 'Library', 'universal-custom-fonts' ),
			esc_html( $font->name() ),
			esc_html( $font->css_stack() ),
			esc_html( FontList::weight_label( $font ) ),
			esc_html( FontList::source_label( $font ) ),
			esc_attr( $font->is_enabled() ? 'ucf-badge ucf-badge--active' : 'ucf-badge' ),
			esc_html( $font->is_enabled() ? __( 'Enabled', 'universal-custom-fonts' ) : __( 'Disabled', 'universal-custom-fonts' ) )
		);
		AdminPage::button_link(
			__( 'Edit settings', 'universal-custom-fonts' ),
			AdminPage::url(
				array(
					'tab'  => 'edit',
					'font' => $font->id(),
				)
			),
			'edit',
			'ghost'
		);
		echo '</div></header>';
	}

	/**
	 * Style controls.
	 *
	 * @param int[] $stops    Reachable weights.
	 * @param bool  $variable Continuous weights.
	 * @param int   $initial  Starting weight.
	 * @param bool  $italic   Italic available.
	 */
	private static function render_panel( array $stops, bool $variable, int $initial, bool $italic ): void {
		$names = self::weight_names();
		printf( '<aside class="ucf-tester__panel" aria-label="%1$s"><div class="ucf-panel-head"><span>%2$s</span><button type="button" class="ucf-icon-btn" data-ucf-t-reset title="%3$s"><span class="screen-reader-text">%3$s</span>', esc_attr__( 'Style controls', 'universal-custom-fonts' ), esc_html__( 'Styles', 'universal-custom-fonts' ), esc_attr__( 'Reset styles', 'universal-custom-fonts' ) );
		Icons::render( 'reset' );
		printf( '</button></div><div class="ucf-control"><div class="ucf-control__head"><span><label for="ucf-t-weight">%1$s</label> <span class="ucf-control__value" data-ucf-t-out="weight">%2$d</span></span><select class="ucf-select ucf-select--mini" data-ucf-t-weight-name aria-label="%3$s">', esc_html__( 'Weight', 'universal-custom-fonts' ), (int) $initial, esc_attr__( 'Named weight', 'universal-custom-fonts' ) );
		foreach ( $stops as $weight ) {
			if ( isset( $names[ $weight ] ) ) {
				printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $weight, selected( $initial, $weight, false ), esc_html( $names[ $weight ] ) );
			}
		}
		printf(
			'</select></div><input type="range" id="ucf-t-weight" class="ucf-range" data-ucf-t-weight data-stops="%1$s" data-variable="%2$d" min="%3$d" max="%4$d" step="1" value="%5$d"%6$s>',
			esc_attr( implode( ',', $stops ) ),
			$variable ? 1 : 0,
			$variable ? (int) min( $stops ) : 0,
			$variable ? (int) max( $stops ) : count( $stops ) - 1,
			$variable ? (int) $initial : (int) array_search( $initial, $stops, true ),
			count( $stops ) > 1 ? '' : ' disabled'
		);
		if ( count( $stops ) < 2 ) {
			printf( '<p class="ucf-control__hint">%s</p>', esc_html__( 'This font has a single weight.', 'universal-custom-fonts' ) );
		}
		printf( '</div><div class="ucf-control"><div class="ucf-control__head"><label for="ucf-t-italic">%1$s</label><input type="checkbox" id="ucf-t-italic" class="ucf-switch" role="switch" data-ucf-t-italic%2$s></div>', esc_html__( 'Italic', 'universal-custom-fonts' ), $italic ? '' : ' disabled' );
		if ( ! $italic ) {
			printf( '<p class="ucf-control__hint">%s</p>', esc_html__( 'No italic files are included, so browsers would only slant the letters.', 'universal-custom-fonts' ) );
		}
		printf(
			'</div><div class="ucf-control"><div class="ucf-control__head"><label for="ucf-t-size">%1$s</label><span class="ucf-control__value" data-ucf-t-out="size">40px</span></div><input type="range" id="ucf-t-size" class="ucf-range" data-ucf-t-size min="12" max="120" step="1" value="40"></div><div class="ucf-control"><div class="ucf-control__head"><label for="ucf-t-lh">%2$s</label><span class="ucf-control__value" data-ucf-t-out="lh">1.50</span></div><input type="range" id="ucf-t-lh" class="ucf-range" data-ucf-t-lh min="1" max="2.4" step="0.05" value="1.5"></div></aside>',
			esc_html__( 'Size', 'universal-custom-fonts' ),
			esc_html__( 'Line height', 'universal-custom-fonts' )
		);
	}

	/**
	 * Toolbar, editable preview and the CSS line.
	 *
	 * @param array  $text    Texts for the script.
	 * @param string $stack   CSS family stack.
	 * @param string $dir     Direction.
	 * @param string $lang    Language.
	 * @param bool   $italic  Italic available.
	 * @param int    $initial Starting weight.
	 */
	private static function render_stage( array $text, string $stack, string $dir, string $lang, bool $italic, int $initial ): void {
		printf( '<section class="ucf-stage" aria-label="%1$s"><div class="ucf-stage__toolbar" role="toolbar" aria-label="%2$s"><div class="ucf-stage__group">', esc_attr__( 'Type tester', 'universal-custom-fonts' ), esc_attr__( 'Preview formatting', 'universal-custom-fonts' ) );
		self::tool_button( array( 'data-ucf-t-step' => '-2' ), 'minus', __( 'Smaller', 'universal-custom-fonts' ) );
		echo '<span class="ucf-stage__size" data-ucf-t-out="size">40px</span>';
		self::tool_button( array( 'data-ucf-t-step' => '2' ), 'plus', __( 'Larger', 'universal-custom-fonts' ) );
		echo '</div><div class="ucf-stage__group">';
		self::tool_button(
			array(
				'data-ucf-t-italic-btn' => true,
				'aria-pressed'          => 'false',
				'disabled'              => ! $italic,
			),
			'italic',
			__( 'Italic', 'universal-custom-fonts' )
		);
		echo '</div><div class="ucf-stage__group">';
		$aligns = array(
			'start'   => __( 'Align to start', 'universal-custom-fonts' ),
			'center'  => __( 'Center', 'universal-custom-fonts' ),
			'end'     => __( 'Align to end', 'universal-custom-fonts' ),
			'justify' => __( 'Justify', 'universal-custom-fonts' ),
		);
		foreach ( $aligns as $align => $label ) {
			self::tool_button(
				array(
					'data-ucf-t-align' => $align,
					'aria-pressed'     => 'start' === $align ? 'true' : 'false',
				),
				'align-' . $align,
				$label
			);
		}
		echo '</div><div class="ucf-stage__group">';
		self::tool_button(
			array(
				'data-ucf-t-dir' => true,
				'aria-pressed'   => 'rtl' === $dir ? 'true' : 'false',
			),
			'direction',
			__( 'Right to left', 'universal-custom-fonts' )
		);
		printf( '</div></div><div class="ucf-stage__canvas" data-ucf-t-canvas data-ucf-t-apply style="font-family:%1$s" dir="%2$s" lang="%3$s">', esc_attr( $stack ), esc_attr( $dir ), esc_attr( $lang ) );
		$blocks = array(
			'heading' => 'ucf-t-heading',
			'body'    => 'ucf-t-body',
			'small'   => 'ucf-t-small',
		);
		foreach ( $blocks as $key => $class ) {
			printf(
				'<div class="%1$s" contenteditable="true" spellcheck="false" role="textbox" aria-multiline="true" aria-label="%2$s" data-ucf-t-text="%3$s">%4$s</div>',
				esc_attr( $class ),
				esc_attr__( 'Editable preview text', 'universal-custom-fonts' ),
				esc_attr( $key ),
				esc_html( $text[ $key ] )
			);
		}
		printf(
			'</div><div class="ucf-stage__css"><span class="ucf-stage__css-label">CSS</span><code id="ucf-t-css" data-ucf-t-css dir="ltr">%1$s</code><button type="button" class="ucf-btn ucf-btn--ghost ucf-btn--sm" data-ucf-copy="#ucf-t-css" data-copied="%2$s"><span data-ucf-copy-label>%3$s</span></button></div></section>',
			esc_html( sprintf( 'font-family: %1$s; font-weight: %2$d; font-size: 40px; line-height: 1.5;', $stack, $initial ) ),
			esc_attr__( 'Copied', 'universal-custom-fonts' ),
			esc_html__( 'Copy', 'universal-custom-fonts' )
		);
	}

	/**
	 * Icon button with escaped attributes.
	 *
	 * @param array  $attrs Attributes (true = boolean attribute, false = omitted).
	 * @param string $icon  Icon.
	 * @param string $label Accessible label.
	 */
	private static function tool_button( array $attrs, string $icon, string $label ): void {
		echo '<button type="button" class="ucf-icon-btn"';
		foreach ( $attrs as $name => $value ) {
			if ( true === $value ) {
				printf( ' %s', esc_attr( $name ) );
			} elseif ( false !== $value ) {
				printf( ' %1$s="%2$s"', esc_attr( $name ), esc_attr( (string) $value ) );
			}
		}
		printf( ' title="%1$s"><span class="screen-reader-text">%1$s</span>', esc_attr( $label ) );
		Icons::render( $icon );
		echo '</button>';
	}

	/**
	 * Character set lines.
	 *
	 * @return array
	 */
	private static function glyph_lines(): array {
		return array(
			array( 'fa', 'ا ب پ ت ث ج چ ح خ د ذ ر ز ژ س ش ص ض ط ظ ع غ ف ق ک گ ل م ن و ه ی' ),
			array( 'fa', '۰ ۱ ۲ ۳ ۴ ۵ ۶ ۷ ۸ ۹ ، ؛ ؟ « » ٪' ),
			array( 'latin', 'A B C D E F G H I J K L M N O P Q R S T U V W X Y Z' ),
			array( 'latin', 'a b c d e f g h i j k l m n o p q r s t u v w x y z' ),
			array( 'latin', '0 1 2 3 4 5 6 7 8 9 & @ # $ % ( ) ! ? . , : ;' ),
		);
	}

	/**
	 * Details list.
	 *
	 * @param Font $font Font.
	 */
	private static function render_details( Font $font ): void {
		$styles = array();
		foreach ( $font->styles() as $style ) {
			$styles[] = 'italic' === $style ? __( 'Italic', 'universal-custom-fonts' ) : __( 'Normal', 'universal-custom-fonts' );
		}
		$rows = array(
			__( 'CSS family', 'universal-custom-fonts' ) => $font->css_stack(),
			__( 'Source', 'universal-custom-fonts' )     => FontList::source_label( $font ),
			__( 'Weights', 'universal-custom-fonts' )    => implode( ', ', $font->weights() ),
			__( 'Styles', 'universal-custom-fonts' )     => implode( ', ', $styles ),
			__( 'Text while loading', 'universal-custom-fonts' ) => 'font-display: ' . $font->display(),
			__( 'Files', 'universal-custom-fonts' )      => self::files_summary( $font ),
		);
		if ( $font->preset_data() ) {
			$rows[ __( 'Scripts', 'universal-custom-fonts' ) ] = implode( ', ', $font->subsets() );
		}
		printf( '<section class="ucf-card"><h2 class="ucf-card__title">%s</h2><dl class="ucf-dl">', esc_html__( 'Details', 'universal-custom-fonts' ) );
		foreach ( $rows as $label => $value ) {
			printf( '<dt>%1$s</dt><dd>%2$s</dd>', esc_html( $label ), esc_html( $value ) );
		}
		echo '</dl></section>';
	}

	/**
	 * Files summary.
	 *
	 * @param Font $font Font.
	 * @return string
	 */
	private static function files_summary( Font $font ): string {
		if ( $font->is_upload() ) {
			$formats = array();
			foreach ( $font->files() as $by_style ) {
				foreach ( (array) $by_style as $by_format ) {
					foreach ( array_keys( (array) $by_format ) as $format ) {
						$formats[ $format ] = ( $formats[ $format ] ?? 0 ) + 1;
					}
				}
			}
			$parts = array();
			foreach ( $formats as $format => $count ) {
				$parts[] = strtoupper( (string) $format ) . ' × ' . $count;
			}
			return implode( ', ', $parts );
		}
		if ( $font->is_self_hosted() ) {
			$local = (array) $font->get( 'local' );
			/* translators: 1: number of files, 2: date. */
			return sprintf( __( '%1$d WOFF2 files, downloaded %2$s', 'universal-custom-fonts' ), count( (array) ( $local['faces'] ?? array() ) ), wp_date( (string) get_option( 'date_format' ), (int) ( $local['downloaded'] ?? 0 ) ) );
		}
		return __( 'Served by the CDN', 'universal-custom-fonts' );
	}

	/**
	 * Where the font appears.
	 *
	 * @param Font $font Font.
	 */
	private static function render_usage( Font $font ): void {
		printf( '<section class="ucf-card"><h2 class="ucf-card__title">%s</h2><ul class="ucf-usage">', esc_html__( 'Where it appears', 'universal-custom-fonts' ) );
		foreach ( FontList::labels() as $key => $label ) {
			$adapter  = IntegrationManager::for_key( $key );
			$detected = $adapter && $adapter->is_available();
			$on       = $detected && $font->is_enabled() && ! empty( $font->integrations()[ $key ] );
			if ( ! $detected ) {
				$state = __( 'Not on this site', 'universal-custom-fonts' );
			} elseif ( $on ) {
				$state = __( 'In the font menus', 'universal-custom-fonts' );
			} else {
				$state = __( 'Turned off', 'universal-custom-fonts' );
			}
			printf( '<li class="%s">', esc_attr( $on ? 'is-on' : 'is-off' ) );
			Icons::render( $on ? 'check' : 'minus' );
			printf( '<span>%1$s</span><span class="ucf-usage__state">%2$s</span></li>', esc_html( $label ), esc_html( $state ) );
		}
		echo '</ul>';
		foreach ( IntegrationManager::available() as $adapter ) {
			$note = $adapter->native_conflict( $font );
			if ( '' !== $note ) {
				printf( '<p class="ucf-card__note">%s</p>', esc_html( $note ) );
			}
		}
		echo '<div class="ucf-card__foot">';
		AdminPage::button_link(
			__( 'Change where it appears', 'universal-custom-fonts' ),
			AdminPage::url(
				array(
					'tab'  => 'edit',
					'font' => $font->id(),
				)
			) . '#ucf-usage',
			'',
			'ghost'
		);
		echo '</div></section>';
	}
}
