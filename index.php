<?php
/**
 * The main template file
 *
 * @package MinimalCode
 */

get_header();
?>

<div class="container">
	<div class="content-area">
		<?php if ( have_posts() ) : ?>
			<?php
			$current_month = '';
			?>
			<div class="posts-chronological">
				<?php
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
									<?php echo esc_html( get_the_date( 'M j' ) ); ?>
								</time>
								<?php
								$tags = get_the_tags();
								if ( $tags ) :
									?>
									<span class="post-tags-inline">
										<?php
										$tag_names = array_map(
											function ( $tag ) {
												return $tag->name;
											},
											$tags
										);
										echo esc_html( implode( ', ', array_slice( $tag_names, 0, 3 ) ) );
										?>
									</span>
								<?php endif; ?>
							</span>
						</a>
					</article>
				<?php endwhile; ?>
				</div><!-- .month-group (last) -->
			</div><!-- .posts-chronological -->

			<?php
			// Numbered pagination.
			the_posts_pagination(
				array(
					'mid_size'           => 2,
					'prev_text'          => __( '← Previous', 'minimalcode' ),
					'next_text'          => __( 'Next →', 'minimalcode' ),
					'screen_reader_text' => __( 'Posts navigation', 'minimalcode' ),
				)
			);
			?>

		<?php else : ?>
			<div class="no-posts">
				<h2><?php esc_html_e( 'Nothing Found', 'minimalcode' ); ?></h2>
				<p><?php esc_html_e( "It seems we can't find what you're looking for.", 'minimalcode' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
