<?php
/**
 * The template for displaying archive pages
 *
 * @package MinimalCode
 */

get_header();
?>

<div class="container">
    <div class="content-area">
        <?php if (have_posts()) : ?>
            
            <header class="page-header">
                <?php
                the_archive_title('<h1 class="page-title">', '</h1>');
                the_archive_description('<div class="archive-description">', '</div>');
                ?>
            </header>

            <div class="posts-list">
                <?php
                while (have_posts()) :
                    the_post();
                ?>
                    <article id="post-<?php the_ID(); ?>" <?php post_class('post-item'); ?>>
                        <header class="entry-header">
                            <h2 class="entry-title">
                                <a href="<?php the_permalink(); ?>" rel="bookmark">
                                    <?php the_title(); ?>
                                </a>
                            </h2>
                            
                            <div class="entry-meta">
                                <time class="published" datetime="<?php echo get_the_date('c'); ?>">
                                    <?php echo get_the_date('F j, Y'); ?>
                                </time>
                                <span class="reading-time"><?php echo minimalcode_reading_time(); ?></span>
                            </div>
                        </header>

                        <div class="entry-summary">
                            <?php the_excerpt(); ?>
                        </div>

                        <footer class="entry-footer">
                            <a href="<?php the_permalink(); ?>" class="read-more">
                                Read more →
                            </a>
                        </footer>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php
            the_posts_pagination(array(
                'mid_size' => 2,
                'prev_text' => __('← Previous', 'minimalcode'),
                'next_text' => __('Next →', 'minimalcode'),
            ));
            ?>

        <?php else : ?>
            
            <div class="no-posts">
                <h2><?php _e('Nothing Found', 'minimalcode'); ?></h2>
                <p><?php _e('It seems we can\'t find what you\'re looking for.', 'minimalcode'); ?></p>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php
get_footer();

