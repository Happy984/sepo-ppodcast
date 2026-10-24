<?php
/**
 * Likes and download counts.
 *
 * Two small tables hold who liked and who downloaded what. The totals are
 * also cached in post meta (_sepo_like_count, _sepo_download_count) so lists
 * and sorting never have to count rows.
 *
 * @package SepoPodcast
 */

namespace SepoPodcast;

defined( 'ABSPATH' ) || exit;

/**
 * Like and download storage.
 */
final class Counters {

	const DB_VERSION = '1';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		// Plugin updates do not re-run activation, so check the schema here.
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete_post' ) );
		add_action( 'deleted_user', array( __CLASS__, 'on_delete_user' ) );
	}

	/**
	 * Table name for likes.
	 */
	public static function likes_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'sepo_podcast_likes';
	}

	/**
	 * Table name for downloads.
	 */
	public static function downloads_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'sepo_podcast_downloads';
	}

	/**
	 * Create or update the tables.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$likes   = self::likes_table();
		$dls     = self::downloads_table();

		dbDelta(
			"CREATE TABLE {$likes} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				episode_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY episode_user (episode_id,user_id),
				KEY user_id (user_id)
			) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$dls} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				episode_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				downloads int(10) unsigned NOT NULL DEFAULT 0,
				first_at datetime NOT NULL,
				last_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY episode_user (episode_id,user_id),
				KEY user_id (user_id)
			) {$charset};"
		);
		update_option( 'sepo_podcast_db_version', self::DB_VERSION, false );
	}

	/**
	 * Run install() when the stored schema version is older.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( 'sepo_podcast_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/* ------------------------------------------------------------- likes */

	/**
	 * Like or unlike. Returns the new state and total.
	 *
	 * @param int $episode_id Episode post ID.
	 * @param int $user_id    User ID (must be a signed-in user).
	 * @return array{liked:bool,count:int}
	 */
	public static function toggle_like( int $episode_id, int $user_id ): array {
		global $wpdb;
		$table = self::likes_table();
		// The unique key makes a double click harmless: the second insert is ignored.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (episode_id, user_id, created_at) VALUES (%d, %d, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$episode_id,
				$user_id,
				current_time( 'mysql', true )
			)
		);
		$liked = (int) $inserted > 0;
		if ( ! $liked ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete(
				$table,
				array(
					'episode_id' => $episode_id,
					'user_id'    => $user_id,
				),
				array( '%d', '%d' )
			);
		}
		return array(
			'liked' => $liked,
			'count' => self::refresh_like_count( $episode_id ),
		);
	}

	/**
	 * Recount likes for an episode and cache the total in post meta.
	 *
	 * @param int $episode_id Episode post ID.
	 */
	public static function refresh_like_count( int $episode_id ): int {
		global $wpdb;
		$table = self::likes_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$n     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE episode_id = %d", $episode_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = $n + (int) get_post_meta( $episode_id, '_sepo_like_offset', true );
		update_post_meta( $episode_id, '_sepo_like_count', $total );
		return $total;
	}

	/**
	 * Cached like total.
	 *
	 * @param int $episode_id Episode post ID.
	 */
	public static function likes( int $episode_id ): int {
		return (int) get_post_meta( $episode_id, '_sepo_like_count', true );
	}

	/**
	 * Did this user like this episode?
	 *
	 * @param int $episode_id Episode post ID.
	 * @param int $user_id    User ID.
	 */
	public static function has_liked( int $episode_id, int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		return in_array( $episode_id, self::liked_ids( $user_id, array( $episode_id ) ), true );
	}

	/**
	 * Which of these episodes has the user liked? One query for a whole list.
	 *
	 * @param int   $user_id     User ID.
	 * @param int[] $episode_ids Episode IDs.
	 * @return int[]
	 */
	public static function liked_ids( int $user_id, array $episode_ids ): array {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $episode_ids ) ) );
		if ( $user_id <= 0 || ! $ids ) {
			return array();
		}
		$table        = self::likes_table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT episode_id FROM {$table} WHERE user_id = %d AND episode_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				array_merge( array( $user_id ), $ids )
			)
		);
		return array_map( 'intval', (array) $rows );
	}

	/* --------------------------------------------------------- downloads */

	/**
	 * Count one download. Guests share user_id 0.
	 *
	 * @param int $episode_id Episode post ID.
	 * @param int $user_id    User ID, or 0.
	 */
	public static function record_download( int $episode_id, int $user_id ): int {
		global $wpdb;
		$table = self::downloads_table();
		$now   = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (episode_id, user_id, downloads, first_at, last_at) VALUES (%d, %d, 1, %s, %s) ON DUPLICATE KEY UPDATE downloads = downloads + 1, last_at = VALUES(last_at)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$episode_id,
				max( 0, $user_id ),
				$now,
				$now
			)
		);
		return self::refresh_download_count( $episode_id );
	}

	/**
	 * Recount downloads for an episode and cache the total in post meta.
	 *
	 * @param int $episode_id Episode post ID.
	 */
	public static function refresh_download_count( int $episode_id ): int {
		global $wpdb;
		$table = self::downloads_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$n     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(downloads),0) FROM {$table} WHERE episode_id = %d", $episode_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = $n + (int) get_post_meta( $episode_id, '_sepo_download_offset', true );
		update_post_meta( $episode_id, '_sepo_download_count', $total );
		return $total;
	}

	/**
	 * Cached download total.
	 *
	 * @param int $episode_id Episode post ID.
	 */
	public static function downloads( int $episode_id ): int {
		return (int) get_post_meta( $episode_id, '_sepo_download_count', true );
	}

	/**
	 * Has this visitor been counted for this episode in the last few seconds?
	 * WordPress has no PHP session, so the window is a short-lived transient
	 * keyed on the user (or a hash of the IP address and browser for guests).
	 *
	 * @param int $episode_id Episode post ID.
	 */
	public static function should_count( int $episode_id ): bool {
		$window = (int) Settings::get( 'count_window' );
		if ( $window <= 0 ) {
			return true;
		}
		$user = get_current_user_id();
		$who  = $user ? 'u' . $user : 'g' . wp_hash( ( $_SERVER['REMOTE_ADDR'] ?? '' ) . '|' . ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- hashed, never output.
		$key  = 'sepo_pdl_' . md5( $episode_id . '|' . $who );
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, $window );
		return true;
	}

	/* ------------------------------------------------------------- reset */

	/**
	 * Clear likes, downloads or both for an episode (including imported totals).
	 *
	 * @param int    $episode_id Episode post ID.
	 * @param string $what       likes | downloads | all.
	 */
	public static function reset( int $episode_id, string $what ): void {
		global $wpdb;
		if ( 'likes' === $what || 'all' === $what ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( self::likes_table(), array( 'episode_id' => $episode_id ), array( '%d' ) );
			delete_post_meta( $episode_id, '_sepo_like_offset' );
			self::refresh_like_count( $episode_id );
		}
		if ( 'downloads' === $what || 'all' === $what ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( self::downloads_table(), array( 'episode_id' => $episode_id ), array( '%d' ) );
			delete_post_meta( $episode_id, '_sepo_download_offset' );
			self::refresh_download_count( $episode_id );
		}
	}

	/**
	 * Remove an episode's rows when the episode is deleted for good.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_delete_post( $post_id ): void {
		global $wpdb;
		if ( Post_Type::TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( self::likes_table(), array( 'episode_id' => (int) $post_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( self::downloads_table(), array( 'episode_id' => (int) $post_id ), array( '%d' ) );
	}

	/**
	 * Remove a deleted user's likes (their downloads become guest downloads).
	 *
	 * @param int $user_id User ID.
	 */
	public static function on_delete_user( $user_id ): void {
		self::erase_user( (int) $user_id );
	}

	/* ----------------------------------------------------------- privacy */

	/**
	 * Everything stored about a user.
	 *
	 * @param int $user_id User ID.
	 * @return array{likes:array,downloads:array}
	 */
	public static function user_data( int $user_id ): array {
		global $wpdb;
		$likes = self::likes_table();
		$dls   = self::downloads_table();
		return array(
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'likes'     => (array) $wpdb->get_results( $wpdb->prepare( "SELECT episode_id, created_at FROM {$likes} WHERE user_id = %d", $user_id ), ARRAY_A ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'downloads' => (array) $wpdb->get_results( $wpdb->prepare( "SELECT episode_id, downloads, first_at, last_at FROM {$dls} WHERE user_id = %d", $user_id ), ARRAY_A ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Remove a user's likes and turn their downloads into guest downloads
	 * (the totals stay correct, the link to the person is gone).
	 *
	 * @param int $user_id User ID.
	 * @return int Rows changed.
	 */
	public static function erase_user( int $user_id ): int {
		global $wpdb;
		if ( $user_id <= 0 ) {
			return 0;
		}
		$data    = self::user_data( $user_id );
		$touched = array();
		foreach ( $data['likes'] as $row ) {
			$touched[ (int) $row['episode_id'] ] = true;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$changed = (int) $wpdb->delete( self::likes_table(), array( 'user_id' => $user_id ), array( '%d' ) );

		$dls = self::downloads_table();
		foreach ( $data['downloads'] as $row ) {
			$ep = (int) $row['episode_id'];
			$n  = (int) $row['downloads'];
			$at = (string) $row['last_at'];
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$dls} (episode_id, user_id, downloads, first_at, last_at) VALUES (%d, 0, %d, %s, %s) ON DUPLICATE KEY UPDATE downloads = downloads + VALUES(downloads)", $ep, $n, $at, $at ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			++$changed;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $dls, array( 'user_id' => $user_id ), array( '%d' ) );

		foreach ( array_keys( $touched ) as $ep ) {
			self::refresh_like_count( $ep );
		}
		return $changed;
	}
}
