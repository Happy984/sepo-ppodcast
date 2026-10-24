<?php
/**
 * REST routes under sepo-podcast/v1.
 *
 * GET  /episodes/{id}/stats  – likes, downloads, and whether you liked it.
 * POST /episodes/{id}/like   – like or unlike (signed-in users).
 * POST /episodes/{id}/reset  – clear counts (editors).
 *
 * Downloads are counted by the file address itself, not by REST, so they
 * cannot be inflated without actually fetching the file.
 *
 * @package SepoPodcast
 */

namespace SepoPodcast;

defined( 'ABSPATH' ) || exit;

/**
 * REST API.
 */
final class Rest {

	const NS = 'sepo-podcast/v1';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Register routes.
	 */
	public static function routes(): void {
		$id_arg = array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		);

		register_rest_route(
			self::NS,
			'/episodes/(?P<id>\d+)/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'stats' ),
				'permission_callback' => array( __CLASS__, 'can_view' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NS,
			'/episodes/(?P<id>\d+)/like',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'like' ),
				'permission_callback' => array( __CLASS__, 'can_like' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::NS,
			'/episodes/(?P<id>\d+)/reset',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'reset' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => $id_arg + array(
					'what' => array(
						'type'    => 'string',
						'enum'    => array( 'likes', 'downloads', 'all' ),
						'default' => 'all',
					),
				),
			)
		);
	}

	/**
	 * The episode may be seen by the current visitor.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public static function can_view( $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || Post_Type::TYPE !== $post->post_type ) {
			return new \WP_Error( 'sepo_podcast_not_found', __( 'Episode not found.', 'sepo-podcast' ), array( 'status' => 404 ) );
		}
		if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post->ID ) ) {
			return new \WP_Error( 'sepo_podcast_not_found', __( 'Episode not found.', 'sepo-podcast' ), array( 'status' => 404 ) );
		}
		if ( post_password_required( $post ) ) {
			return new \WP_Error( 'sepo_podcast_password', __( 'This episode is password protected.', 'sepo-podcast' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Signed-in visitors may like episodes they can see.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public static function can_like( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'sepo_podcast_login', __( 'Sign in to like episodes.', 'sepo-podcast' ), array( 'status' => 401 ) );
		}
		return self::can_view( $request );
	}

	/**
	 * Editors of the episode may reset its counts.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function can_edit( $request ): bool {
		return current_user_can( 'edit_post', (int) $request['id'] );
	}

	/**
	 * GET stats.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function stats( $request ): \WP_REST_Response {
		$id   = (int) $request['id'];
		$user = get_current_user_id();
		return rest_ensure_response(
			array(
				'id'        => $id,
				'likes'     => Counters::likes( $id ),
				'downloads' => Counters::downloads( $id ),
				'liked'     => Counters::has_liked( $id, $user ),
			)
		);
	}

	/**
	 * POST like (toggle).
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function like( $request ): \WP_REST_Response {
		$id     = (int) $request['id'];
		$result = Counters::toggle_like( $id, get_current_user_id() );
		Episode::forget( $id );
		return rest_ensure_response(
			array(
				'id'    => $id,
				'liked' => $result['liked'],
				'likes' => $result['count'],
			)
		);
	}

	/**
	 * POST reset.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function reset( $request ): \WP_REST_Response {
		$id = (int) $request['id'];
		Counters::reset( $id, (string) $request['what'] );
		Episode::forget( $id );
		return rest_ensure_response(
			array(
				'id'        => $id,
				'likes'     => Counters::likes( $id ),
				'downloads' => Counters::downloads( $id ),
			)
		);
	}
}
