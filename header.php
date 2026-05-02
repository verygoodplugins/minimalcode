<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function() {
            var theme = localStorage.getItem('minimalcode-theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="ticker" aria-label="<?php esc_attr_e( 'Site status ticker', 'minimalcode' ); ?>">
	<div class="ticker-track">
		<?php
		$ticker_items = array(
			'automem recall pipeline live',
			'autohub orchestration notes',
			'wp fusion still pays the bills',
			'autojack last pass: recent',
			'skills indexed locally',
			'debug notes from production',
		);

		foreach ( array_merge( $ticker_items, $ticker_items ) as $ticker_item ) :
			?>
			<span><?php echo esc_html( $ticker_item ); ?><span class="dot">&bull;</span></span>
		<?php endforeach; ?>
	</div>
</div>

<header class="masthead wrap">
	<div class="mast-row">
		<div class="brand">
			<a class="brand-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<?php bloginfo( 'name' ); ?>
			</a>
			<div class="brand-meta">
				VOL.04 / <span class="iss">ISS.27</span><br>
				EST. 2009 · MIA / LIS<br>
				jack arturo · vgp
			</div>
		</div>

		<div class="mast-tag serif">
			&quot;Just another <span class="pull">Wordprussite</span>.&quot; — a working notebook for memory-bearing agents, half-built systems, and bugs we learned to live with.
		</div>

		<div class="mast-utility">
			<button class="util-btn search-trigger" aria-label="Search (⌘K)" title="Search (⌘K)">
				<span class="search-kbd">⌘K</span>
				<span class="search-trigger-label"><?php esc_html_e( 'Search', 'minimalcode' ); ?></span>
			</button>
			<button class="util-btn theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">
				<span class="sun-icon"><?php esc_html_e( 'Light', 'minimalcode' ); ?></span>
				<span class="moon-icon"><?php esc_html_e( 'Dark', 'minimalcode' ); ?></span>
			</button>
			<a class="util-btn hot" href="<?php echo esc_url( get_feed_link() ); ?>"><?php esc_html_e( 'RSS', 'minimalcode' ); ?></a>
		</div>
	</div>
</header>

<?php
$minimalcode_virtual = get_query_var( 'minimalcode_virtual' );
$is_log_active       = is_home() && empty( $minimalcode_virtual );
$is_dev_pulse_active = ( 'dev-pulse' === $minimalcode_virtual );
$is_colophon_active  = ( 'colophon' === $minimalcode_virtual );
?>
<nav class="nav wrap" aria-label="<?php esc_attr_e( 'Primary navigation', 'minimalcode' ); ?>">
	<a class="nav-item <?php echo $is_log_active ? 'active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<span class="nav-num">01</span> <?php esc_html_e( 'Log', 'minimalcode' ); ?>
	</a>
	<a class="nav-item <?php echo is_post_type_archive( 'projects' ) ? 'active' : ''; ?>" href="<?php echo esc_url( get_post_type_archive_link( 'projects' ) ); ?>">
		<span class="nav-num">02</span> <?php esc_html_e( 'Projects', 'minimalcode' ); ?>
	</a>
	<a class="nav-item <?php echo $is_dev_pulse_active ? 'active' : ''; ?>" href="<?php echo esc_url( home_url( '/dev-pulse/' ) ); ?>">
		<span class="nav-num">03</span> <?php esc_html_e( 'Dev Pulse', 'minimalcode' ); ?>
	</a>
	<a class="nav-item <?php echo is_page( 'about' ) ? 'active' : ''; ?>" href="<?php echo esc_url( home_url( '/about/' ) ); ?>">
		<span class="nav-num">04</span> <?php esc_html_e( 'About', 'minimalcode' ); ?>
	</a>
	<a class="nav-item <?php echo $is_colophon_active ? 'active' : ''; ?>" href="<?php echo esc_url( home_url( '/colophon/' ) ); ?>">
		<span class="nav-num">05</span> <?php esc_html_e( 'Colophon', 'minimalcode' ); ?>
	</a>
	<span class="nav-spacer" aria-hidden="true"></span>
	<span class="nav-status"><span class="live-dot"></span><?php esc_html_e( 'Live', 'minimalcode' ); ?></span>
</nav>

<main id="main" class="site-main">

