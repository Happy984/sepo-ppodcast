<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Nothing is removed unless the site owner ticked the boxes under
 * Settings → Sepo Podcast → "When the plugin is deleted".
 *
 * @package SepoPodcast
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove data for one site.
 */
function sepo_podcast_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'sepo_podcast_settings', array() );
	if ( ! is_array( $settings ) || empty( $settings['delete_on_uninstall'] ) ) {
		return;
	}

	if ( ! empty( $settings['delete_episodes'] ) ) {
		// Every status, including trash and auto-drafts ("any" skips those).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE post_type = %s', $wpdb->posts, 'sepo_episode' ) );
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
		foreach ( array( 'sepo_series', 'sepo_episode_tag' ) as $taxonomy ) {
			// The taxonomy is not registered during uninstall, so read the terms directly.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$terms = $wpdb->get_results( $wpdb->prepare( 'SELECT t.term_id, tt.term_taxonomy_id FROM %i t INNER JOIN %i tt ON t.term_id = tt.term_id WHERE tt.taxonomy = %s', $wpdb->terms, $wpdb->term_taxonomy, $taxonomy ) );
			foreach ( (array) $terms as $term ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => (int) $term->term_taxonomy_id ) );
				$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => (int) $term->term_taxonomy_id ) );
				$wpdb->delete( $wpdb->termmeta, array( 'term_id' => (int) $term->term_id ) );
				$wpdb->delete( $wpdb->terms, array( 'term_id' => (int) $term->term_id ) );
				// phpcs:enable
			}
		}

		$up  = wp_upload_dir( null, false );
		$dir = trailingslashit( $up['basedir'] ) . 'sepo-podcast-private';
		if ( is_dir( $dir ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
			global $wp_filesystem;
			if ( $wp_filesystem ) {
				$wp_filesystem->delete( $dir, true );
			}
		}
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'sepo_podcast_likes' ) );
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'sepo_podcast_downloads' ) );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key IN (%s, %s, %s, %s)', $wpdb->postmeta, '_sepo_like_count', '_sepo_download_count', '_sepo_like_offset', '_sepo_download_offset' ) );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s', $wpdb->options, $wpdb->esc_like( '_transient_sepo_pdl_' ) . '%', $wpdb->esc_like( '_transient_timeout_sepo_pdl_' ) . '%' ) );
	// phpcs:enable

	delete_option( 'sepo_podcast_settings' );
	delete_option( 'sepo_podcast_db_version' );
	delete_option( 'sepo_podcast_flush' );
	delete_transient( 'sepo_podcast_private_check' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $sepo_podcast_site ) {
		switch_to_blog( (int) $sepo_podcast_site );
		sepo_podcast_uninstall_site();
		restore_current_blog();
	}
} else {
	sepo_podcast_uninstall_site();
}
