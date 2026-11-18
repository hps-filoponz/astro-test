<?php
/**
 * Plugin Name: Astro Fluent Forms
 * Description: Lets the Astro site submit to Fluent Forms. Entries, email notifications and integrations all run in Fluent Forms as normal.
 * Version: 1.0.0
 *
 * Install: upload to wp-content/mu-plugins/astro-fluentforms.php (needs Fluent Forms active).
 * Endpoint: POST /wp-json/fluentform/v1/astro-submit
 *   form_id     Fluent Forms form ID
 *   data[...]   field values, keyed by each field's Name Attribute in Fluent Forms
 *
 * Required in wp-config.php: the forms Astro may submit to (comma-separated IDs).
 * This stops anyone posting to other forms, for example a user registration form.
 *   define( 'ASTRO_FLUENT_FORMS', '3' );
 *
 * Optional in wp-config.php: sites allowed to submit (default: the Astro site and GitHub Pages copy).
 *   define( 'ASTRO_FORM_ORIGINS', 'https://site-a.com,https://site-b.com' );
 *
 * Based on the REST submission example in the Fluent Forms developer docs
 * (developers.fluentforms.com/api/extending-rest-api/).
 */

defined( 'ABSPATH' ) || exit;

function astro_ff_origins() {
	if ( defined( 'ASTRO_FORM_ORIGINS' ) ) {
		$list = ASTRO_FORM_ORIGINS;
	} else {
		$list = ( defined( 'ASTRO_SITE_URL' ) ? ASTRO_SITE_URL : 'https://astrotest-com.stackstaging.com' ) . ',https://hps-filoponz.github.io';
	}
	return array_filter( array_map( 'untrailingslashit', array_map( 'trim', explode( ',', $list ) ) ) );
}

function astro_ff_allowed_forms() {
	return defined( 'ASTRO_FLUENT_FORMS' ) ? array_map( 'intval', explode( ',', ASTRO_FLUENT_FORMS ) ) : array();
}

add_action(
	'fluentform/loaded',
	function ( $app ) {
		$app->router->post(
			'/astro-submit',
			function () use ( $app ) {
				$fail = function ( $message, $status, $errors = array() ) use ( $app ) {
					return $app->response->json(
						array( 'ok' => false, 'message' => $message, 'errors' => (object) $errors ),
						$status
					);
				};

				// 1. Only our Astro sites, and only forms listed in wp-config.php.
				$origin = untrailingslashit( (string) ( $_SERVER['HTTP_ORIGIN'] ?? '' ) );
				if ( ! in_array( $origin, astro_ff_origins(), true ) ) {
					return $fail( 'Submissions are not accepted from this site.', 403 );
				}
				$form_id = intval( $app->request->get( 'form_id' ) );
				if ( ! in_array( $form_id, astro_ff_allowed_forms(), true ) ) {
					return $fail( 'This form is not available.', 403 );
				}

				// 2. Spam traps set by the Astro form: honeypot and a 3 second minimum.
				if ( '' !== trim( (string) $app->request->get( 'website' ) ) ) {
					return $app->response->json( array( 'ok' => true, 'message' => 'Thanks!' ) ); // fake success for bots
				}
				$started = (int) $app->request->get( '_started' );
				if ( ! $started || ( time() * 1000 - $started ) < 3000 ) {
					return $fail( 'That was quick. Please wait a moment and try again.', 429 );
				}

				// 3. Rate limit: 5 submissions per IP every 10 minutes.
				$ip_key = 'astro_ff_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
				$count  = (int) get_transient( $ip_key );
				if ( $count >= 5 ) {
					return $fail( 'Too many messages. Please try again in a few minutes.', 429 );
				}

				$data = $app->request->get( 'data' );
				if ( ! is_array( $data ) ) {
					return $fail( 'No form data received.', 400 );
				}
				// Fluent Forms records the page the entry came from.
				$data['_wp_http_referer'] = esc_url_raw( (string) $app->request->get( '_page' ) );
				$app->request->merge( array( 'data' => $data ) );

				// 4. Hand over to Fluent Forms: validation, entry, notifications, integrations.
				try {
					$response = ( new FluentForm\App\Services\Form\SubmissionHandlerService() )->handleSubmission( $data, $form_id );
				} catch ( FluentForm\Framework\Validator\ValidationException $e ) {
					$errors = array();
					foreach ( (array) $e->errors() as $field => $messages ) {
						$messages         = (array) $messages;
						$errors[ $field ] = wp_strip_all_tags( (string) reset( $messages ) );
					}
					return $fail( 'Please check the highlighted fields.', 422, $errors );
				} catch ( \Throwable $e ) {
					error_log( 'Astro Fluent Forms: ' . $e->getMessage() );
					return $fail( 'Something went wrong. Please try again.', 500 );
				}

				set_transient( $ip_key, $count + 1, 10 * MINUTE_IN_SECONDS );

				// Use the confirmation message set in the form's settings.
				$message = $response['result']['message'] ?? 'Thanks! Your message has been sent.';
				return $app->response->json(
					array(
						'ok'       => true,
						'message'  => trim( wp_strip_all_tags( (string) $message ) ),
						'entry_id' => $response['insert_id'] ?? null,
					)
				);
			}
		);
	}
);
