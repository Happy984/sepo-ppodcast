<?php
/**
 * Single episode: header with artwork and player, the post body, actions,
 * tags and more episodes.
 *
 * Copy to yourtheme/sepo-podcast/episode.php to change it.
 *
 * @package SepoPodcast
 *
 * @var array $args { episode, content, liked, related, list_url, show_art }
 */

use SepoPodcast\Render;

defined( 'ABSPATH' ) || exit;

$sepo_podcast_ep      = $args['episode'];
$sepo_podcast_number  = Render::number_label( $sepo_podcast_ep );
$sepo_podcast_series  = $sepo_podcast_ep['series'] ? $sepo_podcast_ep['series'][0] : null;
$sepo_podcast_related = $args['related'];
?>
<div class="sepo-podcast sp-episode">
	<header class="sp-hero">
		<?php if ( $args['show_art'] && $sepo_podcast_ep['image'] ) : ?>
			<img class="sp-art" src="<?php echo esc_url( $sepo_podcast_ep['image'] ); ?>" alt="" width="180" height="180" loading="eager" decoding="async">
		<?php endif; ?>
		<div class="sp-hero-body">
			<p class="sp-kicker">
				<?php if ( $sepo_podcast_series ) : ?>
					<a href="<?php echo esc_url( $args['list_url'] ? add_query_arg( 'sp_series', rawurlencode( $sepo_podcast_series['slug'] ), $args['list_url'] ) : (string) get_term_link( $sepo_podcast_series['id'] ) ); ?>"><?php echo esc_html( $sepo_podcast_series['name'] ); ?></a>
				<?php endif; ?>
				<?php if ( $sepo_podcast_number ) : ?>
					<span><?php echo esc_html( $sepo_podcast_number ); ?></span>
				<?php endif; ?>
				<span><?php echo esc_html( $sepo_podcast_ep['date'] ); ?></span>
				<?php if ( $sepo_podcast_ep['duration'] ) : ?>
					<span><?php echo esc_html( $sepo_podcast_ep['duration'] ); ?></span>
				<?php endif; ?>
				<?php if ( $sepo_podcast_ep['explicit'] ) : ?>
					<span class="sp-badge" title="<?php esc_attr_e( 'Explicit', 'sepo-podcast' ); ?>">E</span>
				<?php endif; ?>
			</p>
			<?php if ( $sepo_podcast_ep['host'] || $sepo_podcast_ep['guest'] ) : ?>
				<p class="sp-people">
					<?php if ( $sepo_podcast_ep['host'] ) : ?>
						<span><?php esc_html_e( 'Host', 'sepo-podcast' ); ?> <strong><?php echo esc_html( $sepo_podcast_ep['host'] ); ?></strong></span>
					<?php endif; ?>
					<?php if ( $sepo_podcast_ep['guest'] ) : ?>
						<span><?php esc_html_e( 'Guest', 'sepo-podcast' ); ?> <strong><?php echo esc_html( $sepo_podcast_ep['guest'] ); ?></strong></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php Render::the_player( $sepo_podcast_ep ); ?>
		</div>
	</header>

	<div class="sp-actions">
		<?php
		Render::render(
			'actions',
			array(
				'episode' => $sepo_podcast_ep,
				'liked'   => $args['liked'],
			)
		);
		?>
		<?php if ( $args['list_url'] ) : ?>
			<a class="sp-btn sp-pill sp-all" href="<?php echo esc_url( $args['list_url'] ); ?>"><?php Render::the_icon( 'list' ); ?><span><?php esc_html_e( 'All episodes', 'sepo-podcast' ); ?></span></a>
		<?php endif; ?>
	</div>

	<div class="sp-body">
		<?php echo $args['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the post content, already filtered by the_content. ?>
	</div>

	<?php if ( $sepo_podcast_ep['tags'] ) : ?>
		<p class="sp-tags">
			<?php foreach ( $sepo_podcast_ep['tags'] as $sepo_podcast_tag ) : ?>
				<a href="<?php echo esc_url( (string) get_term_link( $sepo_podcast_tag['id'] ) ); ?>">#<?php echo esc_html( $sepo_podcast_tag['name'] ); ?></a>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>

	<?php if ( $sepo_podcast_related ) : ?>
		<section class="sp-related" aria-labelledby="sp-related-<?php echo esc_attr( (string) $sepo_podcast_ep['id'] ); ?>">
			<h2 id="sp-related-<?php echo esc_attr( (string) $sepo_podcast_ep['id'] ); ?>"><?php esc_html_e( 'More episodes', 'sepo-podcast' ); ?></h2>
			<div class="sp-cards sp-cards-compact">
				<?php
				foreach ( $sepo_podcast_related as $sepo_podcast_item ) {
					Render::render(
						'card',
						array(
							'episode' => $sepo_podcast_item,
							'liked'   => false,
							'compact' => true,
						)
					);
				}
				?>
			</div>
		</section>
	<?php endif; ?>
</div>
