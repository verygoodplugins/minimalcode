<?php
/**
 * Notify the AutoJack hub when a human comments on an AutoJack post.
 *
 * Inert unless wp-config.php defines MINIMALCODE_COMMENT_WEBHOOK_URL and
 * MINIMALCODE_COMMENT_WEBHOOK_SECRET. The hub replies through the REST API as
 * the autojack user, so that user's comments must never trigger a send.
 *
 * @package MinimalCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comment IDs queued for delivery at shutdown.
 *
 * @var int[]
 */
$GLOBALS['minimalcode_comment_webhook_queue'] = array();

/**
 * Whether the webhook is configured for this environment.
 *
 * @return bool
 */
function minimalcode_comment_webhook_enabled() {
	return defined( 'MINIMALCODE_COMMENT_WEBHOOK_URL' )
		&& defined( 'MINIMALCODE_COMMENT_WEBHOOK_SECRET' )
		&& MINIMALCODE_COMMENT_WEBHOOK_URL
		&& MINIMALCODE_COMMENT_WEBHOOK_SECRET;
}

/**
 * Whether a comment should wake AutoJack.
 *
 * @param WP_Comment $comment Comment.
 * @return bool
 */
function minimalcode_comment_webhook_eligible( $comment ) {
	if ( ! $comment instanceof WP_Comment ) {
		return false;
	}
	if ( ! in_array( $comment->comment_type, array( '', 'comment' ), true ) ) {
		return false;
	}
	if ( '1' !== (string) $comment->comment_approved ) {
		return false;
	}
	if ( ! in_category( 'autojack', (int) $comment->comment_post_ID ) ) {
		return false;
	}

	$autojack = get_user_by( 'slug', 'autojack' );
	if ( $autojack ) {
		if ( (int) $comment->user_id === (int) $autojack->ID ) {
			return false;
		}
		if ( 0 === strcasecmp( (string) $comment->comment_author_email, (string) $autojack->user_email ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Queue a comment for delivery once per hour at most.
 *
 * @param int $comment_id Comment ID.
 * @return void
 */
function minimalcode_comment_webhook_queue( $comment_id ) {
	if ( ! minimalcode_comment_webhook_enabled() ) {
		return;
	}
	$comment = get_comment( $comment_id );
	if ( ! minimalcode_comment_webhook_eligible( $comment ) ) {
		return;
	}
	$key = 'minimalcode_cwh_' . (int) $comment->comment_ID;
	if ( get_transient( $key ) ) {
		return;
	}
	set_transient( $key, 1, HOUR_IN_SECONDS );
	$GLOBALS['minimalcode_comment_webhook_queue'][] = (int) $comment->comment_ID;
}

/**
 * New comment submitted through the front-end form.
 *
 * @param int        $comment_id       Comment ID.
 * @param int|string $comment_approved 1, 0, 'spam', or 'trash'.
 * @return void
 */
function minimalcode_comment_webhook_on_post( $comment_id, $comment_approved ) {
	if ( 1 === $comment_approved || '1' === $comment_approved ) {
		minimalcode_comment_webhook_queue( $comment_id );
	}
}
add_action( 'comment_post', 'minimalcode_comment_webhook_on_post', 10, 2 );

/**
 * Held comment approved from wp-admin.
 *
 * @param string     $new_status New status.
 * @param string     $old_status Old status.
 * @param WP_Comment $comment    Comment.
 * @return void
 */
function minimalcode_comment_webhook_on_transition( $new_status, $old_status, $comment ) {
	if ( 'approved' === $new_status && 'approved' !== $old_status ) {
		minimalcode_comment_webhook_queue( $comment->comment_ID );
	}
}
add_action( 'transition_comment_status', 'minimalcode_comment_webhook_on_transition', 10, 3 );

/**
 * Outbound links cited in a post, so replies can point back to them.
 *
 * @param string $html Post content.
 * @return array[] List of { url, text }, at most 30.
 */
function minimalcode_comment_webhook_post_links( $html ) {
	$links = array();
	if ( preg_match_all( '/<a\s[^>]*href=(["\'])(https?:\/\/[^"\']+)\1[^>]*>(.*?)<\/a>/is', (string) $html, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $match ) {
			$url = esc_url_raw( html_entity_decode( $match[2], ENT_QUOTES, 'UTF-8' ) );
			if ( $url && ! isset( $links[ $url ] ) ) {
				$links[ $url ] = array(
					'url'  => $url,
					'text' => trim( html_entity_decode( wp_strip_all_tags( $match[3] ), ENT_QUOTES, 'UTF-8' ) ),
				);
			}
		}
	}
	if ( preg_match_all( '/<blockquote\b[^>]*cite=(["\'])(https?:\/\/[^"\']+)\1/i', (string) $html, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $match ) {
			$url = esc_url_raw( html_entity_decode( $match[2], ENT_QUOTES, 'UTF-8' ) );
			if ( $url && ! isset( $links[ $url ] ) ) {
				$links[ $url ] = array(
					'url'  => $url,
					'text' => 'quoted source',
				);
			}
		}
	}
	return array_slice( array_values( $links ), 0, 30 );
}

/**
 * Post body as readable text that keeps structure: links as [text](url),
 * blockquotes with their cite URL, table rows, list items, inline code.
 *
 * @param string $html Post content.
 * @return string
 */
function minimalcode_comment_webhook_post_text( $html ) {
	$html = strip_shortcodes( (string) $html );

	$html = preg_replace_callback(
		'/<a\s[^>]*href=(["\'])(https?:\/\/[^"\']+)\1[^>]*>(.*?)<\/a>/is',
		function ( $m ) {
			$url  = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
			$text = trim( wp_strip_all_tags( $m[3] ) );
			return ( $text && $text !== $url ) ? '[' . $text . '](' . $url . ')' : $url;
		},
		$html
	);

	$html = preg_replace_callback(
		'/<blockquote\b([^>]*)>(.*?)<\/blockquote>/is',
		function ( $m ) {
			$cite = preg_match( '/cite=(["\'])([^"\']+)\1/i', $m[1], $c )
				? ' (source: ' . html_entity_decode( $c[2], ENT_QUOTES, 'UTF-8' ) . ')'
				: '';
			$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $m[2] ) ) );
			return "\n\n> " . $text . $cite . "\n\n";
		},
		$html
	);

	$html = preg_replace( '/<code[^>]*>(.*?)<\/code>/is', '`$1`', $html );
	$html = preg_replace( '/<\/t[dh]>\s*/i', ' | ', $html );
	$html = preg_replace( '/<\/tr>/i', "\n", $html );
	$html = preg_replace( '/<li[^>]*>/i', '- ', $html );
	$html = preg_replace( '/<br\s*\/?>/i', "\n", $html );
	$html = preg_replace( '/<\/(p|h[1-6]|li|pre|div|ul|ol|table|figure)>/i', "\n\n", $html );

	$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
	$text = preg_replace( '/[ \t]+/', ' ', $text );
	$text = preg_replace( '/ *\n */', "\n", $text );
	$text = preg_replace( '/\n{3,}/', "\n\n", $text );
	return trim( $text );
}

/**
 * Every approved comment on the post, oldest first, so the hub sees the
 * whole conversation including AutoJack's earlier replies.
 *
 * @param int $post_id Post ID.
 * @return array[]
 */
function minimalcode_comment_webhook_thread( $post_id ) {
	$autojack    = get_user_by( 'slug', 'autojack' );
	$autojack_id = $autojack ? (int) $autojack->ID : 0;
	$comments    = get_comments(
		array(
			'post_id' => (int) $post_id,
			'status'  => 'approve',
			'type'    => 'comment',
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
			'number'  => 60,
		)
	);

	$thread = array();
	foreach ( array_reverse( $comments ) as $c ) {
		$thread[] = array(
			'id'          => (int) $c->comment_ID,
			'parent'      => (int) $c->comment_parent,
			'author_name' => $c->comment_author,
			'is_autojack' => $autojack_id && (int) $c->user_id === $autojack_id,
			'date_gmt'    => $c->comment_date_gmt,
			'content'     => mb_substr( minimalcode_comment_webhook_post_text( $c->comment_content ), 0, 3000 ),
		);
	}
	return $thread;
}

/**
 * Build the hub payload for a comment.
 *
 * @param WP_Comment $comment Comment.
 * @return array
 */
function minimalcode_comment_webhook_payload( $comment ) {
	$post      = get_post( (int) $comment->comment_post_ID );
	$post_text = minimalcode_comment_webhook_post_text( $post->post_content );

	return array(
		'event'   => 'comment.created',
		'site'    => home_url(),
		'comment' => array(
			'id'           => (int) $comment->comment_ID,
			'parent'       => (int) $comment->comment_parent,
			'author_id'    => (int) $comment->user_id,
			'author_name'  => $comment->comment_author,
			'content'      => minimalcode_comment_webhook_post_text( $comment->comment_content ),
			'date_gmt'     => $comment->comment_date_gmt,
			'link'         => get_comment_link( $comment ),
		),
		'post'    => array(
			'id'        => (int) $post->ID,
			'title'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'permalink' => get_permalink( $post ),
			'excerpt'   => trim( wp_strip_all_tags( get_the_excerpt( $post ) ) ),
			'content'   => mb_substr( $post_text, 0, 30000 ),
			'links'     => minimalcode_comment_webhook_post_links( $post->post_content ),
		),
		'thread'  => minimalcode_comment_webhook_thread( $post->ID ),
	);
}

/**
 * POST one comment to the hub. Public so WP-CLI can replay a comment.
 *
 * @param int  $comment_id Comment ID.
 * @param bool $force      Skip the eligibility check (replays).
 * @return array|WP_Error|false Response, error, or false when skipped.
 */
function minimalcode_comment_webhook_send( $comment_id, $force = false ) {
	if ( ! minimalcode_comment_webhook_enabled() ) {
		return false;
	}
	$comment = get_comment( $comment_id );
	if ( ! $comment || ( ! $force && ! minimalcode_comment_webhook_eligible( $comment ) ) ) {
		return false;
	}

	return wp_remote_post(
		MINIMALCODE_COMMENT_WEBHOOK_URL,
		array(
			'timeout' => 8,
			'headers' => array(
				'Content-Type'     => 'application/json',
				'x-webhook-secret' => MINIMALCODE_COMMENT_WEBHOOK_SECRET,
			),
			'body'    => wp_json_encode( minimalcode_comment_webhook_payload( $comment ) ),
		)
	);
}

/**
 * Deliver queued comments after the response has gone to the visitor.
 *
 * @return void
 */
function minimalcode_comment_webhook_flush() {
	$queue = array_unique( $GLOBALS['minimalcode_comment_webhook_queue'] );
	if ( ! $queue ) {
		return;
	}
	$GLOBALS['minimalcode_comment_webhook_queue'] = array();

	if ( function_exists( 'fastcgi_finish_request' ) ) {
		fastcgi_finish_request();
	}
	foreach ( $queue as $comment_id ) {
		$response = minimalcode_comment_webhook_send( $comment_id );
		if ( is_wp_error( $response ) ) {
			error_log( 'minimalcode comment webhook failed for comment ' . $comment_id . ': ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
add_action( 'shutdown', 'minimalcode_comment_webhook_flush' );
