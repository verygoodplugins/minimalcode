<?php
/**
 * Projects Archive Template
 *
 * @package MinimalCode
 */

get_header();
?>

<div class="container">
	<div class="content-area">
		<header class="page-header projects-header">
			<h1 class="page-title"><?php esc_html_e( 'Projects', 'minimalcode' ); ?></h1>
			<p class="archive-description"><?php esc_html_e( "Here's an overview of some of my open-source AI projects I've worked on.", 'minimalcode' ); ?></p>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="projects-grid">
				<?php
				while ( have_posts() ) :
					the_post();

					$project_url = get_post_meta( get_the_ID(), '_minimalcode_project_url', true );
					$github_url  = get_post_meta( get_the_ID(), '_minimalcode_github_url', true );
					$role        = get_post_meta( get_the_ID(), '_minimalcode_role', true );

					// Determine the link URL (project URL, GitHub, or permalink).
					$link_url = ! empty( $project_url ) ? $project_url : ( ! empty( $github_url ) ? $github_url : get_permalink() );

					// Get image - featured image or GitHub OG image.
					$image_url = '';
					if ( has_post_thumbnail() ) {
						$image_url = get_the_post_thumbnail_url( get_the_ID(), 'project-card' );
					} elseif ( ! empty( $github_url ) ) {
						$image_url = minimalcode_get_github_og_image( $github_url );
					}
					?>
					<article id="post-<?php the_ID(); ?>" <?php post_class( 'project-card' ); ?>>
						<?php if ( $image_url ) : ?>
							<a href="<?php echo esc_url( $link_url ); ?>" class="project-card-image" target="_blank" rel="noopener noreferrer">
								<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
							</a>
						<?php endif; ?>

						<div class="project-card-content">
							<?php if ( $role ) : ?>
								<span class="project-role"><?php echo esc_html( minimalcode_get_role_display( $role ) ); ?></span>
							<?php endif; ?>

							<h2 class="project-title">
								<a href="<?php echo esc_url( $link_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php the_title(); ?>
								</a>
							</h2>

							<?php if ( has_excerpt() ) : ?>
								<p class="project-excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
							<?php endif; ?>
						</div>

						<div class="project-card-footer">
							<?php if ( ! empty( $project_url ) ) : ?>
								<a href="<?php echo esc_url( $project_url ); ?>" class="project-link" target="_blank" rel="noopener noreferrer">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
										<polyline points="15 3 21 3 21 9"></polyline>
										<line x1="10" y1="14" x2="21" y2="3"></line>
									</svg>
									<?php esc_html_e( 'View Project', 'minimalcode' ); ?>
								</a>
							<?php endif; ?>

							<?php if ( ! empty( $github_url ) ) : ?>
								<a href="<?php echo esc_url( $github_url ); ?>" class="project-link github-link" target="_blank" rel="noopener noreferrer">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
										<path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
									</svg>
									<?php esc_html_e( 'GitHub', 'minimalcode' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</article>
				<?php endwhile; ?>
			</div>

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
			<div class="no-posts">
				<h2><?php esc_html_e( 'No Projects Found', 'minimalcode' ); ?></h2>
				<p><?php esc_html_e( 'Check back soon for updates on my latest projects.', 'minimalcode' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
