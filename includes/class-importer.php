<?php
/**
 * Importer: Tools → Import podcast.
 *
 * Takes a .zip of Markdown episode files (front matter + body), like the
 * SepoDesk Podcast module uses, plus any audio and images they point to.
 * Optional likes.json / downloads.json carry the old totals over.
 *
 * Also available as: wp sepo-podcast import <file.zip> [--status=publish] [--base-url=https://old.site]
 *
 * @package SepoPodcast
 */

namespace SepoPodcast;

defined( 'ABSPATH' ) || exit;

/**
 * Episode importer.
 */
final class Importer {

	const PAGE = 'sepo-podcast-import';

	/**
	 * Front-matter names accepted for each field. The first is the canonical one.
	 */
	const ALIASES = array(
		'key'       => array( 'key', 'id', 'uid' ),
		'slug'      => array( 'slug', 'permalink', 'path' ),
		'title'     => array( 'title', 'name', 'heading' ),
		'src'       => array( 'src', 'audio', 'file', 'mp3', 'url', 'audio_url', 'enclosure', 'media' ),
		'image'     => array( 'image', 'cover', 'artwork', 'thumbnail', 'thumb', 'poster', 'picture' ),
		'duration'  => array( 'duration', 'length', 'time', 'runtime' ),
		'episode'   => array( 'episode', 'ep', 'episode_number', 'number', 'no' ),
		'season'    => array( 'season', 'season_number', 'series_number' ),
		'type'      => array( 'type', 'episode_type', 'episodetype', 'kind' ),
		'published' => array( 'published', 'date', 'pubdate', 'publish_date', 'published_at', 'created' ),
		'host'      => array( 'host', 'hosts', 'presenter', 'author' ),
		'guest'     => array( 'guest', 'guests', 'featuring', 'with' ),
		'summary'   => array( 'summary', 'description', 'excerpt', 'subtitle', 'teaser' ),
		'group'     => array( 'group', 'series', 'category', 'playlist', 'collection', 'show' ),
		'tags'      => array( 'tags', 'keywords', 'topics', 'labels' ),
		'explicit'  => array( 'explicit', 'nsfw', 'adult' ),
		'download'  => array( 'download', 'downloadable', 'allow_download', 'downloads_enabled' ),
		'gated'     => array( 'gated', 'requires_login', 'login', 'members_only', 'private', 'protected' ),
		'order'     => array( 'order', 'sort', 'position', 'weight', 'menu_order' ),
		'draft'     => array( 'draft', 'hidden', 'unpublished' ),
		'likes'     => array( 'likes', 'like_count' ),
		'downloads' => array( 'downloads', 'download_count' ),
	);

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_sepo_podcast_import', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Tools → Import podcast.
	 */
	public static function menu(): void {
		add_management_page( __( 'Import podcast', 'sepo-podcast' ), __( 'Import podcast', 'sepo-podcast' ), 'import', self::PAGE, array( __CLASS__, 'page' ) );
	}

	/**
	 * The import screen.
	 */
	public static function page(): void {
		if ( ! current_user_can( 'import' ) ) {
			return;
		}
		$report = get_transient( 'sepo_podcast_import_' . get_current_user_id() );
		if ( $report ) {
			delete_transient( 'sepo_podcast_import_' . get_current_user_id() );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import podcast episodes', 'sepo-podcast' ); ?></h1>

			<?php if ( is_array( $report ) ) : ?>
				<div class="notice <?php echo empty( $report['errors'] ) ? 'notice-success' : 'notice-warning'; ?>">
					<p>
						<?php
						printf(
							/* translators: 1: created, 2: updated, 3: skipped. */
							esc_html__( 'Created %1$d, updated %2$d, skipped %3$d episodes.', 'sepo-podcast' ),
							(int) $report['created'],
							(int) $report['updated'],
							(int) $report['skipped']
						);
						?>
					</p>
					<?php if ( ! empty( $report['errors'] ) ) : ?>
						<ul class="ul-disc">
							<?php foreach ( array_slice( (array) $report['errors'], 0, 50 ) as $err ) : ?>
								<li><?php echo esc_html( (string) $err ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Upload a .zip that holds one Markdown file per episode. Each file starts with front matter, for example:', 'sepo-podcast' ); ?></p>
			<pre style="background:#fff;border:1px solid #dcdcde;padding:12px;max-width:640px;overflow:auto">---
title: Building in public
src: audio/ep-12.mp3
image: images/ep-12.jpg
duration: 32:10
season: 2
episode: 12
published: 2025-03-04
host: Jane Doe
guest: John Roe
group: Founders
tags: [startups, product]
gated: false
---
Show notes in **Markdown**.</pre>
			<p><?php esc_html_e( 'Audio and images inside the zip are added to the Media Library (or the protected folder for gated episodes). Addresses starting with http stay where they are. Add likes.json and downloads.json ({"episode-key": 42}) to carry totals over. Importing the same zip again updates the episodes instead of duplicating them.', 'sepo-podcast' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="sepo_podcast_import">
				<?php wp_nonce_field( 'sepo_podcast_import' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sp-zip"><?php esc_html_e( 'Zip file', 'sepo-podcast' ); ?></label></th>
						<td><input type="file" id="sp-zip" name="sepo_zip" accept=".zip,application/zip" required>
							<?php /* translators: %s: maximum upload size. */ ?>
							<p class="description"><?php echo esc_html( sprintf( __( 'Largest file this server accepts: %s. Bigger archives can be imported with WP-CLI.', 'sepo-podcast' ), size_format( wp_max_upload_size() ) ) ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="sp-base"><?php esc_html_e( 'Old site address (optional)', 'sepo-podcast' ); ?></label></th>
						<td><input type="url" id="sp-base" name="sepo_base" class="regular-text" placeholder="https://old.example.com/">
							<p class="description"><?php esc_html_e( 'Relative audio or image paths that are not in the zip are linked to this address.', 'sepo-podcast' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'sepo-podcast' ); ?></th>
						<td>
							<label><input type="radio" name="sepo_status" value="draft" checked> <?php esc_html_e( 'Drafts, so I can check them first', 'sepo-podcast' ); ?></label><br>
							<label><input type="radio" name="sepo_status" value="publish"> <?php esc_html_e( 'Publish (keeps the original dates)', 'sepo-podcast' ); ?></label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Import', 'sepo-podcast' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle the form.
	 */
	public static function handle(): void {
		check_admin_referer( 'sepo_podcast_import' );
		if ( ! current_user_can( 'import' ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You are not allowed to import.', 'sepo-podcast' ), 403 );
		}
		$report = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		$file = isset( $_FILES['sepo_zip'] ) && is_array( $_FILES['sepo_zip'] ) ? $_FILES['sepo_zip'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked below.
		if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			$report['errors'][] = __( 'The zip file did not upload.', 'sepo-podcast' );
		} elseif ( 'zip' !== strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) ) ) {
			$report['errors'][] = __( 'Please upload a .zip file.', 'sepo-podcast' );
		} else {
			$report = self::run(
				(string) $file['tmp_name'],
				array(
					'status' => ( isset( $_POST['sepo_status'] ) && 'publish' === $_POST['sepo_status'] ) ? 'publish' : 'draft',
					'base'   => isset( $_POST['sepo_base'] ) ? esc_url_raw( wp_unslash( $_POST['sepo_base'] ) ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw.
				)
			);
		}
		set_transient( 'sepo_podcast_import_' . get_current_user_id(), $report, HOUR_IN_SECONDS );
		wp_safe_redirect( admin_url( 'tools.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * WP-CLI: wp sepo-podcast import <file.zip> [--status=<draft|publish>] [--base-url=<url>]
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function cli( $args, $assoc_args ): void {
		$zip = isset( $args[0] ) ? (string) $args[0] : '';
		if ( '' === $zip || ! is_readable( $zip ) ) {
			\WP_CLI::error( 'Give the path to a readable .zip file.' );
		}
		$report = self::run(
			$zip,
			array(
				'status' => ( $assoc_args['status'] ?? 'draft' ) === 'publish' ? 'publish' : 'draft',
				'base'   => esc_url_raw( (string) ( $assoc_args['base-url'] ?? '' ) ),
			)
		);
		foreach ( $report['errors'] as $err ) {
			\WP_CLI::warning( $err );
		}
		\WP_CLI::success( sprintf( 'Created %d, updated %d, skipped %d.', $report['created'], $report['updated'], $report['skipped'] ) );
	}

	/**
	 * Unzip and import.
	 *
	 * @param string $zip  Path to the zip.
	 * @param array  $opts status, base.
	 * @return array{created:int,updated:int,skipped:int,errors:string[]}
	 */
	public static function run( string $zip, array $opts ): array {
		$report = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$up  = wp_upload_dir();
		$tmp = trailingslashit( $up['basedir'] ) . 'sepo-podcast-import-' . wp_generate_password( 12, false );
		if ( ! wp_mkdir_p( $tmp ) ) {
			$report['errors'][] = __( 'Could not create a working folder in uploads.', 'sepo-podcast' );
			return $report;
		}
		// Keep the working folder private while it exists.
		file_put_contents( $tmp . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		WP_Filesystem();
		$unzipped = unzip_file( $zip, $tmp );
		if ( is_wp_error( $unzipped ) ) {
			$report['errors'][] = $unzipped->get_error_message();
			self::rmdir( $tmp );
			return $report;
		}

		$files = self::find_files( $tmp );
		$likes = self::read_counts( $files, 'likes.json' );
		$dls   = self::read_counts( $files, 'downloads.json' );

		foreach ( $files as $path ) {
			if ( ! preg_match( '/\.(md|markdown)$/i', $path ) || 0 === strpos( wp_basename( $path ), '.' ) || 'readme.md' === strtolower( wp_basename( $path ) ) ) {
				continue;
			}
			$result = self::import_file( $path, $tmp, $opts, $likes, $dls );
			if ( is_wp_error( $result ) ) {
				$report['errors'][] = wp_basename( $path ) . ': ' . $result->get_error_message();
				++$report['skipped'];
			} else {
				++$report[ $result ];
			}
		}

		self::rmdir( $tmp );
		return $report;
	}

	/**
	 * Import one Markdown file.
	 *
	 * @param string $path  File.
	 * @param string $root  Unzipped folder.
	 * @param array  $opts  status, base.
	 * @param array  $likes key => count.
	 * @param array  $dls   key => count.
	 * @return string|\WP_Error created | updated
	 */
	private static function import_file( string $path, string $root, array $opts, array $likes, array $dls ) {
		$raw = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file from the unzipped archive.
		list( $meta, $body ) = self::split( $raw );
		$f                   = self::normalise( $meta );

		$name  = pathinfo( $path, PATHINFO_FILENAME );
		$title = (string) ( $f['title'] ?? '' );
		if ( '' === $title && preg_match( '/^#\s+(.+)$/m', $body, $h ) ) {
			$title = trim( $h[1] );
		}
		if ( '' === $title ) {
			$title = ucwords( str_replace( array( '-', '_' ), ' ', $name ) );
		}
		$key  = sanitize_title( (string) ( $f['key'] ?? ( $f['slug'] ?? $name ) ) );
		$slug = sanitize_title( (string) ( $f['slug'] ?? $name ) );

		$existing = get_posts(
			array(
				'post_type'      => Post_Type::TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_sepo_import_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$post_id = $existing ? (int) $existing[0] : 0;

		$date   = isset( $f['published'] ) ? strtotime( (string) $f['published'] ) : false;
		$status = $opts['status'];
		if ( ! empty( $f['draft'] ) && Post_Type::to_bool( $f['draft'] ) ) {
			$status = 'draft';
		}
		$data = array(
			'post_type'    => Post_Type::TYPE,
			'post_title'   => wp_strip_all_tags( $title ),
			'post_name'    => $slug,
			'post_content' => wp_kses_post( self::markdown( $body ) ),
			'post_excerpt' => sanitize_textarea_field( (string) ( $f['summary'] ?? '' ) ),
			'post_status'  => $status,
			'menu_order'   => isset( $f['order'] ) ? (int) $f['order'] : 0,
		);
		if ( $date ) {
			$data['post_date']     = wp_date( 'Y-m-d H:i:s', $date );
			$data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $date );
		}
		if ( $post_id ) {
			// Importing again updates the content but never changes the status
			// (a re-import must not unpublish live episodes).
			$data['ID'] = $post_id;
			unset( $data['post_status'] );
			$result     = wp_update_post( wp_slash( $data ), true );
		} else {
			$result = wp_insert_post( wp_slash( $data ), true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$post_id = (int) $result;
		update_post_meta( $post_id, '_sepo_import_key', $key );

		$gated = isset( $f['gated'] ) && Post_Type::to_bool( $f['gated'] );

		// Audio.
		$src = trim( (string) ( $f['src'] ?? '' ) );
		if ( '' !== $src ) {
			self::attach_audio( $post_id, $src, $path, $root, $opts['base'], $gated );
		}

		// Artwork.
		$image = trim( (string) ( $f['image'] ?? '' ) );
		if ( '' !== $image ) {
			$local = self::local( $image, $path, $root );
			if ( $local ) {
				$att = self::sideload( $local, $post_id );
				if ( $att ) {
					set_post_thumbnail( $post_id, $att );
				}
			} else {
				update_post_meta( $post_id, '_sepo_image_url', esc_url_raw( self::absolute( $image, $opts['base'] ) ) );
			}
		}

		foreach ( array( 'duration', 'episode', 'season', 'host', 'guest' ) as $field ) {
			if ( isset( $f[ $field ] ) ) {
				$val = is_array( $f[ $field ] ) ? implode( ', ', $f[ $field ] ) : (string) $f[ $field ];
				update_post_meta( $post_id, '_sepo_' . $field, sanitize_text_field( $val ) );
			}
		}
		if ( isset( $f['type'] ) ) {
			update_post_meta( $post_id, '_sepo_episode_type', Post_Type::sanitize_meta( strtolower( (string) $f['type'] ), '_sepo_episode_type' ) );
		}
		if ( isset( $f['explicit'] ) ) {
			update_post_meta( $post_id, '_sepo_explicit', Post_Type::to_bool( $f['explicit'] ) );
		}
		update_post_meta( $post_id, '_sepo_allow_download', isset( $f['download'] ) ? Post_Type::to_bool( $f['download'] ) : true );
		update_post_meta( $post_id, '_sepo_requires_login', $gated );

		// Series and tags.
		if ( ! empty( $f['group'] ) ) {
			$group = is_array( $f['group'] ) ? (string) reset( $f['group'] ) : (string) $f['group'];
			wp_set_object_terms( $post_id, sanitize_text_field( $group ), Post_Type::SERIES );
		}
		if ( ! empty( $f['tags'] ) ) {
			$tags = is_array( $f['tags'] ) ? $f['tags'] : explode( ',', (string) $f['tags'] );
			wp_set_object_terms( $post_id, array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $tags ) ) ), Post_Type::TAG );
		}

		// Totals from the old site, kept as offsets so new likes add on top.
		$like_total = $likes[ $key ] ?? ( isset( $f['likes'] ) ? (int) $f['likes'] : null );
		$dl_total   = $dls[ $key ] ?? ( isset( $f['downloads'] ) ? (int) $f['downloads'] : null );
		if ( null !== $like_total ) {
			update_post_meta( $post_id, '_sepo_like_offset', max( 0, (int) $like_total ) );
		}
		if ( null !== $dl_total ) {
			update_post_meta( $post_id, '_sepo_download_offset', max( 0, (int) $dl_total ) );
		}
		Counters::refresh_like_count( $post_id );
		Counters::refresh_download_count( $post_id );
		Episode::forget( $post_id );

		return $existing ? 'updated' : 'created';
	}

	/**
	 * Set the episode's audio from a front-matter value.
	 *
	 * @param int    $post_id Episode.
	 * @param string $src     Value of src.
	 * @param string $md      Markdown file path.
	 * @param string $root    Unzipped folder.
	 * @param string $base    Old site address.
	 * @param bool   $gated   Sign-in needed to download.
	 */
	private static function attach_audio( int $post_id, string $src, string $md, string $root, string $base, bool $gated ): void {
		$local = self::local( $src, $md, $root );
		if ( $local ) {
			// Gated episodes go to the protected folder, so the gate holds.
			if ( $gated ) {
				$current = Private_Files::path( (string) get_post_meta( $post_id, '_sepo_private_file', true ) );
				if ( $current && md5_file( $current ) === md5_file( $local ) ) {
					update_post_meta( $post_id, '_sepo_protected', true );
					return;
				}
				$stored = Private_Files::store_path( $local, wp_basename( $local ) );
				if ( ! is_wp_error( $stored ) ) {
					update_post_meta( $post_id, '_sepo_private_file', $stored );
					update_post_meta( $post_id, '_sepo_protected', true );
					update_post_meta( $post_id, '_sepo_audio_id', 0 );
					update_post_meta( $post_id, '_sepo_audio_url', '' );
					return;
				}
			}
			$att = self::sideload( $local, $post_id );
			if ( $att ) {
				update_post_meta( $post_id, '_sepo_audio_id', $att );
				update_post_meta( $post_id, '_sepo_audio_url', '' );
				update_post_meta( $post_id, '_sepo_protected', false );
				$meta = wp_get_attachment_metadata( $att );
				if ( ! get_post_meta( $post_id, '_sepo_duration', true ) && is_array( $meta ) && ! empty( $meta['length_formatted'] ) ) {
					update_post_meta( $post_id, '_sepo_duration', (string) $meta['length_formatted'] );
				}
			}
			return;
		}
		$url = self::absolute( $src, $base );
		if ( preg_match( '#^https?://#i', $url ) ) {
			update_post_meta( $post_id, '_sepo_audio_url', esc_url_raw( $url ) );
			update_post_meta( $post_id, '_sepo_audio_id', 0 );
			update_post_meta( $post_id, '_sepo_protected', false );
		}
	}

	/**
	 * A path from front matter, if the file is inside the zip.
	 *
	 * @param string $ref  Value.
	 * @param string $md   Markdown file.
	 * @param string $root Unzipped folder.
	 */
	private static function local( string $ref, string $md, string $root ): string {
		if ( preg_match( '#^[a-z]+://#i', $ref ) || '' === $ref ) {
			return '';
		}
		$ref   = ltrim( str_replace( '\\', '/', rawurldecode( $ref ) ), '/' );
		$root  = (string) realpath( $root );
		$tries = array( dirname( $md ) . '/' . $ref, $root . '/' . $ref, $root . '/' . wp_basename( $ref ) );
		foreach ( $tries as $try ) {
			$real = realpath( $try );
			if ( $real && is_file( $real ) && 0 === strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
				return $real;
			}
		}
		// Last chance: the same file name anywhere in the zip.
		foreach ( self::find_files( $root ) as $file ) {
			if ( wp_basename( $file ) === wp_basename( $ref ) ) {
				return $file;
			}
		}
		return '';
	}

	/**
	 * Make a relative address absolute against the old site.
	 *
	 * @param string $ref  Value.
	 * @param string $base Old site address.
	 */
	private static function absolute( string $ref, string $base ): string {
		if ( preg_match( '#^https?://#i', $ref ) || '' === $base ) {
			return $ref;
		}
		return trailingslashit( $base ) . ltrim( $ref, '/' );
	}

	/**
	 * Copy a local file into the Media Library.
	 *
	 * @param string $file    Path.
	 * @param int    $post_id Parent.
	 */
	private static function sideload( string $file, int $post_id ): int {
		static $done = array();
		$hash        = md5_file( $file );
		if ( isset( $done[ $hash ] ) ) {
			return $done[ $hash ];
		}
		// Imported before (same zip again)? Reuse that attachment.
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_sepo_import_hash', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( $found ) {
			$done[ $hash ] = (int) $found[0];
			return (int) $found[0];
		}
		// Copy first: media_handle_sideload moves the file it is given.
		$copy = wp_tempnam( wp_basename( $file ) );
		if ( ! $copy || ! copy( $file, $copy ) ) {
			return 0;
		}
		$id = media_handle_sideload(
			array(
				'name'     => wp_basename( $file ),
				'tmp_name' => $copy,
			),
			$post_id
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $copy );
			return 0;
		}
		update_post_meta( (int) $id, '_sepo_import_hash', $hash );
		$done[ $hash ] = (int) $id;
		return (int) $id;
	}

	/**
	 * Read a counts file: {"key": 3}, {"key": {"count": 3}}, {"key": [users]}
	 * or [{"key": "...", "count": 3}].
	 *
	 * @param string[] $files All files.
	 * @param string   $name  File name.
	 * @return array<string,int>
	 */
	private static function read_counts( array $files, string $name ): array {
		foreach ( $files as $file ) {
			if ( strtolower( wp_basename( $file ) ) !== $name ) {
				continue;
			}
			$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( ! is_array( $data ) ) {
				return array();
			}
			$out = array();
			foreach ( $data as $k => $v ) {
				if ( is_array( $v ) && isset( $v['key'] ) ) {
					$k = (string) $v['key'];
					$v = $v['count'] ?? ( $v['total'] ?? 0 );
				}
				if ( is_array( $v ) ) {
					$n = isset( $v['count'] ) ? (int) $v['count'] : ( isset( $v['total'] ) ? (int) $v['total'] : count( $v ) );
				} else {
					$n = (int) $v;
				}
				$out[ sanitize_title( (string) $k ) ] = $n;
			}
			return $out;
		}
		return array();
	}

	/**
	 * Map front-matter names onto canonical field names.
	 *
	 * @param array $meta Raw front matter.
	 */
	public static function normalise( array $meta ): array {
		$lower = array();
		foreach ( $meta as $k => $v ) {
			$lower[ strtolower( str_replace( array( '-', ' ' ), '_', (string) $k ) ) ] = $v;
		}
		$out = array();
		foreach ( self::ALIASES as $field => $names ) {
			foreach ( $names as $n ) {
				if ( array_key_exists( $n, $lower ) && '' !== $lower[ $n ] && null !== $lower[ $n ] ) {
					$out[ $field ] = $lower[ $n ];
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * Split "---\nfront matter\n---\nbody".
	 *
	 * @param string $raw File contents.
	 * @return array{0:array,1:string}
	 */
	public static function split( string $raw ): array {
		$raw = (string) preg_replace( '/^\xEF\xBB\xBF/', '', str_replace( "\r\n", "\n", $raw ) );
		if ( preg_match( '/^---[ \t]*\n(.*?)\n---[ \t]*(?:\n|$)(.*)$/s', $raw, $m ) ) {
			return array( self::yaml( $m[1] ), $m[2] );
		}
		return array( array(), $raw );
	}

	/**
	 * A small YAML reader for front matter: key: value, quoted strings,
	 * [inline, lists], "- item" lists and "key: |" blocks.
	 *
	 * @param string $text Front matter.
	 */
	public static function yaml( string $text ): array {
		$out   = array();
		$lines = explode( "\n", $text );
		$count = count( $lines );
		for ( $i = 0; $i < $count; $i++ ) {
			$line = rtrim( $lines[ $i ] );
			if ( '' === trim( $line ) || '#' === ltrim( $line )[0] ) {
				continue;
			}
			if ( ! preg_match( '/^([A-Za-z0-9_\- ]+):\s*(.*)$/', $line, $m ) ) {
				continue;
			}
			$key = trim( $m[1] );
			$val = trim( $m[2] );

			if ( '|' === $val || '>' === $val || '|-' === $val || '>-' === $val ) {
				$block = array();
				while ( $i + 1 < $count && ( '' === trim( $lines[ $i + 1 ] ) || preg_match( '/^\s+/', $lines[ $i + 1 ] ) ) ) {
					$block[] = trim( $lines[ ++$i ] );
				}
				$out[ $key ] = '>' === $val[0] ? trim( implode( ' ', $block ) ) : trim( implode( "\n", $block ) );
				continue;
			}
			if ( '' === $val ) {
				$items = array();
				while ( $i + 1 < $count && preg_match( '/^\s*-\s+(.*)$/', $lines[ $i + 1 ], $li ) ) {
					$items[] = self::scalar( $li[1] );
					++$i;
				}
				$out[ $key ] = $items ? $items : '';
				continue;
			}
			if ( '[' === $val[0] && ']' === substr( $val, -1 ) ) {
				$inner       = trim( substr( $val, 1, -1 ) );
				$out[ $key ] = '' === $inner ? array() : array_map( array( __CLASS__, 'scalar' ), str_getcsv( $inner, ',', '"', '\\' ) );
				continue;
			}
			$out[ $key ] = self::scalar( $val );
		}
		return $out;
	}

	/**
	 * One YAML value.
	 *
	 * @param string $v Value.
	 * @return mixed
	 */
	private static function scalar( string $v ) {
		$v = trim( $v );
		if ( strlen( $v ) >= 2 && ( ( '"' === $v[0] && '"' === substr( $v, -1 ) ) || ( "'" === $v[0] && "'" === substr( $v, -1 ) ) ) ) {
			return stripcslashes( substr( $v, 1, -1 ) );
		}
		$v = (string) preg_replace( '/\s+#.*$/', '', $v );
		$l = strtolower( $v );
		if ( in_array( $l, array( 'true', 'yes', 'on' ), true ) ) {
			return true;
		}
		if ( in_array( $l, array( 'false', 'no', 'off' ), true ) ) {
			return false;
		}
		if ( in_array( $l, array( 'null', '~' ), true ) ) {
			return '';
		}
		return $v;
	}

	/**
	 * Small Markdown to HTML: headings, paragraphs, lists, quotes, code,
	 * links, images, bold, italic, rules. The result goes through wp_kses_post.
	 *
	 * @param string $md Markdown.
	 */
	public static function markdown( string $md ): string {
		$md     = str_replace( "\r\n", "\n", trim( $md ) );
		$blocks = array();
		$code   = array();

		// Fenced code first, so nothing inside is touched.
		$md = (string) preg_replace_callback(
			'/^```[^\n]*\n(.*?)\n```$/ms',
			static function ( $m ) use ( &$code ) {
				$code[] = '<pre><code>' . esc_html( $m[1] ) . '</code></pre>';
				return "\n\nSPCODEBLOCK" . ( count( $code ) - 1 ) . "SPCODEBLOCK\n\n";
			},
			$md
		);

		foreach ( preg_split( '/\n{2,}/', $md ) as $chunk ) {
			$chunk = trim( $chunk );
			if ( '' === $chunk ) {
				continue;
			}
			if ( preg_match( '/^SPCODEBLOCK(\d+)SPCODEBLOCK$/', $chunk, $m ) ) {
				$blocks[] = $code[ (int) $m[1] ];
				continue;
			}
			if ( preg_match( '/^(#{1,6})\s+(.+)$/', $chunk, $m ) && false === strpos( $chunk, "\n" ) ) {
				$level    = min( 6, strlen( $m[1] ) + 1 ); // # in the notes becomes h2: the page title is h1.
				$blocks[] = '<h' . $level . '>' . self::inline( $m[2] ) . '</h' . $level . '>';
				continue;
			}
			if ( preg_match( '/^(-{3,}|\*{3,}|_{3,})$/', $chunk ) ) {
				$blocks[] = '<hr>';
				continue;
			}
			$lines = explode( "\n", $chunk );
			if ( preg_match( '/^\s*[-*+]\s+/', $lines[0] ) || preg_match( '/^\s*\d+[.)]\s+/', $lines[0] ) ) {
				$ordered = (bool) preg_match( '/^\s*\d+[.)]\s+/', $lines[0] );
				$items   = array();
				foreach ( $lines as $line ) {
					if ( preg_match( '/^\s*(?:[-*+]|\d+[.)])\s+(.*)$/', $line, $m ) ) {
						$items[] = $m[1];
					} elseif ( $items ) {
						$items[ count( $items ) - 1 ] .= ' ' . trim( $line );
					}
				}
				$tag      = $ordered ? 'ol' : 'ul';
				$blocks[] = '<' . $tag . '>' . implode( '', array_map( static fn( $i ) => '<li>' . self::inline( $i ) . '</li>', $items ) ) . '</' . $tag . '>';
				continue;
			}
			if ( '>' === $chunk[0] ) {
				$text     = implode( "\n", array_map( static fn( $l ) => ltrim( substr( ltrim( $l ), 1 ) ), $lines ) );
				$blocks[] = '<blockquote>' . self::markdown( $text ) . '</blockquote>';
				continue;
			}
			$blocks[] = '<p>' . implode( "<br>\n", array_map( array( __CLASS__, 'inline' ), $lines ) ) . '</p>';
		}
		return implode( "\n\n", $blocks );
	}

	/**
	 * Inline Markdown.
	 *
	 * @param string $t Text.
	 */
	private static function inline( string $t ): string {
		$t     = esc_html( $t );
		$codes = array();
		$t     = (string) preg_replace_callback(
			'/`([^`]+)`/',
			static function ( $m ) use ( &$codes ) {
				$codes[] = '<code>' . $m[1] . '</code>';
				return "\x01" . ( count( $codes ) - 1 ) . "\x01";
			},
			$t
		);
		$t = (string) preg_replace_callback(
			'/!\[([^\]]*)\]\(([^)\s]+)(?:\s+&quot;[^&]*&quot;)?\)/',
			static fn( $m ) => '<img src="' . esc_url( html_entity_decode( $m[2] ) ) . '" alt="' . $m[1] . '">',
			$t
		);
		$t = (string) preg_replace_callback(
			'/\[([^\]]+)\]\(([^)\s]+)(?:\s+&quot;[^&]*&quot;)?\)/',
			static fn( $m ) => '<a href="' . esc_url( html_entity_decode( $m[2] ) ) . '">' . $m[1] . '</a>',
			$t
		);
		$t = (string) preg_replace( '/(\*\*|__)(?=\S)(.+?)(?<=\S)\1/', '<strong>$2</strong>', $t );
		$t = (string) preg_replace( '/(?<![\w*])\*(?=\S)(.+?)(?<=\S)\*(?![\w*])/', '<em>$1</em>', $t );
		$t = (string) preg_replace( '/(?<![\w_])_(?=\S)(.+?)(?<=\S)_(?![\w_])/', '<em>$1</em>', $t );
		return (string) preg_replace_callback( '/\x01(\d+)\x01/', static fn( $m ) => $codes[ (int) $m[1] ], $t );
	}

	/**
	 * Every file under a folder.
	 *
	 * @param string $dir Folder.
	 * @return string[]
	 */
	private static function find_files( string $dir ): array {
		$out = array();
		$it  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( $file->isFile() && false === strpos( $file->getPathname(), '__MACOSX' ) ) {
				$out[] = $file->getPathname();
			}
		}
		sort( $out );
		return $out;
	}

	/**
	 * Remove the working folder.
	 *
	 * @param string $dir Folder.
	 */
	private static function rmdir( string $dir ): void {
		global $wp_filesystem;
		if ( $wp_filesystem ) {
			$wp_filesystem->delete( $dir, true );
		}
	}
}
