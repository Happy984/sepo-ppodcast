<?php
/**
 * Boot, activation and deactivation.
 *
 * @package SepoPodcast
 */

namespace SepoPodcast;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every part of the plugin into WordPress.
 */
final class Plugin {

	/**
	 * Hook everything up. Runs on plugins_loaded.
	 */
	public static function boot(): void {
		Settings::init();
		Post_Type::init();
		Counters::init();
		Private_Files::init();
		Render::init();
		Rest::init();
		Download::init();
		Feed::init();
		Privacy::init();

		if ( is_admin() ) {
			Admin::init();
			Importer::init();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'sepo-podcast import', array( Importer::class, 'cli' ) );
		}
	}

	/**
	 * Activation: tables, protected folder, the Podcast page, rewrite rules.
	 */
	public static function activate(): void {
		Counters::install();
		Private_Files::ensure_dir();
		Post_Type::register();
		Download::add_rewrite();
		Feed::add_feed();
		Settings::ensure_list_page();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation: forget our rewrite rules. Data is only removed on uninstall.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
