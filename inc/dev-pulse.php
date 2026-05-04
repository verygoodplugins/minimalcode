<?php
/**
 * Dev Pulse data fetch + slim-payload helpers.
 *
 * The page-dev-pulse.php template renders an interactive dashboard sourced
 * from the activity-report repo's daily-generated activity-data.json. We
 * fetch server-side, cache in a transient, and inline a slimmed payload so
 * the dashboard JS can hydrate without a client-side network round-trip.
 *
 * @package MinimalCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MINIMALCODE_DEVPULSE_DATA_URL  = 'https://raw.githubusercontent.com/verygoodplugins/activity-report/main/activity-data.json';
const MINIMALCODE_DEVPULSE_CACHE_KEY = 'minimalcode_devpulse_v1';
const MINIMALCODE_DEVPULSE_LAST_GOOD = 'minimalcode_devpulse_last_good';
const MINIMALCODE_DEVPULSE_CACHE_TTL = 6 * HOUR_IN_SECONDS;

/**
 * Fetch the raw activity JSON from GitHub. Returns parsed array or WP_Error.
 */
function minimalcode_devpulse_fetch_raw() {
	$response = wp_remote_get(
		MINIMALCODE_DEVPULSE_DATA_URL,
		array(
			'timeout' => 8,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'devpulse_http', 'activity-data.json fetch returned ' . $code );
	}

	$body = wp_remote_retrieve_body( $response );
	if ( '' === $body ) {
		return new WP_Error( 'devpulse_empty', 'activity-data.json fetch returned empty body' );
	}

	$decoded = json_decode( $body, true );
	if ( ! is_array( $decoded ) ) {
		return new WP_Error( 'devpulse_decode', 'activity-data.json could not be decoded' );
	}

	return $decoded;
}

/**
 * Strip heavy fields the dashboard never renders. Original payload is ~660KB;
 * slim form is ~150KB raw, ~30KB gzipped.
 */
function minimalcode_devpulse_slim_payload( array $raw ) {
	$slim = array(
		'generatedAt' => $raw['generatedAt'] ?? null,
		'periodStart' => $raw['periodStart'] ?? null,
		'stats'       => $raw['stats'] ?? array(),
		'repos'       => $raw['repos'] ?? array(),
		'categories'  => $raw['categories'] ?? null,
		'diagnostics' => $raw['diagnostics'] ?? null,
		'commits'     => array(),
		'prs'         => array(),
	);

	foreach ( $raw['commits'] ?? array() as $commit ) {
		$slim['commits'][] = array(
			'hash'      => $commit['hash'] ?? '',
			'shortHash' => $commit['shortHash'] ?? '',
			'date'      => $commit['date'] ?? '',
			'headline'  => $commit['headline'] ?? '',
			'body'      => mb_substr( (string) ( $commit['body'] ?? '' ), 0, 300 ),
			'repo'      => $commit['repo'] ?? '',
			'owner'     => $commit['owner'] ?? '',
			'remoteUrl' => $commit['remoteUrl'] ?? '',
			'stats'     => $commit['stats'] ?? array(),
		);
	}

	foreach ( $raw['prs'] ?? array() as $pr ) {
		$slim['prs'][] = array(
			'number'     => $pr['number'] ?? null,
			'title'      => $pr['title'] ?? '',
			'url'        => $pr['url'] ?? '',
			'state'      => $pr['state'] ?? '',
			'date'       => $pr['date'] ?? '',
			'repo'       => $pr['repo'] ?? '',
			'baseBranch' => $pr['baseBranch'] ?? '',
			'headBranch' => $pr['headBranch'] ?? '',
			'mergeable'  => $pr['mergeable'] ?? '',
			'commits'    => $pr['commits'] ?? 0,
			'stats'      => $pr['stats'] ?? array(),
			'labels'     => $pr['labels'] ?? array(),
			'body'       => mb_substr( (string) ( $pr['body'] ?? '' ), 0, 500 ),
		);
	}

	return $slim;
}

/**
 * Skeleton payload for cold starts when both the live fetch and the
 * last-good stash miss. The dashboard JS handles these zeros gracefully.
 */
function minimalcode_devpulse_empty_payload() {
	return array(
		'generatedAt' => null,
		'periodStart' => null,
		'stats'       => array(
			'commits'   => 0,
			'prs'       => 0,
			'additions' => 0,
			'deletions' => 0,
			'files'     => 0,
		),
		'repos'       => array(),
		'categories'  => null,
		'diagnostics' => null,
		'commits'     => array(),
		'prs'         => array(),
	);
}

/**
 * Get the slim payload, with stale-while-revalidate fallback.
 */
function minimalcode_devpulse_get_data() {
	$cached = get_transient( MINIMALCODE_DEVPULSE_CACHE_KEY );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$raw = minimalcode_devpulse_fetch_raw();
	if ( ! is_wp_error( $raw ) ) {
		$slim = minimalcode_devpulse_slim_payload( $raw );
		set_transient( MINIMALCODE_DEVPULSE_CACHE_KEY, $slim, MINIMALCODE_DEVPULSE_CACHE_TTL );
		update_option( MINIMALCODE_DEVPULSE_LAST_GOOD, $slim, false );
		return $slim;
	}

	$last_good = get_option( MINIMALCODE_DEVPULSE_LAST_GOOD );
	if ( is_array( $last_good ) ) {
		return $last_good;
	}

	return minimalcode_devpulse_empty_payload();
}

/**
 * Inline the slim payload as <script type="application/json">. Type prevents
 * the browser from executing it; the dashboard JS reads textContent.
 */
function minimalcode_devpulse_render_payload_script( array $payload ) {
	$encoded = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
	if ( false === $encoded ) {
		return;
	}
	echo '<script id="dev-pulse-data" type="application/json">' . $encoded . '</script>';
}

/**
 * Enqueue dev-pulse JS + CSS, but only on the /dev-pulse/ virtual route.
 * Hooks wp_enqueue_scripts so the stylesheet lands in <head>.
 */
function minimalcode_devpulse_maybe_enqueue_assets() {
	if ( get_query_var( 'minimalcode_virtual' ) !== 'dev-pulse' ) {
		return;
	}

	$theme_dir = get_template_directory();
	$theme_uri = get_template_directory_uri();

	wp_enqueue_style(
		'minimalcode-dev-pulse',
		$theme_uri . '/assets/css/dev-pulse.css',
		array( 'minimalcode-custom' ),
		filemtime( $theme_dir . '/assets/css/dev-pulse.css' )
	);

	wp_enqueue_script(
		'minimalcode-dev-pulse',
		$theme_uri . '/assets/js/dev-pulse.js',
		array(),
		filemtime( $theme_dir . '/assets/js/dev-pulse.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'minimalcode_devpulse_maybe_enqueue_assets' );
