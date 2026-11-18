<?php
/**
 * Plugin Name: Astro Headless
 * Description: Locks down the WordPress front end. Visitors go to the Astro site; the admin, login and REST API keep working.
 * Version: 1.0.0
 *
 * Install: upload to wp-content/mu-plugins/astro-headless.php
 * Optional: add to wp-config.php to change the Astro site address:
 *   define( 'ASTRO_SITE_URL', 'https://astrotest-com.stackstaging.com' );
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ASTRO_SITE_URL' ) ) {
	define( 'ASTRO_SITE_URL', 'https://astrotest-com.stackstaging.com' );
}

/**
 * Where a WordPress URL lives on the Astro site.
 * Add a line here for each post type that gets its own Astro pages.
 */
function astro_headless_url( $post = null ) {
	$base = untrailingslashit( ASTRO_SITE_URL );

	if ( $post instanceof WP_Post && 'coffee' === $post->post_type ) {
		return $base . '/shop/' . $post->post_name . '/';
	}
	if ( is_post_type_archive( 'coffee' ) ) {
		return $base . '/shop/';
	}
	return $base . '/';
}

/**
 * 1. Redirect every front-end page to the Astro site.
 * template_redirect only runs for theme pages, so wp-admin, wp-login.php,
 * the REST API, admin-ajax and cron are not affected.
 */
add_action(
	'template_redirect',
	function () {
		// Let logged-in editors use WordPress previews of drafts.
		if ( is_preview() && current_user_can( 'edit_posts' ) ) {
			return;
		}
		$post = is_singular() ? get_queried_object() : null;
		// 302 while testing. Switch to 301 once the setup is final.
		wp_redirect( astro_headless_url( $post ), 302, 'Astro Headless' );
		exit;
	},
	0
);

/**
 * 2. "View" links in the admin, and the "link" field in the REST API,
 * point to the Astro page instead of the WordPress theme.
 */
add_filter(
	'post_type_link',
	function ( $link, $post ) {
		return 'coffee' === $post->post_type && 'publish' === $post->post_status ? astro_headless_url( $post ) : $link;
	},
	10,
	2
);

/**
 * 3. Switch off public extras the Astro site does not use.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );        // XML-RPC, a common brute-force target
add_filter( 'wp_sitemaps_enabled', '__return_false' );   // Astro should own the sitemap
remove_action( 'wp_head', 'rest_output_link_wp_head' );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'template_redirect', 'rest_output_link_header', 11 );

// RSS feeds: send them to the Astro home page too.
foreach ( array( 'do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom' ) as $feed ) {
	add_action(
		$feed,
		function () {
			wp_redirect( astro_headless_url(), 302 );
			exit;
		},
		1
	);
}

/**
 * 4. Hide the user list from anonymous REST requests (stops username discovery).
 * Logged-in editors still get it, which the block editor needs.
 */
add_filter(
	'rest_endpoints',
	function ( $endpoints ) {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		return $endpoints;
	}
);

/**
 * 5. Tell search engines not to index anything served by WordPress.
 */
add_action(
	'send_headers',
	function () {
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}
);
add_filter(
	'rest_post_dispatch',
	function ( $response ) {
		$response->header( 'X-Robots-Tag', 'noindex, nofollow' );
		return $response;
	}
);
