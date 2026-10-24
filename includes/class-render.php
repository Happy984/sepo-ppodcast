<?php
/**
 * Front end: the episode page, the [sepo_podcast] list, the block, assets.
 *
 * All markup lives in templates/. A theme can copy any of them into
 * its own sepo-podcast/ folder to change it.
 *
 * @package SepoPodcast
 */

namespace SepoPodcast;

defined( 'ABSPATH' ) || exit;

/**
 * Rendering.
 */
final class Render {

	const HANDLE = 'sepo-podcast';

	/**
	 * Guard against the_content running inside itself.
	 *
	 * @var bool
	 */
	private static $busy = false;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
		add_filter( 'the_content', array( __CLASS__, 'episode_content' ), 20 );
		add_shortcode( 'sepo_podcast', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Register assets and the block.
	 */
	public static function register(): void {
		wp_register_style( self::HANDLE, SEPO_PODCAST_URL . 'assets/css/podcast.css', array(), SEPO_PODCAST_VERSION );
		wp_register_script( self::HANDLE, SEPO_PODCAST_URL . 'assets/js/podcast.js', array( 'wp-i18n' ), SEPO_PODCAST_VERSION, true );
		wp_set_script_translations( self::HANDLE, 'sepo-podcast', SEPO_PODCAST_DIR . 'languages' );

		register_block_type(
			SEPO_PODCAST_DIR . 'blocks/episodes',
			array(
				'render_callback' => array( __CLASS__, 'block' ),
			)
		);
	}

	/**
	 * Load assets on pages that will show podcast content.
	 */
	public static function maybe_enqueue(): void {
		if ( is_singular( Post_Type::TYPE ) ) {
			self::enqueue();
			return;
		}
		$post = get_post();
		if ( is_singular() && $post && ( has_shortcode( $post->post_content, 'sepo_podcast' ) || has_block( 'sepo-podcast/episodes', $post ) ) ) {
			self::enqueue();
		}
	}

	/**
	 * Enqueue assets (once) with settings for the script.
	 */
	public static function enqueue(): void {
		if ( wp_script_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}
		if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
			self::register_assets_only();
		}
		wp_enqueue_style( self::HANDLE );
		$accent = sanitize_hex_color( (string) Settings::get( 'accent' ) );
		if ( $accent ) {
			wp_add_inline_style( self::HANDLE, '.sepo-podcast,.sp-dock{--sp-accent:' . $accent . ';}' );
		}

		wp_enqueue_script( self::HANDLE );
		$config = array(
			'root'     => esc_url_raw( rest_url( Rest::NS . '/' ) ),
			'nonce'    => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'loggedIn' => is_user_logged_in(),
			'loginUrl' => wp_login_url( self::current_url() ),
		);
		wp_add_inline_script( self::HANDLE, 'window.SepoPodcast = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Register only the front-end assets (when enqueue() runs before init).
	 */
	private static function register_assets_only(): void {
		wp_register_style( self::HANDLE, SEPO_PODCAST_URL . 'assets/css/podcast.css', array(), SEPO_PODCAST_VERSION );
		wp_register_script( self::HANDLE, SEPO_PODCAST_URL . 'assets/js/podcast.js', array( 'wp-i18n' ), SEPO_PODCAST_VERSION, true );
	}

	/**
	 * Address of the page being viewed.
	 */
	public static function current_url(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return home_url( $uri );
	}

	/**
	 * Render a template, preferring the theme's copy.
	 *
	 * @param string $name Template name without .php.
	 * @param array  $args Variables for the template.
	 */
	public static function template( string $name, array $args = array() ): string {
		$name = sanitize_key( $name );
		$file = locate_template( array( 'sepo-podcast/' . $name . '.php' ) );
		if ( ! $file ) {
			$file = SEPO_PODCAST_DIR . 'templates/' . $name . '.php';
		}
		/**
		 * Filter the template file used for a part of the podcast output.
		 *
		 * @param string $file Absolute path.
		 * @param string $name Template name.
		 * @param array  $args Template variables.
		 */
		$file = (string) apply_filters( 'sepo_podcast_template', $file, $name, $args );
		if ( ! is_readable( $file ) ) {
			return '';
		}
		ob_start();
		load_template( $file, false, $args );
		return (string) ob_get_clean();
	}

	/**
	 * Player markup for an episode.
	 *
	 * @param array  $ep      Episode data.
	 * @param string $context single | card.
	 */
	public static function player( array $ep, string $context = 'single' ): string {
		if ( empty( $ep['has_audio'] ) ) {
			return '';
		}
		return self::template(
			'player',
			array(
				'episode' => $ep,
				'context' => $context,
			)
		);
	}

	/**
	 * Wrap the episode body with the player, actions and related episodes.
	 *
	 * @param string $content Post content.
	 */
	public static function episode_content( $content ) {
		if ( self::$busy || ! is_singular( Post_Type::TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( get_the_ID() !== get_queried_object_id() || post_password_required() ) {
			return $content;
		}
		$ep = Episode::get( get_the_ID() );
		if ( ! $ep ) {
			return $content;
		}
		self::$busy = true;
		self::enqueue();

		$html = self::template(
			'episode',
			array(
				'episode'  => $ep,
				'content'  => $content,
				'liked'    => Counters::has_liked( $ep['id'], get_current_user_id() ),
				'related'  => Settings::get( 'show_related' ) ? self::related( $ep ) : array(),
				'list_url' => self::list_url(),
				/**
				 * Show the artwork beside the player on episode pages. Off by default
				 * when the episode has a featured image, because themes show that already.
				 *
				 * @param bool  $show Show it.
				 * @param array $ep   Episode data.
				 */
				'show_art' => (bool) apply_filters( 'sepo_podcast_show_episode_art', ! has_post_thumbnail( $ep['id'] ), $ep ),
			)
		);
		self::$busy = false;
		return $html;
	}

	/**
	 * Address of the episode list page, or ''.
	 */
	public static function list_url(): string {
		$page = (int) Settings::get( 'list_page' );
		if ( $page && 'publish' === get_post_status( $page ) ) {
			return (string) get_permalink( $page );
		}
		return '';
	}

	/**
	 * Up to four other episodes, from the same series first.
	 *
	 * @param array $ep Episode data.
	 * @return array[] Episode data.
	 */
	public static function related( array $ep ): array {
		$args = array(
			'post_type'           => Post_Type::TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => 4,
			'post__not_in'        => array( $ep['id'] ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- one ID.
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'has_password'        => false,
		);
		$out = array();
		if ( $ep['series'] ) {
			$q = new \WP_Query(
				$args + array(
					'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => Post_Type::SERIES,
							'field'    => 'term_id',
							'terms'    => wp_list_pluck( $ep['series'], 'id' ),
						),
					),
				)
			);
			foreach ( $q->posts as $p ) {
				$out[ $p->ID ] = Episode::get( $p );
			}
		}
		if ( count( $out ) < 4 ) {
			$args['posts_per_page'] = 4 - count( $out );
			$args['post__not_in']   = array_merge( array( $ep['id'] ), array_keys( $out ) ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
			$q                      = new \WP_Query( $args );
			foreach ( $q->posts as $p ) {
				$out[ $p->ID ] = Episode::get( $p );
			}
		}
		return array_values( array_filter( $out ) );
	}

	/**
	 * [sepo_podcast series="slug" per_page="10" search="yes" series_filter="yes" order="newest"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'series'        => '',
				'per_page'      => (int) Settings::get( 'per_page' ),
				'search'        => 'yes',
				'series_filter' => 'yes',
				'order'         => 'newest',
			),
			$atts,
			'sepo_podcast'
		);
		return self::episode_list(
			array(
				'series'      => sanitize_title( (string) $atts['series'] ),
				'per_page'    => (int) $atts['per_page'],
				'show_search' => Post_Type::to_bool( $atts['search'] ),
				'show_series' => Post_Type::to_bool( $atts['series_filter'] ),
				'order'       => (string) $atts['order'],
			)
		);
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attrs Block attributes.
	 */
	public static function block( $attrs ): string {
		$attrs = is_array( $attrs ) ? $attrs : array();
		$html  = self::episode_list(
			array(
				'series'      => sanitize_title( (string) ( $attrs['series'] ?? '' ) ),
				'per_page'    => (int) ( $attrs['perPage'] ?? Settings::get( 'per_page' ) ),
				'show_search' => (bool) ( $attrs['showSearch'] ?? true ),
				'show_series' => (bool) ( $attrs['showSeries'] ?? true ),
				'order'       => (string) ( $attrs['order'] ?? 'newest' ),
			)
		);
		$wrap  = get_block_wrapper_attributes();
		return '<div ' . $wrap . '>' . $html . '</div>';
	}

	/**
	 * The searchable, filterable, paged episode list.
	 *
	 * Reads ?sp_q (search), ?sp_series (series slug, or _none) and ?sp_pg (page)
	 * so it works without JavaScript and every view has its own address.
	 *
	 * @param array $opts series, per_page, show_search, show_series, order.
	 */
	public static function episode_list( array $opts ): string {
		self::enqueue();

		$per_page = max( 1, min( 100, (int) $opts['per_page'] ) );
		$fixed    = '' !== $opts['series'];
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$search = isset( $_GET['sp_q'] ) ? sanitize_text_field( wp_unslash( $_GET['sp_q'] ) ) : '';
		$series = $fixed ? $opts['series'] : ( isset( $_GET['sp_series'] ) ? sanitize_title( wp_unslash( $_GET['sp_series'] ) ) : '' );
		$page   = isset( $_GET['sp_pg'] ) ? max( 1, absint( $_GET['sp_pg'] ) ) : 1;
		// phpcs:enable
		if ( '_none' === ( $_GET['sp_series'] ?? '' ) && ! $fixed ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- compared to a constant only.
			$series = '_none';
		}

		$args = array(
			'post_type'           => Post_Type::TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
			'has_password'        => false,
		);
		switch ( $opts['order'] ) {
			case 'oldest':
				$args['orderby'] = 'date';
				$args['order']   = 'ASC';
				break;
			case 'custom':
				$args['orderby'] = array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				);
				break;
			default:
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( '_none' === $series ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Post_Type::SERIES,
					'operator' => 'NOT EXISTS',
				),
			);
		} elseif ( '' !== $series ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy'         => Post_Type::SERIES,
					'field'            => 'slug',
					'terms'            => $series,
					'include_children' => true,
				),
			);
		}

		/**
		 * Filter the episode list query.
		 *
		 * @param array $args WP_Query arguments.
		 * @param array $opts List options.
		 */
		$args = (array) apply_filters( 'sepo_podcast_list_query', $args, $opts );
		$q    = new \WP_Query( $args );

		$episodes = array();
		foreach ( $q->posts as $p ) {
			$ep = Episode::get( $p );
			if ( $ep ) {
				$episodes[] = $ep;
			}
		}
		$liked = Counters::liked_ids( get_current_user_id(), wp_list_pluck( $episodes, 'id' ) );

		$base = remove_query_arg( array( 'sp_q', 'sp_series', 'sp_pg' ), self::current_url() );

		return self::template(
			'list',
			array(
				'episodes'    => $episodes,
				'liked'       => $liked,
				'search'      => $search,
				'series'      => $series,
				'fixed'       => $fixed,
				'show_search' => ! empty( $opts['show_search'] ),
				'show_series' => ! empty( $opts['show_series'] ) && ! $fixed,
				'all_series'  => self::series_terms(),
				'has_loose'   => self::has_ungrouped(),
				'page'        => $page,
				'pages'       => (int) $q->max_num_pages,
				'total'       => (int) $q->found_posts,
				'base'        => $base,
			)
		);
	}

	/**
	 * Series that have episodes, in their set order.
	 *
	 * @return \WP_Term[]
	 */
	public static function series_terms(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Post_Type::SERIES,
				'hide_empty' => true,
			)
		);
		if ( ! is_array( $terms ) ) {
			return array();
		}
		usort(
			$terms,
			static function ( $a, $b ) {
				$oa = (int) get_term_meta( $a->term_id, 'sepo_order', true );
				$ob = (int) get_term_meta( $b->term_id, 'sepo_order', true );
				return $oa === $ob ? strcasecmp( $a->name, $b->name ) : $oa <=> $ob;
			}
		);
		return $terms;
	}

	/**
	 * Are there published episodes without a series?
	 */
	public static function has_ungrouped(): bool {
		$q = new \WP_Query(
			array(
				'post_type'      => Post_Type::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Post_Type::SERIES,
						'operator' => 'NOT EXISTS',
					),
				),
			)
		);
		return (bool) $q->posts;
	}

	/**
	 * Address for a list view.
	 *
	 * @param string $base   Page address without list arguments.
	 * @param array  $params sp_q, sp_series, sp_pg.
	 */
	public static function list_link( string $base, array $params ): string {
		$params = array_filter(
			$params,
			static function ( $v ) {
				return '' !== $v && null !== $v && 1 !== $v;
			}
		);
		return $params ? add_query_arg( array_map( 'rawurlencode', $params ), $base ) : $base;
	}

	/**
	 * "S1 · E4" style label.
	 *
	 * @param array $ep Episode data.
	 */
	public static function number_label( array $ep ): string {
		$bits = array();
		if ( '' !== $ep['season'] ) {
			/* translators: %s: season number. */
			$bits[] = sprintf( __( 'S%s', 'sepo-podcast' ), $ep['season'] );
		}
		if ( '' !== $ep['episode'] ) {
			/* translators: %s: episode number. */
			$bits[] = sprintf( __( 'E%s', 'sepo-podcast' ), $ep['episode'] );
		}
		if ( 'trailer' === $ep['episode_type'] ) {
			$bits[] = __( 'Trailer', 'sepo-podcast' );
		} elseif ( 'bonus' === $ep['episode_type'] ) {
			$bits[] = __( 'Bonus', 'sepo-podcast' );
		}
		return implode( ' · ', $bits );
	}

	/**
	 * Inline SVG icon (fixed markup, safe to print).
	 *
	 * @param string $name play | pause | heart | download | back | forward | mic | list | search | lock.
	 */
	public static function icon( string $name ): string {
		$paths = array(
			'play'     => '<path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.4-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/>',
			'pause'    => '<rect x="6.5" y="5" width="4" height="14" rx="1"/><rect x="13.5" y="5" width="4" height="14" rx="1"/>',
			'heart'    => '<path d="M12 20.5s-7.5-4.6-9.3-9.2C1.5 8 3.6 4.5 7.2 4.5c2 0 3.3 1.1 4.8 2.9 1.5-1.8 2.8-2.9 4.8-2.9 3.6 0 5.7 3.5 4.5 6.8C19.5 15.9 12 20.5 12 20.5z"/>',
			'download' => '<path d="M12 3.5v11m0 0l-4.5-4.5M12 14.5l4.5-4.5M4.5 17.5v2h15v-2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
			'back'     => '<path d="M11.5 6.5L6 12l5.5 5.5M18 6.5L12.5 12l5.5 5.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
			'forward'  => '<path d="M12.5 6.5L18 12l-5.5 5.5M6 6.5l5.5 5.5L6 17.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
			'mic'      => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
			'list'     => '<path d="M4 6.5h16M4 12h16M4 17.5h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
			'search'   => '<circle cx="10.5" cy="10.5" r="6" fill="none" stroke="currentColor" stroke-width="2"/><path d="M15 15l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
			'lock'     => '<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" fill="none" stroke="currentColor" stroke-width="2"/>',
		);
		$body = $paths[ $name ] ?? '';
		return '<svg class="sp-icon sp-icon-' . esc_attr( $name ) . '" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false">' . $body . '</svg>';
	}

	/**
	 * Allowed SVG markup for wp_kses when printing icons.
	 */
	public static function svg_kses(): array {
		$common = array(
			'd'               => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
		);
		return array(
			'svg'    => array(
				'class'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'aria-hidden' => true,
				'focusable'   => true,
			),
			'path'   => $common,
			'rect'   => $common + array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
			),
			'circle' => $common + array(
				'cx' => true,
				'cy' => true,
				'r'  => true,
			),
		);
	}

	/**
	 * Print an icon.
	 *
	 * @param string $name Icon name.
	 */
	public static function the_icon( string $name ): void {
		echo wp_kses( self::icon( $name ), self::svg_kses() );
	}
}
