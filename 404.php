<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @package MinimalCode
 */

get_header();
?>

<div class="container">
    <div class="content-area error-404">
        <header class="page-header">
            <h1 class="page-title"><?php _e('404', 'minimalcode'); ?></h1>
            <p class="error-message"><?php _e('Oops! That page can\'t be found.', 'minimalcode'); ?></p>
        </header>

        <div class="page-content">
            <p><?php _e('It looks like nothing was found at this location. Maybe try searching?', 'minimalcode'); ?></p>

            <?php get_search_form(); ?>

            <div class="recent-posts" style="margin-top: var(--spacing-xl);">
                <h3><?php _e('Recent Posts', 'minimalcode'); ?></h3>
                <ul>
                    <?php
                    $recent_posts = wp_get_recent_posts(array(
                        'numberposts' => 5,
                        'post_status' => 'publish',
                    ));
                    
                    foreach ($recent_posts as $post) :
                    ?>
                        <li>
                            <a href="<?php echo get_permalink($post['ID']); ?>">
                                <?php echo $post['post_title']; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();

