<?php
/**
 * Static page template.
 *
 * @package MinimalCode
 */

get_header();
?>

<div class="single wrap">
	<aside class="post-meta-rail">
		<a class="post-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">← back to log</a>
		<?php if ( have_posts() ) : ?>
			<?php
			rewind_posts();
			the_post();
			?>
			<div class="row"><span class="k">type</span><span class="v">page</span></div>
			<div class="row"><span class="k">slug</span><span class="v"><?php echo esc_html( $post->post_name ); ?></span></div>
			<div class="row"><span class="k">updated</span><span class="v"><?php echo esc_html( strtoupper( get_the_modified_date( 'M d' ) ) ); ?></span></div>
			<?php rewind_posts(); ?>
		<?php endif; ?>
	</aside>

	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'page-article' ); ?>>
			<div class="post-kicker">
				<span class="tag hot-tag"><?php esc_html_e( 'page', 'minimalcode' ); ?></span>
			</div>
			<h1 class="post-headline serif"><?php the_title(); ?></h1>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="entry-featured-image">
					<?php the_post_thumbnail( 'large' ); ?>
				</figure>
			<?php endif; ?>

			<div class="post-body entry-content">
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

			<?php if ( comments_open() || get_comments_number() ) : ?>
				<?php comments_template(); ?>
			<?php endif; ?>
		</article>
	<?php endwhile; ?>

	<aside class="toc-rail">
		<div class="h">Contents</div>
		<ol id="toc-list"></ol>
	</aside>
</div>

<?php
get_footer();
