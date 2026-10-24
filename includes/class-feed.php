<?php
/**
 * Podcast RSS feed at /feed/podcast-feed/ (or ?feed=podcast-feed), with Apple
 * Podcasts (itunes:) and Podcasting 2.0 (podcast:) tags.
 *
 * Add ?series=slug for a feed of one series.
 * Episodes that need sign-in to download are left out.
 *
 * @package SepoPodcast
 */

namespace SepoPodcast;

defined( 'ABSPATH' ) || exit;

/**
 * The feed.
 */
final class Feed {

	const NAME = 'podcast-feed';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'add_feed' ) );
		add_action( 'wp_head', array( __CLASS__, 'head_link' ) );
	}

	/**
	 * Register the feed.
	 */
	public static function add_feed(): void {
		add_feed( self::NAME, array( __CLASS__, 'render' ) );
	}

	/**
	 * Feed address.
	 *
	 * @param string $series Optional series slug.
	 */
	public static function url( string $series = '' ): string {
		$url = get_feed_link( self::NAME );
		return '' !== $series ? add_query_arg( 'series', rawurlencode( $series ), $url ) : $url;
	}

	/**
	 * Let feed readers find the podcast from episode pages and the list page.
	 */
	public static function head_link(): void {
		$list = (int) Settings::get( 'list_page' );
		if ( ! is_singular( Post_Type::TYPE ) && ! ( $list && is_page( $list ) ) ) {
			return;
		}
		printf(
			'<link rel="alternate" type="application/rss+xml" title="%s" href="%s">' . "\n",
			esc_attr( self::title() ),
			esc_url( self::url() )
		);
	}

	/**
	 * Show title.
	 */
	public static function title(): string {
		$t = (string) Settings::get( 'feed_title' );
		return '' !== $t ? $t : (string) get_bloginfo( 'name' );
	}

	/**
	 * Apple Podcasts categories and their subcategories.
	 *
	 * @return array<string,string[]>
	 */
	public static function categories(): array {
		return array(
			'Arts'                    => array( 'Books', 'Design', 'Fashion & Beauty', 'Food', 'Performing Arts', 'Visual Arts' ),
			'Business'                => array( 'Careers', 'Entrepreneurship', 'Investing', 'Management', 'Marketing', 'Non-Profit' ),
			'Comedy'                  => array( 'Comedy Interviews', 'Improv', 'Stand-Up' ),
			'Education'               => array( 'Courses', 'How To', 'Language Learning', 'Self-Improvement' ),
			'Fiction'                 => array( 'Comedy Fiction', 'Drama', 'Science Fiction' ),
			'Government'              => array(),
			'History'                 => array(),
			'Health & Fitness'        => array( 'Alternative Health', 'Fitness', 'Medicine', 'Mental Health', 'Nutrition', 'Sexuality' ),
			'Kids & Family'           => array( 'Education for Kids', 'Parenting', 'Pets & Animals', 'Stories for Kids' ),
			'Leisure'                 => array( 'Animation & Manga', 'Automotive', 'Aviation', 'Crafts', 'Games', 'Hobbies', 'Home & Garden', 'Video Games' ),
			'Music'                   => array( 'Music Commentary', 'Music History', 'Music Interviews' ),
			'News'                    => array( 'Business News', 'Daily News', 'Entertainment News', 'News Commentary', 'Politics', 'Sports News', 'Tech News' ),
			'Religion & Spirituality' => array( 'Buddhism', 'Christianity', 'Hinduism', 'Islam', 'Judaism', 'Religion', 'Spirituality' ),
			'Science'                 => array( 'Astronomy', 'Chemistry', 'Earth Sciences', 'Life Sciences', 'Mathematics', 'Natural Sciences', 'Nature', 'Physics', 'Social Sciences' ),
			'Society & Culture'       => array( 'Documentary', 'Personal Journals', 'Philosophy', 'Places & Travel', 'Relationships' ),
			'Sports'                  => array( 'Baseball', 'Basketball', 'Cricket', 'Fantasy Sports', 'Football', 'Golf', 'Hockey', 'Rugby', 'Running', 'Soccer', 'Swimming', 'Tennis', 'Volleyball', 'Wilderness', 'Wrestling' ),
			'Technology'              => array(),
			'True Crime'              => array(),
			'TV & Film'               => array( 'After Shows', 'Film History', 'Film Interviews', 'Film Reviews', 'TV Reviews' ),
		);
	}

	/**
	 * Podcasting 2.0 GUID: UUID v5 of the feed address without its scheme.
	 *
	 * @param string $url Feed address.
	 */
	public static function guid( string $url ): string {
		$name = untrailingslashit( (string) preg_replace( '#^[a-z]+://#i', '', $url ) );
		$ns   = pack( 'H*', 'ead4c236bf5858c6a2c6a6b28d128cb6' );
		$hash = sha1( $ns . $name );
		return sprintf(
			'%08s-%04s-%04x-%04x-%12s',
			substr( $hash, 0, 8 ),
			substr( $hash, 8, 4 ),
			( hexdec( substr( $hash, 12, 4 ) ) & 0x0fff ) | 0x5000,
			( hexdec( substr( $hash, 16, 4 ) ) & 0x3fff ) | 0x8000,
			substr( $hash, 20, 12 )
		);
	}

	/**
	 * Episodes for the feed.
	 *
	 * @param string $series Series slug, or ''.
	 * @return \WP_Post[]
	 */
	public static function episodes( string $series ): array {
		$args = array(
			'post_type'           => Post_Type::TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => (int) Settings::get( 'feed_limit' ),
			'orderby'             => 'date',
			'order'               => 'DESC',
			'has_password'        => false,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => '_sepo_requires_login',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_sepo_requires_login',
					'value'   => array( '', '0' ),
					'compare' => 'IN',
				),
			),
		);
		if ( '' !== $series ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Post_Type::SERIES,
					'field'    => 'slug',
					'terms'    => $series,
				),
			);
		}
		/**
		 * Filter the feed query.
		 *
		 * @param array $args WP_Query arguments.
		 */
		$q = new \WP_Query( (array) apply_filters( 'sepo_podcast_feed_query', $args ) );
		return $q->posts;
	}

	/**
	 * Print the feed.
	 */
	public static function render(): void {
		$s      = Settings::all();
		$series = isset( $_GET['series'] ) ? sanitize_title( wp_unslash( $_GET['series'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public feed filter.
		$term   = '' !== $series ? get_term_by( 'slug', $series, Post_Type::SERIES ) : false;
		if ( '' !== $series && ! $term ) {
			$series = '';
		}

		$self    = self::url( $series );
		$title   = self::title() . ( $term ? ' — ' . $term->name : '' );
		$desc    = '' !== $s['feed_description'] ? $s['feed_description'] : (string) get_bloginfo( 'description' );
		if ( $term && '' !== $term->description ) {
			$desc = $term->description;
		}
		$author  = '' !== $s['feed_author'] ? $s['feed_author'] : (string) get_bloginfo( 'name' );
		$lang    = '' !== $s['feed_language'] ? $s['feed_language'] : (string) get_bloginfo( 'language' );
		$link    = Render::list_url();
		$link    = '' !== $link ? $link : home_url( '/' );
		$image   = (string) $s['feed_image'];
		if ( '' === $image && has_site_icon() ) {
			$image = (string) get_site_icon_url( 1400 );
		}
		$posts   = self::episodes( $series );
		$updated = $posts ? get_post_modified_time( 'r', true, $posts[0] ) : gmdate( 'r' );

		header( 'Content-Type: application/rss+xml; charset=' . get_option( 'blog_charset' ), true );
		echo '<?xml version="1.0" encoding="' . esc_attr( (string) get_option( 'blog_charset' ) ) . '"?>' . "\n";
		?>
<rss version="2.0"
	xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
	xmlns:podcast="https://podcastindex.org/namespace/1.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<title><?php echo esc_html( self::plain( $title ) ); ?></title>
	<link><?php echo esc_url( $link ); ?></link>
	<atom:link href="<?php echo esc_url( $self ); ?>" rel="self" type="application/rss+xml" />
	<description><?php echo esc_html( self::plain( $desc ) ); ?></description>
	<language><?php echo esc_html( strtolower( $lang ) ); ?></language>
	<lastBuildDate><?php echo esc_html( (string) $updated ); ?></lastBuildDate>
	<generator><?php echo esc_html( 'Sepo Podcast ' . SEPO_PODCAST_VERSION ); ?></generator>
		<?php if ( '' !== $s['feed_copyright'] ) : ?>
	<copyright><?php echo esc_html( self::plain( $s['feed_copyright'] ) ); ?></copyright>
		<?php endif; ?>
	<itunes:author><?php echo esc_html( self::plain( $author ) ); ?></itunes:author>
	<itunes:summary><?php echo esc_html( self::plain( $desc ) ); ?></itunes:summary>
	<itunes:type><?php echo esc_html( 'serial' === $s['feed_type'] ? 'serial' : 'episodic' ); ?></itunes:type>
	<itunes:explicit><?php echo $s['feed_explicit'] ? 'true' : 'false'; ?></itunes:explicit>
		<?php if ( '' !== $s['feed_owner_name'] || '' !== $s['feed_owner_email'] ) : ?>
	<itunes:owner>
		<itunes:name><?php echo esc_html( self::plain( '' !== $s['feed_owner_name'] ? $s['feed_owner_name'] : $author ) ); ?></itunes:name>
		<itunes:email><?php echo esc_html( $s['feed_owner_email'] ); ?></itunes:email>
	</itunes:owner>
		<?php endif; ?>
		<?php if ( '' !== $image ) : ?>
	<itunes:image href="<?php echo esc_url( $image ); ?>" />
	<image>
		<url><?php echo esc_url( $image ); ?></url>
		<title><?php echo esc_html( self::plain( $title ) ); ?></title>
		<link><?php echo esc_url( $link ); ?></link>
	</image>
		<?php endif; ?>
		<?php if ( '' !== $s['feed_category'] ) : ?>
	<itunes:category text="<?php echo esc_attr( $s['feed_category'] ); ?>">
			<?php if ( '' !== $s['feed_subcategory'] ) : ?>
		<itunes:category text="<?php echo esc_attr( $s['feed_subcategory'] ); ?>" />
			<?php endif; ?>
	</itunes:category>
		<?php endif; ?>
	<podcast:guid><?php echo esc_html( self::guid( $self ) ); ?></podcast:guid>
	<podcast:locked<?php echo '' !== $s['feed_owner_email'] ? ' owner="' . esc_attr( $s['feed_owner_email'] ) . '"' : ''; ?>><?php echo $s['feed_locked'] ? 'yes' : 'no'; ?></podcast:locked>
		<?php
		foreach ( $posts as $post ) {
			self::item( $post, $author );
		}
		?>
</channel>
</rss>
		<?php
	}

	/**
	 * Text for XML: named HTML entities (&hellip;, &nbsp;) are not valid XML,
	 * so decode them; esc_html() then encodes only what XML needs.
	 *
	 * @param string $text Text.
	 */
	private static function plain( string $text ): string {
		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * One <item>.
	 *
	 * @param \WP_Post $post   Episode.
	 * @param string   $author Show author.
	 */
	private static function item( \WP_Post $post, string $author ): void {
		$ep  = Episode::get( $post );
		$src = Episode::source( (int) $post->ID );
		if ( ! $ep || '' === $src['kind'] ) {
			return;
		}
		$enclosure = Download::url( (int) $post->ID, 'listen', '' !== $src['name'] ? $src['name'] : 'episode.mp3' );
		$content   = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
		$summary   = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $content ), 55 );
		$secs      = Episode::seconds( $ep['duration'] );
		$who       = trim( implode( ', ', array_filter( array( $ep['host'], $ep['guest'] ) ) ) );
		$guid      = get_the_guid( $post );
		?>
	<item>
		<title><?php echo esc_html( self::plain( $ep['title'] ) ); ?></title>
		<link><?php echo esc_url( $ep['url'] ); ?></link>
		<guid isPermaLink="false"><?php echo esc_html( '' !== $guid ? $guid : 'sepo-podcast-' . $post->ID ); ?></guid>
		<pubDate><?php echo esc_html( (string) get_post_time( 'r', true, $post ) ); ?></pubDate>
		<description><![CDATA[<?php echo wp_kses_post( str_replace( ']]>', ']]&gt;', $summary ) ); ?>]]></description>
		<content:encoded><![CDATA[<?php echo wp_kses_post( str_replace( ']]>', ']]&gt;', (string) $content ) ); ?>]]></content:encoded>
		<enclosure url="<?php echo esc_url( $enclosure ); ?>" length="<?php echo esc_attr( (string) (int) $src['length'] ); ?>" type="<?php echo esc_attr( $src['mime'] ); ?>" />
		<itunes:title><?php echo esc_html( self::plain( $ep['title'] ) ); ?></itunes:title>
		<itunes:author><?php echo esc_html( self::plain( '' !== $who ? $who : $author ) ); ?></itunes:author>
		<itunes:summary><?php echo esc_html( self::plain( wp_strip_all_tags( $summary ) ) ); ?></itunes:summary>
		<itunes:explicit><?php echo $ep['explicit'] ? 'true' : 'false'; ?></itunes:explicit>
		<itunes:episodeType><?php echo esc_html( $ep['episode_type'] ); ?></itunes:episodeType>
		<?php if ( $secs > 0 ) : ?>
		<itunes:duration><?php echo esc_html( (string) $secs ); ?></itunes:duration>
		<?php endif; ?>
		<?php if ( ctype_digit( $ep['episode'] ) ) : ?>
		<itunes:episode><?php echo esc_html( $ep['episode'] ); ?></itunes:episode>
		<podcast:episode><?php echo esc_html( $ep['episode'] ); ?></podcast:episode>
		<?php endif; ?>
		<?php if ( ctype_digit( $ep['season'] ) ) : ?>
		<itunes:season><?php echo esc_html( $ep['season'] ); ?></itunes:season>
		<podcast:season><?php echo esc_html( $ep['season'] ); ?></podcast:season>
		<?php endif; ?>
		<?php if ( $ep['image'] ) : ?>
		<itunes:image href="<?php echo esc_url( $ep['image'] ); ?>" />
		<?php endif; ?>
		<?php if ( '' !== $ep['host'] ) : ?>
		<podcast:person role="host"><?php echo esc_html( self::plain( $ep['host'] ) ); ?></podcast:person>
		<?php endif; ?>
		<?php if ( '' !== $ep['guest'] ) : ?>
		<podcast:person role="guest"><?php echo esc_html( self::plain( $ep['guest'] ) ); ?></podcast:person>
		<?php endif; ?>
	</item>
		<?php
	}
}
