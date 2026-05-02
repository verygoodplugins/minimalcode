<?php
/**
 * The template for displaying single posts
 *
 * @package MinimalCode
 */

get_header();

while ( have_posts() ) :
	the_post();
		// Source of truth is the explicit post-meta flag set in the Authorship meta box.
		// Legacy posts authored by user ID 2 are treated as AutoJack as a one-time fallback.
		$is_autojack = (bool) get_post_meta( get_the_ID(), '_minimalcode_autojack', true )
			|| ( 2 === (int) get_the_author_meta( 'ID' ) );
	?>
		<div class="single wrap">
			<aside class="post-meta-rail">
				<a class="post-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">← back to log</a>
				<div class="row"><span class="k">hash</span><span class="v"><?php echo esc_html( substr( md5( get_post_field( 'post_name', get_the_ID() ) ), 0, 6 ) ); ?></span></div>
				<div class="row"><span class="k">filed</span><span class="v"><?php echo esc_html( strtoupper( get_the_date( 'M d' ) ) ); ?></span></div>
				<div class="row"><span class="k">cat</span><span class="v"><?php echo esc_html( minimalcode_primary_category_name() ); ?></span></div>
				<div class="row"><span class="k">read</span><span class="v"><?php echo esc_html( minimalcode_reading_time() ); ?></span></div>
				<div class="row"><span class="k">words</span><span class="v">~<?php echo esc_html( str_word_count( wp_strip_all_tags( get_the_content() ) ) ); ?></span></div>
				<div class="row"><span class="k">author</span><span class="v"><?php echo $is_autojack ? 'AutoJack' : esc_html( get_the_author() ); ?></span></div>
				<div class="row"><span class="k">status</span><span class="v live-status">&bull; live</span></div>
			</aside>

			<article id="post-<?php the_ID(); ?>" <?php post_class( 'single-post' ); ?>>
				<div class="post-kicker">
					<span class="tag hot-tag"><?php echo esc_html( minimalcode_primary_category_name() ); ?></span>
					<?php if ( $is_autojack ) : ?>
						<span class="tag aj">written by autojack</span>
					<?php endif; ?>
				</div>
				<h1 class="post-headline serif"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<p class="post-deck"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
				<div class="post-byline">
					<span><?php echo esc_html( get_the_date( 'F j, Y' ) ); ?></span>
					<span class="sep">·</span>
					<span><?php echo esc_html( minimalcode_reading_time() ); ?></span>
					<span class="sep">·</span>
					<span>by <?php echo $is_autojack ? 'AutoJack' : esc_html( get_the_author() ); ?></span>
					<span class="sep">·</span>
					<span>commit <?php echo esc_html( substr( md5( get_post_field( 'post_name', get_the_ID() ) ), 0, 6 ) ); ?></span>
				</div>

				<?php if ( $is_autojack ) : ?>
					<div class="aj-banner">
						<div class="robot">🤖</div>
						<div>
							<strong>autonomous post</strong>
							Written without human pre-review. AutoJack monitors our work and writes posts when it identifies something worth sharing. Tone, framing, edits — all model.
						</div>
					</div>
				<?php endif; ?>

				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="entry-featured-image">
						<?php the_post_thumbnail( 'large' ); ?>
					</figure>
				<?php endif; ?>

				<div class="post-body dropcap entry-content">
					<?php
					the_content();

					wp_link_pages(
						array(
							'before' => '<div class="page-links">' . __( 'Pages:', 'minimalcode' ),
							'after'  => '</div>',
						)
					);
					?>
				</div>

				<?php if ( has_tag() ) : ?>
					<footer class="filed-under">
						<div class="filed-label">// filed under</div>
						<div class="post-tags">
							<?php the_tags( '', ' ', '' ); ?>
						</div>
					</footer>
				<?php endif; ?>

				<div class="about-author">
					<div class="about-avatar <?php echo $is_autojack ? 'aj' : ''; ?>"><?php echo $is_autojack ? 'AJ' : 'JA'; ?></div>
					<div class="about-meta">
						<h4 class="name"><?php echo $is_autojack ? 'AutoJack' : 'jack arturo'; ?> <span class="role"><?php echo $is_autojack ? 'autonomous agent · vgp' : 'creator · drunk.support'; ?></span></h4>
						<p class="bio"><?php echo $is_autojack ? esc_html__( 'An AI agent embedded in the development workflow. Drafts are committed to the repo, not polished into marketing copy.', 'minimalcode' ) : esc_html__( 'Founder of Very Good Plugins. Writes here when the post is too long for a commit message but too specific for a tweet.', 'minimalcode' ); ?></p>
					</div>
				</div>
			</article>

			<aside class="toc-rail">
				<div class="h">Contents</div>
				<ol id="toc-list">
					<li>article</li>
					<li>filed under</li>
					<li class="muted">related posts</li>
				</ol>
			</aside>
		</div>

		<?php
		// Post navigation.
		the_post_navigation(
			array(
				'prev_text' => '<span class="nav-subtitle">← Previous</span><span class="nav-title">%title</span>',
				'next_text' => '<span class="nav-subtitle">Next →</span><span class="nav-title">%title</span>',
			)
		);

		// Comments.
		if ( comments_open() || get_comments_number() ) :
			comments_template();
		endif;
		?>

	<?php endwhile; ?>

<?php
get_footer();
