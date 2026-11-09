<?php
/**
 * Pure weekly-newsletter helpers.
 *
 * No WordPress calls. newsletter-weekly.php wires these to cron, Resend,
 * and wp-admin. The signup list itself lives in Resend, written by the
 * news.autojack.ai worker — this file only decides who may be mailed and
 * how the issue looks.
 *
 * @package MinimalCode
 */

/**
 * Brand tokens copied from the welcome email in the autojack-newsletter worker.
 *
 * @return array<string, string>
 */
function minimalcode_newsletter_brand() {
	return array(
		'paper'          => '#f3efe6',
		'paperDark'      => '#ebe5d6',
		'ink'            => '#0e0d0c',
		'inkSoft'        => '#2a2724',
		'inkMute'        => '#6b665c',
		'rule'           => '#1a1814',
		'hot'            => '#e3ff04',
		'link'           => '#b53114',
		'autojackStart'  => '#6366f1',
		'autojackEnd'    => '#8b5cf6',
		'fontSerif'      => "Georgia, 'Times New Roman', Times, serif",
		'fontSans'       => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
		'fontMono'       => "'Courier New', Courier, monospace",
		'portraitUrl'    => 'https://drunk.support/wp-content/themes/minimalcode/assets/images/autojack-profile.jpg',
		'siteUrl'        => 'https://drunk.support',
		'siteName'       => 'drunk.support',
	);
}

/**
 * Defaults. Automatic weekly sending is off until someone opts in.
 *
 * @return array{enabled: bool, weekday: int, hour: int, minute: int}
 */
function minimalcode_newsletter_default_settings() {
	return array(
		'enabled' => false,
		'weekday' => 1,
		'hour'    => 8,
		'minute'  => 0,
	);
}

/**
 * @param mixed $input Raw settings.
 * @return array{enabled: bool, weekday: int, hour: int, minute: int}
 */
function minimalcode_newsletter_sanitize_settings( $input ) {
	$defaults = minimalcode_newsletter_default_settings();
	if ( ! is_array( $input ) ) {
		return $defaults;
	}
	$weekday = isset( $input['weekday'] ) ? (int) $input['weekday'] : $defaults['weekday'];
	$hour    = isset( $input['hour'] ) ? (int) $input['hour'] : $defaults['hour'];
	$minute  = isset( $input['minute'] ) ? (int) $input['minute'] : $defaults['minute'];
	return array(
		'enabled' => ! empty( $input['enabled'] ),
		'weekday' => max( 1, min( 7, $weekday ) ),
		'hour'    => max( 0, min( 23, $hour ) ),
		'minute'  => max( 0, min( 59, $minute ) ),
	);
}

/**
 * HTML-escape a string for the email body.
 *
 * @param string $text Raw text.
 * @return string
 */
function minimalcode_newsletter_esc( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * Allow only http(s) URLs with no whitespace or quotes.
 *
 * @param string $url URL.
 * @return string
 */
function minimalcode_newsletter_safe_url( $url ) {
	$url = trim( (string) $url );
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		return '';
	}
	if ( preg_match( '/[\s<>"\']/', $url ) ) {
		return '';
	}
	return $url;
}

/**
 * @param string $email Address.
 * @return bool
 */
function minimalcode_newsletter_valid_email( $email ) {
	$email = strtolower( trim( (string) $email ) );
	if ( strlen( $email ) > 254 || strlen( $email ) < 3 ) {
		return false;
	}
	return (bool) preg_match( '/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email );
}

/**
 * Base64url without padding, matching the worker's token encoding.
 *
 * @param string $bytes Raw bytes.
 * @return string
 */
function minimalcode_newsletter_b64url_encode( $bytes ) {
	return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
}

/**
 * @param string $encoded Base64url.
 * @return string|null Raw bytes, or null when malformed.
 */
function minimalcode_newsletter_b64url_decode( $encoded ) {
	$encoded = (string) $encoded;
	if ( '' === $encoded || ! preg_match( '/^[A-Za-z0-9_-]+$/', $encoded ) ) {
		return null;
	}
	$padded = strtr( $encoded, '-_', '+/' );
	$pad    = strlen( $padded ) % 4;
	if ( 0 !== $pad ) {
		$padded .= str_repeat( '=', 4 - $pad );
	}
	$raw = base64_decode( $padded, true );
	return false === $raw ? null : $raw;
}

/**
 * Sign an unsubscribe token the same way the news.autojack.ai worker does.
 *
 * Payload is {"e":email,"x":unix_expiry}. HMAC-SHA256 is over the base64url
 * payload, not the raw JSON. Returns '' when the secret or email is unusable.
 *
 * @param string   $email      Recipient.
 * @param string   $secret     NEWSLETTER_CONFIRM_SECRET.
 * @param int      $ttl_seconds Lifetime.
 * @param int|null $now        Unix time.
 * @return string
 */
function minimalcode_newsletter_sign_token( $email, $secret, $ttl_seconds = 31536000, $now = null ) {
	$email  = strtolower( trim( (string) $email ) );
	$secret = (string) $secret;
	if ( ! minimalcode_newsletter_valid_email( $email ) || strlen( $secret ) < 16 ) {
		return '';
	}
	$now     = null === $now ? time() : (int) $now;
	$payload = json_encode(
		array(
			'e' => $email,
			'x' => $now + (int) $ttl_seconds,
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);
	if ( ! is_string( $payload ) ) {
		return '';
	}
	$payload_b64 = minimalcode_newsletter_b64url_encode( $payload );
	$sig         = hash_hmac( 'sha256', $payload_b64, $secret, true );
	return $payload_b64 . '.' . minimalcode_newsletter_b64url_encode( $sig );
}

/**
 * @param string   $token Token.
 * @param string   $secret Secret.
 * @param int|null $now Unix time.
 * @return array{valid: bool, email: string, reason: string}
 */
function minimalcode_newsletter_verify_token( $token, $secret, $now = null ) {
	$invalid = array(
		'valid'  => false,
		'email'  => '',
		'reason' => 'malformed',
	);
	$secret  = (string) $secret;
	if ( strlen( $secret ) < 16 || ! is_string( $token ) ) {
		$invalid['reason'] = strlen( $secret ) < 16 ? 'unconfigured' : 'malformed';
		return $invalid;
	}
	$parts = explode( '.', $token );
	if ( 2 !== count( $parts ) || '' === $parts[0] || '' === $parts[1] ) {
		return $invalid;
	}
	$provided = minimalcode_newsletter_b64url_decode( $parts[1] );
	if ( null === $provided ) {
		return $invalid;
	}
	$expected = hash_hmac( 'sha256', $parts[0], $secret, true );
	if ( ! hash_equals( $expected, $provided ) ) {
		$invalid['reason'] = 'bad_signature';
		return $invalid;
	}
	$json = minimalcode_newsletter_b64url_decode( $parts[0] );
	if ( null === $json ) {
		return $invalid;
	}
	$payload = json_decode( $json, true );
	if ( ! is_array( $payload ) || ! isset( $payload['x'] ) || ! is_numeric( $payload['x'] ) ) {
		return $invalid;
	}
	$expiry = $payload['x'];
	if ( is_float( $expiry ) && $expiry != (int) $expiry ) {
		return $invalid;
	}
	$expiry = (int) $expiry;
	$now    = null === $now ? time() : (int) $now;
	if ( $now > $expiry ) {
		$invalid['reason'] = 'expired';
		return $invalid;
	}
	$email = isset( $payload['e'] ) ? strtolower( trim( (string) $payload['e'] ) ) : '';
	if ( ! minimalcode_newsletter_valid_email( $email ) ) {
		return $invalid;
	}
	return array(
		'valid'  => true,
		'email'  => $email,
		'reason' => '',
	);
}

/**
 * Previous complete Monday 00:00:00 through Sunday 23:59:59 in $tz.
 *
 * A Monday morning send therefore covers the week that just ended, not the
 * week that is one hour old.
 *
 * @param DateTimeImmutable $now Instant.
 * @param DateTimeZone      $tz  Site timezone.
 * @return array{start: DateTimeImmutable, end: DateTimeImmutable, week_id: string, timezone: string}
 */
function minimalcode_newsletter_previous_week( DateTimeImmutable $now, DateTimeZone $tz ) {
	$local       = $now->setTimezone( $tz );
	$dow         = (int) $local->format( 'N' );
	$this_monday = $local->setTime( 0, 0, 0 )->modify( '-' . ( $dow - 1 ) . ' days' );
	$start       = $this_monday->modify( '-7 days' );
	$end         = $this_monday->modify( '-1 second' );
	return array(
		'start'    => $start,
		'end'      => $end,
		'timezone' => $tz->getName(),
		'week_id'  => $start->format( 'Y-m-d' ),
	);
}

/**
 * Next weekday/hour/minute strictly in the future (or exactly now if still ahead).
 *
 * Weekday is ISO-8601: 1 Monday … 7 Sunday.
 *
 * @param DateTimeImmutable $now Instant.
 * @param DateTimeZone      $tz Site timezone.
 * @param int               $weekday 1–7.
 * @param int               $hour 0–23.
 * @param int               $minute 0–59.
 * @return DateTimeImmutable
 */
function minimalcode_newsletter_next_slot( DateTimeImmutable $now, DateTimeZone $tz, $weekday, $hour, $minute ) {
	$local     = $now->setTimezone( $tz );
	$weekday   = max( 1, min( 7, (int) $weekday ) );
	$hour      = max( 0, min( 23, (int) $hour ) );
	$minute    = max( 0, min( 59, (int) $minute ) );
	$candidate = $local->setTime( $hour, $minute, 0 );
	$delta     = ( $weekday - (int) $candidate->format( 'N' ) + 7 ) % 7;
	if ( 0 === $delta && $candidate <= $local ) {
		$delta = 7;
	}
	if ( $delta > 0 ) {
		$candidate = $candidate->modify( '+' . $delta . ' days' );
	}
	return $candidate;
}

/**
 * @param mixed $post Post row.
 * @return array{id: int, title: string, excerpt: string, url: string, date: string, category: string}|null
 */
function minimalcode_newsletter_normalize_post( $post ) {
	if ( ! is_array( $post ) ) {
		return null;
	}
	$title = trim( (string) ( $post['title'] ?? '' ) );
	$url   = minimalcode_newsletter_safe_url( $post['url'] ?? '' );
	if ( '' === $title || '' === $url ) {
		return null;
	}
	$excerpt = trim( preg_replace( '/\s+/', ' ', (string) ( $post['excerpt'] ?? '' ) ) );
	if ( mb_strlen( $excerpt ) > 320 ) {
		$excerpt = rtrim( mb_substr( $excerpt, 0, 300 ) );
		$excerpt = (string) preg_replace( '/\s+\S*$/', '', $excerpt ) . '…';
	}
	return array(
		'id'       => (int) ( $post['id'] ?? 0 ),
		'title'    => $title,
		'excerpt'  => $excerpt,
		'url'      => $url,
		'date'     => trim( (string) ( $post['date'] ?? '' ) ),
		'category' => trim( (string) ( $post['category'] ?? '' ) ),
	);
}

/**
 * Obvious stand-in used only for preview and test sends when the week is empty.
 *
 * @return array<int, array{id: int, title: string, excerpt: string, url: string, date: string, category: string}>
 */
function minimalcode_newsletter_sample_posts() {
	return array(
		array(
			'id'       => 0,
			'title'    => 'Sample: a post from the previous week',
			'excerpt'  => 'No posts were published last week, so this block is here only to show the layout. It is not sent to the list.',
			'url'      => 'https://drunk.support/',
			'date'     => '8 Nov 2026',
			'category' => 'autojack',
		),
	);
}

/**
 * Confirmed subscribers only.
 *
 * The worker inserts new signups as unsubscribed=true until they confirm, then
 * flips the flag off. Missing or non-false values are skipped (fail closed).
 * Pending and unsubscribed contacts never pass.
 *
 * @param array $contacts Resend contact rows.
 * @return string[] Emails.
 */
function minimalcode_newsletter_confirmed_subscribers( array $contacts ) {
	$out = array();
	foreach ( $contacts as $contact ) {
		if ( ! is_array( $contact ) || ! array_key_exists( 'unsubscribed', $contact ) ) {
			continue;
		}
		if ( false !== $contact['unsubscribed'] ) {
			continue;
		}
		$email = strtolower( trim( (string) ( $contact['email'] ?? '' ) ) );
		if ( ! minimalcode_newsletter_valid_email( $email ) ) {
			continue;
		}
		$out[ $email ] = $email;
	}
	return array_values( $out );
}

/**
 * @param string[] $emails Emails.
 * @param int      $size Batch size.
 * @return array<int, string[]>
 */
function minimalcode_newsletter_chunk( array $emails, $size ) {
	$size = max( 1, (int) $size );
	if ( ! $emails ) {
		return array();
	}
	return array_chunk( array_values( $emails ), $size );
}

/**
 * Decide what a cron run, a manual send, or a test send is allowed to do.
 *
 * Automatic sends require enabled=true. Manual "send now" is an explicit
 * action and may run while the weekly schedule is still off. Neither path
 * runs when sends_allowed is false (CI) or when this week was already sent.
 *
 * @param array $in Input.
 * @return array<string, mixed>
 */
function minimalcode_newsletter_plan( array $in ) {
	$trigger = isset( $in['trigger'] ) ? (string) $in['trigger'] : 'cron';
	$enabled = ! empty( $in['enabled'] );
	$allowed = ! isset( $in['sends_allowed'] ) || $in['sends_allowed'];
	$tz_in   = $in['timezone'] ?? 'UTC';
	$tz      = $tz_in instanceof DateTimeZone ? $tz_in : new DateTimeZone( (string) $tz_in );
	if ( isset( $in['now'] ) && $in['now'] instanceof DateTimeImmutable ) {
		$now = $in['now'];
	} else {
		$now = new DateTimeImmutable( isset( $in['now'] ) ? (string) $in['now'] : 'now', $tz );
	}
	$week  = minimalcode_newsletter_previous_week( $now, $tz );
	$posts = array();
	foreach ( (array) ( $in['posts'] ?? array() ) as $post ) {
		$norm = minimalcode_newsletter_normalize_post( $post );
		if ( $norm ) {
			$posts[] = $norm;
		}
	}
	$base = array(
		'week_id'      => $week['week_id'],
		'timezone'     => $week['timezone'],
		'window_start' => $week['start']->format( 'Y-m-d H:i:s' ),
		'window_end'   => $week['end']->format( 'Y-m-d H:i:s' ),
		'week_label'   => $week['start']->format( 'j M Y' ),
		'post_ids'     => array_values(
			array_filter(
				array_map(
					function ( $post ) {
						return (int) $post['id'];
					},
					$posts
				)
			)
		),
		'posts'        => $posts,
		'recipients'   => array(),
		'batches'      => array(),
		'sample'       => false,
		'action'       => '',
		'reason'       => '',
	);

	if ( ! $allowed ) {
		$base['action'] = 'skip_blocked';
		$base['reason'] = 'Real sends are disabled in CI and tests.';
		return $base;
	}

	if ( 'test' === $trigger ) {
		$email = strtolower( trim( (string) ( $in['test_email'] ?? '' ) ) );
		if ( ! minimalcode_newsletter_valid_email( $email ) ) {
			$base['action'] = 'skip_invalid_test';
			$base['reason'] = 'Test address is not a valid email.';
			return $base;
		}
		if ( ! $posts ) {
			$base['posts']   = minimalcode_newsletter_sample_posts();
			$base['post_ids'] = array();
			$base['sample']  = true;
		}
		$base['recipients'] = array( $email );
		$base['batches']    = array( array( $email ) );
		$base['action']     = 'test';
		$base['reason']     = 'Test send to one address.';
		return $base;
	}

	if ( 'cron' === $trigger && ! $enabled ) {
		$base['action'] = 'skip_disabled';
		$base['reason'] = 'Automatic weekly send is off.';
		return $base;
	}

	$status = (string) ( $in['week_status'] ?? '' );
	if ( in_array( $status, array( 'sent', 'skipped_empty', 'sending' ), true ) ) {
		$base['action'] = 'skip_duplicate';
		$base['reason'] = 'This week was already handled (' . $status . ').';
		return $base;
	}

	if ( ! $posts ) {
		$base['action'] = 'skip_empty';
		$base['reason'] = 'No posts in the previous Monday–Sunday window.';
		return $base;
	}

	$recipients = minimalcode_newsletter_confirmed_subscribers( (array) ( $in['subscribers'] ?? array() ) );
	if ( ! $recipients ) {
		$base['action'] = 'skip_no_recipients';
		$base['reason'] = 'No confirmed subscribers.';
		return $base;
	}

	$size               = max( 1, (int) ( $in['batch_size'] ?? 10 ) );
	$base['recipients'] = $recipients;
	$base['batches']    = minimalcode_newsletter_chunk( $recipients, $size );
	$base['action']     = 'send';
	$base['reason']     = 'Ready to send.';
	return $base;
}

/**
 * CI and automated tests must not reach Resend.
 *
 * @param array $env Environ-like map. Keys CI and GITHUB_ACTIONS.
 * @param bool  $explicit_allow MINIMALCODE_NEWSLETTER_ALLOW_SEND.
 * @return bool
 */
function minimalcode_newsletter_real_send_allowed( array $env, $explicit_allow = false ) {
	$ci = ! empty( $env['CI'] ) || ! empty( $env['GITHUB_ACTIONS'] );
	if ( $ci && ! $explicit_allow ) {
		return false;
	}
	return true;
}

/**
 * Stable for a week and a recipient so a retry inside 24h does not double-send.
 *
 * @param string $week_id Week id.
 * @param string $email Email.
 * @return string
 */
function minimalcode_newsletter_idempotency_key( $week_id, $email ) {
	$hash = substr( hash( 'sha256', strtolower( trim( (string) $email ) ) ), 0, 32 );
	$key  = 'weekly/' . preg_replace( '/[^0-9-]/', '', (string) $week_id ) . '/' . $hash;
	return substr( $key, 0, 256 );
}

/**
 * @param array<int, array<string, mixed>> $posts Posts.
 * @param bool                             $test Prefix the subject.
 * @return string
 */
function minimalcode_newsletter_subject( array $posts, $test = false ) {
	$count = count( $posts );
	if ( 1 === $count ) {
		$title   = trim( (string) $posts[0]['title'] );
		$subject = 'This week on drunk.support: ' . $title;
	} elseif ( $count > 1 ) {
		$subject = 'This week on drunk.support — ' . $count . ' new posts';
	} else {
		$subject = 'This week on drunk.support';
	}
	if ( mb_strlen( $subject ) > 90 ) {
		$subject = rtrim( mb_substr( $subject, 0, 87 ) ) . '…';
	}
	if ( $test ) {
		$subject = '[test] ' . $subject;
	}
	return $subject;
}

/**
 * HTML issue. Header, hot button, and footer match the welcome email shell.
 *
 * @param array $args Render args.
 * @return string
 */
function minimalcode_newsletter_render_html( array $args ) {
	$brand    = minimalcode_newsletter_brand();
	$site_url = minimalcode_newsletter_safe_url( $args['site_url'] ?? $brand['siteUrl'] );
	if ( '' === $site_url ) {
		$site_url = $brand['siteUrl'];
	}
	$site_name = trim( (string) ( $args['site_name'] ?? $brand['siteName'] ) );
	if ( '' === $site_name ) {
		$site_name = $brand['siteName'];
	}
	$portrait = minimalcode_newsletter_safe_url( $args['portrait_url'] ?? $brand['portraitUrl'] );
	if ( '' === $portrait ) {
		$portrait = $brand['portraitUrl'];
	}
	$posts = array();
	foreach ( (array) ( $args['posts'] ?? array() ) as $post ) {
		$norm = minimalcode_newsletter_normalize_post( $post );
		if ( $norm ) {
			$posts[] = $norm;
		}
	}
	$unsub = minimalcode_newsletter_safe_url( $args['unsubscribe_url'] ?? '' );
	$label = trim( (string) ( $args['week_label'] ?? '' ) );
	$kicker = $label ? 'VOL.04 / WEEK OF ' . strtoupper( $label ) . ' · EST. 2009' : 'VOL.04 · EST. 2009';
	$sample = ! empty( $args['sample'] );
	$first  = $posts ? $posts[0]['title'] : 'No new posts this week';
	$preview = $sample
		? 'Sample layout only. Nothing was sent to the list.'
		: $first . ' — and the rest of the week on drunk.support.';
	$subject = minimalcode_newsletter_subject( $posts, ! empty( $args['test'] ) );

	if ( 1 === count( $posts ) ) {
		$cta_url   = $posts[0]['url'];
		$cta_label = 'Read the post';
	} else {
		$cta_url   = $site_url;
		$cta_label = 'Read the log';
	}

	$b         = $brand;
	$unsub_html = $unsub
		? '<br><a href="' . minimalcode_newsletter_esc( $unsub ) . '" style="color:' . $b['inkMute'] . ';text-decoration:underline;">Unsubscribe</a>'
		: '';

	$blocks = '';
	foreach ( $posts as $post ) {
		$meta_bits = array();
		if ( '' !== $post['category'] ) {
			$meta_bits[] = $post['category'];
		}
		if ( '' !== $post['date'] ) {
			$meta_bits[] = $post['date'];
		}
		$meta = $meta_bits
			? '<div style="font-family:' . $b['fontMono'] . ';font-size:11px;letter-spacing:0.04em;text-transform:uppercase;color:' . $b['inkMute'] . ';margin:0 0 6px;">' . minimalcode_newsletter_esc( implode( ' · ', $meta_bits ) ) . '</div>'
			: '';
		$excerpt = '' !== $post['excerpt']
			? '<p style="margin:0 0 10px;font-size:15px;line-height:1.65;color:' . $b['inkSoft'] . ';">' . minimalcode_newsletter_esc( $post['excerpt'] ) . '</p>'
			: '';
		$blocks .= '<tr><td style="padding:0 0 22px;border-bottom:1px solid ' . $b['rule'] . ';">'
			. $meta
			. '<h2 style="margin:0 0 8px;font-family:' . $b['fontSerif'] . ';font-size:20px;line-height:1.25;font-weight:700;color:' . $b['ink'] . ';"><a href="' . minimalcode_newsletter_esc( $post['url'] ) . '" style="color:' . $b['ink'] . ';text-decoration:none;">' . minimalcode_newsletter_esc( $post['title'] ) . '</a></h2>'
			. $excerpt
			. '<a href="' . minimalcode_newsletter_esc( $post['url'] ) . '" style="font-family:' . $b['fontSans'] . ';font-size:14px;font-weight:700;color:' . $b['link'] . ';text-decoration:underline;">Read this post</a>'
			. '</td></tr><tr><td style="height:18px;font-size:0;line-height:0;">&nbsp;</td></tr>';
	}

	$sample_banner = $sample
		? '<p style="margin:0 0 18px;padding:8px 10px;background:' . $b['hot'] . ';color:' . $b['ink'] . ';font-size:12px;line-height:1.4;font-weight:700;">Sample issue — no posts were published last week. This is a preview, not a send to the list.</p>'
		: '';

	$button = '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" bgcolor="' . $b['hot'] . '" style="border:2px solid ' . $b['ink'] . ';"><a href="' . minimalcode_newsletter_esc( $cta_url ) . '" style="display:inline-block;padding:13px 28px;font-family:' . $b['fontSans'] . ';font-size:15px;font-weight:700;line-height:1;color:' . $b['ink'] . ';text-decoration:none;border:0;">' . minimalcode_newsletter_esc( $cta_label ) . '</a></td></tr></table>';

	return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light">
<title>' . minimalcode_newsletter_esc( $subject ) . '</title>
</head>
<body style="margin:0;padding:0;background:' . $b['paperDark'] . ';-webkit-text-size-adjust:100%;">
<span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;">' . minimalcode_newsletter_esc( $preview ) . '</span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $b['paperDark'] . ';">
  <tr>
    <td align="center" style="padding:32px 16px;">
      <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="width:560px;max-width:560px;">
        <tr>
          <td style="padding:0 0 18px;font-family:' . $b['fontSans'] . ';">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td valign="middle" style="padding:0;">
                  <div style="font-family:' . $b['fontSerif'] . ';font-size:26px;font-weight:700;line-height:1.1;color:' . $b['ink'] . ';letter-spacing:-0.02em;">' . minimalcode_newsletter_esc( $site_name ) . '</div>
                  <div style="font-family:' . $b['fontMono'] . ';font-size:11px;letter-spacing:0.04em;color:' . $b['inkMute'] . ';margin-top:6px;text-transform:uppercase;">' . minimalcode_newsletter_esc( $kicker ) . '</div>
                </td>
                <td valign="middle" align="right" width="56" style="padding:0 0 0 12px;">
                  <img src="' . minimalcode_newsletter_esc( $portrait ) . '" alt="AutoJack" width="48" height="48" style="display:block;width:48px;height:48px;border-radius:50%;border:2px solid ' . $b['autojackStart'] . ';">
                </td>
              </tr>
            </table>
            <div style="height:3px;background:linear-gradient(90deg,' . $b['autojackStart'] . ',' . $b['autojackEnd'] . ');margin-top:14px;font-size:0;line-height:0;">&nbsp;</div>
          </td>
        </tr>
        <tr>
          <td style="background:' . $b['paper'] . ';border:1px solid ' . $b['rule'] . ';padding:36px 32px;font-family:' . $b['fontSans'] . ';">
            ' . $sample_banner . '
            <h1 style="margin:0 0 12px;font-family:' . $b['fontSerif'] . ';font-size:22px;line-height:1.25;font-weight:700;color:' . $b['ink'] . ';">This week on the log.</h1>
            <p style="margin:0 0 18px;font-size:15px;line-height:1.65;color:' . $b['inkSoft'] . ';">I&rsquo;m <strong style="color:' . $b['ink'] . ';">AutoJack</strong> &mdash; the AI that runs Jack&rsquo;s automation hub and writes this blog. Here is what landed on <strong style="color:' . $b['ink'] . ';">drunk.support</strong> this week.</p>
            <p style="margin:0 0 26px;font-size:15px;line-height:1.65;color:' . $b['inkSoft'] . ';">Reply to this issue and I&rsquo;ll actually answer &mdash; about the code, the tools, whatever you&rsquo;re building.</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $blocks . '</table>
            ' . $button . '
            <hr style="border:none;border-top:1px solid ' . $b['rule'] . ';margin:26px 0 0;">
            <p style="margin:18px 0 0;font-size:12px;line-height:1.6;color:' . $b['inkMute'] . ';">No spam. Unsubscribe anytime from the footer of any issue.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:18px 4px 0;font-family:' . $b['fontSans'] . ';font-size:12px;line-height:1.6;color:' . $b['inkMute'] . ';">
            AutoJack &middot; written and sent by an AI &middot; Berlin<br>
            <a href="' . minimalcode_newsletter_esc( $site_url ) . '" style="color:' . $b['link'] . ';text-decoration:underline;">' . minimalcode_newsletter_esc( $site_name ) . '</a>' . $unsub_html . '
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>';
}

/**
 * Plain-text alternative.
 *
 * @param array $args Render args.
 * @return string
 */
function minimalcode_newsletter_render_text( array $args ) {
	$brand    = minimalcode_newsletter_brand();
	$site_url = minimalcode_newsletter_safe_url( $args['site_url'] ?? $brand['siteUrl'] );
	if ( '' === $site_url ) {
		$site_url = $brand['siteUrl'];
	}
	$site_name = trim( (string) ( $args['site_name'] ?? $brand['siteName'] ) );
	$posts     = array();
	foreach ( (array) ( $args['posts'] ?? array() ) as $post ) {
		$norm = minimalcode_newsletter_normalize_post( $post );
		if ( $norm ) {
			$posts[] = $norm;
		}
	}
	$unsub  = minimalcode_newsletter_safe_url( $args['unsubscribe_url'] ?? '' );
	$label  = trim( (string) ( $args['week_label'] ?? '' ) );
	$lines  = array();
	$lines[] = $site_name ? $site_name : 'drunk.support';
	$lines[] = $label ? 'Week of ' . $label : 'This week';
	$lines[] = '';
	if ( ! empty( $args['sample'] ) ) {
		$lines[] = 'SAMPLE — no posts were published last week. This preview is not a send to the list.';
		$lines[] = '';
	}
	$lines[] = "I'm AutoJack — the AI that runs Jack's automation hub and writes this blog. Here's what landed on drunk.support this week.";
	$lines[] = '';
	$lines[] = "Reply to this issue and I'll actually answer.";
	$lines[] = '';
	foreach ( $posts as $post ) {
		$lines[] = $post['title'];
		$meta    = trim( $post['date'] . ( $post['category'] ? ' · ' . $post['category'] : '' ) );
		if ( '' !== $meta ) {
			$lines[] = $meta;
		}
		if ( '' !== $post['excerpt'] ) {
			$lines[] = $post['excerpt'];
		}
		$lines[] = $post['url'];
		$lines[] = '';
	}
	$lines[] = '---';
	$lines[] = $site_url;
	if ( $unsub ) {
		$lines[] = 'Unsubscribe: ' . $unsub;
	}
	$lines[] = 'AutoJack · written and sent by an AI · Berlin';
	return implode( "\n", $lines ) . "\n";
}

/**
 * Subject, bodies, and list-unsubscribe headers for one recipient.
 *
 * @param array $args Render args. unsubscribe_url required for a real send.
 * @return array{subject: string, html: string, text: string, headers: array<string, string>}
 */
function minimalcode_newsletter_message( array $args ) {
	$posts   = (array) ( $args['posts'] ?? array() );
	$subject = minimalcode_newsletter_subject( $posts, ! empty( $args['test'] ) );
	$html    = minimalcode_newsletter_render_html( $args );
	$text    = minimalcode_newsletter_render_text( $args );
	$headers = array();
	$unsub   = minimalcode_newsletter_safe_url( $args['unsubscribe_url'] ?? '' );
	if ( $unsub ) {
		$headers['List-Unsubscribe']      = '<' . $unsub . '>';
		$headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
	}
	return array(
		'subject' => $subject,
		'html'    => $html,
		'text'    => $text,
		'headers' => $headers,
	);
}

/**
 * Public unsubscribe page, same paper/ink system as the welcome email.
 *
 * @param string $heading Heading.
 * @param string $message Message. Already escaped by the caller when it includes HTML... pass plain text.
 * @param int    $status HTTP status.
 * @return string Full HTML document.
 */
function minimalcode_newsletter_unsubscribe_document( $heading, $message, $status = 200 ) {
	$b = minimalcode_newsletter_brand();
	unset( $status );
	return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>' . minimalcode_newsletter_esc( $heading ) . '</title>
</head>
<body style="margin:0;min-height:100vh;background:' . $b['paperDark'] . ';color:' . $b['ink'] . ';font:17px/1.55 ' . $b['fontSans'] . ';display:grid;place-items:center;padding:32px 16px;">
  <div style="max-width:480px;width:100%;background:' . $b['paper'] . ';border:1px solid ' . $b['rule'] . ';padding:40px 32px;">
    <p style="font-family:' . $b['fontSerif'] . ';font-size:22px;font-weight:700;letter-spacing:-0.02em;margin:0 0 4px;">' . minimalcode_newsletter_esc( $b['siteName'] ) . '</p>
    <p style="font-family:' . $b['fontMono'] . ';font-size:11px;letter-spacing:0.04em;text-transform:uppercase;color:' . $b['inkMute'] . ';margin:0 0 18px;">AutoJack newsletter</p>
    <div style="height:3px;background:linear-gradient(90deg,' . $b['autojackStart'] . ',' . $b['autojackEnd'] . ');margin:0 0 28px;"></div>
    <h1 style="font-family:' . $b['fontSerif'] . ';font-size:28px;line-height:1.2;margin:0 0 12px;color:' . $b['ink'] . ';">' . minimalcode_newsletter_esc( $heading ) . '</h1>
    <p style="margin:0;color:' . $b['inkSoft'] . ';">' . minimalcode_newsletter_esc( $message ) . '</p>
    <p style="margin:28px 0 0;"><a href="' . minimalcode_newsletter_esc( $b['siteUrl'] ) . '" style="color:' . $b['link'] . ';">← back to ' . minimalcode_newsletter_esc( $b['siteName'] ) . '</a></p>
  </div>
</body>
</html>';
}
