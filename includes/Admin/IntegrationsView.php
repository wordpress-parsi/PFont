<?php
/**
 * Integrations: honest status per platform and the Customizer selection.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Admin;

use UniversalCustomFonts\Core\FontRegistry;
use UniversalCustomFonts\Integrations\IntegrationManager;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Integrations view.
 */
final class IntegrationsView {

	/**
	 * Render.
	 */
	public static function render(): void {
		AdminPage::page_header( __( 'Integrations', 'universal-custom-fonts' ), __( 'Where your fonts can appear, and what was found on this site.', 'universal-custom-fonts' ) );
		echo '<div class="ucf-int-list">';
		$shown = array();
		foreach ( IntegrationManager::adapters() as $candidate ) {
			$key = $candidate->id();
			if ( isset( $shown[ $key ] ) ) {
				continue;
			}
			$shown[ $key ] = true;
			$adapter       = IntegrationManager::for_key( $key ) ?? $candidate;
			$status        = $adapter->status();
			$state         = (string) $status['state'];
			$version       = $adapter->is_available() ? $adapter->version() : '';
			$count         = count( $adapter->fonts() );
			printf( '<div class="%s"><span class="ucf-int__mark">', esc_attr( 'missing' === $state ? 'ucf-int is-missing' : 'ucf-int' ) );
			Icons::render( FontForm::icon_for( $key ) );
			printf(
				'</span><div class="ucf-int__body"><div class="ucf-int__title"><strong>%1$s</strong><span class="ucf-badge ucf-badge--%2$s">%3$s</span>',
				esc_html( $adapter->label() ),
				esc_attr( $state ),
				esc_html( self::state_label( $state ) )
			);
			if ( preg_match( '/^\d+(\.\d+)+/', $version ) ) {
				/* translators: %s: version number. */
				printf( '<span class="ucf-int__version">%s</span>', esc_html( sprintf( __( 'Version %s', 'universal-custom-fonts' ), $version ) ) );
			}
			printf( '</div><p>%s</p></div>', esc_html( (string) $status['message'] ) );
			if ( in_array( $state, array( 'active', 'limited' ), true ) ) {
				printf( '<div class="ucf-int__count"><strong>%1$d</strong>%2$s</div>', (int) $count, esc_html( _n( 'font', 'fonts', $count, 'universal-custom-fonts' ) ) );
			}
			echo '</div>';
		}
		echo '</div>';
		self::render_customizer();
	}

	/**
	 * Badge text.
	 *
	 * @param string $state State.
	 * @return string
	 */
	private static function state_label( string $state ): string {
		$labels = array(
			'active'      => __( 'Active', 'universal-custom-fonts' ),
			'limited'     => __( 'Limited', 'universal-custom-fonts' ),
			'unsupported' => __( 'Unsupported', 'universal-custom-fonts' ),
			'missing'     => __( 'Not detected', 'universal-custom-fonts' ),
			'covered'     => __( 'Not needed', 'universal-custom-fonts' ),
		);
		return $labels[ $state ] ?? $state;
	}

	/**
	 * Fonts offered to theme Customizers.
	 */
	private static function render_customizer(): void {
		$fonts = FontRegistry::all();
		if ( ! $fonts ) {
			return;
		}
		AdminPage::section_title( __( 'Theme Customizer fonts', 'universal-custom-fonts' ), __( 'Fonts offered to supported theme Customizers. Kadence lists them; GeneratePress loads them by name.', 'universal-custom-fonts' ) );
		printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( 'ucf_bulk_update' );
		echo '<input type="hidden" name="action" value="ucf_bulk_update"><input type="hidden" name="ucf_scope" value="customizer"><div class="ucf-card"><div class="ucf-chips">';
		foreach ( $fonts as $font ) {
			printf(
				'<label class="ucf-chip"><input type="hidden" name="ucf_ids[]" value="%1$s"><input type="checkbox" name="ucf_matrix[%1$s][customizer]" value="1"%2$s><span>%3$s</span></label>',
				esc_attr( $font->id() ),
				checked( ! empty( $font->integrations()['customizer'] ), true, false ),
				esc_html( $font->name() )
			);
		}
		printf( '</div><div class="ucf-card__foot"><button type="submit" class="ucf-btn">%s</button></div></div></form>', esc_html__( 'Save Customizer fonts', 'universal-custom-fonts' ) );
	}
}
