<?php
/**
 * One episode, read from its post and meta into a plain array.
 *
 * @package SepoPodcast
 */

namespace SepoPodcast;

defined( 'ABSPATH' ) || exit;

/**
 * Episode data for templates, REST and the feed.
 */
final class Episode {

	/**
	 * Audio types the plugin knows, by file extension.
	 */
	const MIMES = array(
		'mp3'  => 'audio/mpeg',
		'm4a'  => 'audio/mp4',
		'mp4'  => 'audio/mp4',
		'aac'  => 'audio/aac',
		'ogg'  => 'audio/ogg',
		'oga'  => 'audio/ogg',
		'opus' => 'audio/ogg',
		'wav'  => 'audio/wav',
		'flac' => 'audio/flac',
		'webm' => 'audio/webm',
	);

	/**
	 * Per-request cache.
	 *
	 * @var array<int,array>
	 */
	private static $cache = array();

	/**
	 * Episode data, or null when the post is not an episode.
	 *
	 * @param int|\WP_Post $post Post or ID.
	 */
	public static function get( $post ): ?array {
		$post = get_post( $post );
		if ( ! $post instanceof \WP_Post || Post_Type::TYPE !== $post->post_type ) {
			return null;
		}
		$id = (int) $post->ID;
		if ( isset( self::$cache[ $id ] ) ) {
			return self::$cache[ $id ];
		}

		$m = static function ( string $key ) use ( $id ) {
			return get_post_meta( $id, $key, true );
		};

		$source = self::source( $id );
		$image  = get_the_post_thumbnail_url( $id, 'medium_large' );
		if ( ! $image ) {
			$image = (string) $m( '_sepo_image_url' );
		}

		$series = get_the_terms( $id, Post_Type::SERIES );
		$tags   = get_the_terms( $id, Post_Type::TAG );

		// Unset means "allowed": the registered default is true.
		$allow    = metadata_exists( 'post', $id, '_sepo_allow_download' ) ? Post_Type::to_bool( $m( '_sepo_allow_download' ) ) : true;
		$gated    = Post_Type::to_bool( $m( '_sepo_requires_login' ) );
		$has_file = '' !== $source['kind'];

		$data = array(
			'id'             => $id,
			'title'          => get_the_title( $post ),
			'url'            => get_permalink( $post ),
			'excerpt'        => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30 ),
			'date'           => get_the_date( '', $post ),
			'date_iso'       => get_post_time( 'c', true, $post ),
			'image'          => $image ? $image : '',
			'duration'       => (string) $m( '_sepo_duration' ),
			'episode'        => (string) $m( '_sepo_episode' ),
			'season'         => (string) $m( '_sepo_season' ),
			'episode_type'   => (string) ( $m( '_sepo_episode_type' ) ? $m( '_sepo_episode_type' ) : 'full' ),
			'host'           => (string) $m( '_sepo_host' ),
			'guest'          => (string) $m( '_sepo_guest' ),
			'explicit'       => Post_Type::to_bool( $m( '_sepo_explicit' ) ),
			'allow_download' => $allow && $has_file,
			'requires_login' => $gated,
			'protected'      => 'private' === $source['kind'],
			'has_audio'      => $has_file,
			'src'            => $has_file ? self::play_url( $id, $source ) : '',
			'download_url'   => $has_file ? Download::url( $id, 'download', $source['name'] ) : '',
			'mime'           => $source['mime'],
			'series'         => is_array( $series ) ? array_map( array( __CLASS__, 'term' ), $series ) : array(),
			'tags'           => is_array( $tags ) ? array_map( array( __CLASS__, 'term' ), $tags ) : array(),
			'likes'          => Counters::likes( $id ),
			'downloads'      => Counters::downloads( $id ),
		);

		/**
		 * Filter the episode data used by templates, REST and the feed.
		 *
		 * @param array    $data Episode data.
		 * @param \WP_Post $post The episode post.
		 */
		$data = (array) apply_filters( 'sepo_podcast_episode_data', $data, $post );

		self::$cache[ $id ] = $data;
		return $data;
	}

	/**
	 * Forget cached data (after counters change).
	 *
	 * @param int $id Episode ID.
	 */
	public static function forget( int $id ): void {
		unset( self::$cache[ $id ] );
	}

	/**
	 * Where the audio lives.
	 *
	 * kind is one of: private (protected folder), media (attachment),
	 * external (a URL), or '' when the episode has no audio yet.
	 *
	 * @param int $id Episode ID.
	 * @return array{kind:string,path:string,url:string,mime:string,name:string,length:int}
	 */
	public static function source( int $id ): array {
		$out = array(
			'kind'   => '',
			'path'   => '',
			'url'    => '',
			'mime'   => '',
			'name'   => '',
			'length' => 0,
		);

		$private = (string) get_post_meta( $id, '_sepo_private_file', true );
		if ( Post_Type::to_bool( get_post_meta( $id, '_sepo_protected', true ) ) && '' !== $private ) {
			$path = Private_Files::path( $private );
			if ( $path ) {
				$out['kind']   = 'private';
				$out['path']   = $path;
				$out['name']   = wp_basename( $path );
				$out['mime']   = self::mime_for( $path );
				$out['length'] = (int) filesize( $path );
				return $out;
			}
		}

		$att = (int) get_post_meta( $id, '_sepo_audio_id', true );
		if ( $att && 'attachment' === get_post_type( $att ) ) {
			$path        = (string) get_attached_file( $att );
			$out['kind'] = 'media';
			$out['url']  = (string) wp_get_attachment_url( $att );
			$out['path'] = ( $path && is_readable( $path ) ) ? $path : '';
			$out['name'] = wp_basename( $path ? $path : $out['url'] );
			$mime        = (string) get_post_mime_type( $att );
			$out['mime'] = $mime ? $mime : self::mime_for( $out['name'] );
			if ( $out['path'] ) {
				$out['length'] = (int) filesize( $out['path'] );
			} else {
				$meta          = wp_get_attachment_metadata( $att );
				$out['length'] = is_array( $meta ) && isset( $meta['filesize'] ) ? (int) $meta['filesize'] : 0;
			}
			return $out;
		}

		$url = (string) get_post_meta( $id, '_sepo_audio_url', true );
		if ( '' !== $url ) {
			$out['kind'] = 'external';
			$out['url']  = $url;
			$out['name'] = wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
			$out['mime'] = self::mime_for( $out['name'] );
		}
		return $out;
	}

	/**
	 * Address the player uses.
	 *
	 * Media Library and external files play straight from their own address.
	 * Protected files play through a signed stream address that expires, so
	 * a copied link stops working.
	 *
	 * @param int   $id     Episode ID.
	 * @param array $source Result of source().
	 */
	public static function play_url( int $id, array $source ): string {
		if ( 'private' === $source['kind'] ) {
			return Download::signed_stream_url( $id, $source['name'] );
		}
		return (string) $source['url'];
	}

	/**
	 * MIME type from a file name.
	 *
	 * @param string $file File name or path.
	 */
	public static function mime_for( string $file ): string {
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		return self::MIMES[ $ext ] ?? 'audio/mpeg';
	}

	/**
	 * Duration text (mm:ss, hh:mm:ss or seconds) to seconds.
	 *
	 * @param string $text Duration.
	 */
	public static function seconds( string $text ): int {
		$text = trim( $text );
		if ( '' === $text ) {
			return 0;
		}
		if ( ctype_digit( $text ) ) {
			return (int) $text;
		}
		$parts = array_reverse( array_map( 'intval', explode( ':', $text ) ) );
		$secs  = 0;
		foreach ( $parts as $i => $n ) {
			$secs += $n * ( 60 ** min( $i, 2 ) );
		}
		return $secs;
	}

	/**
	 * Term to a small array.
	 *
	 * @param \WP_Term $t Term.
	 */
	private static function term( $t ): array {
		return array(
			'id'   => (int) $t->term_id,
			'name' => $t->name,
			'slug' => $t->slug,
		);
	}
}
