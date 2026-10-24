<?php
/**
 * Settings: Settings → Sepo Podcast.
 *
 * @package SepoPodcast
 */

namespace SepoPodcast;

defined( 'ABSPATH' ) || exit;

/**
 * One option array, `sepo_podcast_settings`, read through Settings::get().
 */
final class Settings {

	const OPTION = 'sepo_podcast_settings';

	/**
	 * Cached settings for this request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default values for every setting.
	 */
	public static function defaults(): array {
		return array(
			'slug'               => 'podcast',
			'list_page'          => 0,
			'per_page'           => 10,
			'ungrouped_label'    => __( 'Other episodes', 'sepo-podcast' ),
			'show_related'       => 1,
			'accent'             => '#0b3d33',
			'count_window'       => 60,
			'protect_new'        => 0,
			'feed_title'         => '',
			'feed_description'   => '',
			'feed_author'        => '',
			'feed_owner_name'    => '',
			'feed_owner_email'   => '',
			'feed_category'      => 'Technology',
			'feed_subcategory'   => '',
			'feed_explicit'      => 0,
			'feed_language'      => '',
			'feed_image'         => '',
			'feed_copyright'     => '',
			'feed_type'          => 'episodic',
			'feed_limit'         => 300,
			'feed_locked'        => 0,
			'delete_on_uninstall' => 0,
			'delete_episodes'    => 0,
		);
	}

	/**
	 * Register the settings page and the option.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'on_update' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
	}

	/**
	 * All settings merged with defaults.
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Forget the per-request cache (after saving).
	 */
	public static function reset_cache(): void {
		self::$cache = null;
	}

	/**
	 * Create the "Podcast" page holding the episode list, once.
	 */
	public static function ensure_list_page(): void {
		$page_id = (int) self::get( 'list_page' );
		if ( $page_id && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
			return;
		}
		$slug     = sanitize_title( (string) self::get( 'slug' ) );
		$existing = get_page_by_path( $slug );
		if ( $existing instanceof \WP_Post ) {
			$page_id = (int) $existing->ID;
		} else {
			$page_id = (int) wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'Podcast', 'sepo-podcast' ),
					'post_name'    => $slug,
					'post_content' => "<!-- wp:paragraph -->\n<p>" . esc_html__( 'Listen to our latest episodes.', 'sepo-podcast' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[sepo_podcast]\n<!-- /wp:shortcode -->",
				)
			);
		}
		if ( $page_id ) {
			$all              = self::all();
			$all['list_page'] = $page_id;
			update_option( self::OPTION, $all );
			self::reset_cache();
		}
	}

	/**
	 * Settings → Sepo Podcast.
	 */
	public static function menu(): void {
		add_options_page(
			__( 'Sepo Podcast', 'sepo-podcast' ),
			__( 'Sepo Podcast', 'sepo-podcast' ),
			'manage_options',
			'sepo-podcast',
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Register the option with its sanitizer.
	 */
	public static function register(): void {
		register_setting(
			'sepo_podcast',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Clean every submitted value.
	 *
	 * @param mixed $input Raw submitted values.
	 */
	public static function sanitize( $input ): array {
		$in  = is_array( $input ) ? $input : array();
		$old = self::all();
		$out = $old;

		$slug        = sanitize_title( (string) ( $in['slug'] ?? $old['slug'] ) );
		$out['slug'] = '' !== $slug ? $slug : 'podcast';

		$out['list_page']       = absint( $in['list_page'] ?? 0 );
		$out['per_page']        = max( 1, min( 100, absint( $in['per_page'] ?? 10 ) ) );
		$out['ungrouped_label'] = sanitize_text_field( (string) ( $in['ungrouped_label'] ?? '' ) );
		$out['count_window']    = max( 0, min( 86400, absint( $in['count_window'] ?? 60 ) ) );
		$accent                 = sanitize_hex_color( (string) ( $in['accent'] ?? '' ) );
		$out['accent']          = $accent ? $accent : '#0b3d33';

		foreach ( array( 'show_related', 'protect_new', 'feed_explicit', 'feed_locked', 'delete_on_uninstall', 'delete_episodes' ) as $flag ) {
			$out[ $flag ] = empty( $in[ $flag ] ) ? 0 : 1;
		}

		foreach ( array( 'feed_title', 'feed_author', 'feed_owner_name', 'feed_category', 'feed_subcategory', 'feed_copyright', 'feed_language' ) as $text ) {
			$out[ $text ] = sanitize_text_field( (string) ( $in[ $text ] ?? '' ) );
		}
		$out['feed_description'] = sanitize_textarea_field( (string) ( $in['feed_description'] ?? '' ) );
		$out['feed_owner_email'] = sanitize_email( (string) ( $in['feed_owner_email'] ?? '' ) );
		$out['feed_image']       = esc_url_raw( (string) ( $in['feed_image'] ?? '' ) );
		$out['feed_type']        = in_array( $in['feed_type'] ?? '', array( 'episodic', 'serial' ), true ) ? $in['feed_type'] : 'episodic';
		$out['feed_limit']       = max( 1, min( 1000, absint( $in['feed_limit'] ?? 300 ) ) );

		return $out;
	}

	/**
	 * When the episode address changes, rewrite rules must be rebuilt.
	 *
	 * @param mixed $old Previous value.
	 * @param mixed $new New value.
	 */
	public static function on_update( $old, $new ): void {
		self::reset_cache();
		$old_slug = is_array( $old ) ? ( $old['slug'] ?? '' ) : '';
		$new_slug = is_array( $new ) ? ( $new['slug'] ?? '' ) : '';
		if ( $old_slug !== $new_slug ) {
			update_option( 'sepo_podcast_flush', 1 );
		}
	}

	/**
	 * Rebuild rewrite rules once, after the post type registered with its new slug.
	 */
	public static function maybe_flush(): void {
		if ( get_option( 'sepo_podcast_flush' ) ) {
			delete_option( 'sepo_podcast_flush' );
			flush_rewrite_rules();
		}
	}

	/**
	 * The settings screen.
	 */
	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = self::all();
		$name  = self::OPTION;
		$check = Private_Files::self_check();
		$cats  = Feed::categories();
		?>
		<div class="wrap sepo-podcast-settings">
			<h1><?php esc_html_e( 'Sepo Podcast', 'sepo-podcast' ); ?></h1>

			<?php if ( 'exposed' === $check['status'] ) : ?>
				<div class="notice notice-error"><p>
					<strong><?php esc_html_e( 'Protected files are reachable from the web.', 'sepo-podcast' ); ?></strong>
					<?php esc_html_e( 'Your server ignores the .htaccess file in the protected folder (this is normal on nginx). Add this rule to your server configuration:', 'sepo-podcast' ); ?>
				</p><pre><?php echo esc_html( 'location ^~ ' . $check['path'] . ' { deny all; }' ); ?></pre></div>
			<?php elseif ( 'protected' === $check['status'] ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Protected files: the private folder is blocked from the web.', 'sepo-podcast' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'sepo_podcast' ); ?>

				<h2 class="title"><?php esc_html_e( 'Episodes', 'sepo-podcast' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sp-slug"><?php esc_html_e( 'Episode address', 'sepo-podcast' ); ?></label></th>
						<td><code><?php echo esc_html( home_url( '/' ) ); ?></code><input id="sp-slug" name="<?php echo esc_attr( $name ); ?>[slug]" value="<?php echo esc_attr( $s['slug'] ); ?>" class="regular-text" style="width:12em"><code>/episode-name/</code></td>
					</tr>
					<tr>
						<th scope="row"><label for="sp-page"><?php esc_html_e( 'Episode list page', 'sepo-podcast' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => esc_attr( $name ) . '[list_page]',
									'id'                => 'sp-page',
									'selected'          => (int) $s['list_page'],
									'show_option_none'  => esc_html__( '— None —', 'sepo-podcast' ),
									'option_none_value' => 0,
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'The page that holds the [sepo_podcast] shortcode or the Podcast episodes block. Linked from each episode.', 'sepo-podcast' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sp-per"><?php esc_html_e( 'Episodes per page', 'sepo-podcast' ); ?></label></th>
						<td><input type="number" min="1" max="100" id="sp-per" name="<?php echo esc_attr( $name ); ?>[per_page]" value="<?php echo esc_attr( (string) $s['per_page'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sp-ung"><?php esc_html_e( 'Label for episodes without a series', 'sepo-podcast' ); ?></label></th>
						<td><input id="sp-ung" name="<?php echo esc_attr( $name ); ?>[ungrouped_label]" value="<?php echo esc_attr( $s['ungrouped_label'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Episode pages', 'sepo-podcast' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[show_related]" value="1" <?php checked( $s['show_related'] ); ?>> <?php esc_html_e( 'Show "More episodes" under each episode', 'sepo-podcast' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="sp-accent"><?php esc_html_e( 'Accent colour', 'sepo-podcast' ); ?></label></th>
						<td><input type="color" id="sp-accent" name="<?php echo esc_attr( $name ); ?>[accent]" value="<?php echo esc_attr( $s['accent'] ); ?>"></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Downloads', 'sepo-podcast' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sp-win"><?php esc_html_e( 'Count a repeat download after', 'sepo-podcast' ); ?></label></th>
						<td><input type="number" min="0" max="86400" id="sp-win" name="<?php echo esc_attr( $name ); ?>[count_window]" value="<?php echo esc_attr( (string) $s['count_window'] ); ?>" class="small-text"> <?php esc_html_e( 'seconds (stops double clicks inflating counts)', 'sepo-podcast' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Protected files', 'sepo-podcast' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[protect_new]" value="1" <?php checked( $s['protect_new'] ); ?>> <?php esc_html_e( 'Tick "Protect this file" by default on new episodes', 'sepo-podcast' ); ?></label>
							<p class="description"><?php esc_html_e( 'Protected audio is stored outside the public Media Library and only streamed through Sepo Podcast, so "Sign in to download" cannot be bypassed by copying the file address.', 'sepo-podcast' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Podcast feed (Apple Podcasts, Spotify)', 'sepo-podcast' ); ?></h2>
				<p><?php esc_html_e( 'Feed address:', 'sepo-podcast' ); ?> <code><?php echo esc_html( Feed::url() ); ?></code></p>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="sp-ft"><?php esc_html_e( 'Show title', 'sepo-podcast' ); ?></label></th><td><input id="sp-ft" name="<?php echo esc_attr( $name ); ?>[feed_title]" value="<?php echo esc_attr( $s['feed_title'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></td></tr>
					<tr><th scope="row"><label for="sp-fd"><?php esc_html_e( 'Show description', 'sepo-podcast' ); ?></label></th><td><textarea id="sp-fd" name="<?php echo esc_attr( $name ); ?>[feed_description]" rows="3" class="large-text" placeholder="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>"><?php echo esc_textarea( $s['feed_description'] ); ?></textarea></td></tr>
					<tr><th scope="row"><label for="sp-fa"><?php esc_html_e( 'Author', 'sepo-podcast' ); ?></label></th><td><input id="sp-fa" name="<?php echo esc_attr( $name ); ?>[feed_author]" value="<?php echo esc_attr( $s['feed_author'] ); ?>" class="regular-text"></td></tr>
					<tr><th scope="row"><label for="sp-fon"><?php esc_html_e( 'Owner name and email', 'sepo-podcast' ); ?></label></th><td><input id="sp-fon" name="<?php echo esc_attr( $name ); ?>[feed_owner_name]" value="<?php echo esc_attr( $s['feed_owner_name'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Name', 'sepo-podcast' ); ?>"> <input type="email" name="<?php echo esc_attr( $name ); ?>[feed_owner_email]" value="<?php echo esc_attr( $s['feed_owner_email'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Email (Apple uses it to verify you)', 'sepo-podcast' ); ?>"></td></tr>
					<tr><th scope="row"><label for="sp-fc"><?php esc_html_e( 'Category', 'sepo-podcast' ); ?></label></th><td>
						<select id="sp-fc" name="<?php echo esc_attr( $name ); ?>[feed_category]">
							<?php foreach ( $cats as $cat => $subs ) : ?>
								<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $s['feed_category'], $cat ); ?>><?php echo esc_html( $cat ); ?></option>
							<?php endforeach; ?>
						</select>
						<input name="<?php echo esc_attr( $name ); ?>[feed_subcategory]" value="<?php echo esc_attr( $s['feed_subcategory'] ); ?>" placeholder="<?php esc_attr_e( 'Subcategory (optional)', 'sepo-podcast' ); ?>">
					</td></tr>
					<tr><th scope="row"><label for="sp-fi"><?php esc_html_e( 'Show artwork URL', 'sepo-podcast' ); ?></label></th><td><input type="url" id="sp-fi" name="<?php echo esc_attr( $name ); ?>[feed_image]" value="<?php echo esc_attr( $s['feed_image'] ); ?>" class="large-text"><p class="description"><?php esc_html_e( 'Square JPG or PNG, 1400–3000 px.', 'sepo-podcast' ); ?></p></td></tr>
					<tr><th scope="row"><label for="sp-fl"><?php esc_html_e( 'Language', 'sepo-podcast' ); ?></label></th><td><input id="sp-fl" name="<?php echo esc_attr( $name ); ?>[feed_language]" value="<?php echo esc_attr( $s['feed_language'] ); ?>" class="small-text" placeholder="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>"></td></tr>
					<tr><th scope="row"><label for="sp-fcr"><?php esc_html_e( 'Copyright', 'sepo-podcast' ); ?></label></th><td><input id="sp-fcr" name="<?php echo esc_attr( $name ); ?>[feed_copyright]" value="<?php echo esc_attr( $s['feed_copyright'] ); ?>" class="regular-text"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Show type', 'sepo-podcast' ); ?></th><td>
						<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[feed_type]" value="episodic" <?php checked( $s['feed_type'], 'episodic' ); ?>> <?php esc_html_e( 'Episodic (newest first)', 'sepo-podcast' ); ?></label>&nbsp;
						<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[feed_type]" value="serial" <?php checked( $s['feed_type'], 'serial' ); ?>> <?php esc_html_e( 'Serial (listen in order)', 'sepo-podcast' ); ?></label>
					</td></tr>
					<tr><th scope="row"><label for="sp-flim"><?php esc_html_e( 'Episodes in the feed', 'sepo-podcast' ); ?></label></th><td><input type="number" id="sp-flim" min="1" max="1000" name="<?php echo esc_attr( $name ); ?>[feed_limit]" value="<?php echo esc_attr( (string) $s['feed_limit'] ); ?>" class="small-text"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Options', 'sepo-podcast' ); ?></th><td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[feed_explicit]" value="1" <?php checked( $s['feed_explicit'] ); ?>> <?php esc_html_e( 'The show contains explicit content', 'sepo-podcast' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[feed_locked]" value="1" <?php checked( $s['feed_locked'] ); ?>> <?php esc_html_e( 'Lock the feed (other platforms may not import it — Podcasting 2.0)', 'sepo-podcast' ); ?></label>
						<p class="description"><?php esc_html_e( 'Episodes that need sign-in to download are left out of the public feed.', 'sepo-podcast' ); ?></p>
					</td></tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'When the plugin is deleted', 'sepo-podcast' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Data', 'sepo-podcast' ); ?></th><td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_on_uninstall]" value="1" <?php checked( $s['delete_on_uninstall'] ); ?>> <?php esc_html_e( 'Delete likes, download counts and settings', 'sepo-podcast' ); ?></label><br>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_episodes]" value="1" <?php checked( $s['delete_episodes'] ); ?>> <?php esc_html_e( 'Also delete every episode and the protected audio files', 'sepo-podcast' ); ?></label>
					</td></tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
