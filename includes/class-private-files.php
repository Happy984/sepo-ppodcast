<?php
/**
 * The protected audio folder: uploads/sepo-podcast-private/.
 *
 * Files here are kept out of the Media Library and blocked from direct web
 * access, so they can only be fetched through the plugin's download address,
 * which checks sign-in.
 *
 * @package SepoPodcast
 */

namespace SepoPodcast;

defined( 'ABSPATH' ) || exit;

/**
 * Protected file storage.
 */
final class Private_Files {

	const DIR = 'sepo-podcast-private';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete_post' ) );
	}

	/**
	 * Absolute folder path (no trailing slash).
	 */
	public static function dir(): string {
		$up = wp_upload_dir( null, false );
		return untrailingslashit( $up['basedir'] ) . '/' . self::DIR;
	}

	/**
	 * Folder URL, used only by the self check.
	 */
	public static function url(): string {
		$up = wp_upload_dir( null, false );
		return untrailingslashit( $up['baseurl'] ) . '/' . self::DIR;
	}

	/**
	 * Audio types accepted into the protected folder.
	 */
	public static function mimes(): array {
		return array(
			'mp3'         => 'audio/mpeg',
			'm4a|m4b'     => 'audio/mp4',
			'aac'         => 'audio/aac',
			'ogg|oga'     => 'audio/ogg',
			'opus'        => 'audio/ogg',
			'wav'         => 'audio/wav',
			'flac'        => 'audio/flac',
			'webm'        => 'audio/webm',
		);
	}

	/**
	 * Create the folder and its blocking files. Safe to call often.
	 */
	public static function ensure_dir(): bool {
		$dir = self::dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$files = array(
			'.htaccess'  => "# Sepo Podcast: protected audio. Served only through the plugin.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n",
			'index.php'  => "<?php\n// Silence is golden.\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n\t<system.webServer>\n\t\t<authorization>\n\t\t\t<deny users=\"*\" />\n\t\t</authorization>\n\t</system.webServer>\n</configuration>\n",
		);
		foreach ( $files as $name => $body ) {
			$file = $dir . '/' . $name;
			if ( ! file_exists( $file ) ) {
				file_put_contents( $file, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tiny guard files in our own uploads folder.
			}
		}
		return true;
	}

	/**
	 * Store an uploaded file (one entry of $_FILES) in the protected folder.
	 *
	 * @param array $file One $_FILES entry.
	 * @return string|\WP_Error Stored file name.
	 */
	public static function store_upload( array $file ) {
		if ( ! self::ensure_dir() ) {
			return new \WP_Error( 'sepo_podcast_dir', __( 'The protected folder could not be created.', 'sepo-podcast' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$redirect = static function ( $dirs ) {
			$dirs['subdir'] = '/' . self::DIR;
			$dirs['path']   = $dirs['basedir'] . '/' . self::DIR;
			$dirs['url']    = $dirs['baseurl'] . '/' . self::DIR;
			return $dirs;
		};
		add_filter( 'upload_dir', $redirect );
		$result = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => self::mimes(),
			)
		);
		remove_filter( 'upload_dir', $redirect );

		if ( isset( $result['error'] ) ) {
			return new \WP_Error( 'sepo_podcast_upload', (string) $result['error'] );
		}
		return wp_basename( (string) $result['file'] );
	}

	/**
	 * Move a file that is already on disk (used by the importer).
	 *
	 * @param string $source Absolute path.
	 * @param string $name   Wanted file name.
	 * @return string|\WP_Error Stored file name.
	 */
	public static function store_path( string $source, string $name ) {
		if ( ! self::ensure_dir() ) {
			return new \WP_Error( 'sepo_podcast_dir', __( 'The protected folder could not be created.', 'sepo-podcast' ) );
		}
		$check = wp_check_filetype( $name, self::mimes() );
		if ( ! $check['ext'] ) {
			return new \WP_Error( 'sepo_podcast_type', __( 'That file type is not allowed for protected audio.', 'sepo-podcast' ) );
		}
		$dir    = self::dir();
		$target = wp_unique_filename( $dir, sanitize_file_name( $name ) );
		if ( ! copy( $source, $dir . '/' . $target ) ) {
			return new \WP_Error( 'sepo_podcast_copy', __( 'The file could not be copied.', 'sepo-podcast' ) );
		}
		return $target;
	}

	/**
	 * Full path for a stored file name, or '' when it is missing or would
	 * leave the folder.
	 *
	 * @param string $name Stored file name.
	 */
	public static function path( string $name ): string {
		$name = wp_basename( $name );
		if ( '' === $name || '.' === $name[0] || 'index.php' === $name || 'web.config' === $name ) {
			return '';
		}
		$dir  = realpath( self::dir() );
		$path = realpath( self::dir() . '/' . $name );
		if ( ! $dir || ! $path || ! is_file( $path ) ) {
			return '';
		}
		if ( 0 !== strpos( $path, $dir . DIRECTORY_SEPARATOR ) ) {
			return '';
		}
		return $path;
	}

	/**
	 * Delete one stored file.
	 *
	 * @param string $name Stored file name.
	 */
	public static function delete( string $name ): void {
		$path = self::path( $name );
		if ( $path ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Delete the protected file when its episode is deleted for good, unless
	 * another episode still uses it.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_delete_post( $post_id ): void {
		if ( Post_Type::TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$name = (string) get_post_meta( $post_id, '_sepo_private_file', true );
		if ( '' === $name ) {
			return;
		}
		$others = get_posts(
			array(
				'post_type'      => Post_Type::TYPE,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'post__not_in'   => array( (int) $post_id ),
				'meta_key'       => '_sepo_private_file', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $name, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( ! $others ) {
			self::delete( $name );
		}
	}

	/**
	 * Is the folder really blocked from the web? Fetches a probe file from
	 * this same site (like Site Health does) and caches the answer for 12 hours.
	 *
	 * @param bool $fresh Ignore the cached answer.
	 * @return array{status:string,path:string}
	 */
	public static function self_check( bool $fresh = false ): array {
		$up   = wp_upload_dir( null, false );
		$path = (string) wp_parse_url( self::url(), PHP_URL_PATH );
		$out  = array(
			'status' => 'unknown',
			'path'   => $path ? trailingslashit( $path ) : '/' . self::DIR . '/',
		);
		if ( ! empty( $up['error'] ) || ! self::ensure_dir() ) {
			return $out;
		}

		$cached = get_transient( 'sepo_podcast_private_check' );
		if ( ! $fresh && is_string( $cached ) ) {
			$out['status'] = $cached;
			return $out;
		}

		$token = wp_generate_password( 20, false );
		$file  = self::dir() . '/sepo-check.txt';
		file_put_contents( $file, $token ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temporary probe file.

		$response = wp_remote_get(
			self::url() . '/sepo-check.txt',
			array(
				'timeout'   => 5,
				'sslverify' => false,
				'cookies'   => array(),
			)
		);
		wp_delete_file( $file );

		if ( is_wp_error( $response ) ) {
			$status = 'unknown';
		} else {
			$body   = (string) wp_remote_retrieve_body( $response );
			$code   = (int) wp_remote_retrieve_response_code( $response );
			$status = ( 200 === $code && false !== strpos( $body, $token ) ) ? 'exposed' : 'protected';
		}
		set_transient( 'sepo_podcast_private_check', $status, 12 * HOUR_IN_SECONDS );
		$out['status'] = $status;
		return $out;
	}
}
