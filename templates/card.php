<?php
/**
 * One episode in a list.
 *
 * The play button hands the episode to the player dock at the bottom of
 * the screen (podcast.js). Without JavaScript it links to the episode.
 *
 * Copy to yourtheme/sepo-podcast/card.php to change it.
 *
 * @package SepoPodcast
 *
 * @var array $args { episode, liked, compact }
 */

use SepoPodcast\Render;

defined( 'ABSPATH' ) || exit;

$sepo_podcast_ep      = $args['episode'];
$sepo_podcast_compact = ! empty( $args['compact'] );
$sepo_podcast_number  = Render::number_label( $sepo_podcast_ep );
?>
<article class="sp-card<?php echo $sepo_podcast_compact ? ' is-compact' : ''; ?>" data-sp-card="<?php echo esc_attr( (string) $sepo_podcast_ep['id'] ); ?>">
	<a class="sp-card-art" href="<?php echo esc_url( $sepo_podcast_ep['url'] ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( $sepo_podcast_ep['image'] ) : ?>
			<img src="<?php echo esc_url( $sepo_podcast_ep['image'] ); ?>" alt="" width="96" height="96" loading="lazy" decoding="async">
		<?php else : ?>
			<span class="sp-art-blank"><?php Render::the_icon( 'mic' ); ?></span>
		<?php endif; ?>
	</a>
	<div class="sp-card-body">
		<p class="sp-meta">
			<?php if ( $sepo_podcast_number ) : ?>
				<span><?php echo esc_html( $sepo_podcast_number ); ?></span>
			<?php endif; ?>
			<?php if ( ! $sepo_podcast_compact ) : ?>
				<span><?php echo esc_html( $sepo_podcast_ep['date'] ); ?></span>
			<?php endif; ?>
			<?php if ( $sepo_podcast_ep['duration'] ) : ?>
				<span><?php echo esc_html( $sepo_podcast_ep['duration'] ); ?></span>
			<?php endif; ?>
		</p>
		<h3 class="sp-card-title"><a href="<?php echo esc_url( $sepo_podcast_ep['url'] ); ?>"><?php echo esc_html( $sepo_podcast_ep['title'] ); ?></a></h3>
		<?php if ( ! $sepo_podcast_compact && $sepo_podcast_ep['excerpt'] ) : ?>
			<p class="sp-excerpt"><?php echo esc_html( $sepo_podcast_ep['excerpt'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! $sepo_podcast_compact ) : ?>
			<p class="sp-stats">
				<span title="<?php esc_attr_e( 'Likes', 'sepo-podcast' ); ?>" class="<?php echo in_array( $sepo_podcast_ep['id'], (array) ( $args['liked'] ?? array() ), true ) ? 'is-liked' : ''; ?>"><?php Render::the_icon( 'heart' ); ?><?php echo esc_html( number_format_i18n( $sepo_podcast_ep['likes'] ) ); ?></span>
				<span title="<?php esc_attr_e( 'Downloads', 'sepo-podcast' ); ?>"><?php Render::the_icon( 'download' ); ?><?php echo esc_html( number_format_i18n( $sepo_podcast_ep['downloads'] ) ); ?></span>
			</p>
		<?php endif; ?>
	</div>
	<?php if ( $sepo_podcast_ep['has_audio'] ) : ?>
		<a class="sp-btn sp-card-play" href="<?php echo esc_url( $sepo_podcast_ep['url'] ); ?>"
			data-sp-play
			data-id="<?php echo esc_attr( (string) $sepo_podcast_ep['id'] ); ?>"
			data-src="<?php echo esc_url( $sepo_podcast_ep['src'] ); ?>"
			data-title="<?php echo esc_attr( $sepo_podcast_ep['title'] ); ?>"
			data-url="<?php echo esc_url( $sepo_podcast_ep['url'] ); ?>"
			data-image="<?php echo esc_url( $sepo_podcast_ep['image'] ); ?>"
			data-duration="<?php echo esc_attr( $sepo_podcast_ep['duration'] ); ?>"
			<?php /* translators: %s: episode title. */ ?>
			aria-label="<?php echo esc_attr( sprintf( __( 'Play %s', 'sepo-podcast' ), $sepo_podcast_ep['title'] ) ); ?>">
			<?php Render::the_icon( 'play' ); ?><?php Render::the_icon( 'pause' ); ?>
		</a>
	<?php endif; ?>
</article>
