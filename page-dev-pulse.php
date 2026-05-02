<?php
/**
 * Template Name: Dev Pulse
 * Description: Curated dashboard — system status, cadence counters, what shipped this week.
 *
 * Mounted at /dev-pulse/ via the rewrite rule registered in functions.php.
 *
 * @package MinimalCode
 */

get_header();

$pulse_posts = get_posts(
	array(
		'numberposts' => 6,
		'post_status' => 'publish',
		'tax_query'   => array(
			array(
				'taxonomy' => 'post_tag',
				'field'    => 'slug',
				'terms'    => array( 'dev-pulse', 'pulse' ),
			),
		),
	)
);

if ( empty( $pulse_posts ) ) {
	$pulse_posts = get_posts(
		array(
			'numberposts' => 6,
			'post_status' => 'publish',
		)
	);
}

$total_posts = (int) wp_count_posts()->publish;
?>

<div class="layout wrap">
	<aside class="rail">
		<div class="rail-block">
			<h4 class="rail-h"><?php esc_html_e( 'System Status', 'minimalcode' ); ?></h4>
			<div class="rail-row"><span class="k">automem</span><span class="v ok">&bull; ok</span></div>
			<div class="rail-row"><span class="k">autohub</span><span class="v ok">&bull; ok</span></div>
			<div class="rail-row"><span class="k">autojack</span><span class="v ok">&bull; awake</span></div>
			<div class="rail-row"><span class="k">wp-fusion</span><span class="v ok">&bull; 99.98</span></div>
			<div class="rail-row"><span class="k">wakeword</span><span class="v warn">&bull; flaky</span></div>
		</div>

		<div class="rail-block">
			<h4 class="rail-h"><?php esc_html_e( 'This Week', 'minimalcode' ); ?></h4>
			<div class="rail-row"><span class="k">posts</span><span class="v"><?php echo esc_html( count( $pulse_posts ) ); ?></span></div>
			<div class="rail-row"><span class="k">commits</span><span class="v">147</span></div>
			<div class="rail-row"><span class="k">prs open</span><span class="v">3</span></div>
			<div class="rail-row"><span class="k">aj autonom.</span><span class="v">31</span></div>
		</div>

		<div class="rail-block">
			<a class="post-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">← back to log</a>
		</div>

		<div class="rail-block">
			<div class="now-card">
				<p><?php esc_html_e( 'Currently chasing: a citation bug in autohub, the next round of memory eviction, and one more newspaper template.', 'minimalcode' ); ?></p>
				<p class="mono-note">// updated recently</p>
			</div>
		</div>
	</aside>

	<main class="main">
		<header class="archive-lede">
			<span class="lede-eyebrow"><?php esc_html_e( 'Pulse', 'minimalcode' ); ?></span>
			<h1 class="archive-title serif"><?php esc_html_e( 'Dev Pulse', 'minimalcode' ); ?></h1>
			<div class="archive-deck"><?php esc_html_e( "What's running, what shipped this week, what's still smoking. The dashboard view of the notebook.", 'minimalcode' ); ?></div>
		</header>

		<div class="section-bar">
			<span class="label"><?php esc_html_e( 'This Week', 'minimalcode' ); ?></span>
			<span><?php esc_html_e( 'recent activity · most recent first', 'minimalcode' ); ?></span>
			<span class="rule"></span>
			<span><?php echo esc_html( count( $pulse_posts ) ); ?> <?php esc_html_e( 'entries', 'minimalcode' ); ?></span>
		</div>

		<?php if ( ! empty( $pulse_posts ) ) : ?>
			<div class="log">
				<?php
				foreach ( $pulse_posts as $rp ) :
					$rp_hash     = substr( md5( $rp->post_name ), 0, 6 );
					$rp_autojack = (bool) get_post_meta( $rp->ID, '_minimalcode_autojack', true );
					?>
					<a class="entry" href="<?php echo esc_url( get_permalink( $rp->ID ) ); ?>">
						<span class="entry-hash"><?php echo esc_html( $rp_hash ); ?></span>
						<span class="entry-date"><?php echo esc_html( strtoupper( get_the_date( 'M d', $rp->ID ) ) ); ?></span>
						<span class="entry-title serif <?php echo $rp_autojack ? 'aj' : ''; ?>"><?php echo esc_html( get_the_title( $rp->ID ) ); ?></span>
						<span class="entry-tags">
							<?php if ( $rp_autojack ) : ?>
								<span class="tag aj">autojack</span>
							<?php endif; ?>
							<?php
							$rp_tags = get_the_tags( $rp->ID );
							if ( $rp_tags ) :
								foreach ( array_slice( $rp_tags, 0, 2 ) as $rp_tag ) :
									?>
									<span class="tag"><?php echo esc_html( $rp_tag->name ); ?></span>
									<?php
								endforeach;
							endif;
							?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="no-posts-empty">
				<h2 class="serif"><?php esc_html_e( 'Quiet week', 'minimalcode' ); ?></h2>
				<p><?php esc_html_e( 'Nothing tagged dev-pulse yet. The dashboard fills as work ships.', 'minimalcode' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="section-bar">
			<span class="label"><?php esc_html_e( 'Cadence', 'minimalcode' ); ?></span>
			<span><?php esc_html_e( 'rolling 30-day averages', 'minimalcode' ); ?></span>
			<span class="rule"></span>
		</div>

		<div class="log">
			<div class="entry">
				<span class="entry-hash">cm</span>
				<span class="entry-date">commits</span>
				<span class="entry-title serif"><?php esc_html_e( '~21 / week', 'minimalcode' ); ?></span>
				<span class="entry-tags"><span class="tag">git</span></span>
			</div>
			<div class="entry">
				<span class="entry-hash">po</span>
				<span class="entry-date">posts</span>
				<span class="entry-title serif"><?php esc_html_e( '~2 / week', 'minimalcode' ); ?></span>
				<span class="entry-tags"><span class="tag">notebook</span></span>
			</div>
			<div class="entry">
				<span class="entry-hash">dp</span>
				<span class="entry-date">deploys</span>
				<span class="entry-title serif"><?php esc_html_e( 'as needed', 'minimalcode' ); ?></span>
				<span class="entry-tags"><span class="tag">prod</span></span>
			</div>
			<div class="entry">
				<span class="entry-hash">aj</span>
				<span class="entry-date">autonom.</span>
				<span class="entry-title serif"><?php esc_html_e( 'a few per day', 'minimalcode' ); ?></span>
				<span class="entry-tags"><span class="tag aj">autojack</span></span>
			</div>
		</div>

		<div class="section-bar">
			<span class="label"><?php esc_html_e( 'Stack Health', 'minimalcode' ); ?></span>
			<span><?php esc_html_e( 'last seen', 'minimalcode' ); ?></span>
			<span class="rule"></span>
		</div>

		<div class="log">
			<div class="entry">
				<span class="entry-hash">am</span>
				<span class="entry-date">automem</span>
				<span class="entry-title serif"><?php esc_html_e( 'graph + recall live', 'minimalcode' ); ?></span>
				<span class="entry-tags"><span class="tag">memory</span></span>
			</div>
			<div class="entry">
				<span class="entry-hash">ah</span>
				<span class="entry-date">autohub</span>
				<span class="entry-title serif"><?php esc_html_e( 'orchestration steady', 'minimalcode' ); ?></span>
				<span class="entry-tags"><span class="tag">orchestration</span></span>
			</div>
			<div class="entry">
				<span class="entry-hash">aj</span>
				<span class="entry-date">autojack</span>
				<span class="entry-title serif"><?php esc_html_e( 'last pass: recent', 'minimalcode' ); ?></span>
				<span class="entry-tags"><span class="tag aj">agent</span></span>
			</div>
			<div class="entry">
				<span class="entry-hash">wp</span>
				<span class="entry-date">wp-fusion</span>
				<span class="entry-title serif"><?php esc_html_e( 'still pays the bills', 'minimalcode' ); ?></span>
				<span class="entry-tags"><span class="tag">prod</span></span>
			</div>
		</div>
	</main>

	<aside>
		<div class="aside-block">
			<h4 class="aside-h"><?php esc_html_e( 'Subscribe', 'minimalcode' ); ?></h4>
			<div class="rail-row"><span class="k">rss</span><span class="v"><a href="<?php echo esc_url( get_feed_link() ); ?>">subscribe.rss</a></span></div>
			<div class="rail-row"><span class="k">posts</span><span class="v"><?php echo esc_html( $total_posts ); ?></span></div>
		</div>

		<div class="aside-block">
			<div class="signal">
				<p><strong><?php esc_html_e( 'The notebook is the product.', 'minimalcode' ); ?></strong></p>
				<p><?php esc_html_e( 'Pulse is the dashboard view: cadence, health, what shipped. Refreshes when work does.', 'minimalcode' ); ?></p>
				<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>">read the log</a> · <a href="<?php echo esc_url( get_feed_link() ); ?>">subscribe.rss</a></p>
			</div>
		</div>

		<div class="aside-block">
			<div class="stamp">
				WORK<span class="big">PULSE</span>
				live
			</div>
		</div>
	</aside>
</div>

<?php
get_footer();
