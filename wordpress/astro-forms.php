<?php
/**
 * Plugin Name: Astro Forms
 * Description: Receives form submissions from the Astro site, stores them under "Form entries" and emails a notification.
 * Version: 1.0.0
 *
 * Install: upload to wp-content/mu-plugins/astro-forms.php
 * Endpoint: POST /wp-json/astro/v1/form/{form}   (for example /form/contact)
 *
 * Optional, in wp-config.php:
 *   define( 'ASTRO_FORM_TO', 'you@example.com' );            // default: Settings > General admin email
 *   define( 'ASTRO_FORM_ORIGINS', 'https://site-a.com,https://site-b.com' ); // sites allowed to post
 */

defined( 'ABSPATH' ) || exit;

/** Sites allowed to submit forms. Falls back to the Astro site URL used by Astro Headless. */
function astro_forms_origins() {
	if ( defined( 'ASTRO_FORM_ORIGINS' ) ) {
		$list = ASTRO_FORM_ORIGINS;
	} else {
		$list = ( defined( 'ASTRO_SITE_URL' ) ? ASTRO_SITE_URL : 'https://astrotest-com.stackstaging.com' ) . ',https://hps-filoponz.github.io';
	}
	return array_filter( array_map( 'untrailingslashit', array_map( 'trim', explode( ',', $list ) ) ) );
}

/**
 * Forms and their fields. Each field: label, required, type (text, email, textarea).
 * Add another form here (for example a wholesale enquiry) and post to /form/{key}.
 */
function astro_forms_config() {
	return array(
		'contact' => array(
			'title'  => 'Contact form',
			'fields' => array(
				'name'    => array( 'label' => 'Name', 'required' => true, 'type' => 'text' ),
				'email'   => array( 'label' => 'Email', 'required' => true, 'type' => 'email' ),
				'topic'   => array( 'label' => 'Topic', 'required' => false, 'type' => 'text' ),
				'message' => array( 'label' => 'Message', 'required' => true, 'type' => 'textarea' ),
			),
		),
	);
}

/* ------------------------------------------------------------------ */
/* Storage: a private post type that shows in the admin only           */
/* ------------------------------------------------------------------ */

add_action(
	'init',
	function () {
		register_post_type(
			'astro_entry',
			array(
				'labels'       => array(
					'name'          => 'Form entries',
					'singular_name' => 'Form entry',
					'edit_item'     => 'Form entry',
					'search_items'  => 'Search entries',
					'not_found'     => 'No entries yet',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => false,
				'menu_icon'    => 'dashicons-email-alt',
				'supports'     => array( 'title' ),
				'capabilities' => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap' => true,
			)
		);
	}
);

// Show the submitted fields on the entry screen.
add_action(
	'add_meta_boxes_astro_entry',
	function () {
		add_meta_box(
			'astro_entry_fields',
			'Submission',
			function ( $post ) {
				$fields = get_post_meta( $post->ID, '_astro_fields', true ) ?: array();
				echo '<table class="widefat striped"><tbody>';
				foreach ( $fields as $f ) {
					printf(
						'<tr><th style="width:140px">%s</th><td>%s</td></tr>',
						esc_html( $f['label'] ),
						nl2br( esc_html( $f['value'] ) )
					);
				}
				printf(
					'<tr><th>Page</th><td>%s</td></tr><tr><th>Received</th><td>%s</td></tr>',
					esc_html( get_post_meta( $post->ID, '_astro_page', true ) ),
					esc_html( get_the_date( 'j F Y, H:i', $post ) )
				);
				echo '</tbody></table>';
			},
			'astro_entry',
			'normal',
			'high'
		);
	}
);

// List columns: form and email.
add_filter(
	'manage_astro_entry_posts_columns',
	function ( $cols ) {
		return array(
			'cb'          => $cols['cb'],
			'title'       => 'From',
			'astro_form'  => 'Form',
			'astro_email' => 'Email',
			'date'        => 'Received',
		);
	}
);
add_action(
	'manage_astro_entry_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'astro_form' === $col ) {
			echo esc_html( get_post_meta( $post_id, '_astro_form', true ) );
		}
		if ( 'astro_email' === $col ) {
			$email = get_post_meta( $post_id, '_astro_email', true );
			printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
		}
	},
	10,
	2
);

/* ------------------------------------------------------------------ */
/* REST endpoint                                                       */
/* ------------------------------------------------------------------ */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'astro/v1',
			'/form/(?P<form>[a-z0-9_-]+)',
			array(
				'methods'             => 'POST',
				'callback'            => 'astro_forms_submit',
				'permission_callback' => '__return_true', // public form; checks happen in the callback
			)
		);
	}
);

function astro_forms_error( $message, $status = 400, $errors = array() ) {
	return new WP_REST_Response(
		array( 'ok' => false, 'message' => $message, 'errors' => (object) $errors ),
		$status
	);
}

function astro_forms_submit( WP_REST_Request $req ) {
	$forms = astro_forms_config();
	$key   = $req['form'];
	if ( ! isset( $forms[ $key ] ) ) {
		return astro_forms_error( 'Unknown form.', 404 );
	}
	$form = $forms[ $key ];

	// 1. Only accept posts from our own Astro sites.
	$origin = untrailingslashit( (string) $req->get_header( 'origin' ) );
	if ( ! in_array( $origin, astro_forms_origins(), true ) ) {
		return astro_forms_error( 'Submissions are not accepted from this site.', 403 );
	}

	// 2. Spam checks: hidden honeypot field, and a form filled in under 3 seconds.
	if ( '' !== trim( (string) $req->get_param( 'website' ) ) ) {
		return new WP_REST_Response( array( 'ok' => true, 'message' => 'Thanks!' ), 200 ); // fake success for bots
	}
	$started = (int) $req->get_param( '_started' );
	if ( ! $started || ( time() * 1000 - $started ) < 3000 ) {
		return astro_forms_error( 'That was quick. Please wait a moment and try again.', 429 );
	}

	// 3. Rate limit: 5 submissions per IP every 10 minutes.
	$ip_key = 'astro_form_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 5 ) {
		return astro_forms_error( 'Too many messages. Please try again in a few minutes.', 429 );
	}

	// 4. Validate and clean the fields.
	$clean  = array();
	$errors = array();
	foreach ( $form['fields'] as $name => $field ) {
		$raw   = (string) $req->get_param( $name );
		$value = 'textarea' === $field['type'] ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
		$value = mb_substr( $value, 0, 'textarea' === $field['type'] ? 5000 : 200 );

		if ( $field['required'] && '' === $value ) {
			$errors[ $name ] = $field['label'] . ' is required.';
		} elseif ( 'email' === $field['type'] && '' !== $value && ! is_email( $value ) ) {
			$errors[ $name ] = 'Please enter a valid email address.';
		}
		$clean[ $name ] = array( 'label' => $field['label'], 'value' => $value );
	}
	if ( $errors ) {
		return astro_forms_error( 'Please check the highlighted fields.', 422, $errors );
	}

	set_transient( $ip_key, $count + 1, 10 * MINUTE_IN_SECONDS );

	// 5. Store the entry.
	$email   = $clean['email']['value'] ?? '';
	$from    = $clean['name']['value'] ?? $email;
	$page    = esc_url_raw( (string) $req->get_param( '_page' ) );
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'astro_entry',
			'post_status' => 'private',
			'post_title'  => $from ?: 'Submission',
		)
	);
	if ( $post_id && ! is_wp_error( $post_id ) ) {
		update_post_meta( $post_id, '_astro_form', $form['title'] );
		update_post_meta( $post_id, '_astro_fields', $clean );
		update_post_meta( $post_id, '_astro_email', $email );
		update_post_meta( $post_id, '_astro_page', $page );
	}

	// 6. Email the notification. Reply goes straight to the sender.
	$to   = defined( 'ASTRO_FORM_TO' ) ? ASTRO_FORM_TO : get_option( 'admin_email' );
	$body = '';
	foreach ( $clean as $f ) {
		$body .= $f['label'] . ":\n" . $f['value'] . "\n\n";
	}
	$body .= 'Page: ' . $page . "\n";
	if ( $post_id && ! is_wp_error( $post_id ) ) {
		$body .= 'Entry: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) . "\n";
	}
	$headers = $email ? array( 'Reply-To: ' . $from . ' <' . $email . '>' ) : array();
	$sent    = wp_mail( $to, sprintf( '[%s] %s from %s', $form['title'], $clean['topic']['value'] ?? 'New message', $from ), $body, $headers );

	if ( ! $sent ) {
		error_log( 'Astro Forms: wp_mail failed for entry ' . $post_id );
	}

	// The entry is saved either way, so the visitor sees success.
	return new WP_REST_Response(
		array( 'ok' => true, 'message' => 'Thanks! Your message has been sent. We will reply within one working day.' ),
		200
	);
}
