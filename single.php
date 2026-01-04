<?php
/**
 * The template for displaying single posts
 *
 * @package MinimalCode
 */

get_header();
?>

<div class="container">
    <?php
    while (have_posts()) :
        the_post();
    ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class('single-post'); ?>>
            <header class="entry-header">
                <h1 class="entry-title"><?php the_title(); ?></h1>
                
                <div class="entry-meta">
                    <time class="published" datetime="<?php echo get_the_date('c'); ?>">
                        <?php echo get_the_date('F j, Y'); ?>
                    </time>
                    <span class="reading-time"><?php echo minimalcode_reading_time(); ?></span>
                    <?php
                    if (has_category()) {
                        echo '<span class="categories">';
                        the_category(', ');
                        echo '</span>';
                    }
                    ?>
                </div>
            </header>

            <?php if (has_post_thumbnail()) : ?>
                <div class="entry-featured-image">
                    <?php the_post_thumbnail('large'); ?>
                </div>
            <?php endif; ?>

            <div class="entry-content">
                <?php
                the_content();

                wp_link_pages(array(
                    'before' => '<div class="page-links">' . __('Pages:', 'minimalcode'),
                    'after' => '</div>',
                ));
                ?>
            </div>

            <?php if (has_tag()) : ?>
                <footer class="entry-footer">
                    <div class="post-tags">
                        <?php the_tags('', ' ', ''); ?>
                    </div>
                </footer>
            <?php endif; ?>
        </article>

        <?php
        // Post navigation
        the_post_navigation(array(
            'prev_text' => '<span class="nav-subtitle">← Previous</span><span class="nav-title">%title</span>',
            'next_text' => '<span class="nav-subtitle">Next →</span><span class="nav-title">%title</span>',
        ));

        // Comments
        if (comments_open() || get_comments_number()) :
            comments_template();
        endif;
        ?>

    <?php endwhile; ?>
</div>

<?php
get_footer();

