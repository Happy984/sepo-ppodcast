<?php
/**
 * Personal data: export, erase, and the suggested privacy policy text.
 *
 * @package SepoPodcast
 */

namespace SepoPodcast;

defined( 'ABSPATH' ) || exit;

/**
 * Privacy tools integration.
 */
final class Privacy {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $list Exporters.
	 */
	public static function exporters( $list ): array {
		$list['sepo-podcast'] = array(
			'exporter_friendly_name' => __( 'Podcast likes and downloads', 'sepo-podcast' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $list;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $list Erasers.
	 */
	public static function erasers( $list ): array {
		$list['sepo-podcast'] = array(
			'eraser_friendly_name' => __( 'Podcast likes and downloads', 'sepo-podcast' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $list;
	}

	/**
	 * Export a user's likes and downloads.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page (all data fits on one).
	 */
	public static function export( $email, $page = 1 ): array {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}
		$data  = Counters::user_data( (int) $user->ID );
		$items = array();
		foreach ( $data['likes'] as $row ) {
			$items[] = array(
				'group_id'    => 'sepo-podcast-likes',
				'group_label' => __( 'Podcast likes', 'sepo-podcast' ),
				'item_id'     => 'sepo-like-' . (int) $row['episode_id'],
				'data'        => array(
					array(
						'name'  => __( 'Episode', 'sepo-podcast' ),
						'value' => get_the_title( (int) $row['episode_id'] ),
					),
					array(
						'name'  => __( 'Liked on', 'sepo-podcast' ),
						'value' => (string) $row['created_at'],
					),
				),
			);
		}
		foreach ( $data['downloads'] as $row ) {
			$items[] = array(
				'group_id'    => 'sepo-podcast-downloads',
				'group_label' => __( 'Podcast downloads', 'sepo-podcast' ),
				'item_id'     => 'sepo-download-' . (int) $row['episode_id'],
				'data'        => array(
					array(
						'name'  => __( 'Episode', 'sepo-podcast' ),
						'value' => get_the_title( (int) $row['episode_id'] ),
					),
					array(
						'name'  => __( 'Times downloaded', 'sepo-podcast' ),
						'value' => (string) (int) $row['downloads'],
					),
					array(
						'name'  => __( 'First download', 'sepo-podcast' ),
						'value' => (string) $row['first_at'],
					),
					array(
						'name'  => __( 'Last download', 'sepo-podcast' ),
						'value' => (string) $row['last_at'],
					),
				),
			);
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Erase a user's likes and unlink their downloads.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page.
	 */
	public static function erase( $email, $page = 1 ): array {
		$user    = get_user_by( 'email', $email );
		$removed = $user ? Counters::erase_user( (int) $user->ID ) : 0;
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => $removed > 0 ? array( __( 'Podcast likes were removed and downloads were made anonymous (totals are kept).', 'sepo-podcast' ) ) : array(),
			'done'           => true,
		);
	}

	/**
	 * Suggested text for the privacy policy page.
	 */
	public static function policy(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text = '<p>' . esc_html__( 'When you are signed in and like a podcast episode, we store which episode you liked and when. When you download an episode while signed in, we store how many times you downloaded it and when. Downloads by visitors who are not signed in are counted without storing who they are.', 'sepo-podcast' ) . '</p>'
			. '<p>' . esc_html__( 'To stop one person being counted many times in a row, a short-lived marker made from a one-way hash of the IP address and browser is kept for about a minute. It is not stored with the download count.', 'sepo-podcast' ) . '</p>'
			. '<p>' . esc_html__( 'The player remembers where you stopped listening in your own browser (local storage). It is not sent to us.', 'sepo-podcast' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Sepo Podcast', 'sepo-podcast' ), wp_kses_post( $text ) );
	}
}
