<?php
/**
 * Classic Editor / TinyMCE (including the Classic block).
 *
 * @package PFont
 */

namespace PFont\Integrations;

use PFont\Core\Font;
use PFont\Core\FontLoader;
use PFont\Core\Settings;
use PFont\Helpers\FontHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Uses core hooks: mce_buttons_2, tiny_mce_before_init (font_formats, content_style) and mce_css.
 */
final class TinyMceAdapter extends AbstractAdapter {

	/**
	 * TinyMCE 4.9 default font list, used when nothing else set font_formats.
	 */
	private const DEFAULT_FORMATS = 'Andale Mono=andale mono,monospace;Arial=arial,helvetica,sans-serif;Arial Black=arial black,sans-serif;Book Antiqua=book antiqua,palatino,serif;Comic Sans MS=comic sans ms,sans-serif;Courier New=courier new,courier,monospace;Georgia=georgia,palatino,serif;Helvetica=helvetica,arial,sans-serif;Impact=impact,sans-serif;Symbol=symbol;Tahoma=tahoma,arial,helvetica,sans-serif;Terminal=terminal,monaco,monospace;Times New Roman=times new roman,times,serif;Trebuchet MS=trebuchet ms,geneva,sans-serif;Verdana=verdana,geneva,sans-serif;Webdings=webdings;Wingdings=wingdings,zapf dingbats';

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'tinymce';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Classic Editor (TinyMCE)', 'pfont' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		global $tinymce_version;
		return is_string( $tinymce_version ) ? $tinymce_version : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		if ( Settings::get( 'tinymce_toolbar' ) ) {
			$this->hook( 'filter', 'mce_buttons_2', array( $this, 'add_font_select' ), 20, 2 );
		}
		$this->hook( 'filter', 'tiny_mce_before_init', array( $this, 'before_init' ), 20, 2 );
		$this->hook( 'filter', 'mce_css', array( $this, 'mce_css' ), 20 );
		$this->hook( 'action', 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		$this->hook( 'action', 'enqueue_block_assets', array( $this, 'block_assets' ) );
		if ( ! is_admin() ) {
			$this->hook( 'filter', 'the_content', array( $this, 'scan_content' ), 999 );
		}
	}

	/**
	 * Add the font dropdown to the second toolbar row.
	 *
	 * @param mixed  $buttons   Buttons.
	 * @param string $editor_id Editor ID.
	 * @return mixed
	 */
	public function add_font_select( mixed $buttons, string $editor_id = '' ): mixed {
		unset( $editor_id );
		if ( is_array( $buttons ) && $this->fonts() && ! in_array( 'fontselect', $buttons, true ) ) {
			array_unshift( $buttons, 'fontselect' );
		}
		return $buttons;
	}

	/**
	 * Add fonts to font_formats and their @font-face to the editor iframe.
	 *
	 * @param mixed  $init      TinyMCE settings.
	 * @param string $editor_id Editor ID.
	 * @return mixed
	 */
	public function before_init( mixed $init, string $editor_id = '' ): mixed {
		unset( $editor_id );
		$fonts = $this->fonts();
		if ( ! is_array( $init ) || ! $fonts ) {
			return $init;
		}

		$formats  = ( isset( $init['font_formats'] ) && is_string( $init['font_formats'] ) && '' !== trim( $init['font_formats'] ) ) ? $init['font_formats'] : self::DEFAULT_FORMATS;
		$existing = array();
		foreach ( explode( ';', $formats ) as $entry ) {
			$pair = explode( '=', $entry, 2 );
			if ( 2 === count( $pair ) ) {
				$existing[ FontHelper::normalize_family_name( $pair[1] ) ] = true;
			}
		}

		$ours = array();
		foreach ( $fonts as $font ) {
			$key = FontHelper::normalize_family_name( $font->family() );
			if ( isset( $existing[ $key ] ) ) {
				continue;
			}
			$existing[ $key ] = true;
			// WordPress prints these settings into JS without escaping, so strip every delimiter.
			$label  = str_replace( array( '=', ';', '"', '\\', "\n", "\r" ), '', $font->name() );
			$stack  = str_replace( array( ';', '"', '\\', "\n", "\r" ), '', $font->family() . ',' . $font->fallback() );
			$ours[] = $label . '=' . $stack;
		}
		if ( $ours ) {
			$init['font_formats'] = implode( ';', $ours ) . ';' . trim( $formats, ';' );
		}

		$css = FontLoader::get_inline_css( $fonts );
		if ( '' !== $css ) {
			$css                   = str_replace( array( "\r", "\n", '"', '\\' ), array( '', '', "'", '' ), $css );
			$previous              = ( isset( $init['content_style'] ) && is_string( $init['content_style'] ) ) ? $init['content_style'] . ' ' : '';
			$init['content_style'] = trim( $previous . $css );
		}

		return self::dedupe_fontselect( $init );
	}

	/**
	 * Add CDN stylesheets to the editor iframe.
	 *
	 * @param mixed $mce_css Comma-separated URLs.
	 * @return mixed
	 */
	public function mce_css( mixed $mce_css ): mixed {
		$urls = FontLoader::get_remote_urls( $this->fonts() );
		if ( ! $urls ) {
			return $mce_css;
		}
		$list = is_string( $mce_css ) && '' !== trim( $mce_css ) ? array( trim( $mce_css, ' ,' ) ) : array();
		foreach ( $urls as $url ) {
			$list[] = str_replace( ',', '%2C', esc_url_raw( $url ) );
		}
		return implode( ',', $list );
	}

	/**
	 * Load fonts on classic edit screens so the dropdown can preview them.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public function admin_assets( string $hook_suffix ): void {
		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) && $this->fonts() ) {
			FontLoader::enqueue_for_editor( array_keys( $this->fonts() ) );
		}
	}

	/**
	 * Load fonts inside the block editor canvas for the Classic block.
	 */
	public function block_assets(): void {
		if ( is_admin() && $this->fonts() ) {
			FontLoader::enqueue_for_editor( array_keys( $this->fonts() ) );
		}
	}

	/**
	 * Front end: fonts used in the main post (printed in the head).
	 */
	public function enqueue_frontend(): void {
		$this->enqueue_used_in( self::queried_content() );
	}

	/**
	 * Front end: fonts used in any rendered content (late fonts go to the footer).
	 *
	 * @param mixed $content Content.
	 * @return mixed
	 */
	public function scan_content( mixed $content ): mixed {
		if ( is_string( $content ) ) {
			$this->enqueue_used_in( $content );
		}
		return $content;
	}

	/**
	 * Queue fonts used in font-family declarations of some HTML.
	 *
	 * @param string $html HTML.
	 */
	private function enqueue_used_in( string $html ): void {
		if ( '' === $html || false === stripos( $html, 'font-family' ) ) {
			return;
		}
		foreach ( $this->fonts() as $font ) {
			if ( FontHelper::uses_in_css( $html, $font->family() ) ) {
				FontLoader::enqueue( $font->id() );
			}
		}
	}

	/**
	 * Keep a single fontselect across all toolbar rows (other plugins may add one too).
	 *
	 * @param array $init Settings.
	 * @return array
	 */
	private static function dedupe_fontselect( array $init ): array {
		$seen = false;
		foreach ( array( 'toolbar1', 'toolbar2', 'toolbar3', 'toolbar4' ) as $row ) {
			if ( empty( $init[ $row ] ) || ! is_string( $init[ $row ] ) ) {
				continue;
			}
			$buttons = explode( ',', $init[ $row ] );
			foreach ( $buttons as $i => $button ) {
				if ( 'fontselect' === trim( $button ) ) {
					if ( $seen ) {
						unset( $buttons[ $i ] );
					}
					$seen = true;
				}
			}
			$init[ $row ] = implode( ',', $buttons );
		}
		return $init;
	}
}
