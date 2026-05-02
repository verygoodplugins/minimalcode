<?php
/**
 * MinimalCode Theme Functions
 *
 * @package MinimalCode
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include custom post types.
require_once get_template_directory() . '/inc/post-types.php';

// Inline SVG icon helper (minimalcode_icon).
require_once get_template_directory() . '/inc/icons.php';

/**
 * Theme setup
 */
function minimalcode_setup() {
    // Add default posts and comments RSS feed links to head
    add_theme_support('automatic-feed-links');

    // Let WordPress manage the document title
    add_theme_support('title-tag');

    // Enable support for Post Thumbnails
    add_theme_support('post-thumbnails');

    // Custom image sizes
    add_image_size('project-card', 400, 240, true);

    // Register navigation menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'minimalcode'),
        'social' => __('Social Menu', 'minimalcode'),
    ));

    // Switch default core markup to output valid HTML5
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ));

    // Add support for editor styles
    add_theme_support('editor-styles');
    add_editor_style('assets/css/editor-style.css');

    // Add support for responsive embedded content
    add_theme_support('responsive-embeds');
}
add_action('after_setup_theme', 'minimalcode_setup');

/**
 * Enqueue scripts and styles
 */
function minimalcode_scripts() {
    // Main stylesheet - use filemtime for cache busting
    wp_enqueue_style('minimalcode-style', get_stylesheet_uri(), array(), filemtime(get_stylesheet_directory() . '/style.css'));

    // Custom styles
    wp_enqueue_style('minimalcode-custom', get_template_directory_uri() . '/assets/css/custom.css', array(), filemtime(get_template_directory() . '/assets/css/custom.css'));
    
    // Prism.js for code highlighting (dark mode compatible)
    wp_enqueue_style('prismjs', 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css', array(), '1.29.0');
    wp_enqueue_script('prismjs', 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js', array(), '1.29.0', true);
    
    // Add language support for common languages
    $prism_languages = array('javascript', 'python', 'bash', 'json', 'css', 'php', 'typescript');
    foreach ($prism_languages as $lang) {
        wp_enqueue_script(
            'prismjs-' . $lang,
            'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-' . $lang . '.min.js',
            array('prismjs'),
            '1.29.0',
            true
        );
    }
    
    // Theme behavior + live ⌘K search.
    // Depends on wp-api-fetch so the search modal can hit /wp/v2/search.
    wp_enqueue_script('minimalcode-theme', get_template_directory_uri() . '/assets/js/theme.js', array('wp-api-fetch'), filemtime(get_template_directory() . '/assets/js/theme.js'), true);
    
    // Comment reply script
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'minimalcode_scripts');

/**
 * Add reading time to posts
 */
function minimalcode_reading_time() {
    $content = get_post_field('post_content', get_the_ID());
    $word_count = str_word_count(strip_tags($content));
    $reading_time = ceil($word_count / 200);
    
    return $reading_time . ' minute read';
}

/**
 * Return the first category name for compact newspaper-style labels.
 */
function minimalcode_primary_category_name() {
    $categories = get_the_category();

    if (!empty($categories)) {
        return $categories[0]->name;
    }

    return __('Log', 'minimalcode');
}

/**
 * Custom excerpt length
 */
function minimalcode_excerpt_length($length) {
    return 40;
}
add_filter('excerpt_length', 'minimalcode_excerpt_length');

/**
 * Custom excerpt more
 */
function minimalcode_excerpt_more($more) {
    return '...';
}
add_filter('excerpt_more', 'minimalcode_excerpt_more');

/**
 * Add social links to footer
 */
function minimalcode_social_links() {
    $social_links = array(
        'twitter' => get_theme_mod('minimalcode_twitter', ''),
        'github' => get_theme_mod('minimalcode_github', ''),
        'linkedin' => get_theme_mod('minimalcode_linkedin', ''),
        'email' => get_theme_mod('minimalcode_email', ''),
    );
    
    return $social_links;
}

/**
 * Customizer settings
 */
function minimalcode_customize_register($wp_customize) {
    // Social media section
    $wp_customize->add_section('minimalcode_social', array(
        'title' => __('Social Links', 'minimalcode'),
        'priority' => 30,
    ));
    
    // Twitter
    $wp_customize->add_setting('minimalcode_twitter', array(
        'default' => '',
        'sanitize_callback' => 'esc_url_raw',
    ));
    
    $wp_customize->add_control('minimalcode_twitter', array(
        'label' => __('Twitter URL', 'minimalcode'),
        'section' => 'minimalcode_social',
        'type' => 'url',
    ));
    
    // GitHub
    $wp_customize->add_setting('minimalcode_github', array(
        'default' => '',
        'sanitize_callback' => 'esc_url_raw',
    ));
    
    $wp_customize->add_control('minimalcode_github', array(
        'label' => __('GitHub URL', 'minimalcode'),
        'section' => 'minimalcode_social',
        'type' => 'url',
    ));
    
    // LinkedIn
    $wp_customize->add_setting('minimalcode_linkedin', array(
        'default' => '',
        'sanitize_callback' => 'esc_url_raw',
    ));
    
    $wp_customize->add_control('minimalcode_linkedin', array(
        'label' => __('LinkedIn URL', 'minimalcode'),
        'section' => 'minimalcode_social',
        'type' => 'url',
    ));
    
    // Email
    $wp_customize->add_setting('minimalcode_email', array(
        'default' => '',
        'sanitize_callback' => 'sanitize_email',
    ));
    
    $wp_customize->add_control('minimalcode_email', array(
        'label' => __('Email Address', 'minimalcode'),
        'section' => 'minimalcode_social',
        'type' => 'email',
    ));
    
    // Site tagline/subtitle
    $wp_customize->add_setting('minimalcode_tagline', array(
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    
    $wp_customize->add_control('minimalcode_tagline', array(
        'label' => __('Site Tagline', 'minimalcode'),
        'section' => 'title_tagline',
        'type' => 'text',
    ));
}
add_action('customize_register', 'minimalcode_customize_register');

/**
 * Add meta viewport tag for mobile
 */
function minimalcode_viewport_meta() {
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
}
add_action('wp_head', 'minimalcode_viewport_meta');

/**
 * Add body classes for dark mode
 */
function minimalcode_body_classes($classes) {
    return $classes;
}
add_filter('body_class', 'minimalcode_body_classes');

/**
 * Virtual templated pages — /dev-pulse/ and /colophon/.
 *
 * Avoids needing a corresponding WP page in wp-admin; the templates live in
 * version control. Rewrite rules are auto-flushed on theme switch.
 */

/**
 * Map of virtual route slug → template file (relative to theme root).
 */
function minimalcode_virtual_routes() {
    return array(
        'dev-pulse' => 'page-dev-pulse.php',
        'colophon'  => 'page-colophon.php',
    );
}

function minimalcode_register_virtual_routes() {
    foreach (array_keys(minimalcode_virtual_routes()) as $slug) {
        add_rewrite_rule('^' . $slug . '/?$', 'index.php?minimalcode_virtual=' . $slug, 'top');
    }
}
add_action('init', 'minimalcode_register_virtual_routes');

add_filter('query_vars', function ($vars) {
    $vars[] = 'minimalcode_virtual';
    return $vars;
});

/**
 * Auto-flush rewrite rules on theme switch so /dev-pulse/ and /colophon/
 * resolve immediately — no manual options-permalink.php save required.
 */
function minimalcode_flush_virtual_rewrites_on_switch() {
    minimalcode_register_virtual_routes();
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'minimalcode_flush_virtual_rewrites_on_switch');

/**
 * Auto-flush rewrite rules once per deploy. The deploy workflow writes
 * DEPLOY_SHA to the theme root on every build; we compare it against the
 * stored option and flush exactly once when it changes. Catches new
 * routes and refreshed CPT slugs without a manual permalink save.
 */
function minimalcode_maybe_flush_after_deploy() {
    $sha_file = get_template_directory() . '/DEPLOY_SHA';
    if (!is_readable($sha_file)) {
        return;
    }
    $current = trim((string) file_get_contents($sha_file));
    if ('' === $current) {
        return;
    }
    if (get_option('minimalcode_deploy_sha') === $current) {
        return;
    }
    flush_rewrite_rules();
    update_option('minimalcode_deploy_sha', $current, false);
}
add_action('init', 'minimalcode_maybe_flush_after_deploy', 99);

/**
 * Short-circuit the default main query for virtual routes — the templates
 * don't use the loop, so loading 10 posts per request is wasted DB work.
 */
add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query()) {
        return;
    }
    if ($query->get('minimalcode_virtual')) {
        $query->set('posts_per_page', 0);
        $query->set('no_found_rows', true);
        $query->set('update_post_meta_cache', false);
        $query->set('update_post_term_cache', false);
    }
});

add_filter('template_include', function ($template) {
    $virtual = get_query_var('minimalcode_virtual');
    $routes  = minimalcode_virtual_routes();

    if (!$virtual || !isset($routes[$virtual])) {
        return $template;
    }

    // Canonical-URL guard: minimalcode_virtual is a public query var, so
    // /?minimalcode_virtual=colophon would otherwise render the colophon
    // template at the wrong URL (duplicate content). Reject anything that
    // didn't come in via the rewrite rule and redirect to the canonical path.
    $request_path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
    if ($request_path !== $virtual) {
        wp_safe_redirect(home_url('/' . $virtual . '/'), 301);
        exit;
    }

    $candidate = get_template_directory() . '/' . $routes[$virtual];
    if (file_exists($candidate)) {
        return $candidate;
    }

    return $template;
});

