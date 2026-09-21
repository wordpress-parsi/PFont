<?php
/**
 * Form handlers (admin-post.php). Every handler checks capability and nonce first.
 *
 * @package UniversalCustomFonts
 */

namespace UniversalCustomFonts\Admin;

use UniversalCustomFonts\Core\Font;
use UniversalCustomFonts\Core\FontRegistry;
use UniversalCustomFonts\Core\FontRepository;
use UniversalCustomFonts\Core\FontValidator;
use UniversalCustomFonts\Core\Settings;
use UniversalCustomFonts\Fonts\CdnFonts;
use UniversalCustomFonts\Fonts\FontStorage;
use UniversalCustomFonts\Fonts\GoogleFontsDownloader;
use UniversalCustomFonts\Fonts\UploadedFonts;
use UniversalCustomFonts\Helpers\FontHelper;
use UniversalCustomFonts\Integrations\IntegrationManager;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Admin actions.
 */
final class Actions {

	/**
	 * Register handlers.
	 */
	public static function register_hooks(): void {
		foreach ( array( 'save_font', 'delete_font', 'add_presets', 'bulk_update' ) as $action ) {
			add_action( 'admin_post_ucf_' . $action, array( self::class, $action ) );
		}
	}

	/**
	 * Stop unless the user may manage fonts. Each handler verifies its nonce right after.
	 */
	private static function require_capability(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage fonts.', 'universal-custom-fonts' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Add or update a font.
	 */
	public static function save_font(): void {
		self::require_capability();
		check_admin_referer( 'ucf_save_font' );
		$input    = isset( $_POST['ucf'] ) && is_array( $_POST['ucf'] ) ? wp_unslash( $_POST['ucf'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized field by field in FontValidator and UploadedFonts.
		$id       = isset( $input['id'] ) ? sanitize_key( (string) $input['id'] ) : '';
		$existing = '' !== $id ? FontRegistry::get_font( $id ) : null;
		$after    = isset( $input['after'] ) ? sanitize_key( (string) $input['after'] ) : '';

		$data = FontValidator::sanitize_font_input( $input, $existing );
		if ( is_wp_error( $data ) ) {
			self::fail( $data, $input, $id );
		}

		$record = $existing
			? array_merge( $existing->to_array(), $data )
			: array_merge( $data, array( 'id' => FontRepository::unique_id( $data['family'] ) ) );

		if ( $existing && $existing->source() !== $record['source'] ) {
			self::remove_files( $existing );
			$record['files']   = array();
			$record['local']   = array();
			$record['hosting'] = 'remote';
		}

		if ( Font::SOURCE_UPLOAD === $record['source'] ) {
			$result          = UploadedFonts::apply(
				$record['id'],
				(array) ( $record['files'] ?? array() ), // A new font has no files yet.
				$input,
				UploadedFonts::normalize_files_field( self::files_field( 'ucf_existing' ) ),
				UploadedFonts::normalize_files_field( self::files_field( 'ucf_new' ) )
			);
			$record['files'] = $result['files'];
			foreach ( $result['errors'] as $message ) {
				AdminPage::notice( 'error', $message );
			}
			if ( ! $record['files'] ) {
				self::fail( new WP_Error( 'ucf_no_files', __( 'Upload at least one font file (WOFF2 is recommended).', 'universal-custom-fonts' ) ), $input, $id );
			}
		} else {
			$candidate = new Font( $record );
			$changed   = ! $existing || self::remote_signature( $existing ) !== self::remote_signature( $candidate );
			if ( $changed ) {
				$check = self::verify_remote( $candidate );
				if ( is_wp_error( $check ) ) {
					if ( 'ucf_remote_unreachable' !== $check->get_error_code() ) {
						self::fail( $check, $input, $id );
					}
					AdminPage::notice( 'warning', $check->get_error_message() );
				}
			}
			if ( $existing && $changed && $existing->is_self_hosted() && 'unhost' !== $after ) {
				$after = 'selfhost';
			}
		}

		FontRepository::save( $record );
		$font = FontRegistry::get_font( $record['id'] );
		if ( $font && 'selfhost' === $after ) {
			self::self_host( $font );
		} elseif ( $font && 'unhost' === $after ) {
			self::unhost( $font );
		}

		/* translators: %s: font name. */
		AdminPage::notice( 'success', sprintf( __( '“%s” was saved.', 'universal-custom-fonts' ), $record['name'] ) );
		$font = FontRegistry::get_font( $record['id'] );
		if ( $font ) {
			foreach ( IntegrationManager::available() as $adapter ) {
				$message = $font->enabled_for( $adapter->id() ) ? $adapter->native_conflict( $font ) : '';
				if ( '' !== $message ) {
					AdminPage::notice( 'info', $message );
				}
			}
		}
		self::redirect(
			array(
				'tab'  => 'font',
				'font' => $record['id'],
			)
		);
	}

	/**
	 * Delete a font and its files.
	 */
	public static function delete_font(): void {
		$id = isset( $_GET['font'] ) ? sanitize_key( wp_unslash( $_GET['font'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce below includes this ID.
		self::require_capability();
		check_admin_referer( 'ucf_delete_font_' . $id );
		$font = FontRegistry::get_font( $id );
		if ( $font ) {
			self::remove_files( $font );
			FontRepository::delete( $id );
			if ( Settings::get( 'admin_font' ) === $id ) {
				Settings::update( array( 'admin_font' => '' ) );
			}
			/* translators: %s: font name. */
			$message = sprintf( __( '“%s” was deleted.', 'universal-custom-fonts' ), $font->name() );
			AdminPage::notice( 'success', $message );
		}
		self::redirect();
	}

	/**
	 * Add selected presets (loaded from the CDN until self-hosted).
	 */
	public static function add_presets(): void {
		self::require_capability();
		check_admin_referer( 'ucf_add_presets' );
		$ids   = isset( $_POST['ucf_presets'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['ucf_presets'] ) ) : array();
		$added = array();
		foreach ( $ids as $preset_id ) {
			$preset = CdnFonts::preset( $preset_id );
			if ( ! $preset || FontRegistry::find_by_family( $preset['family'] ) ) {
				continue;
			}
			FontRepository::save(
				array(
					'id'       => FontRepository::unique_id( $preset['family'] ),
					'name'     => $preset['family'],
					'family'   => $preset['family'],
					'fallback' => $preset['fallback'],
					'source'   => Font::SOURCE_CDN,
					'preset'   => $preset_id,
					'weights'  => $preset['weights'],
					'styles'   => $preset['styles'],
					'enabled'  => true,
				)
			);
			$added[] = $preset['family'];
		}
		if ( $added ) {
			/* translators: %s: comma-separated font names. */
			AdminPage::notice( 'success', sprintf( __( 'Added: %s. They load from the CDN until you open a font and choose “Host on this server”.', 'universal-custom-fonts' ), implode( ', ', $added ) ) );
		} else {
			AdminPage::notice( 'info', __( 'Nothing was added.', 'universal-custom-fonts' ) );
		}
		self::redirect();
	}

	/**
	 * Save the library matrix (enabled + integrations) or only the Customizer column.
	 */
	public static function bulk_update(): void {
		self::require_capability();
		check_admin_referer( 'ucf_bulk_update' );
		$ids     = isset( $_POST['ucf_ids'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['ucf_ids'] ) ) : array();
		$matrix  = isset( $_POST['ucf_matrix'] ) && is_array( $_POST['ucf_matrix'] ) ? wp_unslash( $_POST['ucf_matrix'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are only tested with empty().
		$enabled = isset( $_POST['ucf_enabled'] ) && is_array( $_POST['ucf_enabled'] ) ? wp_unslash( $_POST['ucf_enabled'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are only tested with empty().
		$scope   = isset( $_POST['ucf_scope'] ) ? sanitize_key( wp_unslash( $_POST['ucf_scope'] ) ) : 'all';
		$keys    = 'customizer' === $scope ? array( 'customizer' ) : ( 'enabled' === $scope ? array() : Font::INTEGRATIONS );

		foreach ( array_unique( $ids ) as $id ) {
			$font = FontRegistry::get_font( $id );
			if ( ! $font ) {
				continue;
			}
			$integrations = $font->integrations();
			foreach ( $keys as $key ) {
				$integrations[ $key ] = ! empty( $matrix[ $id ][ $key ] );
			}
			$changes = array( 'integrations' => $integrations );
			if ( 'customizer' !== $scope ) {
				$changes['enabled'] = ! empty( $enabled[ $id ] );
			}
			FontRepository::save( array_merge( $font->to_array(), $changes ) );
		}
		AdminPage::notice( 'success', __( 'Changes saved.', 'universal-custom-fonts' ) );
		self::redirect( 'customizer' === $scope ? array( 'tab' => 'integrations' ) : array() );
	}

	/**
	 * Download a CDN font to this server.
	 *
	 * @param Font $font Font.
	 */
	private static function self_host( Font $font ): void {
		$result = GoogleFontsDownloader::download( $font );
		if ( is_wp_error( $result ) ) {
			AdminPage::notice( 'error', $result->get_error_message() );
			return;
		}
		$old = (array) $font->get( 'local' );
		FontRepository::save(
			array_merge(
				$font->to_array(),
				array(
					'hosting' => 'local',
					'local'   => $result,
				)
			)
		);
		if ( ! empty( $old['dir'] ) && $old['dir'] !== $result['dir'] ) {
			FontStorage::delete_dir( (string) $old['dir'] );
		}
		$files = array();
		foreach ( $result['faces'] as $face ) {
			foreach ( $face['src'] as $src ) {
				$files[ $src['file'] ] = true;
			}
		}
		/* translators: 1: font name, 2: number of files. */
		AdminPage::notice( 'success', sprintf( __( '“%1$s” is now served from your server (%2$d files, no third-party requests).', 'universal-custom-fonts' ), $font->name(), count( $files ) ) );
	}

	/**
	 * Go back to the CDN.
	 *
	 * @param Font $font Font.
	 */
	private static function unhost( Font $font ): void {
		$preset = $font->preset_data();
		if ( $preset && ! FontHelper::same_family( $font->family(), $preset['family'] ) ) {
			/* translators: %s: Google family name. */
			AdminPage::notice( 'error', sprintf( __( 'Rename the CSS family back to “%s” before switching to the CDN.', 'universal-custom-fonts' ), $preset['family'] ) );
			return;
		}
		$old = (array) $font->get( 'local' );
		FontRepository::save(
			array_merge(
				$font->to_array(),
				array(
					'hosting' => 'remote',
					'local'   => array(),
				)
			)
		);
		if ( ! empty( $old['dir'] ) ) {
			FontStorage::delete_dir( (string) $old['dir'] );
		}
		AdminPage::notice( 'success', __( 'Switched back to the CDN and removed the local copies.', 'universal-custom-fonts' ) );
	}

	/**
	 * Check that the CDN accepts this family/weight combination (it answers HTTP 400 otherwise).
	 *
	 * @param Font $font Font.
	 * @return bool|WP_Error
	 */
	private static function verify_remote( Font $font ): bool|WP_Error {
		if ( '' !== $font->cdn_url() ) {
			$response = wp_safe_remote_get( $font->cdn_url(), array( 'timeout' => 10 ) );
			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'ucf_remote_unreachable', __( 'The stylesheet could not be checked right now. It was saved anyway.', 'universal-custom-fonts' ) );
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				/* translators: %d: HTTP status. */
				return new WP_Error( 'ucf_remote_http', sprintf( __( 'The stylesheet URL answered with HTTP %d.', 'universal-custom-fonts' ), $code ) );
			}
			preg_match_all( '/font-family\s*:\s*[\'"]?([^;\'"}]+)/i', (string) wp_remote_retrieve_body( $response ), $matches );
			$declared = array_values( array_unique( array_map( 'trim', $matches[1] ?? array() ) ) );
			foreach ( $declared as $name ) {
				if ( FontHelper::same_family( $name, $font->family() ) ) {
					return true;
				}
			}
			if ( $declared ) {
				/* translators: 1: declared names, 2: entered family. */
				return new WP_Error( 'ucf_remote_family', sprintf( __( 'That stylesheet declares %1$s, not “%2$s”. Use the exact family name it declares.', 'universal-custom-fonts' ), '“' . implode( '”, “', array_slice( $declared, 0, 3 ) ) . '”', $font->family() ) );
			}
			return true;
		}

		$preset   = $font->preset_data();
		$url      = CdnFonts::css2_url( array( CdnFonts::css2_family( $font->remote_family(), $font->weights(), $preset['range'] ?? null, $font->styles() ) ), $font->display() );
		$response = wp_safe_remote_get( $url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ucf_remote_unreachable', __( 'The font service could not be reached to verify this font. It was saved anyway.', 'universal-custom-fonts' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 400 === $code ) {
			/* translators: %s: family. */
			return new WP_Error( 'ucf_remote_invalid', sprintf( __( 'The font service rejected “%s” with the selected weights or styles. Check the exact name and the available weights on fonts.google.com.', 'universal-custom-fonts' ), $font->remote_family() ) );
		}
		if ( 200 !== $code ) {
			/* translators: %d: HTTP status. */
			return new WP_Error( 'ucf_remote_unreachable', sprintf( __( 'The font service answered with HTTP %d, so the font could not be verified. It was saved anyway.', 'universal-custom-fonts' ), $code ) );
		}
		return true;
	}

	/**
	 * Fields that change the CDN request.
	 *
	 * @param Font $font Font.
	 * @return string
	 */
	private static function remote_signature( Font $font ): string {
		return md5( (string) wp_json_encode( array( $font->family(), $font->preset(), $font->cdn_url(), $font->weights(), $font->styles(), $font->source() ) ) );
	}

	/**
	 * Delete uploaded and self-hosted files of a font.
	 *
	 * @param Font $font Font.
	 */
	private static function remove_files( Font $font ): void {
		UploadedFonts::delete_all( $font );
		$local = (array) $font->get( 'local' );
		if ( ! empty( $local['dir'] ) ) {
			FontStorage::delete_dir( (string) $local['dir'] );
		}
	}

	/**
	 * A nested $_FILES entry.
	 *
	 * @param string $name Field name.
	 * @return array
	 */
	private static function files_field( string $name ): array {
		return isset( $_FILES[ $name ] ) && is_array( $_FILES[ $name ] ) ? $_FILES[ $name ] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Nonce checked in authorize(); every file is validated by FontValidator.
	}

	/**
	 * Report errors and return to the form with the input kept.
	 *
	 * @param WP_Error $error Error.
	 * @param array    $input Input.
	 * @param string   $id    Font ID.
	 * @return never
	 */
	private static function fail( WP_Error $error, array $input, string $id ): never {
		foreach ( $error->get_error_messages() as $message ) {
			AdminPage::notice( 'error', $message );
		}
		set_transient( 'ucf_form_' . get_current_user_id(), $input, 10 * MINUTE_IN_SECONDS );
		self::redirect(
			array(
				'tab'  => 'edit',
				'font' => $id,
			)
		);
	}

	/**
	 * Redirect back to the screen.
	 *
	 * @param array $args Query args.
	 * @return never
	 */
	private static function redirect( array $args = array() ): never {
		wp_safe_redirect( AdminPage::url( array_filter( $args ) ) );
		exit;
	}
}
