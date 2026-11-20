<?php
/**
 * AutoJack's note at the top of each weekly issue.
 *
 * An AutoHub workflow writes one hidden `newsletter_intro` entry per issue,
 * slug `week-<week_id>` (the Monday the issue's week starts, site timezone).
 * The sender snapshots it when an issue starts, so every batch carries the
 * same words. No entry, a draft, or a trashed one means the issue goes out
 * with the standing copy.
 *
 * @package MinimalCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hidden from the front end. REST stays on because the WordPress MCP writes
 * through it. Published entries of a REST type can be read without logging
 * in, so the workflow saves them as private, and both count for the send.
 *
 * @return void
 */
function minimalcode_newsletter_register_intro_type() {
	register_post_type(
		'newsletter_intro',
		array(
			'labels'              => array(
				'name'          => __( 'Newsletter intros', 'minimalcode' ),
				'singular_name' => __( 'Newsletter intro', 'minimalcode' ),
				'add_new_item'  => __( 'Add newsletter intro', 'minimalcode' ),
				'edit_item'     => __( 'Edit newsletter intro', 'minimalcode' ),
				'not_found'     => __( 'No intros yet. Issues use the standing copy.', 'minimalcode' ),
			),
			'description'         => __( 'AutoJack\'s note at the top of a weekly issue. Slug week-YYYY-MM-DD, the Monday the issue covers. Draft or trash it to send the standing copy instead.', 'minimalcode' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => true,
			'rest_base'           => 'newsletter-intros',
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'menu_icon'           => 'dashicons-email-alt',
			'supports'            => array( 'title', 'editor', 'thumbnail', 'revisions' ),
		)
	);
}
add_action( 'init', 'minimalcode_newsletter_register_intro_type' );

/**
 * @param string $week_id Week id (Monday date, Y-m-d).
 * @return string
 */
function minimalcode_newsletter_intro_slug( $week_id ) {
	return 'week-' . $week_id;
}

/**
 * Theme and upload URLs follow is_ssl(), which is false under WP-CLI and
 * Action Scheduler. Give them the home URL's scheme so every send agrees.
 *
 * @param string $url URL.
 * @return string
 */
function minimalcode_newsletter_home_scheme( $url ) {
	return set_url_scheme( $url, wp_parse_url( home_url(), PHP_URL_SCHEME ) );
}

/**
 * The intro for one issue, ready for the template, or null.
 *
 * The optional GIF or image is the entry's featured image, so it is always
 * an upload on this site. It needs alt text to be used.
 *
 * @param string $week_id Week id.
 * @return array{paragraphs: string[], image: array|null, post_id: int}|null
 */
function minimalcode_newsletter_find_intro( $week_id ) {
	$found = get_posts(
		array(
			'post_type'        => 'newsletter_intro',
			'name'             => minimalcode_newsletter_intro_slug( (string) $week_id ),
			'post_status'      => array( 'publish', 'private' ),
			'posts_per_page'   => 1,
			'no_found_rows'    => true,
			'suppress_filters' => true,
		)
	);
	if ( ! $found || ! $found[0] instanceof WP_Post ) {
		return null;
	}
	$post = $found[0];

	$image = null;
	$thumb = (int) get_post_thumbnail_id( $post );
	if ( $thumb && wp_attachment_is_image( $thumb ) ) {
		// Full size: generated sizes drop GIF animation.
		$src = wp_get_attachment_image_src( $thumb, 'full' );
		if ( is_array( $src ) && ! empty( $src[0] ) ) {
			$image = array(
				'url'     => minimalcode_newsletter_home_scheme( (string) $src[0] ),
				'width'   => (int) $src[1],
				'height'  => (int) $src[2],
				'alt'     => (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ),
				'caption' => wp_strip_all_tags( (string) wp_get_attachment_caption( $thumb ) ),
			);
		}
	}

	$intro = minimalcode_newsletter_normalize_intro(
		array(
			'paragraphs' => minimalcode_newsletter_intro_paragraphs( strip_shortcodes( $post->post_content ) ),
			'image'      => $image,
		)
	);
	if ( ! $intro ) {
		return null;
	}
	$intro['post_id'] = (int) $post->ID;
	return $intro;
}
