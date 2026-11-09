<?php
/**
 * Weekly AutoJack newsletter.
 *
 * Signup, confirm, and the welcome email live in the Cloudflare Worker
 * `autojack-newsletter` (news.autojack.ai, Very Good Plugins account). That
 * worker writes contacts to a Resend audience: unsubscribed=true until the
 * reader confirms, then unsubscribed=false. Unsubscribe on the welcome mail
 * hits GET /unsubscribe on the worker and flips the same flag.
 *
 * This module is the weekly issue. It reads published posts from the previous
 * Monday–Sunday in the site timezone, renders HTML and text in the welcome
 * email's shell, and sends through the Resend API to confirmed contacts only.
 * Secrets stay in wp-config.php, same pattern as the comment webhook:
 *
 *     define( 'MINIMALCODE_RESEND_API_KEY', '...' );
 *     define( 'MINIMALCODE_NEWSLETTER_AUDIENCE_ID', '...' ); // worker NEWSLETTER_AUDIENCE_ID
 *     define( 'MINIMALCODE_NEWSLETTER_CONFIRM_SECRET', '...' ); // worker NEWSLETTER_CONFIRM_SECRET
 *     define( 'MINIMALCODE_NEWSLETTER_FROM', 'AutoJack <newsletter@news.autojack.ai>' ); // optional
 *     define( 'MINIMALCODE_NEWSLETTER_SEGMENT_ID', '...' ); // optional, only if the audience id was migrated
 *
 * Automatic sending is OFF until Settings → Newsletter enables it. Enabling
 * does not mail the list immediately; the first automatic run is the next
 * scheduled slot. Send now is a separate, explicit action.
 *
 * WP-Cron does not run without site traffic. A real crontab should hit
 * wp-cron.php every five minutes, or `wp cron event run --due-now`.
 *
 * @package MinimalCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/newsletter-digest.php';

/**
 * @return array{enabled: bool, weekday: int, hour: int, minute: int}
 */
function minimalcode_newsletter_settings() {
	$stored = get_option( 'minimalcode_newsletter_settings', array() );
	return minimalcode_newsletter_sanitize_settings( is_array( $stored ) ? $stored : array() );
}

/**
 * @param array $input Raw settings.
 * @return array{enabled: bool, weekday: int, hour: int, minute: int}
 */
function minimalcode_newsletter_save_settings( array $input ) {
	$old      = minimalcode_newsletter_settings();
	$settings = minimalcode_newsletter_sanitize_settings( $input );
	update_option( 'minimalcode_newsletter_settings', $settings, true );
	if ( $settings['enabled'] && ! $old['enabled'] ) {
		update_option( 'minimalcode_newsletter_armed_at', time(), false );
	}
	if ( ! $settings['enabled'] ) {
		delete_option( 'minimalcode_newsletter_armed_at' );
	}
	minimalcode_newsletter_ensure_schedule();
	return $settings;
}

/**
 * Constant names that are still empty. Values are never returned.
 *
 * @return string[]
 */
function minimalcode_newsletter_missing_config() {
	$missing = array();
	if ( ! defined( 'MINIMALCODE_RESEND_API_KEY' ) || ! is_string( MINIMALCODE_RESEND_API_KEY ) || '' === MINIMALCODE_RESEND_API_KEY ) {
		$missing[] = 'MINIMALCODE_RESEND_API_KEY';
	}
	$has_audience = defined( 'MINIMALCODE_NEWSLETTER_AUDIENCE_ID' ) && is_string( MINIMALCODE_NEWSLETTER_AUDIENCE_ID ) && '' !== MINIMALCODE_NEWSLETTER_AUDIENCE_ID;
	$has_segment  = defined( 'MINIMALCODE_NEWSLETTER_SEGMENT_ID' ) && is_string( MINIMALCODE_NEWSLETTER_SEGMENT_ID ) && '' !== MINIMALCODE_NEWSLETTER_SEGMENT_ID;
	if ( ! $has_audience && ! $has_segment ) {
		$missing[] = 'MINIMALCODE_NEWSLETTER_AUDIENCE_ID';
	}
	if ( ! defined( 'MINIMALCODE_NEWSLETTER_CONFIRM_SECRET' ) || ! is_string( MINIMALCODE_NEWSLETTER_CONFIRM_SECRET ) || strlen( MINIMALCODE_NEWSLETTER_CONFIRM_SECRET ) < 16 ) {
		$missing[] = 'MINIMALCODE_NEWSLETTER_CONFIRM_SECRET';
	}
	return $missing;
}

/**
 * @return bool
 */
function minimalcode_newsletter_ci_blocked() {
	return ! minimalcode_newsletter_real_send_allowed(
		array(
			'CI'             => getenv( 'CI' ) ? '1' : '',
			'GITHUB_ACTIONS' => getenv( 'GITHUB_ACTIONS' ) ? '1' : '',
		),
		defined( 'MINIMALCODE_NEWSLETTER_ALLOW_SEND' ) && MINIMALCODE_NEWSLETTER_ALLOW_SEND
	);
}

/**
 * @return int
 */
function minimalcode_newsletter_batch_size() {
	$size = (int) apply_filters( 'minimalcode_newsletter_batch_size', 10 );
	return max( 1, min( 50, $size ) );
}

/**
 * Pause between recipients inside one batch. Default is half a second,
 * under Resend's typical 2 requests/second.
 *
 * @return int Microseconds.
 */
function minimalcode_newsletter_send_delay_us() {
	$delay = (int) apply_filters( 'minimalcode_newsletter_send_delay_us', 500000 );
	return max( 0, min( 2000000, $delay ) );
}

/**
 * @return string
 */
function minimalcode_newsletter_from() {
	$from = 'AutoJack <newsletter@news.autojack.ai>';
	if ( defined( 'MINIMALCODE_NEWSLETTER_FROM' ) && is_string( MINIMALCODE_NEWSLETTER_FROM ) && '' !== MINIMALCODE_NEWSLETTER_FROM ) {
		$from = MINIMALCODE_NEWSLETTER_FROM;
	}
	return (string) apply_filters( 'minimalcode_newsletter_from', $from );
}

/**
 * @return bool
 */
function minimalcode_newsletter_using_action_scheduler() {
	return function_exists( 'as_schedule_single_action' )
		&& function_exists( 'as_next_scheduled_action' )
		&& function_exists( 'as_unschedule_all_actions' );
}

/**
 * @param string $hook Hook.
 * @param array  $args Args. Null clears every instance of the hook.
 * @return void
 */
function minimalcode_newsletter_unschedule( $hook, $args = null ) {
	if ( null === $args ) {
		wp_clear_scheduled_hook( $hook );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook );
		}
		return;
	}
	wp_clear_scheduled_hook( $hook, $args );
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( $hook, $args, 'minimalcode-newsletter' );
	}
}

/**
 * @param int    $timestamp Unix time.
 * @param string $hook Hook.
 * @param array  $args Args.
 * @return void
 */
function minimalcode_newsletter_schedule_single( $timestamp, $hook, $args = array() ) {
	$timestamp = max( time() + 5, (int) $timestamp );
	if ( minimalcode_newsletter_using_action_scheduler() ) {
		wp_clear_scheduled_hook( $hook, $args );
		if ( ! as_next_scheduled_action( $hook, $args, 'minimalcode-newsletter' ) ) {
			as_schedule_single_action( $timestamp, $hook, $args, 'minimalcode-newsletter' );
		}
		return;
	}
	if ( ! wp_next_scheduled( $hook, $args ) ) {
		wp_schedule_single_event( $timestamp, $hook, $args );
	}
}

/**
 * @param string $hook Hook.
 * @param array  $args Args.
 * @return int|false
 */
function minimalcode_newsletter_next_scheduled( $hook, $args = array() ) {
	if ( minimalcode_newsletter_using_action_scheduler() ) {
		$next = as_next_scheduled_action( $hook, $args, 'minimalcode-newsletter' );
		return $next ? (int) $next : false;
	}
	$next = wp_next_scheduled( $hook, $args );
	return $next ? (int) $next : false;
}

/**
 * Queue the next slot, or leave a due event alone so cron can fire it.
 *
 * Enabling arms the clock at "now", so a past slot from before the toggle
 * was turned on is not sent as a surprise. A slot that was missed while the
 * feature was already on is caught up for 18 hours.
 *
 * @return void
 */
function minimalcode_newsletter_ensure_schedule() {
	$settings = minimalcode_newsletter_settings();
	if ( ! $settings['enabled'] ) {
		if ( minimalcode_newsletter_next_scheduled( 'minimalcode_newsletter_weekly' ) ) {
			minimalcode_newsletter_unschedule( 'minimalcode_newsletter_weekly' );
		}
		minimalcode_newsletter_resume_if_needed();
		return;
	}

	if ( ! get_option( 'minimalcode_newsletter_armed_at' ) ) {
		update_option( 'minimalcode_newsletter_armed_at', time(), false );
	}

	$scheduled = minimalcode_newsletter_next_scheduled( 'minimalcode_newsletter_weekly' );
	if ( $scheduled && $scheduled <= time() + 30 ) {
		minimalcode_newsletter_resume_if_needed();
		return;
	}

	$tz     = wp_timezone();
	$now    = new DateTimeImmutable( 'now', $tz );
	$next   = minimalcode_newsletter_next_slot( $now, $tz, $settings['weekday'], $settings['hour'], $settings['minute'] );
	$armed  = (int) get_option( 'minimalcode_newsletter_armed_at', 0 );
	$prev   = $next->modify( '-7 days' );
	$age    = $now->getTimestamp() - $prev->getTimestamp();
	$week   = minimalcode_newsletter_previous_week( $now, $tz );
	$status = minimalcode_newsletter_week_status( $week['week_id'] );
	$claim  = get_option( 'minimalcode_nl_claim_' . $week['week_id'] );
	$catchup = $armed > 0
		&& $prev->getTimestamp() >= $armed
		&& $age >= 0
		&& $age < 18 * HOUR_IN_SECONDS
		&& ! in_array( $status, array( 'sent', 'skipped_empty', 'sending' ), true )
		&& ! $claim;

	$want = $catchup ? time() + 90 : $next->getTimestamp();
	if ( $scheduled && abs( $scheduled - $want ) <= 120 ) {
		minimalcode_newsletter_resume_if_needed();
		return;
	}

	minimalcode_newsletter_unschedule( 'minimalcode_newsletter_weekly' );
	minimalcode_newsletter_schedule_single( $want, 'minimalcode_newsletter_weekly' );
	minimalcode_newsletter_resume_if_needed();
}
add_action( 'init', 'minimalcode_newsletter_ensure_schedule', 20 );

/**
 * Continue a batch that died between cron hits. Admin, cron, and WP-CLI only.
 *
 * @return void
 */
function minimalcode_newsletter_resume_if_needed() {
	$cli = defined( 'WP_CLI' ) && WP_CLI;
	if ( ! is_admin() && ! wp_doing_cron() && ! $cli ) {
		return;
	}
	$state = get_option( 'minimalcode_newsletter_active' );
	if ( ! is_array( $state ) || empty( $state['week_id'] ) || ! empty( $state['finished'] ) ) {
		return;
	}
	$week_id = (string) $state['week_id'];
	if ( minimalcode_newsletter_next_scheduled( 'minimalcode_newsletter_batch', array( $week_id ) ) ) {
		return;
	}
	$lock = (int) ( $state['lock'] ?? 0 );
	if ( $lock > time() - 90 ) {
		return;
	}
	minimalcode_newsletter_schedule_single( time() + 60, 'minimalcode_newsletter_batch', array( $week_id ) );
}

/**
 * @param string $week_id Week id (Monday date).
 * @return string
 */
function minimalcode_newsletter_week_status( $week_id ) {
	$weeks = get_option( 'minimalcode_newsletter_weeks', array() );
	if ( ! is_array( $weeks ) || ! isset( $weeks[ $week_id ] ) ) {
		return '';
	}
	return (string) $weeks[ $week_id ];
}

/**
 * @param string $week_id Week id.
 * @param string $status Status.
 * @return void
 */
function minimalcode_newsletter_set_week_status( $week_id, $status ) {
	$weeks = get_option( 'minimalcode_newsletter_weeks', array() );
	if ( ! is_array( $weeks ) ) {
		$weeks = array();
	}
	$weeks[ $week_id ] = $status;
	if ( count( $weeks ) > 60 ) {
		$weeks = array_slice( $weeks, -60, null, true );
	}
	update_option( 'minimalcode_newsletter_weeks', $weeks, false );
}

/**
 * @param array $entry Log row.
 * @return void
 */
function minimalcode_newsletter_log_run( array $entry ) {
	$log = get_option( 'minimalcode_newsletter_log', array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	array_unshift( $log, $entry );
	update_option( 'minimalcode_newsletter_log', array_slice( $log, 0, 30 ), false );
	$status = isset( $entry['status'] ) ? (string) $entry['status'] : 'unknown';
	$week   = isset( $entry['week_id'] ) ? (string) $entry['week_id'] : '';
	$sent   = isset( $entry['sent_count'] ) ? (int) $entry['sent_count'] : 0;
	$failed = isset( $entry['failure_count'] ) ? (int) $entry['failure_count'] : 0;
	error_log( 'minimalcode newsletter ' . $status . ' week ' . $week . ' sent ' . $sent . ' failed ' . $failed ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

/**
 * @param string $week_id Week id.
 * @param string $status Status.
 * @return bool
 */
function minimalcode_newsletter_already_logged( $week_id, $status ) {
	$log = get_option( 'minimalcode_newsletter_log', array() );
	if ( ! is_array( $log ) ) {
		return false;
	}
	foreach ( $log as $row ) {
		if ( is_array( $row ) && ( $row['week_id'] ?? '' ) === $week_id && ( $row['status'] ?? '' ) === $status ) {
			return true;
		}
	}
	return false;
}

/**
 * Posts published in the window, newest first.
 *
 * @param DateTimeImmutable $start Inclusive, site timezone.
 * @param DateTimeImmutable $end Inclusive, site timezone.
 * @return array<int, array<string, mixed>>
 */
function minimalcode_newsletter_collect_posts( DateTimeImmutable $start, DateTimeImmutable $end ) {
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 40,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'date_query'          => array(
				array(
					'column'    => 'post_date',
					'after'     => $start->format( 'Y-m-d H:i:s' ),
					'before'    => $end->format( 'Y-m-d H:i:s' ),
					'inclusive' => true,
				),
			),
		)
	);

	$posts = array();
	foreach ( $query->posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		if ( has_excerpt( $post ) ) {
			$excerpt = $post->post_excerpt;
		} else {
			$excerpt = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 40, '…' );
		}
		$cats    = get_the_category( $post->ID );
		$posts[] = array(
			'id'       => (int) $post->ID,
			'title'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'excerpt'  => trim( html_entity_decode( wp_strip_all_tags( $excerpt ), ENT_QUOTES, 'UTF-8' ) ),
			'url'      => (string) get_permalink( $post ),
			'date'     => (string) get_the_date( 'j M Y', $post ),
			'category' => ( $cats && isset( $cats[0]->name ) ) ? (string) $cats[0]->name : '',
		);
	}
	wp_reset_postdata();
	return $posts;
}

/**
 * @param string $method GET|POST|PATCH.
 * @param string $path Path beginning with /.
 * @param array|null $body JSON body.
 * @param array $extra_headers Extra headers.
 * @return array{status: int, body: array}|WP_Error
 */
function minimalcode_newsletter_resend( $method, $path, $body = null, $extra_headers = array() ) {
	if ( minimalcode_newsletter_ci_blocked() ) {
		return new WP_Error( 'blocked', 'Refusing to call Resend from CI.' );
	}
	if ( ! defined( 'MINIMALCODE_RESEND_API_KEY' ) || ! is_string( MINIMALCODE_RESEND_API_KEY ) || '' === MINIMALCODE_RESEND_API_KEY ) {
		return new WP_Error( 'config', 'Resend API key is not configured.' );
	}

	$headers = array(
		'Authorization' => 'Bearer ' . MINIMALCODE_RESEND_API_KEY,
		'Content-Type'  => 'application/json',
		'Accept'        => 'application/json',
	);
	foreach ( $extra_headers as $key => $value ) {
		$headers[ $key ] = (string) $value;
	}

	$args = array(
		'method'  => $method,
		'timeout' => 20,
		'headers' => $headers,
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}

	$response = wp_remote_request( 'https://api.resend.com' . $path, $args );
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'resend', $response->get_error_message() );
	}

	$raw     = (string) wp_remote_retrieve_body( $response );
	$decoded = json_decode( $raw, true );
	return array(
		'status' => (int) wp_remote_retrieve_response_code( $response ),
		'body'   => is_array( $decoded ) ? $decoded : array(),
	);
}

/**
 * Short Resend failure text with any key-shaped fragment removed.
 *
 * @param int   $status HTTP status.
 * @param array $body Decoded body.
 * @return string
 */
function minimalcode_newsletter_resend_error_text( $status, $body ) {
	$msg = 'HTTP ' . (int) $status;
	if ( isset( $body['message'] ) && is_string( $body['message'] ) ) {
		$msg .= ' ' . $body['message'];
	}
	$msg = (string) preg_replace( '/re_[A-Za-z0-9]+/', '[redacted]', $msg );
	return mb_substr( trim( wp_strip_all_tags( $msg ) ), 0, 180 );
}

/**
 * List contacts in the newsletter audience. Never lists the whole account.
 *
 * @param string $path Path without query string.
 * @return array|WP_Error
 */
function minimalcode_newsletter_list_contact_pages( $path ) {
	$all   = array();
	$after = '';
	for ( $page = 0; $page < 100; $page++ ) {
		$url = $path . '?limit=100';
		if ( '' !== $after ) {
			$url .= '&after=' . rawurlencode( $after );
		}
		$res = minimalcode_newsletter_resend( 'GET', $url );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 404 === $res['status'] ) {
			return new WP_Error( 'not_found', 'That Resend list was not found.' );
		}
		if ( $res['status'] < 200 || $res['status'] >= 300 ) {
			return new WP_Error( 'resend', minimalcode_newsletter_resend_error_text( $res['status'], $res['body'] ) );
		}
		$rows = isset( $res['body']['data'] ) && is_array( $res['body']['data'] ) ? $res['body']['data'] : array();
		foreach ( $rows as $row ) {
			$all[] = $row;
		}
		// Legacy audience responses omit has_more. A short page is the end;
		// a full page continues unless the API explicitly says there is no more.
		if ( count( $rows ) < 100 ) {
			break;
		}
		if ( array_key_exists( 'has_more', $res['body'] ) && empty( $res['body']['has_more'] ) ) {
			break;
		}
		$last = end( $rows );
		if ( ! is_array( $last ) || empty( $last['id'] ) || $last['id'] === $after ) {
			break;
		}
		$after = (string) $last['id'];
	}
	return $all;
}

/**
 * @return array|WP_Error Contact rows.
 */
function minimalcode_newsletter_fetch_subscribers() {
	$audience = defined( 'MINIMALCODE_NEWSLETTER_AUDIENCE_ID' ) ? (string) MINIMALCODE_NEWSLETTER_AUDIENCE_ID : '';
	$segment  = defined( 'MINIMALCODE_NEWSLETTER_SEGMENT_ID' ) ? (string) MINIMALCODE_NEWSLETTER_SEGMENT_ID : '';

	if ( '' !== $audience ) {
		$listed = minimalcode_newsletter_list_contact_pages( '/audiences/' . rawurlencode( $audience ) . '/contacts' );
		if ( ! is_wp_error( $listed ) ) {
			return $listed;
		}
		if ( 'not_found' !== $listed->get_error_code() || '' === $segment ) {
			return $listed;
		}
	}
	if ( '' !== $segment ) {
		return minimalcode_newsletter_list_contact_pages( '/segments/' . rawurlencode( $segment ) . '/contacts' );
	}
	return new WP_Error( 'config', 'Newsletter audience is not configured.' );
}

/**
 * @param string $email Address.
 * @return true|WP_Error
 */
function minimalcode_newsletter_mark_unsubscribed( $email ) {
	$email    = strtolower( trim( (string) $email ) );
	$audience = defined( 'MINIMALCODE_NEWSLETTER_AUDIENCE_ID' ) ? (string) MINIMALCODE_NEWSLETTER_AUDIENCE_ID : '';
	$body     = array( 'unsubscribed' => true );

	if ( '' !== $audience ) {
		$res = minimalcode_newsletter_resend( 'PATCH', '/audiences/' . rawurlencode( $audience ) . '/contacts/' . rawurlencode( $email ), $body );
		if ( ! is_wp_error( $res ) && $res['status'] >= 200 && $res['status'] < 300 ) {
			return true;
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 404 !== $res['status'] ) {
			return new WP_Error( 'resend', minimalcode_newsletter_resend_error_text( $res['status'], $res['body'] ) );
		}
	}

	$res = minimalcode_newsletter_resend( 'PATCH', '/contacts/' . rawurlencode( $email ), $body );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	if ( $res['status'] >= 200 && $res['status'] < 300 ) {
		return true;
	}
	return new WP_Error( 'resend', minimalcode_newsletter_resend_error_text( $res['status'], $res['body'] ) );
}

/**
 * @param string $email Recipient.
 * @return string
 */
function minimalcode_newsletter_unsubscribe_url( $email ) {
	if ( ! defined( 'MINIMALCODE_NEWSLETTER_CONFIRM_SECRET' ) ) {
		return '';
	}
	$token = minimalcode_newsletter_sign_token( $email, MINIMALCODE_NEWSLETTER_CONFIRM_SECRET, 365 * DAY_IN_SECONDS, time() );
	if ( '' === $token ) {
		return '';
	}
	return add_query_arg( 'token', $token, home_url( '/newsletter/unsubscribe/' ) );
}

/**
 * @param array  $state Send state.
 * @param string $email Recipient.
 * @return array{ok: bool, error: string}
 */
function minimalcode_newsletter_deliver_one( array $state, $email ) {
	$url = minimalcode_newsletter_unsubscribe_url( $email );
	if ( '' === $url ) {
		return array(
			'ok'    => false,
			'error' => 'Could not build an unsubscribe link.',
		);
	}
	$message = minimalcode_newsletter_message(
		array(
			'posts'            => $state['posts'],
			'unsubscribe_url'  => $url,
			'site_url'         => home_url( '/' ),
			'site_name'        => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'week_label'       => $state['week_label'] ?? '',
			'test'             => ! empty( $state['test'] ),
			'sample'           => ! empty( $state['sample'] ),
			'portrait_url'     => get_template_directory_uri() . '/assets/images/autojack-profile.jpg',
		)
	);
	if ( empty( $message['headers']['List-Unsubscribe'] ) || empty( $message['headers']['List-Unsubscribe-Post'] ) ) {
		return array(
			'ok'    => false,
			'error' => 'Refusing to send without List-Unsubscribe.',
		);
	}

	$idem = ! empty( $state['test'] )
		? 'test/' . gmdate( 'YmdHis' ) . '/' . bin2hex( random_bytes( 4 ) )
		: minimalcode_newsletter_idempotency_key( (string) $state['week_id'], $email );

	$res = minimalcode_newsletter_resend(
		'POST',
		'/emails',
		array(
			'from'    => minimalcode_newsletter_from(),
			'to'      => array( $email ),
			'subject' => $message['subject'],
			'html'    => $message['html'],
			'text'    => $message['text'],
			'headers' => $message['headers'],
		),
		array(
			'Idempotency-Key' => $idem,
		)
	);
	if ( is_wp_error( $res ) ) {
		return array(
			'ok'    => false,
			'error' => $res->get_error_message(),
		);
	}
	if ( $res['status'] < 200 || $res['status'] >= 300 ) {
		return array(
			'ok'    => false,
			'error' => minimalcode_newsletter_resend_error_text( $res['status'], $res['body'] ),
		);
	}
	return array(
		'ok'    => true,
		'error' => '',
	);
}

/**
 * @param array  $plan Plan.
 * @param string $trigger cron|manual|test.
 * @return void
 */
function minimalcode_newsletter_record_empty( array $plan, $trigger ) {
	$week_id = (string) $plan['week_id'];
	if ( get_option( 'minimalcode_nl_claim_' . $week_id ) ) {
		return;
	}
	if ( ! add_option( 'minimalcode_nl_claim_' . $week_id, 'skipped_empty', '', false ) ) {
		return;
	}
	minimalcode_newsletter_set_week_status( $week_id, 'skipped_empty' );
	minimalcode_newsletter_log_run(
		array(
			'week_id'          => $week_id,
			'date'             => gmdate( 'c' ),
			'post_ids'         => $plan['post_ids'],
			'recipient_count'  => 0,
			'sent_count'       => 0,
			'failure_count'    => 0,
			'failures'         => array(),
			'status'           => 'skipped_empty',
			'trigger'          => $trigger,
		)
	);
}

/**
 * @param array $state Active state.
 * @param string $override_status Optional status.
 * @return void
 */
function minimalcode_newsletter_finish( array $state, $override_status = '' ) {
	$sent     = (int) $state['sent'];
	$failures = array_slice( (array) $state['failures'], 0, 40 );
	$status   = $override_status;
	if ( '' === $status ) {
		if ( $sent > 0 && $failures ) {
			$status = 'partial';
		} elseif ( $sent > 0 ) {
			$status = 'sent';
		} else {
			$status = 'failed';
		}
	}

	$week_id = (string) $state['week_id'];
	$release = ( 'failed' === $status ) || ( 'cancelled' === $status && $sent < 1 );
	if ( $release ) {
		delete_option( 'minimalcode_nl_claim_' . $week_id );
		minimalcode_newsletter_set_week_status( $week_id, $status );
	} else {
		update_option( 'minimalcode_nl_claim_' . $week_id, 'sent', false );
		minimalcode_newsletter_set_week_status( $week_id, 'sent' );
	}

	minimalcode_newsletter_log_run(
		array(
			'week_id'         => $week_id,
			'date'            => gmdate( 'c' ),
			'post_ids'        => isset( $state['post_ids'] ) ? array_values( (array) $state['post_ids'] ) : array(),
			'recipient_count' => isset( $state['recipients'] ) ? count( (array) $state['recipients'] ) : 0,
			'sent_count'      => $sent,
			'failure_count'   => count( $failures ),
			'failures'        => $failures,
			'status'          => $status,
			'trigger'         => isset( $state['trigger'] ) ? (string) $state['trigger'] : '',
		)
	);
	delete_option( 'minimalcode_newsletter_active' );
}

/**
 * Send the next slice. One failure does not stop the rest; HTTP 429 pauses the batch.
 *
 * @param string $week_id Week id.
 * @return void
 */
function minimalcode_newsletter_process_batch( $week_id ) {
	$week_id = (string) $week_id;
	if ( minimalcode_newsletter_ci_blocked() ) {
		return;
	}
	$state = get_option( 'minimalcode_newsletter_active' );
	if ( ! is_array( $state ) || (string) ( $state['week_id'] ?? '' ) !== $week_id || ! empty( $state['finished'] ) ) {
		return;
	}
	if ( 'cron' === ( $state['trigger'] ?? '' ) && ! minimalcode_newsletter_settings()['enabled'] ) {
		minimalcode_newsletter_finish( $state, 'cancelled' );
		return;
	}

	$lock = (int) ( $state['lock'] ?? 0 );
	if ( $lock > time() - 90 ) {
		return;
	}
	$state['lock'] = time();
	update_option( 'minimalcode_newsletter_active', $state, false );

	$recipients = array_values( (array) $state['recipients'] );
	$offset     = (int) $state['offset'];
	$slice      = array_slice( $recipients, $offset, minimalcode_newsletter_batch_size() );
	$delay      = minimalcode_newsletter_send_delay_us();

	foreach ( $slice as $index => $email ) {
		$result = minimalcode_newsletter_deliver_one( $state, $email );
		if ( ! $result['ok'] && str_contains( $result['error'], 'HTTP 429' ) ) {
			$state['lock'] = 0;
			update_option( 'minimalcode_newsletter_active', $state, false );
			minimalcode_newsletter_schedule_single( time() + 120, 'minimalcode_newsletter_batch', array( $week_id ) );
			return;
		}
		if ( $result['ok'] ) {
			$state['sent']++;
		} else {
			$state['failures'][] = array(
				'email' => (string) $email,
				'error' => $result['error'],
			);
		}
		$state['offset']++;
		$state['lock'] = time();
		update_option( 'minimalcode_newsletter_active', $state, false );
		if ( $delay > 0 && $index < count( $slice ) - 1 ) {
			usleep( $delay );
		}
	}

	if ( (int) $state['offset'] >= count( $recipients ) ) {
		minimalcode_newsletter_finish( $state );
		return;
	}

	$state['lock'] = 0;
	update_option( 'minimalcode_newsletter_active', $state, false );
	minimalcode_newsletter_schedule_single( time() + 45, 'minimalcode_newsletter_batch', array( $week_id ) );
}
add_action( 'minimalcode_newsletter_batch', 'minimalcode_newsletter_process_batch' );

/**
 * @param array $plan Test plan.
 * @return array|WP_Error
 */
function minimalcode_newsletter_deliver_test( array $plan ) {
	$email = $plan['recipients'][0] ?? '';
	$state = array(
		'week_id'    => $plan['week_id'],
		'week_label' => $plan['week_label'],
		'post_ids'   => $plan['post_ids'],
		'posts'      => $plan['posts'],
		'test'       => true,
		'sample'     => ! empty( $plan['sample'] ),
	);
	$result = minimalcode_newsletter_deliver_one( $state, $email );
	minimalcode_newsletter_log_run(
		array(
			'week_id'         => $plan['week_id'],
			'date'            => gmdate( 'c' ),
			'post_ids'        => $plan['post_ids'],
			'recipient_count' => 1,
			'sent_count'      => $result['ok'] ? 1 : 0,
			'failure_count'   => $result['ok'] ? 0 : 1,
			'failures'        => $result['ok'] ? array() : array( array( 'email' => $email, 'error' => $result['error'] ) ),
			'status'          => $result['ok'] ? 'test' : 'test_failed',
			'trigger'         => 'test',
		)
	);
	if ( ! $result['ok'] ) {
		return new WP_Error( 'test_failed', $result['error'] );
	}
	return array(
		'action'  => 'test',
		'message' => 'Test issue sent to ' . $email . '.',
	);
}

/**
 * Start a cron send, a manual send, or a one-address test.
 *
 * @param string $trigger cron|manual|test.
 * @param string $test_email Required for test.
 * @return array|WP_Error
 */
function minimalcode_newsletter_begin( $trigger, $test_email = '' ) {
	if ( minimalcode_newsletter_ci_blocked() ) {
		return new WP_Error( 'skip_blocked', 'Real sends are disabled in CI and tests.' );
	}
	$missing = minimalcode_newsletter_missing_config();
	if ( $missing ) {
		return new WP_Error( 'config', 'Newsletter is not configured. Missing ' . implode( ', ', $missing ) . '.' );
	}

	$settings = minimalcode_newsletter_settings();
	if ( 'cron' === $trigger && ! $settings['enabled'] ) {
		return new WP_Error( 'skip_disabled', 'Automatic weekly send is off.' );
	}

	$tz     = wp_timezone();
	$now    = new DateTimeImmutable( 'now', $tz );
	$week   = minimalcode_newsletter_previous_week( $now, $tz );
	$posts  = minimalcode_newsletter_collect_posts( $week['start'], $week['end'] );
	$status = minimalcode_newsletter_week_status( $week['week_id'] );
	$claim  = get_option( 'minimalcode_nl_claim_' . $week['week_id'] );
	if ( $claim && 'failed' !== (string) $claim ) {
		$status = '' !== $status ? $status : (string) $claim;
	}

	$common = array(
		'trigger'       => $trigger,
		'enabled'       => $settings['enabled'],
		'sends_allowed' => true,
		'now'           => $now,
		'timezone'      => $tz,
		'posts'         => $posts,
		'week_status'   => $status,
		'test_email'    => $test_email,
		'batch_size'    => minimalcode_newsletter_batch_size(),
	);

	$pre = minimalcode_newsletter_plan( $common );
	if ( 'test' === $pre['action'] ) {
		return minimalcode_newsletter_deliver_test( $pre );
	}
	if ( in_array( $pre['action'], array( 'skip_disabled', 'skip_blocked', 'skip_duplicate', 'skip_empty', 'skip_invalid_test' ), true ) ) {
		if ( 'skip_empty' === $pre['action'] ) {
			minimalcode_newsletter_record_empty( $pre, $trigger );
		}
		return new WP_Error( $pre['action'], $pre['reason'] );
	}

	$fetched = minimalcode_newsletter_fetch_subscribers();
	if ( is_wp_error( $fetched ) ) {
		if ( ! minimalcode_newsletter_already_logged( $pre['week_id'], 'failed_fetch' ) ) {
			minimalcode_newsletter_log_run(
				array(
					'week_id'         => $pre['week_id'],
					'date'            => gmdate( 'c' ),
					'post_ids'        => $pre['post_ids'],
					'recipient_count' => 0,
					'sent_count'      => 0,
					'failure_count'   => 1,
					'failures'        => array(
						array(
							'email' => '',
							'error' => $fetched->get_error_message(),
						),
					),
					'status'          => 'failed_fetch',
					'trigger'         => $trigger,
				)
			);
		}
		return $fetched;
	}

	$plan = minimalcode_newsletter_plan(
		array_merge(
			$common,
			array(
				'subscribers' => $fetched,
			)
		)
	);
	if ( 'send' !== $plan['action'] ) {
		if ( 'skip_no_recipients' === $plan['action'] && ! minimalcode_newsletter_already_logged( $plan['week_id'], 'skip_no_recipients' ) ) {
			minimalcode_newsletter_log_run(
				array(
					'week_id'         => $plan['week_id'],
					'date'            => gmdate( 'c' ),
					'post_ids'        => $plan['post_ids'],
					'recipient_count' => 0,
					'sent_count'      => 0,
					'failure_count'   => 0,
					'failures'        => array(),
					'status'          => 'skip_no_recipients',
					'trigger'         => $trigger,
				)
			);
		}
		return new WP_Error( $plan['action'], $plan['reason'] );
	}

	if ( ! add_option( 'minimalcode_nl_claim_' . $plan['week_id'], 'sending', '', false ) ) {
		return new WP_Error( 'skip_duplicate', 'This week was already handled (sending).' );
	}
	minimalcode_newsletter_set_week_status( $plan['week_id'], 'sending' );

	$state = array(
		'week_id'     => $plan['week_id'],
		'week_label'  => $plan['week_label'],
		'post_ids'    => $plan['post_ids'],
		'posts'       => $plan['posts'],
		'recipients'  => $plan['recipients'],
		'offset'      => 0,
		'sent'        => 0,
		'failures'    => array(),
		'trigger'     => $trigger,
		'started_at'  => gmdate( 'c' ),
		'lock'        => 0,
		'finished'    => false,
		'test'        => false,
		'sample'      => false,
	);
	update_option( 'minimalcode_newsletter_active', $state, false );
	minimalcode_newsletter_process_batch( $plan['week_id'] );

	$active = get_option( 'minimalcode_newsletter_active' );
	if ( ! is_array( $active ) ) {
		return array(
			'action'  => 'send',
			'message' => 'Weekly issue handed to Resend.',
		);
	}
	$remaining = count( (array) $active['recipients'] ) - (int) $active['offset'];
	return array(
		'action'  => 'send',
		'message' => sprintf(
			'Sending started. %d sent so far, %d still queued. Further batches run on cron, about ten addresses at a time.',
			(int) $active['sent'],
			max( 0, $remaining )
		),
	);
}

/**
 * Weekly cron callback. No-ops while the opt-in toggle is off.
 *
 * @return void
 */
function minimalcode_newsletter_on_weekly() {
	if ( ! minimalcode_newsletter_settings()['enabled'] ) {
		return;
	}
	minimalcode_newsletter_begin( 'cron' );
	minimalcode_newsletter_ensure_schedule();
}
add_action( 'minimalcode_newsletter_weekly', 'minimalcode_newsletter_on_weekly' );

/**
 * @return void
 */
function minimalcode_newsletter_register_route() {
	add_rewrite_rule( '^newsletter/unsubscribe/?$', 'index.php?minimalcode_newsletter_unsub=1', 'top' );
}
add_action( 'init', 'minimalcode_newsletter_register_route' );

/**
 * @param array $vars Query vars.
 * @return array
 */
function minimalcode_newsletter_query_var( $vars ) {
	$vars[] = 'minimalcode_newsletter_unsub';
	return $vars;
}
add_filter( 'query_vars', 'minimalcode_newsletter_query_var' );

/**
 * @return bool
 */
function minimalcode_newsletter_is_unsubscribe_request() {
	if ( '1' === (string) get_query_var( 'minimalcode_newsletter_unsub' ) ) {
		return true;
	}
	$path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
	return 'newsletter/unsubscribe' === $path;
}

/**
 * @param string|false $redirect_url Canonical target.
 * @return string|false
 */
function minimalcode_newsletter_skip_canonical( $redirect_url ) {
	if ( minimalcode_newsletter_is_unsubscribe_request() ) {
		return false;
	}
	return $redirect_url;
}
add_filter( 'redirect_canonical', 'minimalcode_newsletter_skip_canonical' );

/**
 * GET shows a confirmation page. POST is the one-click List-Unsubscribe action.
 *
 * @return void
 */
function minimalcode_newsletter_maybe_unsubscribe() {
	if ( ! minimalcode_newsletter_is_unsubscribe_request() ) {
		return;
	}

	nocache_headers();
	header( 'Referrer-Policy: no-referrer' );
	header( 'X-Robots-Tag: noindex, nofollow' );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public token link, not a form.
	$token = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';

	if ( 'preview' === $token ) {
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo minimalcode_newsletter_unsubscribe_document( 'Preview only', 'This link is from a preview. Nobody was unsubscribed.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- builder escapes.
		exit;
	}

	$secret = defined( 'MINIMALCODE_NEWSLETTER_CONFIRM_SECRET' ) ? MINIMALCODE_NEWSLETTER_CONFIRM_SECRET : '';
	$result = minimalcode_newsletter_verify_token( $token, (string) $secret, time() );
	$ok     = false;
	$reason = $result['reason'];
	if ( $result['valid'] ) {
		$updated = minimalcode_newsletter_mark_unsubscribed( $result['email'] );
		$ok      = true === $updated;
		if ( ! $ok && is_wp_error( $updated ) ) {
			$reason = 'resend';
		}
	}

	if ( 'POST' === $method ) {
		status_header( $ok ? 200 : 400 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		exit;
	}

	if ( $ok ) {
		status_header( 200 );
		$heading = 'Unsubscribed';
		$message = "You won't get any more newsletter emails. Take care.";
	} elseif ( 'expired' === $reason ) {
		status_header( 400 );
		$heading = 'That link has expired';
		$message = 'Unsubscribe links last a year. Use the link in a newer issue, or reply and ask to be removed.';
	} else {
		status_header( 400 );
		$heading = 'Invalid link';
		$message = 'We could not process that unsubscribe link.';
	}
	header( 'Content-Type: text/html; charset=utf-8' );
	echo minimalcode_newsletter_unsubscribe_document( $heading, $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- builder escapes.
	exit;
}
add_action( 'template_redirect', 'minimalcode_newsletter_maybe_unsubscribe', 0 );

/**
 * @return void
 */
function minimalcode_newsletter_admin_menu() {
	add_options_page(
		__( 'Newsletter', 'minimalcode' ),
		__( 'Newsletter', 'minimalcode' ),
		'manage_options',
		'minimalcode-newsletter',
		'minimalcode_newsletter_admin_page'
	);
}
add_action( 'admin_menu', 'minimalcode_newsletter_admin_menu' );

/**
 * @param string $type success|error.
 * @param string $text Message.
 * @return void
 */
function minimalcode_newsletter_redirect_notice( $type, $text ) {
	set_transient(
		'minimalcode_nl_notice_' . get_current_user_id(),
		array(
			'type' => 'error' === $type ? 'error' : 'success',
			'text' => $text,
		),
		120
	);
	wp_safe_redirect( admin_url( 'options-general.php?page=minimalcode-newsletter' ) );
	exit;
}

/**
 * @return void
 */
function minimalcode_newsletter_guard_admin_post() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage the newsletter.', 'minimalcode' ), '', array( 'response' => 403 ) );
	}
}

/**
 * @return void
 */
function minimalcode_newsletter_handle_save() {
	minimalcode_newsletter_guard_admin_post();
	check_admin_referer( 'minimalcode_newsletter_save' );
	$settings = minimalcode_newsletter_save_settings(
		array(
			'enabled' => ! empty( $_POST['enabled'] ),
			'weekday' => isset( $_POST['weekday'] ) ? (int) wp_unslash( $_POST['weekday'] ) : 1,
			'hour'    => isset( $_POST['hour'] ) ? (int) wp_unslash( $_POST['hour'] ) : 8,
			'minute'  => isset( $_POST['minute'] ) ? (int) wp_unslash( $_POST['minute'] ) : 0,
		)
	);
	$text = $settings['enabled']
		? __( 'Saved. Automatic weekly send is on. The list is not mailed until the next scheduled slot.', 'minimalcode' )
		: __( 'Saved. Automatic weekly send is off.', 'minimalcode' );
	minimalcode_newsletter_redirect_notice( 'success', $text );
}
add_action( 'admin_post_minimalcode_newsletter_save', 'minimalcode_newsletter_handle_save' );

/**
 * @return void
 */
function minimalcode_newsletter_handle_test() {
	minimalcode_newsletter_guard_admin_post();
	check_admin_referer( 'minimalcode_newsletter_test' );
	$email  = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';
	$result = minimalcode_newsletter_begin( 'test', $email );
	if ( is_wp_error( $result ) ) {
		minimalcode_newsletter_redirect_notice( 'error', $result->get_error_message() );
	}
	minimalcode_newsletter_redirect_notice( 'success', (string) $result['message'] );
}
add_action( 'admin_post_minimalcode_newsletter_test', 'minimalcode_newsletter_handle_test' );

/**
 * @return void
 */
function minimalcode_newsletter_handle_send() {
	minimalcode_newsletter_guard_admin_post();
	check_admin_referer( 'minimalcode_newsletter_send' );
	$confirm = isset( $_POST['confirm_send'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm_send'] ) ) : '';
	if ( 'yes' !== $confirm ) {
		minimalcode_newsletter_redirect_notice( 'error', __( 'Send was not confirmed.', 'minimalcode' ) );
	}
	$result = minimalcode_newsletter_begin( 'manual' );
	if ( is_wp_error( $result ) ) {
		minimalcode_newsletter_redirect_notice( 'error', $result->get_error_message() );
	}
	minimalcode_newsletter_redirect_notice( 'success', (string) $result['message'] );
}
add_action( 'admin_post_minimalcode_newsletter_send', 'minimalcode_newsletter_handle_send' );

/**
 * Browser preview of the issue for the previous week. Sample layout if it's empty.
 *
 * @return void
 */
function minimalcode_newsletter_maybe_preview() {
	if ( ! is_admin() || ! isset( $_GET['page'] ) || 'minimalcode-newsletter' !== $_GET['page'] || empty( $_GET['preview'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to preview the newsletter.', 'minimalcode' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'minimalcode_newsletter_preview' );

	$tz    = wp_timezone();
	$now   = new DateTimeImmutable( 'now', $tz );
	$week  = minimalcode_newsletter_previous_week( $now, $tz );
	$posts = minimalcode_newsletter_collect_posts( $week['start'], $week['end'] );
	$sample = false;
	if ( ! $posts ) {
		$posts  = minimalcode_newsletter_sample_posts();
		$sample = true;
	}
	$message = minimalcode_newsletter_message(
		array(
			'posts'           => $posts,
			'unsubscribe_url' => home_url( '/newsletter/unsubscribe/?token=preview' ),
			'site_url'        => home_url( '/' ),
			'site_name'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'week_label'      => $week['start']->format( 'j M Y' ),
			'sample'          => $sample,
			'portrait_url'    => get_template_directory_uri() . '/assets/images/autojack-profile.jpg',
		)
	);
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	echo $message['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes.
	exit;
}
add_action( 'admin_init', 'minimalcode_newsletter_maybe_preview' );

/**
 * @param int $weekday ISO weekday.
 * @return string
 */
function minimalcode_newsletter_weekday_label( $weekday ) {
	$labels = array(
		1 => __( 'Monday', 'minimalcode' ),
		2 => __( 'Tuesday', 'minimalcode' ),
		3 => __( 'Wednesday', 'minimalcode' ),
		4 => __( 'Thursday', 'minimalcode' ),
		5 => __( 'Friday', 'minimalcode' ),
		6 => __( 'Saturday', 'minimalcode' ),
		7 => __( 'Sunday', 'minimalcode' ),
	);
	return $labels[ (int) $weekday ] ?? $labels[1];
}

/**
 * Settings screen.
 *
 * @return void
 */
function minimalcode_newsletter_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings = minimalcode_newsletter_settings();
	$missing  = minimalcode_newsletter_missing_config();
	$tz       = wp_timezone();
	$now      = new DateTimeImmutable( 'now', $tz );
	$week     = minimalcode_newsletter_previous_week( $now, $tz );
	$posts    = minimalcode_newsletter_collect_posts( $week['start'], $week['end'] );
	$status   = minimalcode_newsletter_week_status( $week['week_id'] );
	$next     = minimalcode_newsletter_next_scheduled( 'minimalcode_newsletter_weekly' );
	$notice   = get_transient( 'minimalcode_nl_notice_' . get_current_user_id() );
	if ( $notice ) {
		delete_transient( 'minimalcode_nl_notice_' . get_current_user_id() );
	}
	$log      = get_option( 'minimalcode_newsletter_log', array() );
	$preview  = wp_nonce_url( admin_url( 'options-general.php?page=minimalcode-newsletter&preview=1' ), 'minimalcode_newsletter_preview' );
	$engine   = minimalcode_newsletter_using_action_scheduler() ? 'Action Scheduler' : 'WP-Cron';
	$can_send = ! $missing && $posts && ! in_array( $status, array( 'sent', 'skipped_empty', 'sending' ), true );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Newsletter', 'minimalcode' ); ?></h1>
		<?php if ( is_array( $notice ) && ! empty( $notice['text'] ) ) : ?>
			<div class="notice notice-<?php echo 'error' === ( $notice['type'] ?? '' ) ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html( (string) $notice['text'] ); ?></p></div>
		<?php endif; ?>

		<p><?php esc_html_e( 'The signup form posts to the news.autojack.ai worker, which stores the address in Resend and sends the confirmation and welcome email. This screen sends the weekly issue to people who have confirmed. Pending and unsubscribed addresses are skipped.', 'minimalcode' ); ?></p>
		<p><strong><?php echo $settings['enabled'] ? esc_html__( 'Automatic weekly send is ON.', 'minimalcode' ) : esc_html__( 'Automatic weekly send is OFF.', 'minimalcode' ); ?></strong>
			<?php esc_html_e( 'Deploying the theme does not mail anyone. Turning the schedule on waits for the next slot — it does not send the current week by itself.', 'minimalcode' ); ?></p>

		<?php if ( $missing ) : ?>
			<div class="notice notice-warning"><p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: comma-separated wp-config constant names */
						__( 'Add these wp-config.php constants before a real send (same values as the newsletter worker — do not commit them): %s', 'minimalcode' ),
						implode( ', ', $missing )
					)
				);
				?>
			</p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Schedule', 'minimalcode' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'minimalcode_newsletter_save' ); ?>
			<input type="hidden" name="action" value="minimalcode_newsletter_save">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Automatic send', 'minimalcode' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>>
							<?php esc_html_e( 'Email confirmed subscribers every week', 'minimalcode' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="minimalcode-nl-weekday"><?php esc_html_e( 'Send on', 'minimalcode' ); ?></label></th>
					<td>
						<select name="weekday" id="minimalcode-nl-weekday">
							<?php for ( $day = 1; $day <= 7; $day++ ) : ?>
								<option value="<?php echo (int) $day; ?>" <?php selected( $settings['weekday'], $day ); ?>><?php echo esc_html( minimalcode_newsletter_weekday_label( $day ) ); ?></option>
							<?php endfor; ?>
						</select>
						<input type="number" name="hour" min="0" max="23" value="<?php echo (int) $settings['hour']; ?>" aria-label="<?php esc_attr_e( 'Hour', 'minimalcode' ); ?>">
						:
						<input type="number" name="minute" min="0" max="59" value="<?php echo (int) $settings['minute']; ?>" aria-label="<?php esc_attr_e( 'Minute', 'minimalcode' ); ?>">
						<span class="description"><?php echo esc_html( $tz->getName() ); ?></span>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save schedule', 'minimalcode' ) ); ?>
		</form>
		<p>
			<?php
			if ( ! $settings['enabled'] ) {
				esc_html_e( 'Nothing is scheduled.', 'minimalcode' );
			} elseif ( $next ) {
				echo esc_html(
					sprintf(
						/* translators: 1: localized datetime, 2: scheduler name */
						__( 'Next run: %1$s via %2$s.', 'minimalcode' ),
						wp_date( 'l j M Y, H:i T', $next ),
						$engine
					)
				);
			} else {
				esc_html_e( 'Enabled, but the next run is not queued yet. Reload this page.', 'minimalcode' );
			}
			?>
		</p>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: scheduler name */
					__( '%s only runs when WordPress is triggered. On a quiet Monday the issue waits until someone visits. Hit wp-cron.php from the server every five minutes, or run `wp cron event run --due-now`.', 'minimalcode' ),
					$engine
				)
			);
			?>
		</p>
		<p><code>*/5 * * * * curl -s <?php echo esc_html( site_url( 'wp-cron.php?doing_wp_cron' ) ); ?> &gt;/dev/null 2&gt;&amp;1</code></p>

		<h2><?php esc_html_e( 'This week’s issue', 'minimalcode' ); ?></h2>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: start datetime, 2: end datetime, 3: timezone */
					__( 'Posts from %1$s through %2$s (%3$s).', 'minimalcode' ),
					$week['start']->format( 'j M Y H:i' ),
					$week['end']->format( 'j M Y H:i' ),
					$tz->getName()
				)
			);
			?>
			<?php if ( $status ) : ?>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: week status */
						__( 'Week status: %s.', 'minimalcode' ),
						$status
					)
				);
				?>
			<?php endif; ?>
		</p>
		<?php if ( $posts ) : ?>
			<ul>
				<?php foreach ( $posts as $post ) : ?>
					<li><a href="<?php echo esc_url( $post['url'] ); ?>"><?php echo esc_html( $post['title'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'No posts in that window. The weekly send will be skipped.', 'minimalcode' ); ?></p>
		<?php endif; ?>
		<p><a class="button" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview in browser', 'minimalcode' ); ?></a></p>

		<h2><?php esc_html_e( 'Test send', 'minimalcode' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Sends this issue to one address, with [test] on the subject. It does not mark the week as sent and it does not mail the list.', 'minimalcode' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'minimalcode_newsletter_test' ); ?>
			<input type="hidden" name="action" value="minimalcode_newsletter_test">
			<input type="email" name="test_email" required maxlength="254" placeholder="you@example.com" aria-label="<?php esc_attr_e( 'Test recipient', 'minimalcode' ); ?>">
			<?php submit_button( __( 'Send test', 'minimalcode' ), 'secondary', 'submit', false ); ?>
		</form>

		<h2><?php esc_html_e( 'Send now', 'minimalcode' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Mails every confirmed subscriber, even while the weekly schedule is off. The same week cannot be sent twice. Empty weeks are skipped.', 'minimalcode' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Send this week’s issue to every confirmed subscriber?');">
			<?php wp_nonce_field( 'minimalcode_newsletter_send' ); ?>
			<input type="hidden" name="action" value="minimalcode_newsletter_send">
			<input type="hidden" name="confirm_send" value="yes">
			<?php submit_button( __( 'Send now', 'minimalcode' ), 'primary', 'submit', false, $can_send ? array() : array( 'disabled' => 'disabled' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'Log', 'minimalcode' ); ?></h2>
		<?php if ( ! is_array( $log ) || ! $log ) : ?>
			<p><?php esc_html_e( 'No sends yet.', 'minimalcode' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'When (UTC)', 'minimalcode' ); ?></th>
						<th><?php esc_html_e( 'Week', 'minimalcode' ); ?></th>
						<th><?php esc_html_e( 'Status', 'minimalcode' ); ?></th>
						<th><?php esc_html_e( 'Posts', 'minimalcode' ); ?></th>
						<th><?php esc_html_e( 'Recipients', 'minimalcode' ); ?></th>
						<th><?php esc_html_e( 'Sent', 'minimalcode' ); ?></th>
						<th><?php esc_html_e( 'Failures', 'minimalcode' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log as $row ) : ?>
						<?php if ( ! is_array( $row ) ) { continue; } ?>
						<tr>
							<td><?php echo esc_html( (string) ( $row['date'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['week_id'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['status'] ?? '' ) ); ?> <span class="description"><?php echo esc_html( (string) ( $row['trigger'] ?? '' ) ); ?></span></td>
							<td><?php echo esc_html( implode( ', ', array_map( 'strval', (array) ( $row['post_ids'] ?? array() ) ) ) ); ?></td>
							<td><?php echo (int) ( $row['recipient_count'] ?? 0 ); ?></td>
							<td><?php echo (int) ( $row['sent_count'] ?? 0 ); ?></td>
							<td>
								<?php echo (int) ( $row['failure_count'] ?? 0 ); ?>
								<?php if ( ! empty( $row['failures'] ) && is_array( $row['failures'] ) ) : ?>
									<ul>
										<?php foreach ( array_slice( $row['failures'], 0, 10 ) as $failure ) : ?>
											<?php if ( ! is_array( $failure ) ) { continue; } ?>
											<li><?php echo esc_html( trim( (string) ( $failure['email'] ?? '' ) . ' ' . (string) ( $failure['error'] ?? '' ) ) ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'WP-CLI', 'minimalcode' ); ?></h2>
		<pre>wp minimalcode newsletter status
wp minimalcode newsletter preview
wp minimalcode newsletter test you@example.com
wp minimalcode newsletter send --yes
wp minimalcode newsletter enable
wp minimalcode newsletter disable</pre>
	</div>
	<?php
}

/**
 * WP-CLI: wp minimalcode newsletter …
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Weekly newsletter commands.
	 */
	class Minimalcode_Newsletter_CLI {
		/**
		 * Inspect or run the weekly newsletter.
		 *
		 * ## OPTIONS
		 *
		 * <command>
		 * : status, preview, test, send, enable, or disable.
		 *
		 * [<email>]
		 * : Recipient for `test`.
		 *
		 * [--yes]
		 * : Skip the confirmation prompt for `send`.
		 *
		 * ## EXAMPLES
		 *
		 *     wp minimalcode newsletter status
		 *     wp minimalcode newsletter preview
		 *     wp minimalcode newsletter test you@example.com
		 *     wp minimalcode newsletter send --yes
		 *
		 * @param array $args Positional args.
		 * @param array $assoc Assoc args.
		 * @return void
		 */
		public function __invoke( $args, $assoc ) {
			$command = isset( $args[0] ) ? (string) $args[0] : 'status';
			switch ( $command ) {
				case 'status':
					$this->status();
					return;
				case 'preview':
					$this->preview();
					return;
				case 'test':
					$email = isset( $args[1] ) ? (string) $args[1] : '';
					$result = minimalcode_newsletter_begin( 'test', $email );
					if ( is_wp_error( $result ) ) {
						WP_CLI::error( $result->get_error_message() );
					}
					WP_CLI::success( (string) $result['message'] );
					return;
				case 'send':
					WP_CLI::confirm( 'Send this week’s issue to every confirmed subscriber?', $assoc );
					$result = minimalcode_newsletter_begin( 'manual' );
					if ( is_wp_error( $result ) ) {
						WP_CLI::error( $result->get_error_message() );
					}
					WP_CLI::success( (string) $result['message'] );
					return;
				case 'enable':
					$settings           = minimalcode_newsletter_settings();
					$settings['enabled'] = true;
					minimalcode_newsletter_save_settings( $settings );
					WP_CLI::success( 'Automatic weekly send is on. The list is not mailed until the next slot.' );
					return;
				case 'disable':
					$settings            = minimalcode_newsletter_settings();
					$settings['enabled'] = false;
					minimalcode_newsletter_save_settings( $settings );
					WP_CLI::success( 'Automatic weekly send is off.' );
					return;
				default:
					WP_CLI::error( 'Unknown command. Use status, preview, test, send, enable, or disable.' );
			}
		}

		/**
		 * @return void
		 */
		private function status() {
			$settings = minimalcode_newsletter_settings();
			$next     = minimalcode_newsletter_next_scheduled( 'minimalcode_newsletter_weekly' );
			$missing  = minimalcode_newsletter_missing_config();
			WP_CLI::log( 'enabled: ' . ( $settings['enabled'] ? 'yes' : 'no' ) );
			WP_CLI::log( 'slot: ' . minimalcode_newsletter_weekday_label( $settings['weekday'] ) . ' ' . sprintf( '%02d:%02d', $settings['hour'], $settings['minute'] ) . ' ' . wp_timezone_string() );
			WP_CLI::log( 'scheduler: ' . ( minimalcode_newsletter_using_action_scheduler() ? 'action-scheduler' : 'wp-cron' ) );
			WP_CLI::log( 'next: ' . ( $next ? wp_date( 'c', $next ) : 'none' ) );
			WP_CLI::log( 'configured: ' . ( $missing ? 'missing ' . implode( ', ', $missing ) : 'yes' ) );
			WP_CLI::log( 'ci_blocked: ' . ( minimalcode_newsletter_ci_blocked() ? 'yes' : 'no' ) );
		}

		/**
		 * @return void
		 */
		private function preview() {
			$tz     = wp_timezone();
			$now    = new DateTimeImmutable( 'now', $tz );
			$week   = minimalcode_newsletter_previous_week( $now, $tz );
			$posts  = minimalcode_newsletter_collect_posts( $week['start'], $week['end'] );
			$sample = false;
			if ( ! $posts ) {
				$posts  = minimalcode_newsletter_sample_posts();
				$sample = true;
			}
			$message = minimalcode_newsletter_message(
				array(
					'posts'           => $posts,
					'unsubscribe_url' => home_url( '/newsletter/unsubscribe/?token=preview' ),
					'site_url'        => home_url( '/' ),
					'site_name'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					'week_label'      => $week['start']->format( 'j M Y' ),
					'sample'          => $sample,
				)
			);
			WP_CLI::log( $message['html'] );
		}
	}

	WP_CLI::add_command( 'minimalcode newsletter', 'Minimalcode_Newsletter_CLI' );
}
