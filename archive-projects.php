<?php
/**
 * Projects archive — newspaper-system grid with GitHub metadata.
 *
 * @package MinimalCode
 */

get_header();
?>

<div class="layout wrap">
	<aside class="rail">
		<div class="rail-block">
			<h4 class="rail-h"><?php esc_html_e( 'Projects', 'minimalcode' ); ?></h4>
			<div class="rail-row"><span class="k">repos</span><span class="v"><?php echo esc_html( $GLOBALS['wp_query']->found_posts ); ?></span></div>
			<div class="rail-row"><span class="k">scope</span><span class="v">open source</span></div>
			<div class="rail-row"><span class="k">cadence</span><span class="v">irregular</span></div>
		</div>

		<div class="rail-block">
			<a class="post-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">← back to log</a>
		</div>

		<div class="rail-block">
			<div class="now-card">
				<p><?php esc_html_e( 'Most of these are notebooks-with-code rather than products. Stars are noise; commits are signal.', 'minimalcode' ); ?></p>
				<p class="mono-note">// repo policy</p>
			</div>
		</div>
	</aside>

	<main class="main">
		<header class="archive-lede">
			<span class="lede-eyebrow"><?php esc_html_e( 'Catalog', 'minimalcode' ); ?></span>
			<h1 class="archive-title serif"><?php esc_html_e( 'Projects', 'minimalcode' ); ?></h1>
			<div class="archive-deck"><?php esc_html_e( "Open-source AI work I'm playing with — agents, memory systems, and experiments that escaped the notebook.", 'minimalcode' ); ?></div>
		</header>

		<div class="section-bar">
			<span class="label"><?php esc_html_e( 'Repos', 'minimalcode' ); ?></span>
			<span><?php esc_html_e( 'sorted manually', 'minimalcode' ); ?></span>
			<span class="rule"></span>
			<span><?php echo esc_html( $GLOBALS['wp_query']->found_posts ); ?> <?php esc_html_e( 'entries', 'minimalcode' ); ?></span>
		</div>

		<?php if ( have_posts() ) : ?>
			<div class="proj-grid">
				<?php
				while ( have_posts() ) :
					the_post();

					$project_url = get_post_meta( get_the_ID(), '_minimalcode_project_url', true );
					$github_url  = get_post_meta( get_the_ID(), '_minimalcode_github_url', true );
					$role        = get_post_meta( get_the_ID(), '_minimalcode_role', true );

					$link_url = ! empty( $project_url ) ? $project_url : ( ! empty( $github_url ) ? $github_url : get_permalink() );

					$image_url = '';
					if ( has_post_thumbnail() ) {
						$image_url = get_the_post_thumbnail_url( get_the_ID(), 'project-card' );
					} elseif ( ! empty( $github_url ) && function_exists( 'minimalcode_get_github_og_image' ) ) {
						$image_url = minimalcode_get_github_og_image( $github_url );
					}

					$repo_data = ( ! empty( $github_url ) && function_exists( 'minimalcode_get_github_repo_data' ) ) ? minimalcode_get_github_repo_data( $github_url ) : false;
					$role_text = ( $role && function_exists( 'minimalcode_get_role_display' ) ) ? minimalcode_get_role_display( $role ) : $role;
					?>
					<article id="post-<?php the_ID(); ?>" <?php post_class( 'proj-card' ); ?>>
						<?php if ( $image_url ) : ?>
							<a class="proj-card-image" href="<?php echo esc_url( $link_url ); ?>" target="_blank" rel="noopener noreferrer">
								<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" decoding="async" data-no-lazy="1">
							</a>
						<?php endif; ?>

						<div class="proj-card-body">
							<?php if ( $role_text ) : ?>
								<span class="proj-role"><?php echo esc_html( $role_text ); ?></span>
							<?php endif; ?>

							<h2 class="proj-title serif">
								<a href="<?php echo esc_url( $link_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php the_title(); ?>
								</a>
							</h2>

							<?php if ( has_excerpt() ) : ?>
								<p class="proj-deck"><?php echo esc_html( get_the_excerpt() ); ?></p>
							<?php endif; ?>

							<?php if ( $repo_data && ! empty( $repo_data ) ) : ?>
								<div class="proj-meta">
									<?php if ( ! empty( $repo_data['language'] ) && function_exists( 'minimalcode_get_language_color' ) ) : ?>
										<span class="proj-language">
											<span class="proj-lang-dot" style="background-color: <?php echo esc_attr( minimalcode_get_language_color( $repo_data['language'] ) ); ?>"></span>
											<?php echo esc_html( $repo_data['language'] ); ?>
										</span>
									<?php endif; ?>

									<?php if ( ! empty( $repo_data['stars'] ) && $repo_data['stars'] > 0 ) : ?>
										<span class="proj-stars">
											<?php echo minimalcode_icon( 'star', 12 ); ?>
											<?php echo esc_html( number_format( $repo_data['stars'] ) ); ?>
										</span>
									<?php endif; ?>

									<?php if ( ! empty( $repo_data['updated_at'] ) && function_exists( 'minimalcode_time_ago' ) ) : ?>
										<span class="proj-updated">
											<?php
											printf(
												/* translators: %s: relative time (e.g. "3 days ago"). */
												esc_html__( 'updated %s', 'minimalcode' ),
												esc_html( minimalcode_time_ago( $repo_data['updated_at'] ) )
											);
											?>
										</span>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>

						<div class="proj-card-foot">
							<?php if ( ! empty( $github_url ) ) : ?>
								<a class="proj-link" href="<?php echo esc_url( $github_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo minimalcode_icon( 'github', 14 ); ?>
									<span>github</span>
								</a>
							<?php endif; ?>

							<?php if ( ! empty( $project_url ) ) : ?>
								<a class="proj-link" href="<?php echo esc_url( $project_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo minimalcode_icon( 'external', 14 ); ?>
									<span>visit</span>
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
			<div class="no-posts-empty">
				<h2 class="serif"><?php esc_html_e( 'Nothing shipping yet', 'minimalcode' ); ?></h2>
				<p><?php esc_html_e( 'Check back soon — projects show up here when there is something worth pointing at.', 'minimalcode' ); ?></p>
			</div>
		<?php endif; ?>
	</main>

	<aside>
		<div class="aside-block">
			<h4 class="aside-h"><?php esc_html_e( 'Stack', 'minimalcode' ); ?></h4>
			<div class="rail-row"><span class="k">memory</span><span class="v">automem</span></div>
			<div class="rail-row"><span class="k">orchestration</span><span class="v">autohub</span></div>
			<div class="rail-row"><span class="k">agent</span><span class="v">autojack</span></div>
			<div class="rail-row"><span class="k">cms</span><span class="v">wordpress</span></div>
		</div>

		<div class="aside-block">
			<div class="signal">
				<p><strong><?php esc_html_e( 'Working in public.', 'minimalcode' ); ?></strong></p>
				<p><?php esc_html_e( 'These ship before they are polished. Issues, PRs, and notes welcome.', 'minimalcode' ); ?></p>
			</div>
		</div>
	</aside>
</div>

<?php
get_footer();
