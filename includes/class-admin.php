<?php
/**
 * Admin: the Episode details box, list columns, counter resets, series order.
 *
 * @package SepoPodcast
 */

namespace SepoPodcast;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens.
 */
final class Admin {

	const NONCE = 'sepo_podcast_meta';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes_' . Post_Type::TYPE, array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_' . Post_Type::TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_sepo_podcast_upload', array( __CLASS__, 'ajax_upload' ) );

		add_filter( 'manage_' . Post_Type::TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Post_Type::TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . Post_Type::TYPE . '_sortable_columns', array( __CLASS__, 'sortable' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'sort_query' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_sepo_podcast_reset', array( __CLASS__, 'handle_reset' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );

		add_action( Post_Type::SERIES . '_add_form_fields', array( __CLASS__, 'term_add_field' ) );
		add_action( Post_Type::SERIES . '_edit_form_fields', array( __CLASS__, 'term_edit_field' ) );
		add_action( 'created_' . Post_Type::SERIES, array( __CLASS__, 'term_save' ) );
		add_action( 'edited_' . Post_Type::SERIES, array( __CLASS__, 'term_save' ) );
		add_filter( 'manage_edit-' . Post_Type::SERIES . '_columns', array( __CLASS__, 'term_columns' ) );
		add_filter( 'manage_' . Post_Type::SERIES . '_custom_column', array( __CLASS__, 'term_column' ), 10, 3 );

		add_filter( 'plugin_action_links_' . plugin_basename( SEPO_PODCAST_FILE ), array( __CLASS__, 'plugin_links' ) );
	}

	/* -------------------------------------------------------- meta box */

	/**
	 * Add the Episode details box.
	 */
	public static function meta_box(): void {
		add_meta_box( 'sepo-podcast-episode', __( 'Episode details', 'sepo-podcast' ), array( __CLASS__, 'meta_box_html' ), Post_Type::TYPE, 'normal', 'high' );
	}

	/**
	 * Scripts and styles for the episode editor.
	 *
	 * @param string $hook Admin page.
	 */
	public static function assets( $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || Post_Type::TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'sepo-podcast-admin', SEPO_PODCAST_URL . 'assets/css/admin.css', array(), SEPO_PODCAST_VERSION );
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'sepo-podcast-admin', SEPO_PODCAST_URL . 'assets/js/admin.js', array( 'jquery', 'wp-i18n' ), SEPO_PODCAST_VERSION, true );
		wp_set_script_translations( 'sepo-podcast-admin', 'sepo-podcast', SEPO_PODCAST_DIR . 'languages' );
		wp_add_inline_script(
			'sepo-podcast-admin',
			'window.SepoPodcastAdmin = ' . wp_json_encode(
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'sepo_podcast_upload' ),
					'maxBytes' => wp_max_upload_size(),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * The Episode details box.
	 *
	 * @param \WP_Post $post Episode.
	 */
	public static function meta_box_html( $post ): void {
		$id      = (int) $post->ID;
		$get     = static function ( string $key ) use ( $id ) {
			return get_post_meta( $id, $key, true );
		};
		$is_new  = 'auto-draft' === $post->post_status;
		$att     = (int) $get( '_sepo_audio_id' );
		$url     = (string) $get( '_sepo_audio_url' );
		$private = (string) $get( '_sepo_private_file' );

		if ( Post_Type::to_bool( $get( '_sepo_protected' ) ) || ( $is_new && Settings::get( 'protect_new' ) ) ) {
			$source = 'protected';
		} elseif ( ! $att && '' !== $url ) {
			$source = 'external';
		} else {
			$source = 'media';
		}

		$att_name = $att ? wp_basename( (string) get_attached_file( $att ) ) : '';
		$priv_ok  = '' !== $private && '' !== Private_Files::path( $private );
		$allow    = metadata_exists( 'post', $id, '_sepo_allow_download' ) ? Post_Type::to_bool( $get( '_sepo_allow_download' ) ) : true;
		$type     = (string) $get( '_sepo_episode_type' );
		$check    = Private_Files::self_check();

		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		?>
		<div class="sepo-podcast-box" data-source="<?php echo esc_attr( $source ); ?>">
			<fieldset class="sp-source">
				<legend><?php esc_html_e( 'Audio file', 'sepo-podcast' ); ?></legend>
				<label><input type="radio" name="sepo_source" value="media" <?php checked( $source, 'media' ); ?>> <?php esc_html_e( 'Media Library', 'sepo-podcast' ); ?></label>
				<label><input type="radio" name="sepo_source" value="external" <?php checked( $source, 'external' ); ?>> <?php esc_html_e( 'Address on another site', 'sepo-podcast' ); ?></label>
				<label><input type="radio" name="sepo_source" value="protected" <?php checked( $source, 'protected' ); ?>> <?php esc_html_e( 'Protected file', 'sepo-podcast' ); ?></label>
			</fieldset>

			<div class="sp-panel" data-for="media">
				<input type="hidden" name="sepo_audio_id" value="<?php echo esc_attr( (string) $att ); ?>" data-sp-audio-id>
				<button type="button" class="button" data-sp-pick><?php esc_html_e( 'Choose audio', 'sepo-podcast' ); ?></button>
				<span class="sp-file" data-sp-audio-name><?php echo esc_html( $att_name ); ?></span>
				<button type="button" class="button-link sp-remove" data-sp-unpick<?php echo $att ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'sepo-podcast' ); ?></button>
				<p class="description"><?php esc_html_e( 'Anyone with the file address can download Media Library files. Use a protected file if "Only signed-in visitors may download" must hold.', 'sepo-podcast' ); ?></p>
			</div>

			<div class="sp-panel" data-for="external">
				<label for="sepo_audio_url" class="screen-reader-text"><?php esc_html_e( 'Audio address', 'sepo-podcast' ); ?></label>
				<input type="url" class="large-text" id="sepo_audio_url" name="sepo_audio_url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://example.com/episode.mp3">
			</div>

			<div class="sp-panel" data-for="protected">
				<input type="hidden" name="sepo_private_file" value="<?php echo esc_attr( $private ); ?>" data-sp-private>
				<p>
					<span class="sp-file" data-sp-private-name><?php echo esc_html( $priv_ok ? $private : '' ); ?></span>
					<?php if ( '' !== $private && ! $priv_ok ) : ?>
						<span class="sp-warn"><?php esc_html_e( 'The stored file is missing. Upload it again.', 'sepo-podcast' ); ?></span>
					<?php endif; ?>
				</p>
				<label class="button" for="sepo_private_upload"><?php esc_html_e( 'Upload protected audio', 'sepo-podcast' ); ?></label>
				<input type="file" id="sepo_private_upload" accept="audio/*" class="screen-reader-text" data-sp-upload>
				<span class="sp-progress" data-sp-progress aria-live="polite"></span>
				<p class="description">
					<?php esc_html_e( 'Stored outside the Media Library and only served through this site, which checks sign-in before every download.', 'sepo-podcast' ); ?>
					<?php if ( 'exposed' === $check['status'] ) : ?>
						<strong class="sp-warn"><?php esc_html_e( 'Warning: your server does not block the protected folder yet. See Settings → Sepo Podcast.', 'sepo-podcast' ); ?></strong>
					<?php endif; ?>
				</p>
			</div>

			<div class="sp-grid">
				<p>
					<label for="sepo_duration"><?php esc_html_e( 'Length', 'sepo-podcast' ); ?></label>
					<input type="text" id="sepo_duration" name="sepo_duration" value="<?php echo esc_attr( (string) $get( '_sepo_duration' ) ); ?>" placeholder="32:10" data-sp-duration>
				</p>
				<p>
					<label for="sepo_season"><?php esc_html_e( 'Season', 'sepo-podcast' ); ?></label>
					<input type="text" inputmode="numeric" id="sepo_season" name="sepo_season" value="<?php echo esc_attr( (string) $get( '_sepo_season' ) ); ?>">
				</p>
				<p>
					<label for="sepo_episode"><?php esc_html_e( 'Episode number', 'sepo-podcast' ); ?></label>
					<input type="text" inputmode="numeric" id="sepo_episode" name="sepo_episode" value="<?php echo esc_attr( (string) $get( '_sepo_episode' ) ); ?>">
				</p>
				<p>
					<label for="sepo_episode_type"><?php esc_html_e( 'Type', 'sepo-podcast' ); ?></label>
					<select id="sepo_episode_type" name="sepo_episode_type">
						<option value="full" <?php selected( $type, 'full' ); ?>><?php esc_html_e( 'Full episode', 'sepo-podcast' ); ?></option>
						<option value="trailer" <?php selected( $type, 'trailer' ); ?>><?php esc_html_e( 'Trailer', 'sepo-podcast' ); ?></option>
						<option value="bonus" <?php selected( $type, 'bonus' ); ?>><?php esc_html_e( 'Bonus', 'sepo-podcast' ); ?></option>
					</select>
				</p>
				<p>
					<label for="sepo_host"><?php esc_html_e( 'Host', 'sepo-podcast' ); ?></label>
					<input type="text" id="sepo_host" name="sepo_host" value="<?php echo esc_attr( (string) $get( '_sepo_host' ) ); ?>">
				</p>
				<p>
					<label for="sepo_guest"><?php esc_html_e( 'Guest', 'sepo-podcast' ); ?></label>
					<input type="text" id="sepo_guest" name="sepo_guest" value="<?php echo esc_attr( (string) $get( '_sepo_guest' ) ); ?>">
				</p>
			</div>

			<p class="sp-checks">
				<label><input type="checkbox" name="sepo_allow_download" value="1" <?php checked( $allow ); ?>> <?php esc_html_e( 'Show a Download button', 'sepo-podcast' ); ?></label>
				<label><input type="checkbox" name="sepo_requires_login" value="1" <?php checked( Post_Type::to_bool( $get( '_sepo_requires_login' ) ) ); ?>> <?php esc_html_e( 'Only signed-in visitors may download (left out of the public feed)', 'sepo-podcast' ); ?></label>
				<label><input type="checkbox" name="sepo_explicit" value="1" <?php checked( Post_Type::to_bool( $get( '_sepo_explicit' ) ) ); ?>> <?php esc_html_e( 'Explicit content', 'sepo-podcast' ); ?></label>
			</p>

			<?php if ( ! $is_new ) : ?>
				<p class="sp-counts">
					<?php
					printf(
						/* translators: 1: number of likes, 2: number of downloads. */
						esc_html__( '%1$s likes · %2$s downloads', 'sepo-podcast' ),
						esc_html( number_format_i18n( Counters::likes( $id ) ) ),
						esc_html( number_format_i18n( Counters::downloads( $id ) ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Save the Episode details box.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$text = static function ( string $field ): string {
			return isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		};
		$flag = static function ( string $field ): bool {
			return ! empty( $_POST[ $field ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		};

		$source = $text( 'sepo_source' );
		$source = in_array( $source, array( 'media', 'external', 'protected' ), true ) ? $source : 'media';

		$att = absint( $text( 'sepo_audio_id' ) );
		if ( $att && ( 'attachment' !== get_post_type( $att ) || ! current_user_can( 'edit_post', $att ) ) ) {
			$att = 0;
		}
		$url = isset( $_POST['sepo_audio_url'] ) ? esc_url_raw( wp_unslash( $_POST['sepo_audio_url'] ), array( 'http', 'https' ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw sanitizes.

		$old_private = (string) get_post_meta( $post_id, '_sepo_private_file', true );
		$private     = sanitize_file_name( wp_basename( $text( 'sepo_private_file' ) ) );
		if ( '' !== $private && '' === Private_Files::path( $private ) ) {
			$private = $old_private;
		}

		// The chosen source wins; the others are cleared so the episode is never ambiguous.
		update_post_meta( $post_id, '_sepo_audio_id', 'media' === $source ? $att : 0 );
		update_post_meta( $post_id, '_sepo_audio_url', 'external' === $source ? $url : '' );
		update_post_meta( $post_id, '_sepo_protected', 'protected' === $source && '' !== $private );
		update_post_meta( $post_id, '_sepo_private_file', 'protected' === $source ? $private : '' );

		$kept = 'protected' === $source ? $private : '';
		if ( '' !== $old_private && $old_private !== $kept ) {
			self::drop_private_if_unused( $old_private, (int) $post_id );
		}

		$duration = $text( 'sepo_duration' );
		if ( '' === $duration && 'media' === $source && $att ) {
			$meta     = wp_get_attachment_metadata( $att );
			$duration = is_array( $meta ) && ! empty( $meta['length_formatted'] ) ? (string) $meta['length_formatted'] : '';
		}
		update_post_meta( $post_id, '_sepo_duration', $duration );

		foreach ( array( 'season', 'episode', 'host', 'guest' ) as $field ) {
			update_post_meta( $post_id, '_sepo_' . $field, $text( 'sepo_' . $field ) );
		}
		$type = $text( 'sepo_episode_type' );
		update_post_meta( $post_id, '_sepo_episode_type', in_array( $type, array( 'full', 'trailer', 'bonus' ), true ) ? $type : 'full' );
		update_post_meta( $post_id, '_sepo_allow_download', $flag( 'sepo_allow_download' ) );
		update_post_meta( $post_id, '_sepo_requires_login', $flag( 'sepo_requires_login' ) );
		update_post_meta( $post_id, '_sepo_explicit', $flag( 'sepo_explicit' ) );

		Episode::forget( (int) $post_id );
	}

	/**
	 * Delete a protected file no episode uses any more.
	 *
	 * @param string $name    Stored file name.
	 * @param int    $post_id Episode that just stopped using it.
	 */
	private static function drop_private_if_unused( string $name, int $post_id ): void {
		$others = get_posts(
			array(
				'post_type'      => Post_Type::TYPE,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'post__not_in'   => array( $post_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
				'meta_key'       => '_sepo_private_file', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $name, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( ! $others ) {
			Private_Files::delete( $name );
		}
	}

	/**
	 * Upload a protected file from the Episode details box.
	 */
	public static function ajax_upload(): void {
		check_ajax_referer( 'sepo_podcast_upload', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! current_user_can( 'upload_files' ) || ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to upload files for this episode.', 'sepo-podcast' ) ), 403 );
		}
		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was received.', 'sepo-podcast' ) ), 400 );
		}
		$file   = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passed to wp_handle_upload, which validates it.
		$result = Private_Files::store_upload( $file );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success(
			array(
				'name' => $result,
				'size' => size_format( (int) filesize( Private_Files::path( $result ) ) ),
			)
		);
	}

	/* --------------------------------------------------------- columns */

	/**
	 * Episode list columns.
	 *
	 * @param array $cols Columns.
	 */
	public static function columns( $cols ): array {
		$out = array();
		foreach ( $cols as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['sepo_number']    = __( 'Episode', 'sepo-podcast' );
				$out['sepo_duration']  = __( 'Length', 'sepo-podcast' );
				$out['sepo_audio']     = __( 'Audio', 'sepo-podcast' );
				$out['sepo_likes']     = __( 'Likes', 'sepo-podcast' );
				$out['sepo_downloads'] = __( 'Downloads', 'sepo-podcast' );
			}
		}
		return $out;
	}

	/**
	 * Column values.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post ID.
	 */
	public static function column( $col, $post_id ): void {
		switch ( $col ) {
			case 'sepo_number':
				$ep = Episode::get( $post_id );
				echo esc_html( $ep ? Render::number_label( $ep ) : '' );
				break;
			case 'sepo_duration':
				echo esc_html( (string) get_post_meta( $post_id, '_sepo_duration', true ) );
				break;
			case 'sepo_audio':
				$src    = Episode::source( (int) $post_id );
				$labels = array(
					''         => '—',
					'media'    => __( 'Media Library', 'sepo-podcast' ),
					'external' => __( 'External', 'sepo-podcast' ),
					'private'  => __( 'Protected', 'sepo-podcast' ),
				);
				echo esc_html( $labels[ $src['kind'] ] ?? '—' );
				if ( Post_Type::to_bool( get_post_meta( $post_id, '_sepo_requires_login', true ) ) ) {
					echo ' <span class="dashicons dashicons-lock" title="' . esc_attr__( 'Sign in to download', 'sepo-podcast' ) . '"></span>';
				}
				break;
			case 'sepo_likes':
				echo esc_html( number_format_i18n( Counters::likes( (int) $post_id ) ) );
				break;
			case 'sepo_downloads':
				echo esc_html( number_format_i18n( Counters::downloads( (int) $post_id ) ) );
				break;
		}
	}

	/**
	 * Sortable columns.
	 *
	 * @param array $cols Columns.
	 */
	public static function sortable( $cols ): array {
		$cols['sepo_likes']     = 'sepo_likes';
		$cols['sepo_downloads'] = 'sepo_downloads';
		return $cols;
	}

	/**
	 * Sort by the cached counters, keeping episodes that have none.
	 *
	 * @param \WP_Query $q Query.
	 */
	public static function sort_query( $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() || Post_Type::TYPE !== $q->get( 'post_type' ) ) {
			return;
		}
		$by  = $q->get( 'orderby' );
		$map = array(
			'sepo_likes'     => '_sepo_like_count',
			'sepo_downloads' => '_sepo_download_count',
		);
		if ( ! is_string( $by ) || ! isset( $map[ $by ] ) ) {
			return;
		}
		$q->set(
			'meta_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'relation' => 'OR',
				'sp_count' => array(
					'key'  => $map[ $by ],
					'type' => 'NUMERIC',
				),
				array(
					'key'     => $map[ $by ],
					'compare' => 'NOT EXISTS',
				),
			)
		);
		$q->set( 'orderby', 'sp_count' );
	}

	/**
	 * "Reset counts" row action.
	 *
	 * @param array    $actions Actions.
	 * @param \WP_Post $post    Post.
	 */
	public static function row_actions( $actions, $post ): array {
		if ( Post_Type::TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		$url                  = wp_nonce_url( admin_url( 'admin-post.php?action=sepo_podcast_reset&post=' . (int) $post->ID ), 'sepo_podcast_reset_' . (int) $post->ID );
		$actions['sepo_reset'] = '<a href="' . esc_url( $url ) . '" onclick="return window.confirm(this.dataset.confirm)" data-confirm="' . esc_attr__( 'Clear the likes and download count for this episode?', 'sepo-podcast' ) . '">' . esc_html__( 'Reset counts', 'sepo-podcast' ) . '</a>';
		return $actions;
	}

	/**
	 * Handle the reset link.
	 */
	public static function handle_reset(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'sepo_podcast_reset_' . $post_id );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || Post_Type::TYPE !== get_post_type( $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'sepo-podcast' ), 403 );
		}
		Counters::reset( $post_id, 'all' );
		wp_safe_redirect( add_query_arg( 'sepo_reset', 1, admin_url( 'edit.php?post_type=' . Post_Type::TYPE ) ) );
		exit;
	}

	/**
	 * Notices.
	 */
	public static function notices(): void {
		if ( isset( $_GET['sepo_reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Counts cleared.', 'sepo-podcast' ) . '</p></div>';
		}
	}

	/* ------------------------------------------------------ series order */

	/**
	 * Order field on "Add series".
	 */
	public static function term_add_field(): void {
		wp_nonce_field( 'sepo_podcast_term', 'sepo_podcast_term_nonce' );
		?>
		<div class="form-field">
			<label for="sepo_order"><?php esc_html_e( 'Order', 'sepo-podcast' ); ?></label>
			<input type="number" name="sepo_order" id="sepo_order" value="0" step="1">
			<p><?php esc_html_e( 'Lower numbers come first in the series filter.', 'sepo-podcast' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Order field on "Edit series".
	 *
	 * @param \WP_Term $term Term.
	 */
	public static function term_edit_field( $term ): void {
		wp_nonce_field( 'sepo_podcast_term', 'sepo_podcast_term_nonce' );
		?>
		<tr class="form-field">
			<th scope="row"><label for="sepo_order"><?php esc_html_e( 'Order', 'sepo-podcast' ); ?></label></th>
			<td>
				<input type="number" name="sepo_order" id="sepo_order" value="<?php echo esc_attr( (string) (int) get_term_meta( $term->term_id, 'sepo_order', true ) ); ?>" step="1">
				<p class="description"><?php esc_html_e( 'Lower numbers come first in the series filter.', 'sepo-podcast' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the series order.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function term_save( $term_id ): void {
		if ( ! isset( $_POST['sepo_podcast_term_nonce'], $_POST['sepo_order'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sepo_podcast_term_nonce'] ) ), 'sepo_podcast_term' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		update_term_meta( $term_id, 'sepo_order', (int) wp_unslash( $_POST['sepo_order'] ) );
	}

	/**
	 * Order column on the series list.
	 *
	 * @param array $cols Columns.
	 */
	public static function term_columns( $cols ): array {
		$cols['sepo_order'] = __( 'Order', 'sepo-podcast' );
		return $cols;
	}

	/**
	 * Order column value.
	 *
	 * @param string $out     Output.
	 * @param string $col     Column.
	 * @param int    $term_id Term ID.
	 */
	public static function term_column( $out, $col, $term_id ): string {
		if ( 'sepo_order' === $col ) {
			return esc_html( (string) (int) get_term_meta( $term_id, 'sepo_order', true ) );
		}
		return (string) $out;
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 */
	public static function plugin_links( $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=sepo-podcast' ) ) . '">' . esc_html__( 'Settings', 'sepo-podcast' ) . '</a>' );
		return $links;
	}
}
