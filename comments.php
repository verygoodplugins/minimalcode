<?php
/**
 * The template for displaying comments
 *
 * @package MinimalCode
 */

if (post_password_required()) {
    return;
}
?>

<div id="comments" class="comments-area">
    <?php if (have_comments()) : ?>
        <h2 class="comments-title">
            <?php
            $comment_count = get_comments_number();
            if ('1' === $comment_count) {
                printf(
                    esc_html__('One comment on &ldquo;%s&rdquo;', 'minimalcode'),
                    '<span>' . get_the_title() . '</span>'
                );
            } else {
                printf(
                    esc_html(_nx('%1$s comment', '%1$s comments', $comment_count, 'comments title', 'minimalcode')),
                    number_format_i18n($comment_count)
                );
            }
            ?>
        </h2>

        <ol class="comment-list">
            <?php
            wp_list_comments(array(
                'style' => 'ol',
                'short_ping' => true,
                'avatar_size' => 50,
            ));
            ?>
        </ol>

        <?php
        the_comments_navigation(array(
            'prev_text' => __('← Older Comments', 'minimalcode'),
            'next_text' => __('Newer Comments →', 'minimalcode'),
        ));
        ?>

        <?php if (!comments_open()) : ?>
            <p class="no-comments"><?php esc_html_e('Comments are closed.', 'minimalcode'); ?></p>
        <?php endif; ?>

    <?php endif; ?>

    <?php
    comment_form(array(
        'title_reply_before' => '<h2 id="reply-title" class="comment-reply-title">',
        'title_reply_after' => '</h2>',
        'class_submit' => 'submit-button',
        'label_submit' => __('Post Comment', 'minimalcode'),
    ));
    ?>
</div>

