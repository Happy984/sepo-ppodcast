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

$sp_ep      = $args['episode'];
$sp_compact = ! empty( $args['compact'] );
$sp_number  = Render::number_label( $sp_ep );
?>
<article class="sp-card<?php echo $sp_compact ? ' is-compact' : ''; ?>" data-sp-card="<?php echo esc_attr( (string) $sp_ep['id'] ); ?>">
	<a class="sp-card-art" href="<?php echo esc_url( $sp_ep['url'] ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( $sp_ep['image'] ) : ?>
			<img src="<?php echo esc_url( $sp_ep['image'] ); ?>" alt="" width="96" height="96" loading="lazy" decoding="async">
		<?php else : ?>
			<span class="sp-art-blank"><?php Render::the_icon( 'mic' ); ?></span>
		<?php endif; ?>
	</a>
	<div class="sp-card-body">
		<p class="sp-meta">
			<?php if ( $sp_number ) : ?>
				<span><?php echo esc_html( $sp_number ); ?></span>
			<?php endif; ?>
			<span><?php echo esc_html( $sp_ep['date'] ); ?></span>
			<?php if ( $sp_ep['duration'] ) : ?>
				<span><?php echo esc_html( $sp_ep['duration'] ); ?></span>
			<?php endif; ?>
		</p>
		<h3 class="sp-card-title"><a href="<?php echo esc_url( $sp_ep['url'] ); ?>"><?php echo esc_html( $sp_ep['title'] ); ?></a></h3>
		<?php if ( ! $sp_compact && $sp_ep['excerpt'] ) : ?>
			<p class="sp-excerpt"><?php echo esc_html( $sp_ep['excerpt'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! $sp_compact ) : ?>
			<p class="sp-stats">
				<span title="<?php esc_attr_e( 'Likes', 'sepo-podcast' ); ?>" class="<?php echo in_array( $sp_ep['id'], (array) ( $args['liked'] ?? array() ), true ) ? 'is-liked' : ''; ?>"><?php Render::the_icon( 'heart' ); ?><?php echo esc_html( number_format_i18n( $sp_ep['likes'] ) ); ?></span>
				<span title="<?php esc_attr_e( 'Downloads', 'sepo-podcast' ); ?>"><?php Render::the_icon( 'download' ); ?><?php echo esc_html( number_format_i18n( $sp_ep['downloads'] ) ); ?></span>
			</p>
		<?php endif; ?>
	</div>
	<?php if ( $sp_ep['has_audio'] ) : ?>
		<a class="sp-btn sp-card-play" href="<?php echo esc_url( $sp_ep['url'] ); ?>"
			data-sp-play
			data-id="<?php echo esc_attr( (string) $sp_ep['id'] ); ?>"
			data-src="<?php echo esc_url( $sp_ep['src'] ); ?>"
			data-title="<?php echo esc_attr( $sp_ep['title'] ); ?>"
			data-url="<?php echo esc_url( $sp_ep['url'] ); ?>"
			data-image="<?php echo esc_url( $sp_ep['image'] ); ?>"
			data-duration="<?php echo esc_attr( $sp_ep['duration'] ); ?>"
			<?php /* translators: %s: episode title. */ ?>
			aria-label="<?php echo esc_attr( sprintf( __( 'Play %s', 'sepo-podcast' ), $sp_ep['title'] ) ); ?>">
			<?php Render::the_icon( 'play' ); ?><?php Render::the_icon( 'pause' ); ?>
		</a>
	<?php endif; ?>
</article>
