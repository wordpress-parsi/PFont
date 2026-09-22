<?php
/**
 * Files on disk: wp-content/uploads/pfont/.
 *
 * @package PFont
 */

namespace PFont\Fonts;

use PFont\Core\FontValidator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stores font files with WordPress APIs (wp_handle_upload/wp_handle_sideload/wp_upload_bits).
 * Records keep paths relative to the base folder so a domain or HTTPS change never breaks them.
 */
final class FontStorage {

	public const DIR = 'pfont';

	/**
	 * Uploads info without creating the dated folder.
	 *
	 * @return array
	 */
	private static function uploads(): array {
		return wp_upload_dir( null, false );
	}

	/**
	 * Absolute base folder.
	 *
	 * @return string
	 */
	public static function base_dir(): string {
		return trailingslashit( self::uploads()['basedir'] ) . self::DIR;
	}

	/**
	 * Base URL with the current scheme.
	 *
	 * @return string
	 */
	public static function base_url(): string {
		return set_url_scheme( trailingslashit( self::uploads()['baseurl'] ) . self::DIR );
	}

	/**
	 * Reject traversal and odd characters in stored relative paths.
	 *
	 * @param string $relative Relative path.
	 * @return string '' when unsafe.
	 */
	public static function clean_relative( string $relative ): string {
		$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );
		if ( '' === $relative || str_contains( $relative, '..' ) || preg_match( '#[^A-Za-z0-9._/\-]#', $relative ) ) {
			return '';
		}
		return $relative;
	}

	/**
	 * Public URL of a stored file.
	 *
	 * @param string $relative Relative path.
	 * @return string
	 */
	public static function url( string $relative ): string {
		$relative = self::clean_relative( $relative );
		return '' === $relative ? '' : self::base_url() . '/' . $relative;
	}

	/**
	 * Absolute path of a stored file.
	 *
	 * @param string $relative Relative path.
	 * @return string
	 */
	public static function path( string $relative ): string {
		$relative = self::clean_relative( $relative );
		return '' === $relative ? '' : self::base_dir() . '/' . $relative;
	}

	/**
	 * Create the base folder with listing/script protection.
	 *
	 * @return bool
	 */
	public static function ensure_base_dir(): bool {
		$dir = self::base_dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$fs = self::filesystem();
		if ( $fs ) {
			if ( ! $fs->exists( $dir . '/index.php' ) ) {
				$fs->put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n", FS_CHMOD_FILE );
			}
			if ( ! $fs->exists( $dir . '/.htaccess' ) ) {
				$rules = "# PFont: never execute scripts from this folder.\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh|shtml)$\">\n  Require all denied\n</FilesMatch>\nOptions -Indexes\n";
				$fs->put_contents( $dir . '/.htaccess', $rules, FS_CHMOD_FILE );
			}
		}
		return true;
	}

	/**
	 * Store a validated upload for a weight/style.
	 *
	 * @param array  $validated Result of FontValidator::validate_upload().
	 * @param string $font_id   Font ID.
	 * @param int    $weight    Weight.
	 * @param string $style     Style.
	 * @param bool   $sideload  Use wp_handle_sideload (non-HTTP files).
	 * @return string|WP_Error Relative path.
	 */
	public static function store_upload( array $validated, string $font_id, int $weight, string $style, bool $sideload = false ): string|WP_Error {
		self::ensure_base_dir();
		$file = array(
			'name'     => sanitize_file_name( sprintf( '%s-%d-%s.%s', $font_id, $weight, $style, $validated['ext'] ) ),
			'type'     => $validated['mime'],
			'tmp_name' => $validated['tmp_name'],
			'error'    => 0,
			'size'     => $validated['size'],
		);

		$result = self::with_scoped_filters(
			'uploads/' . sanitize_key( $font_id ),
			$validated['tmp_name'],
			$validated['format'],
			static function () use ( $file, $sideload ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				$overrides = array(
					'test_form' => false,
					'mimes'     => FontValidator::mime_types(),
				);
				return $sideload ? wp_handle_sideload( $file, $overrides ) : wp_handle_upload( $file, $overrides );
			}
		);

		if ( ! is_array( $result ) || ! empty( $result['error'] ) ) {
			return new WP_Error( 'ucf_store_failed', is_array( $result ) && is_string( $result['error'] ?? null ) ? $result['error'] : __( 'The file could not be saved.', 'pfont' ) );
		}
		return self::relative_from_path( (string) $result['file'] );
	}

	/**
	 * Store downloaded bytes (self-hosting).
	 *
	 * @param string $bytes    File contents.
	 * @param string $subdir   Sub folder, e.g. google/vazirmatn-1a2b3c4d.
	 * @param string $filename File name.
	 * @param string $format   Font format.
	 * @return string|WP_Error Relative path.
	 */
	public static function store_bytes( string $bytes, string $subdir, string $filename, string $format ): string|WP_Error {
		self::ensure_base_dir();
		$result = self::with_scoped_filters(
			$subdir,
			'',
			$format,
			static function () use ( $filename, $bytes ) {
				return wp_upload_bits( $filename, null, $bytes );
			}
		);
		if ( ! is_array( $result ) || ! empty( $result['error'] ) ) {
			return new WP_Error( 'ucf_write_failed', is_array( $result ) && is_string( $result['error'] ?? null ) ? $result['error'] : __( 'The file could not be written.', 'pfont' ) );
		}
		return self::relative_from_path( (string) $result['file'] );
	}

	/**
	 * Delete one stored file.
	 *
	 * @param string $relative Relative path.
	 */
	public static function delete_relative( string $relative ): void {
		$path = self::path( $relative );
		if ( '' !== $path && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Delete a sub folder (e.g. uploads/{id} or google/{id}-hash).
	 *
	 * @param string $relative_dir Relative folder.
	 */
	public static function delete_dir( string $relative_dir ): void {
		$dir = self::path( $relative_dir );
		if ( '' === $dir || ! is_dir( $dir ) || untrailingslashit( $dir ) === untrailingslashit( self::base_dir() ) ) {
			return;
		}
		$fs = self::filesystem();
		if ( $fs ) {
			$fs->rmdir( $dir, true );
			return;
		}
		foreach ( (array) glob( trailingslashit( $dir ) . '*' ) as $file ) {
			if ( is_string( $file ) && is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Run a WordPress upload API call with our folder, font MIME types and signature-verified type check.
	 *
	 * @param string   $subdir   Sub folder.
	 * @param string   $tmp      Temp file already verified by FontValidator ('' for upload_bits).
	 * @param string   $format   Verified format.
	 * @param callable $callback Work.
	 * @return mixed
	 */
	private static function with_scoped_filters( string $subdir, string $tmp, string $format, callable $callback ): mixed {
		$subdir  = trim( (string) preg_replace( '#[^A-Za-z0-9._/\-]#', '', $subdir ), '/' );
		$dir_cb  = static function ( $dirs ) use ( $subdir ) {
			$dirs['subdir'] = '/' . self::DIR . '/' . $subdir;
			$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
			$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
			return $dirs;
		};
		$mime_cb = static function ( $mimes ) {
			return array_merge( (array) $mimes, FontValidator::mime_types() );
		};
		// WordPress' fileinfo map does not know font types reliably; our signature check already ran.
		$type_cb = static function ( $data, $file, $filename ) use ( $tmp, $format ) {
			$ext = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
			if ( $ext === $format && '' !== $tmp && $file === $tmp ) {
				return array(
					'ext'             => $format,
					'type'            => FontValidator::mime_types()[ $format ],
					'proper_filename' => false,
				);
			}
			return $data;
		};

		add_filter( 'upload_dir', $dir_cb, 999 );
		add_filter( 'upload_mimes', $mime_cb, 999 );
		add_filter( 'wp_check_filetype_and_ext', $type_cb, 999, 3 );
		try {
			return $callback();
		} finally {
			remove_filter( 'upload_dir', $dir_cb, 999 );
			remove_filter( 'upload_mimes', $mime_cb, 999 );
			remove_filter( 'wp_check_filetype_and_ext', $type_cb, 999 );
		}
	}

	/**
	 * Convert an absolute path inside the base folder to a relative one.
	 *
	 * @param string $path Absolute path.
	 * @return string|WP_Error
	 */
	private static function relative_from_path( string $path ): string|WP_Error {
		$base = wp_normalize_path( self::base_dir() ) . '/';
		$path = wp_normalize_path( $path );
		if ( ! str_starts_with( $path, $base ) ) {
			return new WP_Error( 'ucf_path', __( 'The file was saved outside the fonts folder.', 'pfont' ) );
		}
		return substr( $path, strlen( $base ) );
	}

	/**
	 * Direct filesystem instance when available without credentials.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	private static function filesystem(): ?\WP_Filesystem_Base {
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( 'direct' !== get_filesystem_method( array(), self::uploads()['basedir'] ) ) {
			return null;
		}
		if ( ! $wp_filesystem && ! WP_Filesystem() ) {
			return null;
		}
		return $wp_filesystem instanceof \WP_Filesystem_Base ? $wp_filesystem : null;
	}
}
