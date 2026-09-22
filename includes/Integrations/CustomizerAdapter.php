<?php
/**
 * Theme Customizer font lists for themes without a dedicated adapter.
 *
 * @package PFont
 */

namespace PFont\Integrations;

use PFont\Integrations\Customizer\GeneratePressProvider;
use PFont\Integrations\Customizer\KadenceProvider;
use PFont\Integrations\Customizer\ThemeProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Delegates to a provider for the active theme. Themes without a public font hook are reported
 * as unsupported; UCF never hacks their Customizer controls.
 */
final class CustomizerAdapter extends AbstractAdapter {

	/**
	 * Active provider.
	 *
	 * @var ThemeProvider|null
	 */
	private ?ThemeProvider $provider = null;

	/**
	 * Whether detection ran.
	 *
	 * @var bool
	 */
	private bool $resolved = false;

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'customizer';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		$provider = $this->provider();
		/* translators: %s: theme name. */
		return $provider ? sprintf( __( 'Customizer (%s)', 'pfont' ), $provider->label() ) : __( 'Theme Customizer', 'pfont' );
	}

	/**
	 * Registered providers.
	 *
	 * @return ThemeProvider[]
	 */
	public function providers(): array {
		/**
		 * Add Customizer providers for more themes.
		 *
		 * @param ThemeProvider[] $providers Providers.
		 */
		$providers = apply_filters( 'pfont_customizer_providers', array( new KadenceProvider(), new GeneratePressProvider() ) );
		return (array) apply_filters_deprecated( 'ucf_customizer_providers', array( $providers ), '1.4.0', 'pfont_customizer_providers' );
	}

	/**
	 * Provider for the active theme.
	 *
	 * @return ThemeProvider|null
	 */
	public function provider(): ?ThemeProvider {
		if ( ! $this->resolved ) {
			$this->resolved = true;
			foreach ( $this->providers() as $provider ) {
				if ( $provider instanceof ThemeProvider && $provider->detect() ) {
					$this->provider = $provider;
					break;
				}
			}
		}
		return $this->provider;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return null !== $this->provider();
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return $this->provider() ? (string) wp_get_theme( get_template() )->get( 'Version' ) : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$provider = $this->provider();
		if ( $provider ) {
			$provider->register( $this );
		}
	}

	/**
	 * Public hook registration for providers.
	 *
	 * @param string   $type     filter|action.
	 * @param string   $name     Hook.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Args.
	 */
	public function register_hook( string $type, string $name, callable $callback, int $priority = 10, int $args = 1 ): void {
		$this->hook( $type, $name, $callback, $priority, $args );
	}

	/**
	 * {@inheritDoc}
	 */
	public function enqueue_frontend(): void {
		$provider = $this->provider();
		if ( ! $provider ) {
			return;
		}
		if ( is_customize_preview() ) {
			$this->enqueue_all();
			return;
		}
		$this->enqueue_mentioned( $provider->haystack() . ' ' . self::queried_content(), true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function status(): array {
		$provider = $this->provider();
		if ( $provider ) {
			return $provider->status();
		}
		if ( IntegrationDetector::astra() ) {
			return array(
				'state'   => 'covered',
				'message' => sprintf(
					/* translators: %s: theme name. */
					__( '%s has its own integration above, so this entry is not needed.', 'pfont' ),
					(string) wp_get_theme()->get( 'Name' )
				),
			);
		}
		return array(
			'state'   => 'unsupported',
			'message' => sprintf(
				/* translators: %s: theme name. */
				__( 'Your theme (%s) has no supported public font hook, so nothing is added to its Customizer. If the theme has its own custom font upload (Divi and WoodMart do), add the font there. Your fonts still work in the editors and builders listed above.', 'pfont' ),
				(string) wp_get_theme()->get( 'Name' )
			),
		);
	}
}
