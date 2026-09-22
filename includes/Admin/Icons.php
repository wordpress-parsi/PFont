<?php
/**
 * Original line icons (24×24, stroke uses currentColor).
 *
 * @package PFont
 */

namespace PFont\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Icon set for the admin screens.
 */
final class Icons {

	private const PATHS = array(
		'fonts'         => '<path d="M3 19 8.5 5h1L15 19M5.2 14h7.6"/><path d="M20.5 12.5V19M20.5 15.8c0-1.9-1.2-3.3-2.9-3.3s-2.9 1.4-2.9 3.3 1.2 3.2 2.9 3.2 2.9-1.3 2.9-3.2z"/>',
		'plus'          => '<path d="M12 5v14M5 12h14"/>',
		'minus'         => '<path d="M6 12h12"/>',
		'plug'          => '<path d="M9 3v4M15 3v4M7 7h10v4a5 5 0 0 1-10 0V7zM12 16v5"/>',
		'sliders'       => '<path d="M4 7h9M17 7h3M4 12h3M11 12h9M4 17h11M19 17h1"/><circle cx="15" cy="7" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="17" r="2"/>',
		'terminal'      => '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M7.5 9.5 10 12l-2.5 2.5M12.5 15h4"/>',
		'help'          => '<path d="M5 4.5A1.5 1.5 0 0 1 6.5 3H19v16H6.5A1.5 1.5 0 0 0 5 20.5v-16z"/><path d="M5 20.5A1.5 1.5 0 0 0 6.5 22H19M9 7h6"/>',
		'upload'        => '<path d="M12 15V4M7.5 8.5 12 4l4.5 4.5M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/>',
		'cloud'         => '<path d="M7 18.5h10.5a4 4 0 0 0 .6-7.96A6 6 0 0 0 6.4 9.2 4.7 4.7 0 0 0 7 18.5z"/>',
		'server'        => '<rect x="4" y="4" width="16" height="7" rx="2"/><rect x="4" y="13" width="16" height="7" rx="2"/><path d="M8 7.5h.01M8 16.5h.01"/>',
		'shield'        => '<path d="M12 3 19 6v5.5c0 4.4-2.9 7.7-7 9.5-4.1-1.8-7-5.1-7-9.5V6l7-3z"/><path d="m9 12 2 2 4-4"/>',
		'zap'           => '<path d="M13 3 5 13.5h6l-1 7.5 8-10.5h-6l1-7.5z"/>',
		'editor'        => '<rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="M7 9h10M7 12.5h10M7 16h6"/>',
		'blocks'        => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
		'layout'        => '<rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="M3 9h18M9.5 9v11"/>',
		'window'        => '<rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="M3 8.5h18M6.5 6.3h.01M9 6.3h.01M7 13h6M7 16h10"/>',
		'trash'         => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
		'edit'          => '<path d="M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4"/>',
		'arrow-left'    => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
		'search'        => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.4-4.4"/>',
		'reset'         => '<path d="M4 12a8 8 0 1 0 2.4-5.7M4 4v4.5h4.5"/>',
		'info'          => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.8h.01"/>',
		'check'         => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		'alert'         => '<path d="M12 4 21 20H3L12 4z"/><path d="M12 10v4M12 17h.01"/>',
		'italic'        => '<path d="M10 4.5h8M6 19.5h8M14.5 4.5l-5 15"/>',
		'align-start'   => '<path d="M4 6h16M4 10h10M4 14h16M4 18h10"/>',
		'align-center'  => '<path d="M4 6h16M7 10h10M4 14h16M7 18h10"/>',
		'align-end'     => '<path d="M4 6h16M10 10h10M4 14h16M10 18h10"/>',
		'align-justify' => '<path d="M4 6h16M4 10h16M4 14h16M4 18h16"/>',
		'direction'     => '<path d="M5 8h14M15 4l4 4-4 4M19 16H5M9 12l-4 4 4 4"/>',
	);

	/**
	 * Markup of one icon.
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	public static function get( string $name ): string {
		return '<svg class="ucf-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( self::PATHS[ $name ] ?? '' ) . '</svg>';
	}

	/**
	 * Print one icon.
	 *
	 * @param string $name Icon name.
	 */
	public static function render( string $name ): void {
		echo wp_kses( self::get( $name ), self::allowed() );
	}

	/**
	 * Allowed SVG markup for wp_kses().
	 *
	 * @return array
	 */
	public static function allowed(): array {
		$shape = array_fill_keys( array( 'd', 'cx', 'cy', 'r', 'x', 'y', 'width', 'height', 'rx' ), true );
		return array(
			'svg'    => array_fill_keys( array( 'class', 'viewbox', 'width', 'height', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'aria-hidden', 'focusable' ), true ),
			'path'   => $shape,
			'circle' => $shape,
			'rect'   => $shape,
		);
	}
}
