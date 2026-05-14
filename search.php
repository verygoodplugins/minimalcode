<?php
/**
 * Search results template.
 *
 * @package MinimalCode
 */

get_header();
?>

<div class="layout wrap">
	<aside class="rail">
		<div class="rail-block">
			<h4 class="rail-h"><?php esc_html_e( 'Search', 'minimalcode' ); ?></h4>
			<div class="rail-row"><span class="k">query</span><span class="v"><?php echo esc_html( get_search_query() ); ?></span></div>
			<div class="rail-row"><span class="k">found</span><span class="v"><?php echo esc_html( $GLOBALS['wp_query']->found_posts ); ?></span></div>
			<div class="rail-row"><span class="k">scope</span><span class="v">posts · pages · projects</span></div>
		</div>

		<div class="rail-block">
			<a class="post-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">← back to log</a>
		</div>
	</aside>

	<main class="main">
		<header class="archive-lede">
			<span class="lede-eyebrow"><?php esc_html_e( 'Search', 'minimalcode' ); ?></span>
			<h1 class="archive-title serif">
				<?php
				printf(
					/* translators: %s: search query. */
					esc_html__( 'Results for: %s', 'minimalcode' ),
					'<em>' . esc_html( get_search_query() ) . '</em>'
				);
				?>
			</h1>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="section-bar">
				<span class="label"><?php esc_html_e( 'Matches', 'minimalcode' ); ?></span>
				<span><?php esc_html_e( 'ranked by relevance', 'minimalcode' ); ?></span>
				<span class="rule"></span>
				<span><?php echo esc_html( $GLOBALS['wp_query']->found_posts ); ?> <?php esc_html_e( 'entries', 'minimalcode' ); ?></span>
			</div>

			<div class="log">
				<?php
				while ( have_posts() ) :
					the_post();
					$post_hash   = substr( md5( get_post_field( 'post_name', get_the_ID() ) ), 0, 6 );
					$is_autojack = (bool) get_post_meta( get_the_ID(), '_minimalcode_autojack', true )
						|| has_category( 'autojack' );
					$has_thumb   = has_post_thumbnail();
					?>
					<a id="post-<?php the_ID(); ?>" <?php post_class( 'entry' . ( $has_thumb ? ' entry--has-thumb' : '' ) ); ?> href="<?php the_permalink(); ?>">
						<?php if ( $has_thumb ) : ?>
							<span class="entry-thumb"><?php the_post_thumbnail( 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ); ?></span>
						<?php endif; ?>
						<span class="entry-hash"><?php echo esc_html( $post_hash ); ?></span>
						<span class="entry-date"><?php echo esc_html( strtoupper( get_the_date( 'M d' ) ) ); ?></span>
						<span class="entry-title serif <?php echo $is_autojack ? 'aj' : ''; ?>"><?php the_title(); ?></span>
						<span class="entry-tags">
							<?php if ( $is_autojack ) : ?>
								<span class="tag aj">autojack</span>
							<?php endif; ?>
							<span class="tag"><?php echo esc_html( get_post_type() ); ?></span>
						</span>
					</a>
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
				<h2 class="serif"><?php esc_html_e( 'No matches', 'minimalcode' ); ?></h2>
				<p><?php esc_html_e( 'Nothing matched your search. Try a different keyword, or browse the log.', 'minimalcode' ); ?></p>
				<div class="search-form-block">
					<?php get_search_form(); ?>
				</div>
			</div>
		<?php endif; ?>
	</main>

	<aside>
		<div class="aside-block">
			<h4 class="aside-h"><?php esc_html_e( 'Browse', 'minimalcode' ); ?></h4>
			<div class="tag-cloud">
				<?php foreach ( get_tags( array( 'number' => 18, 'orderby' => 'count', 'order' => 'DESC' ) ) as $tag ) : ?>
					<a class="tag size-<?php echo esc_attr( min( 3, max( 1, (int) $tag->count ) ) ); ?>" href="<?php echo esc_url( get_tag_link( $tag ) ); ?>"><?php echo esc_html( $tag->name ); ?></a>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="aside-block">
			<div class="signal">
				<p><strong><?php esc_html_e( 'Try ⌘K', 'minimalcode' ); ?></strong></p>
				<p><?php esc_html_e( 'The header search opens a live results modal — faster than reloading.', 'minimalcode' ); ?></p>
			</div>
		</div>
	</aside>
</div>

<?php
get_footer();
