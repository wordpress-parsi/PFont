<?php
/**
 * Font page: live type tester, sizes, characters, details and where the font appears.
 *
 * @package PFont
 */

namespace PFont\Admin;

use PFont\Core\Font;
use PFont\Core\FontRegistry;
use PFont\Integrations\IntegrationManager;

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
				'heading' => __( 'تمام افراد بشر آزاد به دنیا می‌آیند', 'pfont' ),
				'body'    => __( 'تمام افراد بشر آزاد به دنیا می‌آیند و از لحاظ حیثیت و حقوق با هم برابرند. همه دارای عقل و وجدان هستند و باید نسبت به یکدیگر با روح برادری رفتار کنند.', 'pfont' ),
				'small'   => __( 'اعداد: ۰۱۲۳۴۵۶۷۸۹ | قیمت: ۲٬۴۵۰٬۰۰۰ تومان | تخفیف ۳۰٪', 'pfont' ),
				'line'    => __( 'زیبایی در سادگی است', 'pfont' ),
			),
			'latin' => array(
				'heading' => __( 'Sphinx of black quartz, judge my vow', 'pfont' ),
				'body'    => __( 'All human beings are born free and equal in dignity and rights. They are endowed with reason and conscience and should act towards one another in a spirit of brotherhood.', 'pfont' ),
				'small'   => __( 'Numbers: 0123456789 | Price: $1,249.00 | Save 30 percent', 'pfont' ),
				'line'    => __( 'Beauty lives in simplicity', 'pfont' ),
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
			100 => __( 'Thin', 'pfont' ),
			200 => __( 'Extra Light', 'pfont' ),
			300 => __( 'Light', 'pfont' ),
			400 => __( 'Regular', 'pfont' ),
			500 => __( 'Medium', 'pfont' ),
			600 => __( 'Semibold', 'pfont' ),
			700 => __( 'Bold', 'pfont' ),
			800 => __( 'Extra Bold', 'pfont' ),
			900 => __( 'Black', 'pfont' ),
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
		return $font->is_cdn() && is_array( $preset['range'] ?? null ) && count( $font->weights() ) > 1;
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
			AdminPage::page_header( __( 'Font not found', 'pfont' ), __( 'It may have been deleted.', 'pfont' ), array(), AdminPage::url(), __( 'Library', 'pfont' ) );
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
		printf( '<div data-pfont-tester data-family="%1$s" data-samples="%2$s" data-weight="%3$d">', esc_attr( $stack ), esc_attr( (string) wp_json_encode( $samples ) ), (int) $initial );
		printf(
			'<div class="pfont-preview-bar"><label for="pfont-t-script">%1$s</label><select id="pfont-t-script" class="pfont-select pfont-select--pill" data-pfont-t-script><option value="fa"%2$s>%3$s</option><option value="latin"%4$s>%5$s</option></select></div>',
			esc_html__( 'Preview text', 'pfont' ),
			selected( $script, 'fa', false ),
			esc_html__( 'Persian', 'pfont' ),
			selected( $script, 'latin', false ),
			esc_html__( 'Latin', 'pfont' )
		);
		echo '<div class="pfont-tester">';
		self::render_panel( $stops, self::is_variable( $font ), $initial, $italic );
		self::render_stage( $samples[ $script ], $stack, $dir, $lang, $italic, $initial );
		echo '</div><div class="pfont-font-sections">';

		printf( '<section class="pfont-card"><h2 class="pfont-card__title">%1$s</h2><div class="pfont-waterfall" data-pfont-t-apply style="font-family:%2$s">', esc_html__( 'Sizes', 'pfont' ), esc_attr( $stack ) );
		foreach ( array( 14, 18, 24, 32, 48, 64 ) as $size ) {
			printf(
				'<div class="pfont-waterfall__row"><span class="pfont-waterfall__size">%1$d</span><span class="pfont-waterfall__text" style="font-size:%1$dpx" data-pfont-t-text="line" dir="%2$s" lang="%3$s">%4$s</span></div>',
				(int) $size,
				esc_attr( $dir ),
				esc_attr( $lang ),
				esc_html( $samples[ $script ]['line'] )
			);
		}
		printf( '</div></section><section class="pfont-card"><h2 class="pfont-card__title">%1$s</h2><div class="pfont-glyphs" data-pfont-t-apply style="font-family:%2$s">', esc_html__( 'Characters', 'pfont' ), esc_attr( $stack ) );
		foreach ( self::glyph_lines() as $line ) {
			printf( '<p dir="%1$s" lang="%2$s">%3$s</p>', esc_attr( 'fa' === $line[0] ? 'rtl' : 'ltr' ), esc_attr( 'fa' === $line[0] ? 'fa' : 'en' ), esc_html( $line[1] ) );
		}
		echo '</div></section></div></div><div class="pfont-detail-grid pfont-mt">';
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
		printf( '<header class="pfont-page-head"><div class="pfont-page-head__main"><a class="pfont-back" href="%s">', esc_url( AdminPage::url() ) );
		Icons::render( 'arrow-left' );
		printf(
			'<span>%1$s</span></a><h1 class="pfont-page-head__title">%2$s</h1><div class="pfont-font-head__meta"><code class="pfont-code">%3$s</code><span>%4$s</span><span class="pfont-font-row__sep" aria-hidden="true">|</span><span>%5$s</span><span class="%6$s">%7$s</span></div></div><div class="pfont-page-head__actions">',
			esc_html__( 'Library', 'pfont' ),
			esc_html( $font->name() ),
			esc_html( $font->css_stack() ),
			esc_html( FontList::weight_label( $font ) ),
			esc_html( FontList::source_label( $font ) ),
			esc_attr( $font->is_enabled() ? 'pfont-badge pfont-badge--active' : 'pfont-badge' ),
			esc_html( $font->is_enabled() ? __( 'Enabled', 'pfont' ) : __( 'Disabled', 'pfont' ) )
		);
		AdminPage::button_link(
			__( 'Edit settings', 'pfont' ),
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
		printf( '<aside class="pfont-tester__panel" aria-label="%1$s"><div class="pfont-panel-head"><span>%2$s</span><button type="button" class="pfont-icon-btn" data-pfont-t-reset title="%3$s"><span class="screen-reader-text">%3$s</span>', esc_attr__( 'Style controls', 'pfont' ), esc_html__( 'Styles', 'pfont' ), esc_attr__( 'Reset styles', 'pfont' ) );
		Icons::render( 'reset' );
		printf( '</button></div><div class="pfont-control"><div class="pfont-control__head"><span><label for="pfont-t-weight">%1$s</label> <span class="pfont-control__value" data-pfont-t-out="weight">%2$d</span></span><select class="pfont-select pfont-select--mini" data-pfont-t-weight-name aria-label="%3$s">', esc_html__( 'Weight', 'pfont' ), (int) $initial, esc_attr__( 'Named weight', 'pfont' ) );
		foreach ( $stops as $weight ) {
			if ( isset( $names[ $weight ] ) ) {
				printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $weight, selected( $initial, $weight, false ), esc_html( $names[ $weight ] ) );
			}
		}
		printf(
			'</select></div><input type="range" id="pfont-t-weight" class="pfont-range" data-pfont-t-weight data-stops="%1$s" data-variable="%2$d" min="%3$d" max="%4$d" step="1" value="%5$d"%6$s>',
			esc_attr( implode( ',', $stops ) ),
			$variable ? 1 : 0,
			$variable ? (int) min( $stops ) : 0,
			$variable ? (int) max( $stops ) : count( $stops ) - 1,
			$variable ? (int) $initial : (int) array_search( $initial, $stops, true ),
			count( $stops ) > 1 ? '' : ' disabled'
		);
		if ( count( $stops ) < 2 ) {
			printf( '<p class="pfont-control__hint">%s</p>', esc_html__( 'This font has a single weight.', 'pfont' ) );
		}
		printf( '</div><div class="pfont-control"><div class="pfont-control__head"><label for="pfont-t-italic">%1$s</label><input type="checkbox" id="pfont-t-italic" class="pfont-switch" role="switch" data-pfont-t-italic%2$s></div>', esc_html__( 'Italic', 'pfont' ), $italic ? '' : ' disabled' );
		if ( ! $italic ) {
			printf( '<p class="pfont-control__hint">%s</p>', esc_html__( 'No italic files are included, so browsers would only slant the letters.', 'pfont' ) );
		}
		printf(
			'</div><div class="pfont-control"><div class="pfont-control__head"><label for="pfont-t-size">%1$s</label><span class="pfont-control__value" data-pfont-t-out="size">40px</span></div><input type="range" id="pfont-t-size" class="pfont-range" data-pfont-t-size min="12" max="120" step="1" value="40"></div><div class="pfont-control"><div class="pfont-control__head"><label for="pfont-t-lh">%2$s</label><span class="pfont-control__value" data-pfont-t-out="lh">1.50</span></div><input type="range" id="pfont-t-lh" class="pfont-range" data-pfont-t-lh min="1" max="2.4" step="0.05" value="1.5"></div></aside>',
			esc_html__( 'Size', 'pfont' ),
			esc_html__( 'Line height', 'pfont' )
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
		printf( '<section class="pfont-stage" aria-label="%1$s"><div class="pfont-stage__toolbar" role="toolbar" aria-label="%2$s"><div class="pfont-stage__group">', esc_attr__( 'Type tester', 'pfont' ), esc_attr__( 'Preview formatting', 'pfont' ) );
		self::tool_button( array( 'data-pfont-t-step' => '-2' ), 'minus', __( 'Smaller', 'pfont' ) );
		echo '<span class="pfont-stage__size" data-pfont-t-out="size">40px</span>';
		self::tool_button( array( 'data-pfont-t-step' => '2' ), 'plus', __( 'Larger', 'pfont' ) );
		echo '</div><div class="pfont-stage__group">';
		self::tool_button(
			array(
				'data-pfont-t-italic-btn' => true,
				'aria-pressed'            => 'false',
				'disabled'                => ! $italic,
			),
			'italic',
			__( 'Italic', 'pfont' )
		);
		echo '</div><div class="pfont-stage__group">';
		$aligns = array(
			'start'   => __( 'Align to start', 'pfont' ),
			'center'  => __( 'Center', 'pfont' ),
			'end'     => __( 'Align to end', 'pfont' ),
			'justify' => __( 'Justify', 'pfont' ),
		);
		foreach ( $aligns as $align => $label ) {
			self::tool_button(
				array(
					'data-pfont-t-align' => $align,
					'aria-pressed'       => 'start' === $align ? 'true' : 'false',
				),
				'align-' . $align,
				$label
			);
		}
		echo '</div><div class="pfont-stage__group">';
		self::tool_button(
			array(
				'data-pfont-t-dir' => true,
				'aria-pressed'     => 'rtl' === $dir ? 'true' : 'false',
			),
			'direction',
			__( 'Right to left', 'pfont' )
		);
		printf( '</div></div><div class="pfont-stage__canvas" data-pfont-t-canvas data-pfont-t-apply style="font-family:%1$s" dir="%2$s" lang="%3$s">', esc_attr( $stack ), esc_attr( $dir ), esc_attr( $lang ) );
		$blocks = array(
			'heading' => 'pfont-t-heading',
			'body'    => 'pfont-t-body',
			'small'   => 'pfont-t-small',
		);
		foreach ( $blocks as $key => $class ) {
			printf(
				'<div class="%1$s" contenteditable="true" spellcheck="false" role="textbox" aria-multiline="true" aria-label="%2$s" data-pfont-t-text="%3$s">%4$s</div>',
				esc_attr( $class ),
				esc_attr__( 'Editable preview text', 'pfont' ),
				esc_attr( $key ),
				esc_html( $text[ $key ] )
			);
		}
		printf(
			'</div><div class="pfont-stage__css"><span class="pfont-stage__css-label">CSS</span><code id="pfont-t-css" data-pfont-t-css dir="ltr">%1$s</code><button type="button" class="pfont-btn pfont-btn--ghost pfont-btn--sm" data-pfont-copy="#pfont-t-css" data-copied="%2$s"><span data-pfont-copy-label>%3$s</span></button></div></section>',
			esc_html( sprintf( 'font-family: %1$s; font-weight: %2$d; font-size: 40px; line-height: 1.5;', $stack, $initial ) ),
			esc_attr__( 'Copied', 'pfont' ),
			esc_html__( 'Copy', 'pfont' )
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
		echo '<button type="button" class="pfont-icon-btn"';
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
			$styles[] = 'italic' === $style ? __( 'Italic', 'pfont' ) : __( 'Normal', 'pfont' );
		}
		$rows = array(
			__( 'CSS family', 'pfont' )         => $font->css_stack(),
			__( 'Source', 'pfont' )             => FontList::source_label( $font ),
			__( 'Weights', 'pfont' )            => implode( ', ', $font->weights() ),
			__( 'Styles', 'pfont' )             => implode( ', ', $styles ),
			__( 'Text while loading', 'pfont' ) => 'font-display: ' . $font->display(),
			__( 'Files', 'pfont' )              => self::files_summary( $font ),
		);
		if ( $font->preset_data() ) {
			$rows[ __( 'Scripts', 'pfont' ) ] = implode( ', ', $font->subsets() );
		}
		printf( '<section class="pfont-card"><h2 class="pfont-card__title">%s</h2><dl class="pfont-dl">', esc_html__( 'Details', 'pfont' ) );
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
			return sprintf( __( '%1$d WOFF2 files, downloaded %2$s', 'pfont' ), count( (array) ( $local['faces'] ?? array() ) ), wp_date( (string) get_option( 'date_format' ), (int) ( $local['downloaded'] ?? 0 ) ) );
		}
		return __( 'Served by the CDN', 'pfont' );
	}

	/**
	 * Where the font appears.
	 *
	 * @param Font $font Font.
	 */
	private static function render_usage( Font $font ): void {
		printf( '<section class="pfont-card"><h2 class="pfont-card__title">%s</h2><ul class="pfont-usage">', esc_html__( 'Where it appears', 'pfont' ) );
		foreach ( FontList::labels() as $key => $label ) {
			$adapter  = IntegrationManager::for_key( $key );
			$detected = $adapter && $adapter->is_available();
			$on       = $detected && $font->is_enabled() && ! empty( $font->integrations()[ $key ] );
			if ( ! $detected ) {
				$state = __( 'Not on this site', 'pfont' );
			} elseif ( $on ) {
				$state = __( 'In the font menus', 'pfont' );
			} else {
				$state = __( 'Turned off', 'pfont' );
			}
			printf( '<li class="%s">', esc_attr( $on ? 'is-on' : 'is-off' ) );
			Icons::render( $on ? 'check' : 'minus' );
			printf( '<span>%1$s</span><span class="pfont-usage__state">%2$s</span></li>', esc_html( $label ), esc_html( $state ) );
		}
		echo '</ul>';
		foreach ( IntegrationManager::available() as $adapter ) {
			$note = $adapter->native_conflict( $font );
			if ( '' !== $note ) {
				printf( '<p class="pfont-card__note">%s</p>', esc_html( $note ) );
			}
		}
		echo '<div class="pfont-card__foot">';
		AdminPage::button_link(
			__( 'Change where it appears', 'pfont' ),
			AdminPage::url(
				array(
					'tab'  => 'edit',
					'font' => $font->id(),
				)
			) . '#pfont-usage',
			'',
			'ghost'
		);
		echo '</div></section>';
	}
}
