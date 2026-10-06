<?php
/**
 * Form handlers (admin-post.php). Every handler checks capability and nonce first.
 *
 * @package PFont
 */

namespace PFont\Admin;

use PFont\Core\Font;
use PFont\Core\FontRegistry;
use PFont\Core\FontRepository;
use PFont\Core\FontValidator;
use PFont\Core\Settings;
use PFont\Fonts\CdnFonts;
use PFont\Fonts\FontStorage;
use PFont\Fonts\GoogleFontsDownloader;
use PFont\Fonts\UploadedFonts;
use PFont\Helpers\FontHelper;
use PFont\Integrations\IntegrationManager;
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
			add_action( 'admin_post_pfont_' . $action, array( self::class, $action ) );
		}
	}

	/**
	 * Stop unless the user may manage fonts. Each handler verifies its nonce right after.
	 */
	private static function require_capability(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage fonts.', 'pfont' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Add or update a font.
	 */
	public static function save_font(): void {
		self::require_capability();
		check_admin_referer( 'pfont_save_font' );
		// Sanitized key by key the moment it is read; nothing raw is kept or stored after this line.
		$input    = FontValidator::sanitize_raw_input( isset( $_POST['pfont'] ) && is_array( $_POST['pfont'] ) ? wp_unslash( $_POST['pfont'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The array is sanitized in full by sanitize_raw_input().
		$id       = $input['id'] ?? '';
		$existing = '' !== $id ? FontRegistry::get_font( $id ) : null;
		$after    = $input['after'] ?? '';

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
				UploadedFonts::normalize_files_field( self::files_field( 'pfont_existing' ) ),
				UploadedFonts::normalize_files_field( self::files_field( 'pfont_new' ) )
			);
			$record['files'] = $result['files'];
			foreach ( $result['errors'] as $message ) {
				AdminPage::notice( 'error', $message );
			}
			if ( ! $record['files'] ) {
				self::fail( new WP_Error( 'pfont_no_files', __( 'Upload at least one font file (WOFF2 is recommended).', 'pfont' ) ), $input, $id );
			}
		} else {
			$candidate = new Font( $record );
			$changed   = ! $existing || self::remote_signature( $existing ) !== self::remote_signature( $candidate );
			if ( $changed ) {
				$check = self::verify_remote( $candidate );
				if ( is_wp_error( $check ) ) {
					if ( 'pfont_remote_unreachable' !== $check->get_error_code() ) {
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
		AdminPage::notice( 'success', sprintf( __( '“%s” was saved.', 'pfont' ), $record['name'] ) );
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
		check_admin_referer( 'pfont_delete_font_' . $id );
		$font = FontRegistry::get_font( $id );
		if ( $font ) {
			self::remove_files( $font );
			FontRepository::delete( $id );
			if ( Settings::get( 'admin_font' ) === $id ) {
				Settings::update( array( 'admin_font' => '' ) );
			}
			/* translators: %s: font name. */
			$message = sprintf( __( '“%s” was deleted.', 'pfont' ), $font->name() );
			AdminPage::notice( 'success', $message );
		}
		self::redirect();
	}

	/**
	 * Add selected presets (loaded from the CDN until self-hosted).
	 */
	public static function add_presets(): void {
		self::require_capability();
		check_admin_referer( 'pfont_add_presets' );
		$ids   = isset( $_POST['pfont_presets'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['pfont_presets'] ) ) : array();
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
			AdminPage::notice( 'success', sprintf( __( 'Added: %s. They load from the CDN until you open a font and choose “Host on this server”.', 'pfont' ), implode( ', ', $added ) ) );
		} else {
			AdminPage::notice( 'info', __( 'Nothing was added.', 'pfont' ) );
		}
		self::redirect();
	}

	/**
	 * Save the library matrix (enabled + integrations) or only the Customizer column.
	 */
	public static function bulk_update(): void {
		self::require_capability();
		check_admin_referer( 'pfont_bulk_update' );
		// sanitize_key() returns '' for anything that is not a scalar, so the filter drops those.
		$ids     = isset( $_POST['pfont_ids'] ) ? array_filter( array_map( 'sanitize_key', (array) wp_unslash( $_POST['pfont_ids'] ) ) ) : array();
		$matrix  = isset( $_POST['pfont_matrix'] ) && is_array( $_POST['pfont_matrix'] ) ? self::sanitize_matrix( wp_unslash( $_POST['pfont_matrix'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in full by sanitize_matrix().
		$enabled = isset( $_POST['pfont_enabled'] ) && is_array( $_POST['pfont_enabled'] ) ? self::sanitize_flags( wp_unslash( $_POST['pfont_enabled'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in full by sanitize_flags().
		$scope   = isset( $_POST['pfont_scope'] ) ? sanitize_key( wp_unslash( $_POST['pfont_scope'] ) ) : 'all';
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
		AdminPage::notice( 'success', __( 'Changes saved.', 'pfont' ) );
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
		AdminPage::notice( 'success', sprintf( __( '“%1$s” is now served from your server (%2$d files, no third-party requests).', 'pfont' ), $font->name(), count( $files ) ) );
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
			AdminPage::notice( 'error', sprintf( __( 'Rename the CSS family back to “%s” before switching to the CDN.', 'pfont' ), $preset['family'] ) );
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
		AdminPage::notice( 'success', __( 'Switched back to the CDN and removed the local copies.', 'pfont' ) );
	}

	/**
	 * Check that the CDN accepts this family/weight combination (it answers HTTP 400 otherwise).
	 *
	 * @param Font $font Font.
	 * @return bool|WP_Error
	 */
	private static function verify_remote( Font $font ): bool|WP_Error {
		$preset   = $font->preset_data();
		$url      = CdnFonts::css2_url( array( CdnFonts::css2_family( $font->remote_family(), $font->weights(), $preset['range'] ?? null, $font->styles() ) ), $font->display() );
		$response = wp_safe_remote_get( $url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'pfont_remote_unreachable', __( 'The font service could not be reached to verify this font. It was saved anyway.', 'pfont' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 400 === $code ) {
			/* translators: %s: family. */
			return new WP_Error( 'pfont_remote_invalid', sprintf( __( 'The font service rejected “%s” with the selected weights or styles. Check the exact name and the available weights on fonts.google.com.', 'pfont' ), $font->remote_family() ) );
		}
		if ( 200 !== $code ) {
			/* translators: %d: HTTP status. */
			return new WP_Error( 'pfont_remote_unreachable', sprintf( __( 'The font service answered with HTTP %d, so the font could not be verified. It was saved anyway.', 'pfont' ), $code ) );
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
		return md5( (string) wp_json_encode( array( $font->family(), $font->preset(), $font->weights(), $font->styles(), $font->source() ) ) );
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
		return isset( $_FILES[ $name ] ) && is_array( $_FILES[ $name ] ) ? $_FILES[ $name ] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- The nonce was checked by the caller; UploadedFonts::normalize_files_field() sanitizes every entry and FontValidator verifies each file.
	}

	/**
	 * Sanitize the library matrix: [font id][integration] => bool.
	 *
	 * @param array $raw Unslashed input.
	 * @return array<string,array<string,bool>>
	 */
	private static function sanitize_matrix( array $raw ): array {
		$clean = array();
		foreach ( $raw as $id => $columns ) {
			$id = sanitize_key( (string) $id );
			if ( '' === $id || ! is_array( $columns ) ) {
				continue;
			}
			$clean[ $id ] = array();
			foreach ( $columns as $key => $value ) {
				$key = sanitize_key( (string) $key );
				if ( in_array( $key, Font::INTEGRATIONS, true ) ) {
					$clean[ $id ][ $key ] = is_scalar( $value ) && ! empty( $value );
				}
			}
		}
		return $clean;
	}

	/**
	 * Sanitize a list of checkboxes keyed by font ID: [font id] => bool.
	 *
	 * @param array $raw Unslashed input.
	 * @return array<string,bool>
	 */
	private static function sanitize_flags( array $raw ): array {
		$clean = array();
		foreach ( $raw as $id => $value ) {
			$id = sanitize_key( (string) $id );
			if ( '' !== $id ) {
				$clean[ $id ] = is_scalar( $value ) && ! empty( $value );
			}
		}
		return $clean;
	}

	/**
	 * Report errors and return to the form with the (already sanitized) input kept for ten minutes.
	 *
	 * @param WP_Error $error Error.
	 * @param array    $input Output of FontValidator::sanitize_raw_input().
	 * @param string   $id    Font ID.
	 * @return never
	 */
	private static function fail( WP_Error $error, array $input, string $id ): never {
		foreach ( $error->get_error_messages() as $message ) {
			AdminPage::notice( 'error', $message );
		}
		set_transient( 'pfont_form_' . get_current_user_id(), $input, 10 * MINUTE_IN_SECONDS );
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
