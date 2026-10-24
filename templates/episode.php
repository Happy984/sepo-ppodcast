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

$sp_ep      = $args['episode'];
$sp_number  = Render::number_label( $sp_ep );
$sp_series  = $sp_ep['series'] ? $sp_ep['series'][0] : null;
$sp_related = $args['related'];
?>
<div class="sepo-podcast sp-episode">
	<header class="sp-hero">
		<?php if ( $args['show_art'] && $sp_ep['image'] ) : ?>
			<img class="sp-art" src="<?php echo esc_url( $sp_ep['image'] ); ?>" alt="" width="180" height="180" loading="eager" decoding="async">
		<?php endif; ?>
		<div class="sp-hero-body">
			<p class="sp-kicker">
				<?php if ( $sp_series ) : ?>
					<a href="<?php echo esc_url( $args['list_url'] ? add_query_arg( 'sp_series', rawurlencode( $sp_series['slug'] ), $args['list_url'] ) : (string) get_term_link( $sp_series['id'] ) ); ?>"><?php echo esc_html( $sp_series['name'] ); ?></a>
				<?php endif; ?>
				<?php if ( $sp_number ) : ?>
					<span><?php echo esc_html( $sp_number ); ?></span>
				<?php endif; ?>
				<span><?php echo esc_html( $sp_ep['date'] ); ?></span>
				<?php if ( $sp_ep['duration'] ) : ?>
					<span><?php echo esc_html( $sp_ep['duration'] ); ?></span>
				<?php endif; ?>
				<?php if ( $sp_ep['explicit'] ) : ?>
					<span class="sp-badge" title="<?php esc_attr_e( 'Explicit', 'sepo-podcast' ); ?>">E</span>
				<?php endif; ?>
			</p>
			<?php if ( $sp_ep['host'] || $sp_ep['guest'] ) : ?>
				<p class="sp-people">
					<?php if ( $sp_ep['host'] ) : ?>
						<span><?php esc_html_e( 'Host', 'sepo-podcast' ); ?> <strong><?php echo esc_html( $sp_ep['host'] ); ?></strong></span>
					<?php endif; ?>
					<?php if ( $sp_ep['guest'] ) : ?>
						<span><?php esc_html_e( 'Guest', 'sepo-podcast' ); ?> <strong><?php echo esc_html( $sp_ep['guest'] ); ?></strong></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php echo Render::player( $sp_ep ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in templates/player.php. ?>
		</div>
	</header>

	<div class="sp-actions">
		<?php
		echo Render::template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in templates/actions.php.
			'actions',
			array(
				'episode' => $sp_ep,
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

	<?php if ( $sp_ep['tags'] ) : ?>
		<p class="sp-tags">
			<?php foreach ( $sp_ep['tags'] as $sp_tag ) : ?>
				<a href="<?php echo esc_url( (string) get_term_link( $sp_tag['id'] ) ); ?>">#<?php echo esc_html( $sp_tag['name'] ); ?></a>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>

	<?php if ( $sp_related ) : ?>
		<section class="sp-related" aria-labelledby="sp-related-<?php echo esc_attr( (string) $sp_ep['id'] ); ?>">
			<h2 id="sp-related-<?php echo esc_attr( (string) $sp_ep['id'] ); ?>"><?php esc_html_e( 'More episodes', 'sepo-podcast' ); ?></h2>
			<div class="sp-cards sp-cards-compact">
				<?php
				foreach ( $sp_related as $sp_item ) {
					echo Render::template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in templates/card.php.
						'card',
						array(
							'episode' => $sp_item,
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
