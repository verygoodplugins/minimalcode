<?php
/**
 * Weekly newsletter planner, token, and template checks.
 *
 * No WordPress, no network, no Resend. The real list must never be touched.
 */

require dirname( __DIR__ ) . '/inc/newsletter-digest.php';

$failures = array();

/**
 * @param bool   $condition Condition.
 * @param string $message Failure message.
 * @return void
 */
function minimalcode_newsletter_assert( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$defaults = minimalcode_newsletter_default_settings();
minimalcode_newsletter_assert( false === $defaults['enabled'], 'default enabled must be false' );
minimalcode_newsletter_assert( 1 === $defaults['weekday'] && 8 === $defaults['hour'] && 0 === $defaults['minute'], 'default slot is Monday 08:00' );

$sanitized = minimalcode_newsletter_sanitize_settings(
	array(
		'weekday' => 9,
		'hour'    => 25,
		'minute'  => -4,
	)
);
minimalcode_newsletter_assert( false === $sanitized['enabled'], 'missing enabled stays off' );
minimalcode_newsletter_assert( 7 === $sanitized['weekday'] && 23 === $sanitized['hour'] && 0 === $sanitized['minute'], 'out of range slot is clamped' );

$berlin = new DateTimeZone( 'Europe/Berlin' );
$monday = new DateTimeImmutable( '2026-11-09 08:00:00', $berlin );
$week   = minimalcode_newsletter_previous_week( $monday, $berlin );
minimalcode_newsletter_assert( '2026-11-02' === $week['week_id'], 'Monday morning week id got ' . $week['week_id'] );
minimalcode_newsletter_assert( '2026-11-02 00:00:00' === $week['start']->format( 'Y-m-d H:i:s' ), 'week start got ' . $week['start']->format( 'c' ) );
minimalcode_newsletter_assert( '2026-11-08 23:59:59' === $week['end']->format( 'Y-m-d H:i:s' ), 'week end got ' . $week['end']->format( 'c' ) );

$sunday = new DateTimeImmutable( '2026-11-08 23:00:00', $berlin );
$prior  = minimalcode_newsletter_previous_week( $sunday, $berlin );
minimalcode_newsletter_assert( '2026-10-26' === $prior['week_id'], 'Sunday still uses the previous complete week, got ' . $prior['week_id'] );

$early = new DateTimeImmutable( '2026-11-09 07:00:00', $berlin );
$slot  = minimalcode_newsletter_next_slot( $early, $berlin, 1, 8, 0 );
minimalcode_newsletter_assert( '2026-11-09 08:00:00' === $slot->format( 'Y-m-d H:i:s' ), 'before the slot stays on today, got ' . $slot->format( 'c' ) );

$exact = minimalcode_newsletter_next_slot( $monday, $berlin, 1, 8, 0 );
minimalcode_newsletter_assert( '2026-11-16 08:00:00' === $exact->format( 'Y-m-d H:i:s' ), 'at the slot rolls to next week, got ' . $exact->format( 'c' ) );

$wednesday = new DateTimeImmutable( '2026-11-11 12:00:00', $berlin );
$next_mon  = minimalcode_newsletter_next_slot( $wednesday, $berlin, 1, 8, 0 );
minimalcode_newsletter_assert( '2026-11-16 08:00:00' === $next_mon->format( 'Y-m-d H:i:s' ), 'Wednesday targets next Monday, got ' . $next_mon->format( 'c' ) );

$secret = 'test-secret-value!';
$token  = minimalcode_newsletter_sign_token( 'Reader@Example.com', $secret, 100000000, 1700000000 );
minimalcode_newsletter_assert(
	'eyJlIjoicmVhZGVyQGV4YW1wbGUuY29tIiwieCI6MTgwMDAwMDAwMH0.K45WsP1nvIiOUPU9AJhmPtlfGdaOWRSOT-UhMq7zHrM' === $token,
	'token does not match the worker HMAC vector: ' . $token
);
$verified = minimalcode_newsletter_verify_token( $token, $secret, 1700000000 );
minimalcode_newsletter_assert( true === $verified['valid'] && 'reader@example.com' === $verified['email'], 'token did not verify' );
$tampered = minimalcode_newsletter_verify_token( $token . 'x', $secret, 1700000000 );
minimalcode_newsletter_assert( false === $tampered['valid'], 'tampered token was accepted' );
$expired = minimalcode_newsletter_verify_token( $token, $secret, 1800000001 );
minimalcode_newsletter_assert( false === $expired['valid'] && 'expired' === $expired['reason'], 'expired token reason got ' . $expired['reason'] );
minimalcode_newsletter_assert( '' === minimalcode_newsletter_sign_token( 'reader@example.com', 'short', 3600, 1700000000 ), 'short secret must not sign' );

$confirmed = minimalcode_newsletter_confirmed_subscribers(
	array(
		array( 'email' => 'Yes@Example.com', 'unsubscribed' => false ),
		array( 'email' => 'yes@example.com', 'unsubscribed' => false ),
		array( 'email' => 'pending@example.com', 'unsubscribed' => true ),
		array( 'email' => 'left@example.com', 'unsubscribed' => true ),
		array( 'email' => 'missing@example.com' ),
		array( 'email' => 'stringfalse@example.com', 'unsubscribed' => 'false' ),
		array( 'email' => 'zero@example.com', 'unsubscribed' => 0 ),
		array( 'email' => 'not-an-email', 'unsubscribed' => false ),
		'nope',
	)
);
minimalcode_newsletter_assert( array( 'yes@example.com' ) === $confirmed, 'confirmed filter got ' . implode( ',', $confirmed ) );

$posts = array(
	array(
		'id'       => 12,
		'title'    => 'Claude-local: Qwen in the window',
		'excerpt'  => 'Jack kept Claude Code and changed what answers it.',
		'url'      => 'https://drunk.support/claude-local/',
		'date'     => '8 Nov 2026',
		'category' => 'autojack',
	),
	array(
		'id'      => 13,
		'title'   => '<script>alert(1)</script>',
		'excerpt' => 'A second note.',
		'url'     => 'https://drunk.support/second/',
		'date'    => '6 Nov 2026',
	),
);

$plan_off = minimalcode_newsletter_plan(
	array(
		'trigger'       => 'cron',
		'enabled'       => false,
		'sends_allowed' => true,
		'now'           => $monday,
		'timezone'      => $berlin,
		'posts'         => $posts,
		'subscribers'   => array(
			array( 'email' => 'yes@example.com', 'unsubscribed' => false ),
		),
	)
);
minimalcode_newsletter_assert( 'skip_disabled' === $plan_off['action'], 'disabled cron action got ' . $plan_off['action'] );
minimalcode_newsletter_assert( array() === $plan_off['recipients'], 'disabled cron must not select recipients' );

$plan_manual = minimalcode_newsletter_plan(
	array(
		'trigger'       => 'manual',
		'enabled'       => false,
		'sends_allowed' => true,
		'now'           => $monday,
		'timezone'      => $berlin,
		'posts'         => $posts,
		'subscribers'   => array(
			array( 'email' => 'yes@example.com', 'unsubscribed' => false ),
			array( 'email' => 'pending@example.com', 'unsubscribed' => true ),
		),
		'batch_size'    => 10,
	)
);
minimalcode_newsletter_assert( 'send' === $plan_manual['action'], 'manual send while automatic is off got ' . $plan_manual['action'] );
minimalcode_newsletter_assert( array( 'yes@example.com' ) === $plan_manual['recipients'], 'manual send included a non-confirmed address' );
minimalcode_newsletter_assert( '2026-11-02' === $plan_manual['week_id'], 'manual week id got ' . $plan_manual['week_id'] );

$plan_empty = minimalcode_newsletter_plan(
	array(
		'trigger'       => 'cron',
		'enabled'       => true,
		'sends_allowed' => true,
		'now'           => $monday,
		'timezone'      => $berlin,
		'posts'         => array(),
		'subscribers'   => array(
			array( 'email' => 'yes@example.com', 'unsubscribed' => false ),
		),
	)
);
minimalcode_newsletter_assert( 'skip_empty' === $plan_empty['action'], 'empty week got ' . $plan_empty['action'] );
minimalcode_newsletter_assert( array() === $plan_empty['recipients'], 'empty week must not select recipients' );

$plan_dup = minimalcode_newsletter_plan(
	array(
		'trigger'       => 'cron',
		'enabled'       => true,
		'sends_allowed' => true,
		'now'           => $monday,
		'timezone'      => $berlin,
		'posts'         => $posts,
		'week_status'   => 'sent',
		'subscribers'   => array(
			array( 'email' => 'yes@example.com', 'unsubscribed' => false ),
		),
	)
);
minimalcode_newsletter_assert( 'skip_duplicate' === $plan_dup['action'], 'second send got ' . $plan_dup['action'] );

$many = array();
for ( $i = 0; $i < 12; $i++ ) {
	$many[] = array(
		'email'        => 'reader' . $i . '@example.com',
		'unsubscribed' => false,
	);
}
$plan_batch = minimalcode_newsletter_plan(
	array(
		'trigger'       => 'cron',
		'enabled'       => true,
		'sends_allowed' => true,
		'now'           => $monday,
		'timezone'      => $berlin,
		'posts'         => array( $posts[0] ),
		'subscribers'   => $many,
		'batch_size'    => 5,
	)
);
minimalcode_newsletter_assert( 'send' === $plan_batch['action'], 'batch plan got ' . $plan_batch['action'] );
minimalcode_newsletter_assert( 3 === count( $plan_batch['batches'] ), 'expected 3 batches, got ' . count( $plan_batch['batches'] ) );
minimalcode_newsletter_assert( 5 === count( $plan_batch['batches'][0] ) && 2 === count( $plan_batch['batches'][2] ), 'batch sizes were wrong' );

$plan_ci = minimalcode_newsletter_plan(
	array(
		'trigger'       => 'cron',
		'enabled'       => true,
		'sends_allowed' => false,
		'now'           => $monday,
		'timezone'      => $berlin,
		'posts'         => $posts,
		'subscribers'   => $many,
	)
);
minimalcode_newsletter_assert( 'skip_blocked' === $plan_ci['action'], 'CI cron got ' . $plan_ci['action'] );
minimalcode_newsletter_assert( array() === $plan_ci['recipients'], 'CI plan selected recipients' );

$plan_ci_test = minimalcode_newsletter_plan(
	array(
		'trigger'       => 'test',
		'enabled'       => true,
		'sends_allowed' => false,
		'now'           => $monday,
		'timezone'      => $berlin,
		'test_email'    => 'qa@example.com',
		'posts'         => $posts,
	)
);
minimalcode_newsletter_assert( 'skip_blocked' === $plan_ci_test['action'], 'CI test send got ' . $plan_ci_test['action'] );

minimalcode_newsletter_assert( false === minimalcode_newsletter_real_send_allowed( array( 'GITHUB_ACTIONS' => 'true' ), false ), 'GitHub Actions must block sends' );
minimalcode_newsletter_assert( false === minimalcode_newsletter_real_send_allowed( array( 'CI' => 'true' ), false ), 'CI must block sends' );
minimalcode_newsletter_assert( true === minimalcode_newsletter_real_send_allowed( array( 'CI' => 'true' ), true ), 'explicit allow overrides CI' );
minimalcode_newsletter_assert( true === minimalcode_newsletter_real_send_allowed( array(), false ), 'a normal server may send' );

$key_a = minimalcode_newsletter_idempotency_key( '2026-11-02', 'Yes@Example.com' );
$key_b = minimalcode_newsletter_idempotency_key( '2026-11-02', 'yes@example.com' );
minimalcode_newsletter_assert( $key_a === $key_b && str_starts_with( $key_a, 'weekly/2026-11-02/' ), 'idempotency key was not stable' );

$unsub = 'https://drunk.support/newsletter/unsubscribe/?token=' . $token;
$html  = minimalcode_newsletter_render_html(
	array(
		'posts'           => $posts,
		'unsubscribe_url' => $unsub,
		'week_label'      => '2 Nov 2026',
		'site_url'        => 'https://drunk.support/',
		'site_name'       => 'drunk.support',
	)
);
$text = minimalcode_newsletter_render_text(
	array(
		'posts'           => $posts,
		'unsubscribe_url' => $unsub,
		'week_label'      => '2 Nov 2026',
	)
);
$message = minimalcode_newsletter_message(
	array(
		'posts'           => $posts,
		'unsubscribe_url' => $unsub,
		'week_label'      => '2 Nov 2026',
	)
);

minimalcode_newsletter_assert( str_contains( $html, 'Claude-local: Qwen in the window' ), 'html missing title' );
minimalcode_newsletter_assert( str_contains( $html, 'Jack kept Claude Code and changed what answers it.' ), 'html missing excerpt' );
minimalcode_newsletter_assert( str_contains( $html, 'https://drunk.support/claude-local/' ), 'html missing link' );
minimalcode_newsletter_assert( str_contains( $html, 'Unsubscribe' ), 'html missing unsubscribe link' );
minimalcode_newsletter_assert( str_contains( $html, 'AutoJack' ) && str_contains( $html, 'written and sent by an AI' ), 'html footer does not match the welcome email' );
minimalcode_newsletter_assert( str_contains( $html, 'drunk.support' ), 'html missing masthead' );
minimalcode_newsletter_assert( str_contains( $html, '&lt;script&gt;alert(1)&lt;/script&gt;' ), 'html did not escape the title' );
minimalcode_newsletter_assert( ! str_contains( $html, $secret ), 'html leaked the signing secret' );
minimalcode_newsletter_assert( str_contains( $text, 'Claude-local: Qwen in the window' ), 'text missing title' );
minimalcode_newsletter_assert( str_contains( $text, 'https://drunk.support/claude-local/' ), 'text missing link' );
minimalcode_newsletter_assert( str_contains( $text, 'Unsubscribe: ' . $unsub ), 'text missing unsubscribe' );
minimalcode_newsletter_assert( '<https://drunk.support/newsletter/unsubscribe/?token=' . $token . '>' === $message['headers']['List-Unsubscribe'], 'List-Unsubscribe header mismatch' );
minimalcode_newsletter_assert( 'List-Unsubscribe=One-Click' === $message['headers']['List-Unsubscribe-Post'], 'List-Unsubscribe-Post missing' );
minimalcode_newsletter_assert( str_starts_with( $message['subject'], 'This week on drunk.support' ), 'subject got ' . $message['subject'] );

$unsafe = minimalcode_newsletter_message(
	array(
		'posts'           => array( $posts[0] ),
		'unsubscribe_url' => 'javascript:alert(1)',
	)
);
minimalcode_newsletter_assert( array() === $unsafe['headers'], 'javascript unsubscribe url produced a header' );
minimalcode_newsletter_assert( ! str_contains( $unsafe['html'], 'javascript:' ), 'javascript url was rendered' );

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "Weekly newsletter planner ok.\n";
