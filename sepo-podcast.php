<?php

/**
 * Plugin Name:       Sepo Podcast
 * Description:       Publish podcast episodes with a built-in player, likes, download counts, protected downloads and an Apple Podcasts / Spotify ready RSS feed.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Euneolink Solutions
 * Author URI:        https://sepodesk.com
 * Plugin URI:        https://edu.sepodesk.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sepo-podcast
 * Domain Path:       /languages
 *
 * @package SepoPodcast
 */

defined('ABSPATH') || exit;

define('SEPO_PODCAST_VERSION', '1.1.0');
define('SEPO_PODCAST_FILE', __FILE__);
define('SEPO_PODCAST_DIR', plugin_dir_path(__FILE__));
define('SEPO_PODCAST_URL', plugin_dir_url(__FILE__));

require_once SEPO_PODCAST_DIR . 'includes/class-settings.php';
require_once SEPO_PODCAST_DIR . 'includes/class-post-type.php';
require_once SEPO_PODCAST_DIR . 'includes/class-counters.php';
require_once SEPO_PODCAST_DIR . 'includes/class-private-files.php';
require_once SEPO_PODCAST_DIR . 'includes/class-episode.php';
require_once SEPO_PODCAST_DIR . 'includes/class-render.php';
require_once SEPO_PODCAST_DIR . 'includes/class-rest.php';
require_once SEPO_PODCAST_DIR . 'includes/class-download.php';
require_once SEPO_PODCAST_DIR . 'includes/class-feed.php';
require_once SEPO_PODCAST_DIR . 'includes/class-admin.php';
require_once SEPO_PODCAST_DIR . 'includes/class-importer.php';
require_once SEPO_PODCAST_DIR . 'includes/class-privacy.php';
require_once SEPO_PODCAST_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__, array('SepoPodcast\\Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('SepoPodcast\\Plugin', 'deactivate'));

add_action('plugins_loaded', array('SepoPodcast\\Plugin', 'boot'));
