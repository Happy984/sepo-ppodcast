<?php
/**
 * Like and download buttons.
 *
 * Copy to yourtheme/sepo-podcast/actions.php to change it.
 *
 * @package SepoPodcast
 *
 * @var array $args { episode, liked }
 */

use SepoPodcast\Render;

defined( 'ABSPATH' ) || exit;

$sp_ep    = $args['episode'];
$sp_liked = ! empty( $args['liked'] );
$sp_login = wp_login_url( $sp_ep['url'] );
?>
<?php if ( is_user_logged_in() ) : ?>
	<button type="button" class="sp-btn sp-pill sp-like<?php echo $sp_liked ? ' is-liked' : ''; ?>" data-sp-like="<?php echo esc_attr( (string) $sp_ep['id'] ); ?>" aria-pressed="<?php echo $sp_liked ? 'true' : 'false'; ?>">
		<?php Render::the_icon( 'heart' ); ?>
		<span class="sp-label"><?php esc_html_e( 'Like', 'sepo-podcast' ); ?></span>
		<span class="sp-count" data-sp-likes><?php echo esc_html( number_format_i18n( $sp_ep['likes'] ) ); ?></span>
	</button>
<?php else : ?>
	<a class="sp-btn sp-pill sp-like" href="<?php echo esc_url( $sp_login ); ?>" title="<?php esc_attr_e( 'Sign in to like this episode', 'sepo-podcast' ); ?>">
		<?php Render::the_icon( 'heart' ); ?>
		<span class="sp-label"><?php esc_html_e( 'Like', 'sepo-podcast' ); ?></span>
		<span class="sp-count" data-sp-likes><?php echo esc_html( number_format_i18n( $sp_ep['likes'] ) ); ?></span>
	</a>
<?php endif; ?>

<?php if ( $sp_ep['allow_download'] ) : ?>
	<?php if ( $sp_ep['requires_login'] && ! is_user_logged_in() ) : ?>
		<a class="sp-btn sp-pill sp-download is-locked" href="<?php echo esc_url( $sp_login ); ?>">
			<?php Render::the_icon( 'lock' ); ?>
			<span class="sp-label"><?php esc_html_e( 'Sign in to download', 'sepo-podcast' ); ?></span>
			<span class="sp-count" data-sp-downloads><?php echo esc_html( number_format_i18n( $sp_ep['downloads'] ) ); ?></span>
		</a>
	<?php else : ?>
		<a class="sp-btn sp-pill sp-download" href="<?php echo esc_url( $sp_ep['download_url'] ); ?>" data-sp-download="<?php echo esc_attr( (string) $sp_ep['id'] ); ?>" rel="nofollow">
			<?php Render::the_icon( 'download' ); ?>
			<span class="sp-label"><?php esc_html_e( 'Download', 'sepo-podcast' ); ?></span>
			<span class="sp-count" data-sp-downloads><?php echo esc_html( number_format_i18n( $sp_ep['downloads'] ) ); ?></span>
		</a>
	<?php endif; ?>
<?php endif; ?>
