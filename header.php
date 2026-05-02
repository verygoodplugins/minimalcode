<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (!theme) {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
    <div class="container">
        <div class="header-content">
            <div class="site-branding">
                <h1 class="site-title">
                    <a href="<?php echo esc_url(home_url('/')); ?>" rel="home">
                        <?php bloginfo('name'); ?>
                    </a>
                </h1>
                <?php
                $tagline = get_theme_mod('minimalcode_tagline', get_bloginfo('description'));
                if ($tagline) :
                ?>
                    <p class="site-description"><?php echo esc_html($tagline); ?></p>
                <?php endif; ?>
            </div>

            <nav class="main-navigation">
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'primary',
                    'menu_class' => 'nav-menu',
                    'container' => false,
                    'fallback_cb' => false,
                ));
                ?>
                <button class="search-trigger" aria-label="Search (⌘K)" title="Search (⌘K)">
                    <?php minimalcode_the_icon('search', 20); ?>
                    <span class="search-trigger-label">Search&hellip;</span>
                    <span class="search-kbd">⌘K</span>
                </button>
                <button class="theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">
                    <span class="sun-icon"><?php minimalcode_the_icon('sun', 20); ?></span>
                    <span class="moon-icon"><?php minimalcode_the_icon('moon', 20); ?></span>
                </button>
                <?php
                $header_social = minimalcode_social_links();
                if ( array_filter( array(
                    'github'   => $header_social['github'],
                    'twitter'  => $header_social['twitter'],
                    'linkedin' => $header_social['linkedin'],
                ) ) ) :
                ?>
                <ul class="header-social" aria-label="Social links">
                    <?php if ( $header_social['github'] ) : ?>
                        <li><a class="social-squircle" href="<?php echo esc_url( $header_social['github'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="GitHub" title="GitHub"><?php minimalcode_the_icon('github', 20); ?></a></li>
                    <?php endif; ?>
                    <?php if ( $header_social['twitter'] ) : ?>
                        <li><a class="social-squircle" href="<?php echo esc_url( $header_social['twitter'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)" title="X (Twitter)"><?php minimalcode_the_icon('twitter', 20); ?></a></li>
                    <?php endif; ?>
                    <?php if ( $header_social['linkedin'] ) : ?>
                        <li><a class="social-squircle" href="<?php echo esc_url( $header_social['linkedin'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" title="LinkedIn"><?php minimalcode_the_icon('linkedin', 20); ?></a></li>
                    <?php endif; ?>
                </ul>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</header>

<main id="main" class="site-main">

