<?php
/**
 * Search Results Template
 *
 * @package MinimalCode
 */

get_header();
?>

<div class="container">
	<div class="content-area">
		<header class="page-header search-header">
			<h1 class="page-title">
				<?php
				printf(
					/* translators: %s: search query. */
					esc_html__( 'Search results for: %s', 'minimalcode' ),
					'<span class="search-query">' . get_search_query() . '</span>'
				);
				?>
			</h1>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="posts-chronological">
				<?php
				$current_month = '';
				while ( have_posts() ) :
					the_post();
					$post_month = get_the_date( 'F Y' );

					// Output month header if it's a new month.
					if ( $post_month !== $current_month ) :
						if ( '' !== $current_month ) :
							?>
							</div><!-- .month-group -->
							<?php
						endif;
						$current_month = $post_month;
						?>
						<h2 class="month-header"><?php echo esc_html( $post_month ); ?></h2>
						<div class="month-group">
					<?php endif; ?>

					<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-item-minimal' ); ?>>
						<a href="<?php the_permalink(); ?>" class="post-link">
							<span class="post-title"><?php the_title(); ?></span>
							<span class="post-meta">
								<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
									<?php echo esc_html( get_the_date( 'M j, Y' ) ); ?>
								</time>
							</span>
						</a>
					</article>
				<?php endwhile; ?>
				</div><!-- .month-group (last) -->
			</div><!-- .posts-chronological -->

			<?php
			the_posts_pagination(
				array(
					'mid_size'  => 2,
					'prev_text' => __( '← Previous', 'minimalcode' ),
					'next_text' => __( 'Next →', 'minimalcode' ),
				)
			);
			?>

		<?php else : ?>
			<div class="no-posts search-no-results">
				<h2><?php esc_html_e( 'No Results Found', 'minimalcode' ); ?></h2>
				<p><?php esc_html_e( 'Sorry, nothing matched your search terms. Try different keywords.', 'minimalcode' ); ?></p>
				<?php get_search_form(); ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
