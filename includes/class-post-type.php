<?php
/**
 * The Episode post type, the Series and Episode tag taxonomies, and episode meta.
 *
 * @package SepoPodcast
 */

namespace SepoPodcast;

defined( 'ABSPATH' ) || exit;

/**
 * Registers content types and meta.
 */
final class Post_Type {

	const TYPE   = 'sepo_episode';
	const SERIES = 'sepo_series';
	const TAG    = 'sepo_episode_tag';

	/**
	 * Episode meta: key => [type, default, description]. All keys are
	 * underscore-prefixed so they stay out of the Custom Fields box.
	 */
	public static function meta_fields(): array {
		return array(
			'_sepo_audio_id'       => array( 'integer', 0, 'Media Library attachment holding the audio.' ),
			'_sepo_audio_url'      => array( 'string', '', 'External audio address (used when there is no attachment).' ),
			'_sepo_private_file'   => array( 'string', '', 'File name in the protected folder.' ),
			'_sepo_protected'      => array( 'boolean', false, 'Serve the audio from the protected folder.' ),
			'_sepo_duration'       => array( 'string', '', 'Length, e.g. 32:10.' ),
			'_sepo_episode'        => array( 'string', '', 'Episode number.' ),
			'_sepo_season'         => array( 'string', '', 'Season number.' ),
			'_sepo_episode_type'   => array( 'string', 'full', 'full, trailer or bonus.' ),
			'_sepo_host'           => array( 'string', '', 'Host.' ),
			'_sepo_guest'          => array( 'string', '', 'Guest.' ),
			'_sepo_explicit'       => array( 'boolean', false, 'Explicit content.' ),
			'_sepo_allow_download' => array( 'boolean', true, 'Show a download button.' ),
			'_sepo_requires_login' => array( 'boolean', false, 'Only signed-in visitors may download.' ),
			'_sepo_image_url'      => array( 'string', '', 'External artwork address (used when there is no featured image).' ),
		);
	}

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the post type, taxonomies and meta.
	 */
	public static function register(): void {
		$slug = sanitize_title( (string) Settings::get( 'slug' ) );
		$slug = '' !== $slug ? $slug : 'podcast';

		register_post_type(
			self::TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Episodes', 'sepo-podcast' ),
					'singular_name'      => __( 'Episode', 'sepo-podcast' ),
					'menu_name'          => __( 'Podcast', 'sepo-podcast' ),
					'add_new'            => __( 'Add episode', 'sepo-podcast' ),
					'add_new_item'       => __( 'Add new episode', 'sepo-podcast' ),
					'edit_item'          => __( 'Edit episode', 'sepo-podcast' ),
					'new_item'           => __( 'New episode', 'sepo-podcast' ),
					'view_item'          => __( 'View episode', 'sepo-podcast' ),
					'search_items'       => __( 'Search episodes', 'sepo-podcast' ),
					'not_found'          => __( 'No episodes found.', 'sepo-podcast' ),
					'not_found_in_trash' => __( 'No episodes in the trash.', 'sepo-podcast' ),
					'all_items'          => __( 'All episodes', 'sepo-podcast' ),
					'featured_image'     => __( 'Episode artwork', 'sepo-podcast' ),
					'set_featured_image' => __( 'Set episode artwork', 'sepo-podcast' ),
				),
				'public'          => true,
				'has_archive'     => false,
				'show_in_rest'    => true,
				'menu_icon'       => 'dashicons-microphone',
				'menu_position'   => 21,
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'author', 'revisions', 'comments', 'custom-fields' ),
				'rewrite'         => array(
					'slug'       => $slug,
					'with_front' => false,
				),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);

		register_taxonomy(
			self::SERIES,
			self::TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Series', 'sepo-podcast' ),
					'singular_name' => __( 'Series', 'sepo-podcast' ),
					'add_new_item'  => __( 'Add new series', 'sepo-podcast' ),
					'edit_item'     => __( 'Edit series', 'sepo-podcast' ),
					'search_items'  => __( 'Search series', 'sepo-podcast' ),
					'all_items'     => __( 'All series', 'sepo-podcast' ),
				),
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => $slug . '-series' ),
			)
		);

		register_taxonomy(
			self::TAG,
			self::TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Episode tags', 'sepo-podcast' ),
					'singular_name' => __( 'Episode tag', 'sepo-podcast' ),
				),
				'hierarchical'      => false,
				'show_in_rest'      => true,
				'show_admin_column' => false,
				'rewrite'           => array( 'slug' => $slug . '-tag' ),
			)
		);

		register_term_meta(
			self::SERIES,
			'sepo_order',
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => static function ( $value ) {
						return (int) $value;
					},
			)
		);

		foreach ( self::meta_fields() as $key => $def ) {
			register_post_meta(
				self::TYPE,
				$key,
				array(
					'type'              => $def[0],
					'single'            => true,
					'default'           => $def[1],
					'description'       => $def[2],
					'show_in_rest'      => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_meta' ),
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}

		// Read-only counters, readable in REST for themes and apps.
		foreach ( array( '_sepo_like_count', '_sepo_download_count' ) as $counter ) {
			register_post_meta(
				self::TYPE,
				$counter,
				array(
					'type'          => 'integer',
					'single'        => true,
					'default'       => 0,
					'show_in_rest'  => true,
					'auth_callback' => '__return_false',
				)
			);
		}
	}

	/**
	 * Sanitize one meta value by its declared type.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $meta_key Meta key.
	 * @return mixed
	 */
	public static function sanitize_meta( $value, $meta_key = '' ) {
		$fields = self::meta_fields();
		$type   = $fields[ $meta_key ][0] ?? 'string';
		switch ( $type ) {
			case 'integer':
				return absint( $value );
			case 'boolean':
				return self::to_bool( $value );
		}
		if ( '_sepo_audio_url' === $meta_key || '_sepo_image_url' === $meta_key ) {
			return esc_url_raw( (string) $value, array( 'http', 'https' ) );
		}
		if ( '_sepo_episode_type' === $meta_key ) {
			return in_array( $value, array( 'full', 'trailer', 'bonus' ), true ) ? $value : 'full';
		}
		if ( '_sepo_private_file' === $meta_key ) {
			return sanitize_file_name( wp_basename( (string) $value ) );
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Read a flag the way people write it: "false", "off", "no", "0" and "" are false.
	 *
	 * @param mixed $value Any value.
	 */
	public static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return 0 !== (int) $value;
		}
		$s = strtolower( trim( (string) $value ) );
		return '' !== $s && ! in_array( $s, array( '0', 'false', 'no', 'off', 'n' ), true );
	}
}
