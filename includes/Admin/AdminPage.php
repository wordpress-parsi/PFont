<?php
/**
 * Admin screen shell: menu, sidebar, notices, shared components and the Settings view.
 *
 * @package PFont
 */

namespace PFont\Admin;

use PFont\Core\FontRegistry;
use PFont\Core\Settings;
use PFont\Fonts\CdnFonts;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → PFont.
 */
final class AdminPage {

	public const SLUG = 'pfont';

	/**
	 * Screen hook suffix.
	 *
	 * @var string
	 */
	private static string $hook_suffix = '';

	/**
	 * Register admin hooks.
	 */
	public static function register_hooks(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( Assets::class, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PFONT_FILE ), array( self::class, 'action_links' ) );
		Actions::register_hooks();
		AdminFont::register_hooks();
	}

	/**
	 * Add the menu item.
	 */
	public static function menu(): void {
		self::$hook_suffix = (string) add_options_page(
			'PFont',
			'PFont',
			'manage_options',
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Screen hook suffix.
	 *
	 * @return string
	 */
	public static function hook_suffix(): string {
		return self::$hook_suffix;
	}

	/**
	 * URL of a view.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'options-general.php' ) );
	}

	/**
	 * Settings API registration.
	 */
	public static function register_settings(): void {
		register_setting(
			'pfont_settings',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * Plugins screen link.
	 *
	 * @param mixed $links Links.
	 * @return array
	 */
	public static function action_links( mixed $links ): array {
		$links = is_array( $links ) ? $links : array();
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Fonts', 'pfont' ) ) );
		return $links;
	}

	/**
	 * Queue a message for the next screen load.
	 *
	 * @param string $type    success|error|warning|info.
	 * @param string $message Message.
	 */
	public static function notice( string $type, string $message ): void {
		$key       = 'pfont_notices_' . get_current_user_id();
		$notices   = get_transient( $key );
		$notices   = is_array( $notices ) ? $notices : array();
		$notices[] = array( in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info', $message );
		set_transient( $key, $notices, 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * Print and clear queued messages.
	 */
	private static function render_notices(): void {
		$key     = 'pfont_notices_' . get_current_user_id();
		$notices = get_transient( $key );
		if ( ! is_array( $notices ) ) {
			return;
		}
		delete_transient( $key );
		$icons = array(
			'success' => 'check',
			'error'   => 'alert',
			'warning' => 'alert',
			'info'    => 'info',
		);
		foreach ( $notices as $notice ) {
			$type = (string) $notice[0];
			printf( '<div class="pfont-alert pfont-alert--%1$s" role="%2$s">', esc_attr( $type ), esc_attr( 'error' === $type ? 'alert' : 'status' ) );
			Icons::render( $icons[ $type ] ?? 'info' );
			printf( '<p>%s</p></div>', esc_html( (string) $notice[1] ) );
		}
	}

	/**
	 * Render the screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'fonts';
		$font = isset( $_GET['font'] ) ? sanitize_key( wp_unslash( $_GET['font'] ) ) : '';
		// phpcs:enable
		$views = array( 'fonts', 'font', 'edit', 'integrations', 'settings', 'help' );
		if ( Settings::get( 'debug' ) ) {
			$views[] = 'debug';
		}
		$tab     = in_array( $tab, $views, true ) ? $tab : 'fonts';
		$current = ( 'font' === $tab || ( 'edit' === $tab && '' !== $font ) ) ? 'fonts' : $tab;

		echo '<div class="wrap pfont-app"><div class="pfont-shell">';
		self::render_sidebar( $current );
		echo '<main class="pfont-main"><hr class="wp-header-end">';
		self::render_notices();
		switch ( $tab ) {
			case 'font':
				FontView::render();
				break;
			case 'edit':
				FontForm::render();
				break;
			case 'integrations':
				IntegrationsView::render();
				break;
			case 'settings':
				self::render_settings();
				break;
			case 'help':
				HelpView::render();
				break;
			case 'debug':
				DebugPanel::render();
				break;
			default:
				FontList::render();
		}
		echo '</main></div></div>';
	}

	/**
	 * Sidebar navigation.
	 *
	 * @param string $current Active view.
	 */
	private static function render_sidebar( string $current ): void {
		$items = array(
			'fonts'        => array( __( 'Library', 'pfont' ), 'fonts', count( FontRegistry::all() ) ),
			'edit'         => array( __( 'Add new font', 'pfont' ), 'plus', null ),
			'integrations' => array( __( 'Integrations', 'pfont' ), 'plug', null ),
			'settings'     => array( __( 'Settings', 'pfont' ), 'sliders', null ),
		);
		if ( Settings::get( 'debug' ) ) {
			$items['debug'] = array( __( 'Debug', 'pfont' ), 'terminal', null );
		}
		echo '<aside class="pfont-sidebar">';
		printf( '<p class="pfont-sidebar__title">%s</p>', esc_html( 'PFont' ) );
		printf( '<nav class="pfont-nav" aria-label="%s">', esc_attr__( 'PFont sections', 'pfont' ) );
		foreach ( $items as $key => $item ) {
			self::nav_link( $key, $item[0], $item[1], $key === $current, $item[2] );
		}
		echo '<hr class="pfont-nav__sep">';
		self::nav_link( 'help', __( 'Help', 'pfont' ), 'help', 'help' === $current, null );
		echo '</nav>';
		/* translators: %s: plugin version. */
		printf( '<p class="pfont-sidebar__foot">%s</p>', esc_html( sprintf( __( 'Version %s', 'pfont' ), PFONT_VERSION ) ) );
		echo '</aside>';
	}

	/**
	 * One sidebar link.
	 *
	 * @param string   $tab    View.
	 * @param string   $label  Label.
	 * @param string   $icon   Icon.
	 * @param bool     $active Whether active.
	 * @param int|null $count  Optional count.
	 */
	private static function nav_link( string $tab, string $label, string $icon, bool $active, ?int $count ): void {
		printf( '<a class="pfont-nav__link" href="%1$s"%2$s>', esc_url( self::url( array( 'tab' => $tab ) ) ), $active ? ' aria-current="page"' : '' );
		Icons::render( $icon );
		printf( '<span>%s</span>', esc_html( $label ) );
		if ( null !== $count ) {
			printf( '<span class="pfont-nav__count">%d</span>', (int) $count );
		}
		echo '</a>';
	}

	/**
	 * Page header with optional back link and actions.
	 *
	 * @param string $title       Title.
	 * @param string $description Description.
	 * @param array  $actions     Items with label, url, icon, style.
	 * @param string $back_url    Back link URL.
	 * @param string $back_label  Back link label.
	 */
	public static function page_header( string $title, string $description = '', array $actions = array(), string $back_url = '', string $back_label = '' ): void {
		echo '<header class="pfont-page-head"><div class="pfont-page-head__main">';
		if ( '' !== $back_url ) {
			printf( '<a class="pfont-back" href="%s">', esc_url( $back_url ) );
			Icons::render( 'arrow-left' );
			printf( '<span>%s</span></a>', esc_html( $back_label ) );
		}
		printf( '<h1 class="pfont-page-head__title">%s</h1>', esc_html( $title ) );
		if ( '' !== $description ) {
			printf( '<p class="pfont-page-head__desc">%s</p>', esc_html( $description ) );
		}
		echo '</div>';
		if ( $actions ) {
			echo '<div class="pfont-page-head__actions">';
			foreach ( $actions as $action ) {
				self::button_link( (string) $action['label'], (string) $action['url'], (string) ( $action['icon'] ?? '' ), (string) ( $action['style'] ?? '' ) );
			}
			echo '</div>';
		}
		echo '</header>';
	}

	/**
	 * Link styled as a button.
	 *
	 * @param string $label Label.
	 * @param string $url   URL.
	 * @param string $icon  Optional icon.
	 * @param string $style Optional modifier (ghost).
	 */
	public static function button_link( string $label, string $url, string $icon = '', string $style = '' ): void {
		printf( '<a class="%1$s" href="%2$s">', esc_attr( '' !== $style ? 'pfont-btn pfont-btn--' . $style : 'pfont-btn' ), esc_url( $url ) );
		if ( '' !== $icon ) {
			Icons::render( $icon );
		}
		printf( '<span>%s</span></a>', esc_html( $label ) );
	}

	/**
	 * Section heading.
	 *
	 * @param string $title       Title.
	 * @param string $description Optional description.
	 */
	public static function section_title( string $title, string $description = '' ): void {
		printf( '<h2 class="pfont-section-title">%s</h2>', esc_html( $title ) );
		if ( '' !== $description ) {
			printf( '<p class="pfont-section-desc">%s</p>', esc_html( $description ) );
		}
	}

	/**
	 * Sub-heading inside a card (one section, several parts).
	 *
	 * @param string $title       Title.
	 * @param string $description Optional description.
	 */
	public static function card_subtitle( string $title, string $description = '' ): void {
		printf( '<hr class="pfont-divider"><h3 class="pfont-card__title pfont-card__title--sub">%s</h3>', esc_html( $title ) );
		if ( '' !== $description ) {
			printf( '<p class="pfont-card__desc">%s</p>', esc_html( $description ) );
		}
	}

	/**
	 * Toggle row: icon, label, description and a switch.
	 *
	 * @param string $name        Field name.
	 * @param string $icon        Icon.
	 * @param string $label       Label.
	 * @param string $description Description.
	 * @param bool   $checked     State.
	 * @param bool   $muted       De-emphasize (for example, a platform not on this site).
	 */
	public static function toggle_row( string $name, string $icon, string $label, string $description, bool $checked, bool $muted = false ): void {
		printf( '<label class="%s"><span class="pfont-toggle-row__icon">', esc_attr( $muted ? 'pfont-toggle-row is-muted' : 'pfont-toggle-row' ) );
		Icons::render( $icon );
		printf(
			'</span><span class="pfont-toggle-row__text"><span class="pfont-toggle-row__label">%1$s</span><span class="pfont-toggle-row__desc">%2$s</span></span><input type="checkbox" class="pfont-switch" role="switch" name="%3$s" value="1"%4$s></label>',
			esc_html( $label ),
			esc_html( $description ),
			esc_attr( $name ),
			checked( $checked, true, false )
		);
	}

	/**
	 * Settings view (Settings API, saved through options.php).
	 */
	private static function render_settings(): void {
		$settings = Settings::all();
		$option   = Settings::OPTION;
		self::page_header( __( 'Settings', 'pfont' ), __( 'How fonts load on your site, and what happens when the plugin is deleted.', 'pfont' ) );
		echo '<form method="post" action="options.php">';
		settings_fields( 'pfont_settings' );

		self::section_title( __( 'Loading', 'pfont' ) );
		printf( '<div class="pfont-card"><p class="pfont-field__label" id="pfont-strategy-label">%s</p><div class="pfont-radio-list" role="radiogroup" aria-labelledby="pfont-strategy-label">', esc_html__( 'Load CDN fonts', 'pfont' ) );
		self::radio_row( $option . '[remote_strategy]', 'smart', __( 'Only on pages that use them', 'pfont' ), __( 'Recommended. Elementor and Astra report exactly which fonts a page uses; for other editors the font name is found in the page content and settings.', 'pfont' ), (string) $settings['remote_strategy'] );
		self::radio_row( $option . '[remote_strategy]', 'always', __( 'On every page', 'pfont' ), __( 'Only for troubleshooting a font that does not show up.', 'pfont' ), (string) $settings['remote_strategy'] );
		printf( '</div><hr class="pfont-divider"><div class="pfont-grid-2"><div class="pfont-field"><label for="pfont-provider">%1$s</label><select id="pfont-provider" class="pfont-select" name="%2$s[cdn_provider]">', esc_html__( 'Font CDN', 'pfont' ), esc_attr( $option ) );
		foreach ( CdnFonts::providers() as $id => $provider ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $id ), selected( $settings['cdn_provider'], $id, false ), esc_html( $provider['label'] ) );
		}
		printf( '</select><p class="pfont-field__help">%s</p></div></div><div class="pfont-toggle-list pfont-mt">', esc_html__( 'Bunny Fonts is an EU-based alternative with the same fonts. Hosting fonts on your own server is the most private option.', 'pfont' ) );
		self::toggle_row( $option . '[preconnect]', 'zap', __( 'Connect to the CDN early', 'pfont' ), __( 'Adds a preconnect hint on pages that use a CDN font, so text appears sooner.', 'pfont' ), ! empty( $settings['preconnect'] ) );
		echo '</div></div>';

		self::section_title( __( 'Editors', 'pfont' ) );
		echo '<div class="pfont-card"><div class="pfont-toggle-list">';
		self::toggle_row( $option . '[tinymce_toolbar]', 'editor', __( 'Font menu in the Classic Editor', 'pfont' ), __( 'Adds the font dropdown to the second toolbar row when it is missing.', 'pfont' ), ! empty( $settings['tinymce_toolbar'] ) );
		echo '</div></div>';

		self::section_title( __( 'WordPress admin', 'pfont' ), __( 'Use one of your fonts for the dashboard screens and the toolbar. Visitors never see this.', 'pfont' ) );
		self::render_admin_font( (string) $settings['admin_font'], $option );

		self::section_title( __( 'When the plugin is deleted', 'pfont' ), __( 'Both options are off by default, so nothing is lost by accident.', 'pfont' ) );
		echo '<div class="pfont-card"><div class="pfont-toggle-list">';
		self::toggle_row( $option . '[delete_files_on_uninstall]', 'trash', __( 'Delete font files', 'pfont' ), __( 'Removes the folder wp-content/uploads/pfont.', 'pfont' ), ! empty( $settings['delete_files_on_uninstall'] ) );
		self::toggle_row( $option . '[delete_settings_on_uninstall]', 'trash', __( 'Delete the font library and settings', 'pfont' ), __( 'Removes every font entry and these settings from the database.', 'pfont' ), ! empty( $settings['delete_settings_on_uninstall'] ) );
		echo '</div></div>';

		self::section_title( __( 'Troubleshooting', 'pfont' ) );
		echo '<div class="pfont-card"><div class="pfont-toggle-list">';
		self::toggle_row( $option . '[debug]', 'terminal', __( 'Debug mode', 'pfont' ), __( 'Shows the Debug page and records which fonts the last front-end page loaded.', 'pfont' ), ! empty( $settings['debug'] ) );
		echo '</div></div>';

		printf( '<div class="pfont-savebar"><button type="submit" class="pfont-btn">%s</button></div></form>', esc_html__( 'Save settings', 'pfont' ) );
	}

	/**
	 * Admin area font selector.
	 *
	 * @param string $current Selected font ID.
	 * @param string $option  Option name.
	 */
	private static function render_admin_font( string $current, string $option ): void {
		$selected = '' !== $current ? FontRegistry::get_font( $current ) : null;
		printf(
			'<div class="pfont-card"><div class="pfont-grid-2"><div class="pfont-field"><label for="pfont-admin-font">%1$s</label><select id="pfont-admin-font" class="pfont-select" name="%2$s[admin_font]"><option value="">%3$s</option>',
			esc_html__( 'Admin area font', 'pfont' ),
			esc_attr( $option ),
			esc_html__( 'WordPress default', 'pfont' )
		);
		foreach ( FontRegistry::all() as $font ) {
			// A disabled font is only listed while it is the saved choice, so the state stays visible.
			if ( ! $font->is_enabled() && $font->id() !== $current ) {
				continue;
			}
			/* translators: %s: font name. */
			$label = $font->is_enabled() ? $font->name() : sprintf( __( '%s (disabled, not applied)', 'pfont' ), $font->name() );
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $font->id() ), selected( $current, $font->id(), false ), esc_html( $label ) );
		}
		$help = __( 'Icons, code fields and the content you edit keep their own fonts. Only enabled fonts are listed.', 'pfont' );
		if ( $selected && ! $selected->is_local() ) {
			$help .= ' ' . __( 'This font loads from a CDN on every admin screen; host it on this server to avoid that request.', 'pfont' );
		}
		printf( '</select><p class="pfont-field__help">%s</p></div></div></div>', esc_html( $help ) );
	}

	/**
	 * Radio option row.
	 *
	 * @param string $name        Field name.
	 * @param string $value       Value.
	 * @param string $label       Label.
	 * @param string $description Description.
	 * @param string $current     Current value.
	 */
	private static function radio_row( string $name, string $value, string $label, string $description, string $current ): void {
		printf(
			'<label class="pfont-radio-row"><input type="radio" name="%1$s" value="%2$s"%3$s><span><span class="pfont-radio-row__label">%4$s</span><span class="pfont-radio-row__desc">%5$s</span></span></label>',
			esc_attr( $name ),
			esc_attr( $value ),
			checked( $current, $value, false ),
			esc_html( $label ),
			esc_html( $description )
		);
	}
}
