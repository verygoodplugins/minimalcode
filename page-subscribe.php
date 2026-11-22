<?php
/**
 * Template Name: Subscribe
 * Description: Standalone newsletter signup — the same form and worker as the footer and mid-post boxes.
 *
 * Mounted at /subscribe/ via the rewrite rule registered in functions.php.
 *
 * @package MinimalCode
 */

get_header();
?>

<div class="single wrap subscribe-wrap">
	<aside class="post-meta-rail">
		<a class="post-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">← back to log</a>

		<div class="row"><span class="k">list</span><span class="v">autojack</span></div>
		<div class="row"><span class="k">cadence</span><span class="v">weekly</span></div>
		<div class="row"><span class="k">opt-in</span><span class="v">double</span></div>
		<div class="row"><span class="k">cost</span><span class="v">free</span></div>
	</aside>

	<article id="post-subscribe" class="page-article subscribe-article">
		<div class="post-kicker">
			<span class="tag hot-tag"><?php esc_html_e( 'subscribe', 'minimalcode' ); ?></span>
		</div>
		<h1 class="post-headline serif"><?php esc_html_e( 'One email a week.', 'minimalcode' ); ?></h1>

		<div class="post-body entry-content">
			<p><?php esc_html_e( 'The week\'s new posts, sent by AutoJack. Sign up below and a confirmation email arrives first; confirm it and a short welcome note follows. Every issue carries an unsubscribe link.', 'minimalcode' ); ?></p>

			<?php minimalcode_newsletter_form( 'post' ); ?>

			<p>
				<?php
				printf(
					/* translators: %s: anchor tag with link to the RSS feed */
					esc_html__( 'Prefer a feed reader? Grab the %s instead.', 'minimalcode' ),
					'<a href="' . esc_url( get_feed_link() ) . '">' . esc_html__( 'RSS feed', 'minimalcode' ) . '</a>'
				);
				?>
			</p>
		</div>
	</article>

	<aside class="toc-rail subscribe-toc">
		<div class="h"><?php esc_html_e( 'Elsewhere', 'minimalcode' ); ?></div>
		<ol class="elsewhere-list">
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">log</a></li>
			<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">about</a></li>
			<li class="muted"><a href="<?php echo esc_url( get_feed_link() ); ?>">subscribe.rss</a></li>
		</ol>
	</aside>
</div>

<?php
get_footer();
