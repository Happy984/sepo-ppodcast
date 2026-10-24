<?php
/**
 * Audio player.
 *
 * Without JavaScript this is the browser's own player. podcast.js swaps the
 * native controls for the styled ones below.
 *
 * Copy to yourtheme/sepo-podcast/player.php to change it.
 *
 * @package SepoPodcast
 *
 * @var array $args { episode: array, context: string }
 */

use SepoPodcast\Render;

defined( 'ABSPATH' ) || exit;

$sp_ep = $args['episode'];
?>
<div class="sp-player" data-sp-player data-id="<?php echo esc_attr( (string) $sp_ep['id'] ); ?>">
	<audio class="sp-audio" controls preload="metadata" src="<?php echo esc_url( $sp_ep['src'] ); ?>"<?php echo ( $sp_ep['protected'] || ! $sp_ep['allow_download'] ) ? ' controlslist="nodownload"' : ''; ?>></audio>
	<div class="sp-controls" hidden>
		<button type="button" class="sp-btn sp-play" data-sp-toggle aria-label="<?php esc_attr_e( 'Play', 'sepo-podcast' ); ?>">
			<?php Render::the_icon( 'play' ); ?><?php Render::the_icon( 'pause' ); ?>
		</button>
		<button type="button" class="sp-btn sp-skip" data-sp-skip="-15" aria-label="<?php esc_attr_e( 'Back 15 seconds', 'sepo-podcast' ); ?>"><span>15</span></button>
		<div class="sp-track">
			<input type="range" class="sp-seek" data-sp-seek min="0" max="1000" step="1" value="0" aria-label="<?php esc_attr_e( 'Seek', 'sepo-podcast' ); ?>">
			<div class="sp-times"><span data-sp-current>0:00</span><span data-sp-duration><?php echo esc_html( '' !== $sp_ep['duration'] ? $sp_ep['duration'] : '0:00' ); ?></span></div>
		</div>
		<button type="button" class="sp-btn sp-skip sp-skip-fwd" data-sp-skip="30" aria-label="<?php esc_attr_e( 'Forward 30 seconds', 'sepo-podcast' ); ?>"><span>30</span></button>
		<button type="button" class="sp-btn sp-rate" data-sp-rate aria-label="<?php esc_attr_e( 'Playback speed', 'sepo-podcast' ); ?>">1×</button>
	</div>
</div>
