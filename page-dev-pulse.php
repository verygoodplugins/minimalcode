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

$devpulse_data = function_exists( 'minimalcode_devpulse_get_data' )
	? minimalcode_devpulse_get_data()
	: array( 'stats' => array(), 'commits' => array(), 'prs' => array() );

$devpulse_stats = is_array( $devpulse_data['stats'] ?? null ) ? $devpulse_data['stats'] : array();
$devpulse_total_commits = (int) ( $devpulse_stats['commits'] ?? 0 );
$devpulse_open_prs = 0;
foreach ( (array) ( $devpulse_data['prs'] ?? array() ) as $pr ) {
	if ( ( $pr['state'] ?? '' ) === 'OPEN' ) {
		$devpulse_open_prs++;
	}
}
$devpulse_active_repos = is_array( $devpulse_data['repos'] ?? null ) ? count( $devpulse_data['repos'] ) : 0;
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
			<h4 class="rail-h"><?php esc_html_e( 'Activity', 'minimalcode' ); ?></h4>
			<div class="rail-row"><span class="k">posts</span><span class="v"><?php echo esc_html( count( $pulse_posts ) ); ?></span></div>
			<div class="rail-row"><span class="k">commits</span><span class="v"><?php echo esc_html( number_format_i18n( $devpulse_total_commits ) ); ?></span></div>
			<div class="rail-row"><span class="k">prs open</span><span class="v"><?php echo esc_html( $devpulse_open_prs ); ?></span></div>
			<div class="rail-row"><span class="k">repos</span><span class="v"><?php echo esc_html( $devpulse_active_repos ); ?></span></div>
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

		<div class="dev-pulse-app">
			<header class="hero">
				<div class="hero-content">
					<div class="hero-left">
						<div class="hero-tagline">
							<span class="live-dot"></span>
							<span id="period-label"><?php esc_html_e( 'Engineering velocity this week', 'minimalcode' ); ?></span>
						</div>
						<div class="time-toggle">
							<button class="toggle-btn" data-period="day"><?php esc_html_e( 'Day', 'minimalcode' ); ?></button>
							<button class="toggle-btn active" data-period="week"><?php esc_html_e( 'Week', 'minimalcode' ); ?></button>
							<button class="toggle-btn" data-period="month"><?php esc_html_e( 'Month', 'minimalcode' ); ?></button>
						</div>
					</div>
					<div class="hero-stats">
						<div class="stat-card repos">
							<div class="stat-value" id="repos-count">0</div>
							<div class="stat-label"><?php esc_html_e( 'Repos Active', 'minimalcode' ); ?></div>
						</div>
						<div class="stat-card commits">
							<div class="stat-value" id="commits-count">0</div>
							<div class="stat-label"><?php esc_html_e( 'Commits', 'minimalcode' ); ?></div>
						</div>
						<div class="stat-card added">
							<div class="stat-value" id="added-count">+0</div>
							<div class="stat-label"><?php esc_html_e( 'Lines Added', 'minimalcode' ); ?></div>
						</div>
						<div class="stat-card deleted">
							<div class="stat-value" id="deleted-count">-0</div>
							<div class="stat-label"><?php esc_html_e( 'Lines Removed', 'minimalcode' ); ?></div>
						</div>
						<div class="stat-card prs">
							<div class="stat-value" id="prs-count">0</div>
							<div class="stat-label"><?php esc_html_e( 'Pull Requests', 'minimalcode' ); ?></div>
						</div>
					</div>
				</div>
			</header>

			<section class="section">
				<div class="section-header">
					<h2 class="section-title" id="rhythm-title">
						<span class="section-title-icon">📊</span>
						<span id="rhythm-title-text"><?php esc_html_e( 'Weekly Rhythm', 'minimalcode' ); ?></span>
					</h2>
					<button class="toggle-btn" id="back-to-week-btn" style="display: none;" onclick="backToWeekView()">
						← <?php esc_html_e( 'Back to Week', 'minimalcode' ); ?>
					</button>
				</div>
				<div class="weekly-grid view-week" id="weekly-grid"></div>
			</section>

			<section class="section">
				<div class="section-header">
					<h2 class="section-title">
						<span class="section-title-icon">📦</span>
						<?php esc_html_e( 'Project Breakdown', 'minimalcode' ); ?>
					</h2>
				</div>
				<div class="categories-grid" id="categories-grid"></div>
			</section>

			<section class="section">
				<div class="section-header">
					<h2 class="section-title">
						<span class="section-title-icon">🔀</span>
						<?php esc_html_e( 'Pull Requests', 'minimalcode' ); ?>
					</h2>
				</div>
				<div class="pr-grid" id="pr-grid"></div>
			</section>

			<section class="section">
				<div class="section-header">
					<h2 class="section-title">
						<span class="section-title-icon">⚡</span>
						<?php esc_html_e( 'Commit Timeline', 'minimalcode' ); ?>
					</h2>
				</div>
				<div class="timeline" id="timeline"></div>
			</section>

			<details class="diagnostics-panel" id="diagnostics-panel" hidden>
				<summary><?php esc_html_e( 'Dataset Diagnostics', 'minimalcode' ); ?></summary>
				<div class="diagnostics-content" id="diagnostics-content"></div>
			</details>
		</div>

		<?php if ( ! empty( $pulse_posts ) ) : ?>
			<div class="section-bar">
				<span class="label"><?php esc_html_e( 'Notebook', 'minimalcode' ); ?></span>
				<span><?php esc_html_e( 'tagged dev-pulse · most recent first', 'minimalcode' ); ?></span>
				<span class="rule"></span>
				<span><?php echo esc_html( count( $pulse_posts ) ); ?> <?php esc_html_e( 'entries', 'minimalcode' ); ?></span>
			</div>
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
		<?php endif; ?>

		<?php
		if ( function_exists( 'minimalcode_devpulse_render_payload_script' ) ) {
			minimalcode_devpulse_render_payload_script( $devpulse_data );
		}
		?>
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
