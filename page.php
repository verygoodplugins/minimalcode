<?php
/**
 * Page Template
 *
 * @package MinimalCode
 */

get_header();
?>

<div class="container">
	<div class="content-area">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'page-content' ); ?>>
				<header class="entry-header page-header">
					<h1 class="entry-title"><?php the_title(); ?></h1>
				</header>

				<?php if ( has_post_thumbnail() ) : ?>
					<div class="entry-featured-image">
						<?php the_post_thumbnail( 'large' ); ?>
					</div>
				<?php endif; ?>

				<div class="entry-content">
					<?php
					the_content();

					wp_link_pages(
						array(
							'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'minimalcode' ),
							'after'  => '</div>',
						)
					);
					?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</div>

<?php
get_footer();
