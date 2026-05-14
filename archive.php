<?php
/**
 * Archive template (categories, tags, date archives).
 *
 * @package MinimalCode
 */

get_header();
?>

<div class="layout wrap">
	<aside class="rail">
		<div class="rail-block">
			<h4 class="rail-h"><?php esc_html_e( 'Archive', 'minimalcode' ); ?></h4>
			<div class="rail-row"><span class="k">filter</span><span class="v"><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></span></div>
			<div class="rail-row"><span class="k">found</span><span class="v"><?php echo esc_html( $GLOBALS['wp_query']->found_posts ); ?></span></div>
			<?php if ( is_category() || is_tag() ) : ?>
				<div class="rail-row"><span class="k">type</span><span class="v"><?php echo is_category() ? 'category' : 'tag'; ?></span></div>
			<?php endif; ?>
		</div>

		<div class="rail-block">
			<a class="post-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">← back to log</a>
		</div>
	</aside>

	<main class="main">
		<?php if ( have_posts() ) : ?>
			<header class="archive-lede">
				<span class="lede-eyebrow"><?php esc_html_e( 'Archive', 'minimalcode' ); ?></span>
				<h1 class="archive-title serif"><?php echo wp_kses_post( get_the_archive_title() ); ?></h1>
				<?php
				$archive_description = get_the_archive_description();
				if ( $archive_description ) :
					?>
					<div class="archive-deck"><?php echo wp_kses_post( $archive_description ); ?></div>
				<?php endif; ?>
			</header>

			<div class="section-bar">
				<span class="label"><?php esc_html_e( 'Log', 'minimalcode' ); ?></span>
				<span><?php esc_html_e( 'chronological · most recent first', 'minimalcode' ); ?></span>
				<span class="rule"></span>
				<span><?php echo esc_html( $GLOBALS['wp_query']->found_posts ); ?> <?php esc_html_e( 'entries', 'minimalcode' ); ?></span>
			</div>

			<div class="log">
				<?php
				$current_month = '';
				while ( have_posts() ) :
					the_post();
					$post_month  = get_the_date( 'F Y' );
					$post_hash   = substr( md5( get_post_field( 'post_name', get_the_ID() ) ), 0, 6 );
					$is_autojack = minimalcode_is_autojack();
					$has_thumb   = has_post_thumbnail();

					if ( $post_month !== $current_month ) :
						$current_month = $post_month;
						?>
						<div class="month-rule">
							<span class="lhs serif"><?php echo esc_html( get_the_date( 'F' ) ); ?><span><?php echo esc_html( get_the_date( 'Y' ) ); ?></span></span>
							<span class="ruling"></span>
							<span class="rhs">// scroll ↓</span>
						</div>
					<?php endif; ?>

					<a id="post-<?php the_ID(); ?>" <?php post_class( 'entry' . ( $has_thumb ? ' entry--has-thumb' : '' ) ); ?> href="<?php the_permalink(); ?>">
						<?php if ( $has_thumb ) : ?>
							<span class="entry-thumb"><?php the_post_thumbnail( 'medium', array( 'loading' => 'lazy', 'alt' => '' ) ); ?></span>
						<?php endif; ?>
						<span class="entry-hash"><?php echo esc_html( $post_hash ); ?></span>
						<span class="entry-date"><?php echo esc_html( strtoupper( get_the_date( 'M d' ) ) ); ?></span>
						<span class="entry-title serif <?php echo $is_autojack ? 'aj' : ''; ?>"><?php the_title(); ?></span>
						<span class="entry-tags">
							<?php if ( $is_autojack ) : ?>
								<span class="tag aj">autojack</span>
							<?php endif; ?>
							<?php
							$tags = get_the_tags();
							if ( $tags ) :
								foreach ( array_slice( $tags, 0, 2 ) as $tag ) :
									?>
									<span class="tag"><?php echo esc_html( $tag->name ); ?></span>
									<?php
								endforeach;
							endif;
							?>
						</span>
					</a>
				<?php endwhile; ?>
			</div>

			<?php
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
			<div class="no-posts-empty">
				<h2 class="serif"><?php esc_html_e( 'Nothing Filed', 'minimalcode' ); ?></h2>
				<p><?php esc_html_e( 'No entries match this filter yet.', 'minimalcode' ); ?></p>
			</div>
		<?php endif; ?>
	</main>

	<aside>
		<div class="aside-block">
			<h4 class="aside-h"><?php esc_html_e( 'Tags', 'minimalcode' ); ?></h4>
			<div class="tag-cloud">
				<?php foreach ( get_tags( array( 'number' => 18, 'orderby' => 'count', 'order' => 'DESC' ) ) as $tag ) : ?>
					<a class="tag size-<?php echo esc_attr( min( 3, max( 1, (int) $tag->count ) ) ); ?>" href="<?php echo esc_url( get_tag_link( $tag ) ); ?>"><?php echo esc_html( $tag->name ); ?></a>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="aside-block">
			<div class="signal">
				<p><strong><?php esc_html_e( 'The notebook is the product.', 'minimalcode' ); ?></strong></p>
				<p><?php esc_html_e( 'Browse by tag, category, or date — every entry is part of the working notebook.', 'minimalcode' ); ?></p>
				<p><a href="<?php echo esc_url( get_feed_link() ); ?>">subscribe.rss</a></p>
			</div>
		</div>
	</aside>
</div>

<?php
get_footer();
