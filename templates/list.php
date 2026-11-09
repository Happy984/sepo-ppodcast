<?php
/**
 * Episode list: search, series filter, cards, pages.
 *
 * Copy to yourtheme/sepo-podcast/list.php to change it.
 *
 * @package SepoPodcast
 *
 * @var array $args See Render::episode_list().
 */

use SepoPodcast\Render;
use SepoPodcast\Settings;

defined( 'ABSPATH' ) || exit;

$sepo_podcast_base   = $args['base'];
$sepo_podcast_series = $args['series'];
$sepo_podcast_search = $args['search'];
$sepo_podcast_label  = (string) Settings::get( 'ungrouped_label' );
$sepo_podcast_label  = '' !== $sepo_podcast_label ? $sepo_podcast_label : __( 'Other episodes', 'sepo-podcast' );
?>
<div class="sepo-podcast sp-list" data-sp-list>
	<?php if ( $args['show_search'] || ( $args['show_series'] && $args['all_series'] ) ) : ?>
		<div class="sp-toolbar">
			<?php if ( $args['show_series'] && $args['all_series'] ) : ?>
				<nav class="sp-pills" aria-label="<?php esc_attr_e( 'Series', 'sepo-podcast' ); ?>">
					<a class="sp-pill<?php echo '' === $sepo_podcast_series ? ' is-active' : ''; ?>" href="<?php echo esc_url( Render::list_link( $sepo_podcast_base, array( 'sp_q' => $sepo_podcast_search ) ) ); ?>"<?php echo '' === $sepo_podcast_series ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'All', 'sepo-podcast' ); ?></a>
					<?php foreach ( $args['all_series'] as $sepo_podcast_term ) : ?>
						<a class="sp-pill<?php echo $sepo_podcast_term->slug === $sepo_podcast_series ? ' is-active' : ''; ?>" href="<?php echo esc_url( Render::list_link( $sepo_podcast_base, array( 'sp_series' => $sepo_podcast_term->slug, 'sp_q' => $sepo_podcast_search ) ) ); ?>"<?php echo $sepo_podcast_term->slug === $sepo_podcast_series ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $sepo_podcast_term->name ); ?></a>
					<?php endforeach; ?>
					<?php if ( $args['has_loose'] ) : ?>
						<a class="sp-pill<?php echo '_none' === $sepo_podcast_series ? ' is-active' : ''; ?>" href="<?php echo esc_url( Render::list_link( $sepo_podcast_base, array( 'sp_series' => '_none', 'sp_q' => $sepo_podcast_search ) ) ); ?>"<?php echo '_none' === $sepo_podcast_series ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $sepo_podcast_label ); ?></a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>

			<?php if ( $args['show_search'] ) : ?>
				<form class="sp-search" method="get" action="<?php echo esc_url( $sepo_podcast_base ); ?>" role="search">
					<?php if ( '' !== $sepo_podcast_series && ! $args['fixed'] ) : ?>
						<input type="hidden" name="sp_series" value="<?php echo esc_attr( $sepo_podcast_series ); ?>">
					<?php endif; ?>
					<?php $sepo_podcast_qid = wp_unique_id( 'sp-q-' ); ?>
					<label class="screen-reader-text" for="<?php echo esc_attr( $sepo_podcast_qid ); ?>"><?php esc_html_e( 'Search episodes', 'sepo-podcast' ); ?></label>
					<?php Render::the_icon( 'search' ); ?>
					<input type="search" id="<?php echo esc_attr( $sepo_podcast_qid ); ?>" name="sp_q" value="<?php echo esc_attr( $sepo_podcast_search ); ?>" placeholder="<?php esc_attr_e( 'Search episodes', 'sepo-podcast' ); ?>">
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $sepo_podcast_search ) : ?>
		<p class="sp-result-note">
			<?php
			printf(
				/* translators: 1: number of episodes, 2: search words. */
				esc_html( _n( '%1$s episode for “%2$s”', '%1$s episodes for “%2$s”', $args['total'], 'sepo-podcast' ) ),
				esc_html( number_format_i18n( $args['total'] ) ),
				esc_html( $sepo_podcast_search )
			);
			?>
			<a href="<?php echo esc_url( Render::list_link( $sepo_podcast_base, array( 'sp_series' => $args['fixed'] ? '' : $sepo_podcast_series ) ) ); ?>"><?php esc_html_e( 'Clear', 'sepo-podcast' ); ?></a>
		</p>
	<?php endif; ?>

	<?php if ( $args['episodes'] ) : ?>
		<div class="sp-cards">
			<?php
			foreach ( $args['episodes'] as $sepo_podcast_item ) {
				Render::render(
					'card',
					array(
						'episode' => $sepo_podcast_item,
						'liked'   => $args['liked'],
						'compact' => false,
					)
				);
			}
			?>
		</div>
	<?php else : ?>
		<p class="sp-empty"><?php esc_html_e( 'No episodes found.', 'sepo-podcast' ); ?></p>
	<?php endif; ?>

	<?php if ( $args['pages'] > 1 ) : ?>
		<nav class="sp-pager" aria-label="<?php esc_attr_e( 'Episode pages', 'sepo-podcast' ); ?>">
			<?php
			$sepo_podcast_keep = array(
				'sp_q'      => $sepo_podcast_search,
				'sp_series' => $args['fixed'] ? '' : $sepo_podcast_series,
			);
			?>
			<?php if ( $args['page'] > 1 ) : ?>
				<a class="sp-pill" rel="prev" href="<?php echo esc_url( Render::list_link( $sepo_podcast_base, $sepo_podcast_keep + array( 'sp_pg' => $args['page'] - 1 ) ) ); ?>">&larr; <?php esc_html_e( 'Previous', 'sepo-podcast' ); ?></a>
			<?php endif; ?>
			<span class="sp-page-of">
				<?php
				/* translators: 1: current page, 2: number of pages. */
				echo esc_html( sprintf( __( 'Page %1$s of %2$s', 'sepo-podcast' ), number_format_i18n( $args['page'] ), number_format_i18n( $args['pages'] ) ) );
				?>
			</span>
			<?php if ( $args['page'] < $args['pages'] ) : ?>
				<a class="sp-pill" rel="next" href="<?php echo esc_url( Render::list_link( $sepo_podcast_base, $sepo_podcast_keep + array( 'sp_pg' => $args['page'] + 1 ) ) ); ?>"><?php esc_html_e( 'Next', 'sepo-podcast' ); ?> &rarr;</a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
</div>
