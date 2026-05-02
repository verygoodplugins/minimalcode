<?php
/**
 * 404 — page-not-found template.
 *
 * @package MinimalCode
 */

get_header();

$recent_posts = get_posts(
	array(
		'numberposts' => 5,
		'post_status' => 'publish',
	)
);
?>

<div class="layout wrap">
	<aside class="rail">
		<div class="rail-block">
			<h4 class="rail-h"><?php esc_html_e( 'Status', 'minimalcode' ); ?></h4>
			<div class="rail-row"><span class="k">code</span><span class="v warn">&bull; 404</span></div>
			<div class="rail-row"><span class="k">path</span><span class="v"><?php echo esc_html( wp_parse_url( ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' ), PHP_URL_PATH ) ); ?></span></div>
			<div class="rail-row"><span class="k">action</span><span class="v">retry</span></div>
		</div>

		<div class="rail-block">
			<a class="post-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">← back to log</a>
		</div>
	</aside>

	<main class="main">
		<header class="archive-lede">
			<span class="lede-eyebrow"><?php esc_html_e( 'Error', 'minimalcode' ); ?></span>
			<h1 class="archive-title serif">404 · <em><?php esc_html_e( 'broken link', 'minimalcode' ); ?></em></h1>
			<div class="archive-deck"><?php esc_html_e( 'That page either moved, was renamed, or never existed. Try a search or scan the recent log below.', 'minimalcode' ); ?></div>
		</header>

		<div class="search-form-block">
			<?php get_search_form(); ?>
		</div>

		<?php if ( $recent_posts ) : ?>
			<div class="section-bar">
				<span class="label"><?php esc_html_e( 'Recent', 'minimalcode' ); ?></span>
				<span><?php esc_html_e( 'fresh from the notebook', 'minimalcode' ); ?></span>
				<span class="rule"></span>
				<span><?php echo count( $recent_posts ); ?> <?php esc_html_e( 'entries', 'minimalcode' ); ?></span>
			</div>

			<div class="log">
				<?php
				foreach ( $recent_posts as $rp ) :
					$rp_hash      = substr( md5( $rp->post_name ), 0, 6 );
					$rp_autojack  = (bool) get_post_meta( $rp->ID, '_minimalcode_autojack', true );
					?>
					<a class="entry" href="<?php echo esc_url( get_permalink( $rp->ID ) ); ?>">
						<span class="entry-hash"><?php echo esc_html( $rp_hash ); ?></span>
						<span class="entry-date"><?php echo esc_html( strtoupper( get_the_date( 'M d', $rp->ID ) ) ); ?></span>
						<span class="entry-title serif <?php echo $rp_autojack ? 'aj' : ''; ?>"><?php echo esc_html( get_the_title( $rp->ID ) ); ?></span>
						<span class="entry-tags">
							<?php if ( $rp_autojack ) : ?>
								<span class="tag aj">autojack</span>
							<?php endif; ?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</main>

	<aside>
		<div class="aside-block">
			<div class="signal">
				<p><strong><?php esc_html_e( 'Most 404s here are honest mistakes.', 'minimalcode' ); ?></strong></p>
				<p><?php esc_html_e( 'Slug typos, missing trailing slashes, deleted experiments. Use the search or pop into the log.', 'minimalcode' ); ?></p>
				<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>">go home</a> · <a href="<?php echo esc_url( get_feed_link() ); ?>">subscribe.rss</a></p>
			</div>
		</div>

		<div class="aside-block">
			<div class="stamp">
				NOT<span class="big">FOUND</span>
				try again
			</div>
		</div>
	</aside>
</div>

<?php
get_footer();
