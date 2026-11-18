<?php
/**
 * Plugin Name: Astro Rebuild
 * Description: Triggers a GitHub Actions rebuild of the Astro site when coffee content changes.
 * Version: 1.0.0
 *
 * Install: upload to wp-content/mu-plugins/astro-rebuild.php (create the folder if needed).
 * Config: add to wp-config.php, above "That's all, stop editing!":
 *   define( 'ASTRO_GITHUB_TOKEN', 'github_pat_...' );
 *   define( 'ASTRO_GITHUB_REPO', 'hps-filoponz/astro-test' );
 */

defined( 'ABSPATH' ) || exit;

/** Post types that should trigger a rebuild. */
const ASTRO_REBUILD_POST_TYPES = array( 'coffee', 'post', 'page' );

/** Seconds to wait before rebuilding, so Meta Box fields finish saving and quick edits batch together. */
const ASTRO_REBUILD_DELAY = 30;

/**
 * Queue one rebuild. Repeated saves inside the delay window share the same rebuild.
 */
function astro_rebuild_queue( $post_id = 0 ) {
	if ( $post_id && ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) ) {
		return;
	}
	if ( $post_id && ! in_array( get_post_type( $post_id ), ASTRO_REBUILD_POST_TYPES, true ) ) {
		return;
	}
	if ( ! wp_next_scheduled( 'astro_rebuild_dispatch' ) ) {
		wp_schedule_single_event( time() + ASTRO_REBUILD_DELAY, 'astro_rebuild_dispatch' );
	}
}

// Published content changed, or something moved in or out of "published".
add_action(
	'transition_post_status',
	function ( $new_status, $old_status, $post ) {
		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			astro_rebuild_queue( $post->ID );
		}
	},
	10,
	3
);
add_action( 'before_delete_post', 'astro_rebuild_queue' );

/**
 * Send the repository_dispatch event to GitHub.
 */
add_action(
	'astro_rebuild_dispatch',
	function () {
		if ( ! defined( 'ASTRO_GITHUB_TOKEN' ) || ! defined( 'ASTRO_GITHUB_REPO' ) ) {
			error_log( 'Astro Rebuild: ASTRO_GITHUB_TOKEN or ASTRO_GITHUB_REPO missing in wp-config.php' );
			return;
		}

		$response = wp_remote_post(
			'https://api.github.com/repos/' . ASTRO_GITHUB_REPO . '/dispatches',
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'               => 'application/vnd.github+json',
					'Authorization'        => 'Bearer ' . ASTRO_GITHUB_TOKEN,
					'X-GitHub-Api-Version' => '2022-11-28',
					'User-Agent'           => 'astro-rebuild-wp',
				),
				'body'    => wp_json_encode(
					array(
						'event_type'     => 'cms-update',
						'client_payload' => array( 'site' => home_url() ),
					)
				),
			)
		);

		$code = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response );
		update_option( 'astro_rebuild_last', array( 'time' => time(), 'result' => $code ), false );

		if ( 204 !== $code ) {
			error_log( 'Astro Rebuild: GitHub returned ' . print_r( $code, true ) . ' ' . wp_remote_retrieve_body( $response ) );
		}
	}
);

/**
 * Admin bar: show the last rebuild result and a "Rebuild now" link.
 */
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$last  = get_option( 'astro_rebuild_last' );
		$queue = wp_next_scheduled( 'astro_rebuild_dispatch' );
		if ( $queue ) {
			$title = 'Astro: rebuild queued';
		} elseif ( $last ) {
			$ok    = 204 === $last['result'];
			$title = sprintf( 'Astro: %s %s ago', $ok ? 'rebuilt' : 'rebuild failed', human_time_diff( $last['time'] ) );
		} else {
			$title = 'Astro: no rebuilds yet';
		}
		$bar->add_node( array( 'id' => 'astro-rebuild', 'title' => $title ) );
		$bar->add_node(
			array(
				'id'     => 'astro-rebuild-now',
				'parent' => 'astro-rebuild',
				'title'  => 'Rebuild now',
				'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=astro_rebuild_now' ), 'astro_rebuild_now' ),
			)
		);
	},
	100
);

add_action(
	'admin_post_astro_rebuild_now',
	function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'astro_rebuild_now' );
		do_action( 'astro_rebuild_dispatch' );
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}
);
