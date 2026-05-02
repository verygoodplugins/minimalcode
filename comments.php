<?php
/**
 * Comments template — newspaper-system styling.
 *
 * @package MinimalCode
 */

if ( post_password_required() ) {
	return;
}
?>

<section id="comments" class="np-comments">
	<?php if ( have_comments() ) : ?>
		<div class="np-comments-head">
			<span class="np-comments-label">// thread</span>
			<h2 class="np-comments-title serif">
				<?php
				$comment_count = get_comments_number();
				if ( '1' === $comment_count ) {
					printf(
						/* translators: %s: post title. */
						esc_html__( 'one note on %s', 'minimalcode' ),
						'<em>' . esc_html( get_the_title() ) . '</em>'
					);
				} else {
					printf(
						/* translators: %s: comment count. */
						esc_html( _nx( '%s note', '%s notes', $comment_count, 'comments title', 'minimalcode' ) ),
						esc_html( number_format_i18n( $comment_count ) )
					);
				}
				?>
			</h2>
		</div>

		<ol class="np-comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 44,
				)
			);
			?>
		</ol>

		<?php
		the_comments_navigation(
			array(
				'prev_text' => __( '← Older', 'minimalcode' ),
				'next_text' => __( 'Newer →', 'minimalcode' ),
			)
		);
		?>

		<?php if ( ! comments_open() ) : ?>
			<p class="np-comments-closed"><?php esc_html_e( 'Thread closed.', 'minimalcode' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'title_reply_before' => '<h2 id="reply-title" class="np-comments-reply serif">',
			'title_reply_after'  => '</h2>',
			'class_submit'       => 'np-comment-submit util-btn hot',
			'label_submit'       => __( 'Post note', 'minimalcode' ),
			'class_form'         => 'np-comment-form',
		)
	);
	?>
</section>
