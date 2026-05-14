<?php
/**
 * The main template file
 *
 * @package MinimalCode
 */

get_header();
?>

<div class="layout wrap">
	<aside class="rail">
		<div class="rail-block">
			<h4 class="rail-h"><?php esc_html_e( 'Status', 'minimalcode' ); ?></h4>
			<div class="rail-row"><span class="k">automem</span><span class="v ok">&bull; ok</span></div>
			<div class="rail-row"><span class="k">autohub</span><span class="v ok">&bull; ok</span></div>
			<div class="rail-row"><span class="k">autojack</span><span class="v ok">&bull; awake</span></div>
			<div class="rail-row"><span class="k">wp-fusion</span><span class="v ok">&bull; 99.98</span></div>
			<div class="rail-row"><span class="k">wakeword</span><span class="v warn">&bull; flaky</span></div>
		</div>
		<div class="rail-block">
			<h4 class="rail-h"><?php esc_html_e( 'Counters', 'minimalcode' ); ?></h4>
			<div class="rail-row"><span class="k">posts</span><span class="v"><?php echo esc_html( wp_count_posts()->publish ); ?></span></div>
			<div class="rail-row"><span class="k">commits/wk</span><span class="v">147</span></div>
			<div class="rail-row"><span class="k">memory nodes</span><span class="v">4.7M</span></div>
			<div class="rail-row"><span class="k">aj autonom.</span><span class="v">31</span></div>
		</div>
		<div class="rail-block">
			<div class="now-card">
				<p><?php esc_html_e( 'Spent the morning chasing a citation bug. Spent the afternoon writing about it.', 'minimalcode' ); ?></p>
				<p class="mono-note">// updated recently</p>
			</div>
		</div>
	</aside>

	<main class="main">
		<?php if ( have_posts() ) : ?>
			<?php
			the_post();
			$lede_id      = get_the_ID();
			$lede_hash    = substr( md5( get_post_field( 'post_name', $lede_id ) ), 0, 6 );
			$lede_excerpt = get_the_excerpt();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'lede' ); ?>>
				<div class="lede-marker">FRESH · <?php echo esc_html( strtoupper( $lede_hash ) ); ?></div>
				<div class="lede-content">
					<span class="lede-eyebrow"><?php echo esc_html( minimalcode_primary_category_name() ); ?></span>
					<h1 class="lede-title">
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</h1>
					<?php if ( $lede_excerpt ) : ?>
						<p class="lede-deck"><?php echo esc_html( $lede_excerpt ); ?></p>
					<?php endif; ?>
					<?php
					$lede_is_autojack = minimalcode_is_autojack( $lede_id );
					$lede_author      = $lede_is_autojack ? 'AutoJack' : get_the_author();
					?>
					<div class="lede-byline">
						<?php if ( $lede_is_autojack ) : ?>
							<span class="tag aj">🤖 autojack</span>
						<?php endif; ?>
						<span class="lede-byline-name">by <?php echo esc_html( $lede_author ); ?></span>
					</div>
					<div class="lede-meta">
						<span><?php echo esc_html( strtoupper( get_the_date( 'M d Y' ) ) ); ?></span>
						<span class="sep">/</span>
						<span><?php echo esc_html( minimalcode_reading_time() ); ?></span>
						<span class="sep">/</span>
						<span><a href="<?php the_permalink(); ?>">read.entry →</a></span>
					</div>
				</div>
			</article>

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
				<h2 class="serif"><?php esc_html_e( 'Nothing yet', 'minimalcode' ); ?></h2>
				<p><?php esc_html_e( 'The notebook is empty for now. Check back soon.', 'minimalcode' ); ?></p>
			</div>
		<?php endif; ?>
	</main>

	<aside>
		<div class="aside-block">
			<h4 class="aside-h"><?php esc_html_e( 'Filed Under', 'minimalcode' ); ?></h4>
			<div class="tag-cloud">
				<?php foreach ( get_tags( array( 'number' => 18, 'orderby' => 'count', 'order' => 'DESC' ) ) as $tag ) : ?>
					<a class="tag size-<?php echo esc_attr( min( 3, max( 1, (int) $tag->count ) ) ); ?>" href="<?php echo esc_url( get_tag_link( $tag ) ); ?>"><?php echo esc_html( $tag->name ); ?></a>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="aside-block">
			<div class="signal">
				<p><strong><?php esc_html_e( 'The notebook is the product.', 'minimalcode' ); ?></strong></p>
				<p><?php esc_html_e( 'Where I work in public: half-finished thoughts, post-mortems, and the occasional autonomous post.', 'minimalcode' ); ?></p>
				<p><a href="<?php echo esc_url( get_feed_link() ); ?>">subscribe.rss</a> · <a href="<?php echo esc_url( home_url( '/' ) ); ?>">@drunk.support</a></p>
			</div>
		</div>
		<div class="aside-block">
			<div class="stamp">
				BUILT IN<span class="big">PUBLIC</span>
				since 2009
			</div>
		</div>
	</aside>
</div>

<?php
get_footer();
