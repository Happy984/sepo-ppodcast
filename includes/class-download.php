<?php
/**
 * File addresses: /podcast-file/{id}/{download|listen|stream}/{file-name}.
 *
 * download – the Download button. Checks "sign in to download", counts once
 *            per visitor per window, sends the file as an attachment.
 * listen   – the feed enclosure for podcast apps. Same checks and counting
 *            (only the first byte range counts), sent inline.
 * stream   – the on-page player for protected files. Needs a signed,
 *            expiring address, never counts.
 *
 * @package SepoPodcast
 */

namespace SepoPodcast;

defined( 'ABSPATH' ) || exit;

/**
 * Serves episode audio.
 */
final class Download {

	const BASE  = 'podcast-file';
	const MODES = array( 'download', 'listen', 'stream' );

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'maybe_serve' ) );
	}

	/**
	 * Rewrite rule for pretty addresses.
	 */
	public static function add_rewrite(): void {
		add_rewrite_rule(
			'^' . self::BASE . '/([0-9]+)/(download|listen|stream)(?:/[^/]*)?/?$',
			'index.php?sepo_podcast_file=$matches[1]&sepo_podcast_mode=$matches[2]',
			'top'
		);
	}

	/**
	 * Public query vars.
	 *
	 * @param string[] $vars Query vars.
	 */
	public static function query_vars( $vars ): array {
		$vars[] = 'sepo_podcast_file';
		$vars[] = 'sepo_podcast_mode';
		return $vars;
	}

	/**
	 * Address for an episode file.
	 *
	 * @param int    $id   Episode ID.
	 * @param string $mode download | listen | stream.
	 * @param string $name File name to show at the end of the address.
	 */
	public static function url( int $id, string $mode, string $name = '' ): string {
		$mode = in_array( $mode, self::MODES, true ) ? $mode : 'download';
		if ( get_option( 'permalink_structure' ) ) {
			$name = sanitize_file_name( $name );
			$tail = '' !== $name ? '/' . rawurlencode( $name ) : '';
			return home_url( '/' . self::BASE . '/' . $id . '/' . $mode . $tail );
		}
		return add_query_arg(
			array(
				'sepo_podcast_file' => $id,
				'sepo_podcast_mode' => $mode,
			),
			home_url( '/' )
		);
	}

	/**
	 * Signed stream address for the player. It expires, so a copied address
	 * stops working; the expiry is rounded to the hour so the address stays
	 * the same for page caches within that hour.
	 *
	 * @param int    $id   Episode ID.
	 * @param string $name File name.
	 */
	public static function signed_stream_url( int $id, string $name = '' ): string {
		/**
		 * How long a player address for a protected file stays valid, in seconds.
		 *
		 * @param int $ttl Seconds. Default two days, so cached pages keep working.
		 */
		$ttl = max( 300, (int) apply_filters( 'sepo_podcast_stream_ttl', 2 * DAY_IN_SECONDS ) );
		$exp = (int) ( ceil( ( time() + $ttl ) / HOUR_IN_SECONDS ) * HOUR_IN_SECONDS );
		return add_query_arg(
			array(
				'exp' => $exp,
				'sig' => self::sign( $id, $exp ),
			),
			self::url( $id, 'stream', $name )
		);
	}

	/**
	 * Signature for a stream address.
	 *
	 * @param int $id  Episode ID.
	 * @param int $exp Expiry timestamp.
	 */
	private static function sign( int $id, int $exp ): string {
		return substr( hash_hmac( 'sha256', 'stream|' . $id . '|' . $exp, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Serve the file when the request is one of ours.
	 *
	 * @param \WP $wp Current request.
	 */
	public static function maybe_serve( $wp ): void {
		if ( empty( $wp->query_vars['sepo_podcast_file'] ) ) {
			return;
		}
		$id   = absint( $wp->query_vars['sepo_podcast_file'] );
		$mode = isset( $wp->query_vars['sepo_podcast_mode'] ) ? sanitize_key( (string) $wp->query_vars['sepo_podcast_mode'] ) : 'download';
		$mode = in_array( $mode, self::MODES, true ) ? $mode : 'download';
		self::serve( $id, $mode );
		exit;
	}

	/**
	 * Check access and send the audio.
	 *
	 * @param int    $id   Episode ID.
	 * @param string $mode download | listen | stream.
	 */
	public static function serve( int $id, string $mode ): void {
		$post = get_post( $id );
		if ( ! $post || Post_Type::TYPE !== $post->post_type ) {
			self::fail( 404, __( 'Episode not found.', 'sepo-podcast' ) );
		}
		$editor = current_user_can( 'edit_post', $id );
		if ( 'publish' !== $post->post_status && ! $editor ) {
			self::fail( 404, __( 'Episode not found.', 'sepo-podcast' ) );
		}
		if ( post_password_required( $post ) ) {
			self::fail( 403, __( 'This episode is password protected.', 'sepo-podcast' ) );
		}

		$ep     = Episode::get( $post );
		$source = Episode::source( $id );
		if ( ! $ep || '' === $source['kind'] ) {
			self::fail( 404, __( 'This episode has no audio file.', 'sepo-podcast' ) );
		}

		if ( 'stream' === $mode ) {
			if ( 'private' !== $source['kind'] ) {
				self::redirect( (string) $source['url'] );
			}
			$exp = isset( $_GET['exp'] ) ? absint( $_GET['exp'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed address, checked below.
			$sig = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! $editor && ( $exp < time() || ! hash_equals( self::sign( $id, $exp ), $sig ) ) ) {
				self::fail( 403, __( 'This player address has expired. Reload the episode page.', 'sepo-podcast' ) );
			}
		} else {
			// "Show a Download button" is about the button; podcast apps still get the file.
			if ( 'download' === $mode && ! $ep['allow_download'] && ! $editor ) {
				self::fail( 403, __( 'Downloads are turned off for this episode.', 'sepo-podcast' ) );
			}
			if ( $ep['requires_login'] && ! is_user_logged_in() ) {
				$here = self::url( $id, $mode, $source['name'] );
				wp_safe_redirect( wp_login_url( $here ) );
				exit;
			}
		}

		$range = isset( $_SERVER['HTTP_RANGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) ) : '';
		$head  = isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );

		// Podcast apps fetch in byte ranges: count a listen only for the first one.
		$first = '' === $range || (bool) preg_match( '/^bytes=0-/', $range );
		if ( 'stream' !== $mode && ! $head && $first && Counters::should_count( $id ) ) {
			Counters::record_download( $id, get_current_user_id() );
			Episode::forget( $id );
		}

		/**
		 * Fires before an episode file is sent or redirected to.
		 *
		 * @param int    $id     Episode ID.
		 * @param string $mode   download | listen | stream.
		 * @param array  $source Where the audio lives.
		 */
		do_action( 'sepo_podcast_before_serve', $id, $mode, $source );

		if ( '' === $source['path'] ) {
			self::redirect( (string) $source['url'] );
		}

		$name = sanitize_file_name( '' !== $source['name'] ? $source['name'] : 'episode-' . $id . '.mp3' );
		self::send_file( $source['path'], $source['mime'], $name, 'download' === $mode, 'private' === $source['kind'], $range, $head );
	}

	/**
	 * Send a file with byte-range support.
	 *
	 * @param string $path       Absolute path.
	 * @param string $mime       MIME type.
	 * @param string $name       File name for the browser.
	 * @param bool   $attachment Ask the browser to save it.
	 * @param bool   $private    Do not let shared caches keep it.
	 * @param string $range      Range header.
	 * @param bool   $head       HEAD request: headers only.
	 */
	private static function send_file( string $path, string $mime, string $name, bool $attachment, bool $private, string $range, bool $head ): void {
		$size  = (int) filesize( $path );
		$start = 0;
		$end   = $size - 1;
		$code  = 200;

		if ( '' !== $range && $size > 0 ) {
			if ( ! preg_match( '/^bytes=(\d*)-(\d*)$/', trim( $range ), $m ) || ( '' === $m[1] && '' === $m[2] ) ) {
				self::range_not_satisfiable( $size );
			}
			if ( '' === $m[1] ) {
				$start = max( 0, $size - (int) $m[2] );
			} else {
				$start = (int) $m[1];
				$end   = '' === $m[2] ? $end : min( $end, (int) $m[2] );
			}
			if ( $start > $end || $start >= $size ) {
				self::range_not_satisfiable( $size );
			}
			$code = 206;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- long audio files.
		}
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		status_header( $code );
		if ( $private ) {
			nocache_headers();
		}
		header( 'Content-Type: ' . $mime );
		header( 'Accept-Ranges: bytes' );
		header( 'Content-Length: ' . ( $end - $start + 1 ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: ' . ( $private ? 'private, no-store' : 'private, max-age=3600' ) );
		header( 'Content-Disposition: ' . ( $attachment ? 'attachment' : 'inline' ) . '; filename="' . str_replace( '"', '', $name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
		if ( 206 === $code ) {
			header( sprintf( 'Content-Range: bytes %d-%d/%d', $start, $end, $size ) );
		}
		if ( $head || $size <= 0 ) {
			return;
		}

		// phpcs:disable WordPress.WP.AlternativeFunctions -- streaming a large file in chunks; WP_Filesystem would load it all into memory.
		$fh = fopen( $path, 'rb' );
		if ( ! $fh ) {
			return;
		}
		fseek( $fh, $start );
		$left = $end - $start + 1;
		while ( $left > 0 && ! feof( $fh ) && ! connection_aborted() ) {
			$chunk = fread( $fh, (int) min( 65536, $left ) );
			if ( false === $chunk || '' === $chunk ) {
				break;
			}
			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary file contents.
			$left -= strlen( $chunk );
			flush();
		}
		fclose( $fh );
		// phpcs:enable
	}

	/**
	 * 416 response.
	 *
	 * @param int $size File size.
	 */
	private static function range_not_satisfiable( int $size ): void {
		status_header( 416 );
		header( 'Content-Range: bytes */' . $size );
		exit;
	}

	/**
	 * Redirect to where the file really lives (Media Library or another host).
	 *
	 * @param string $url Address.
	 */
	private static function redirect( string $url ): void {
		if ( '' === $url ) {
			self::fail( 404, __( 'This episode has no audio file.', 'sepo-podcast' ) );
		}
		wp_redirect( $url, 302, 'Sepo Podcast' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- audio may be hosted on another domain, set by an editor.
		exit;
	}

	/**
	 * Stop with an error page.
	 *
	 * @param int    $code    HTTP status.
	 * @param string $message Message.
	 */
	private static function fail( int $code, string $message ): void {
		wp_die( esc_html( $message ), esc_html__( 'Podcast file', 'sepo-podcast' ), array( 'response' => (int) $code ) );
	}
}
