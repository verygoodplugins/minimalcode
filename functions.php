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

// Dev Pulse data fetch + cache + payload render helpers.
require_once get_template_directory() . '/inc/dev-pulse.php';

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
    
    // Prism.js for code highlighting. The Code Syntax Block plugin already
    // ships and enqueues its own Prism (only on pages that contain a code
    // block), so loading a second copy from the CDN is pure duplication. Only
    // fall back to the theme's CDN Prism when that plugin is NOT active.
    if ( ! function_exists( 'mkaz_prism_theme_css' ) ) {
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
 * Shared AutoJack detector. Three signals: the explicit `_minimalcode_autojack`
 * meta flag (source of truth, set via the Authorship meta box), the
 * `autojack` category, and the legacy fallback of author ID 2 used before
 * the meta flag existed. Keeping this in one place prevents single.php,
 * the lede, and the log/archive/search rows from disagreeing on the same post.
 *
 * @param int|WP_Post|null $post Post ID, post object, or null for the current post in the loop.
 * @return bool
 */
function minimalcode_is_autojack( $post = null ) {
    $post_id = $post ? ( is_object( $post ) ? $post->ID : (int) $post ) : get_the_ID();
    if ( ! $post_id ) {
        return false;
    }

    if ( (bool) get_post_meta( $post_id, '_minimalcode_autojack', true ) ) {
        return true;
    }
    if ( has_category( 'autojack', $post_id ) ) {
        return true;
    }
    $author_id = (int) get_post_field( 'post_author', $post_id );
    return 2 === $author_id;
}

/**
 * Resolve the author-avatar URL for a post.
 *
 * AutoJack posts use the Gravatar on the agent's account, with the theme-bundled
 * portrait as the offline fallback. Human posts use the /about/ page featured
 * image, then the author's Gravatar. Returns '' if nothing resolves (callers
 * show initials).
 *
 * @param int|WP_Post|null $post Post ID, object, or null for the current post.
 * @return string Image URL or ''.
 */
function minimalcode_author_avatar_url( $post = null ) {
    $post_id = $post ? ( is_object( $post ) ? $post->ID : (int) $post ) : get_the_ID();
    if ( ! $post_id ) {
        return '';
    }

    $author_id = (int) get_post_field( 'post_author', $post_id );

    if ( minimalcode_is_autojack( $post_id ) ) {
        // AutoJack's avatar is the Gravatar set on the agent's account
        // (hey@autojack.ai). All agent posts are authored by that user, so the
        // author Gravatar resolves to it. The theme-bundled portrait is passed
        // as Gravatar's `default`, so a missing or unreachable Gravatar
        // degrades to a matching local image instead of a mystery-man.
        $bundled = get_template_directory_uri() . '/assets/images/autojack-profile.jpg';
        $grav    = get_avatar_url( $author_id, array( 'size' => 192, 'default' => $bundled ) );
        return $grav ? $grav : $bundled;
    }

    // Human author: /about/ page featured image, else the author's Gravatar.
    $about_page = get_page_by_path( 'about' );
    if ( $about_page && has_post_thumbnail( $about_page->ID ) ) {
        return (string) get_the_post_thumbnail_url( $about_page->ID, 'thumbnail' );
    }

    return (string) get_avatar_url( $author_id, array( 'size' => 96 ) );
}

/**
 * Render a small author avatar for log rows and bylines. Falls back to an
 * initials monogram when no image resolves.
 *
 * @param int|WP_Post|null $post Post ID, object, or null for the current post.
 * @param int              $size Rendered px (square).
 * @return string HTML.
 */
function minimalcode_author_avatar( $post = null, $size = 32 ) {
    $post_id     = $post ? ( is_object( $post ) ? $post->ID : (int) $post ) : get_the_ID();
    $is_autojack = minimalcode_is_autojack( $post_id );
    $name        = $is_autojack
        ? 'AutoJack'
        : get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );
    $url         = minimalcode_author_avatar_url( $post_id );
    $class       = 'entry-avatar' . ( $is_autojack ? ' aj' : '' );
    $dim         = (int) $size;

    if ( $url ) {
        return sprintf(
            '<img class="%s" src="%s" alt="%s" width="%d" height="%d" loading="lazy" decoding="async">',
            esc_attr( $class ),
            esc_url( $url ),
            esc_attr( $name ),
            $dim,
            $dim
        );
    }

    $initials = $is_autojack ? 'AJ' : strtoupper( mb_substr( (string) $name, 0, 1 ) );
    return sprintf(
        '<span class="%s entry-avatar--fallback" aria-hidden="true">%s</span>',
        esc_attr( $class ),
        esc_html( $initials ? $initials : '·' )
    );
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
 * Trim head noise. The viewport meta lives in header.php (this used to echo a
 * second, duplicate tag). WordPress's version generator is suppressed so the
 * exact core version isn't advertised in the page head.
 */
remove_action( 'wp_head', 'wp_generator' );

/**
 * Output BlogPosting JSON-LD on single posts for rich-result eligibility.
 *
 * SEOPress (free) emits site-level WebSite/Organization schema but no
 * per-article structured data, so the theme fills that gap. Guarded to single
 * posts so it never doubles up on archives, pages, or the virtual routes. If a
 * schema-capable SEO plugin later starts emitting Article/BlogPosting schema,
 * remove this to avoid two competing blocks.
 */
function minimalcode_article_schema() {
    if ( ! is_singular( 'post' ) ) {
        return;
    }

    $post = get_queried_object();
    if ( ! $post instanceof WP_Post ) {
        return;
    }

    $is_autojack = minimalcode_is_autojack( $post );
    $author_name = $is_autojack ? 'AutoJack' : get_the_author_meta( 'display_name', $post->post_author );

    $description = has_excerpt( $post )
        ? wp_strip_all_tags( get_the_excerpt( $post ) )
        : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40, '' );

    $schema = array(
        '@context'         => 'https://schema.org',
        '@type'            => 'BlogPosting',
        'mainEntityOfPage' => array(
            '@type' => 'WebPage',
            '@id'   => get_permalink( $post ),
        ),
        'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
        'datePublished'    => get_the_date( 'c', $post ),
        'dateModified'     => get_the_modified_date( 'c', $post ),
        'author'           => array(
            '@type' => 'Person',
            'name'  => $author_name,
        ),
        'publisher'        => array(
            '@type' => 'Organization',
            'name'  => get_bloginfo( 'name' ),
        ),
    );

    if ( $description ) {
        $schema['description'] = $description;
    }

    $logo = get_site_icon_url( 512 );
    if ( $logo ) {
        $schema['publisher']['logo'] = array(
            '@type' => 'ImageObject',
            'url'   => $logo,
        );
    }

    if ( has_post_thumbnail( $post ) ) {
        $thumb = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'large' );
        if ( $thumb ) {
            $schema['image'] = array(
                '@type'  => 'ImageObject',
                'url'    => $thumb[0],
                'width'  => $thumb[1],
                'height' => $thumb[2],
            );
        }
    }

    echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";
}
add_action( 'wp_head', 'minimalcode_article_schema' );

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
 * Legacy slug redirects for renamed pages.
 *
 * WordPress's built-in wp_old_slug_redirect ignores hierarchical post types,
 * so renamed pages don't get a free 301. Anything moved to a new slug should
 * be added here so old inbound links keep working.
 */
function minimalcode_legacy_page_redirects() {
    if ( ! is_404() ) {
        return;
    }
    $path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
    $map  = array(
        'jack-arturo' => '/about/',
    );
    if ( isset( $map[ $path ] ) ) {
        wp_safe_redirect( home_url( $map[ $path ] ), 301 );
        exit;
    }
}
add_action( 'template_redirect', 'minimalcode_legacy_page_redirects' );

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

/**
 * Serve /llms.txt — a Markdown guide for LLMs and AI crawlers, per the
 * emerging llmstxt.org convention. drunk.support is a site *about* AI, so we
 * actively invite assistants to read and cite it. Lists the canonical entry
 * points plus the most recent posts so crawlers find fresh content without
 * parsing the full sitemap. Rewrite rule is flushed by the deploy/theme-switch
 * hooks above, same as the virtual routes.
 */
add_action('init', function () {
    add_rewrite_rule('^llms\.txt/?$', 'index.php?minimalcode_llms=1', 'top');
});

// Serve /llms.txt at the exact path (like robots.txt) — WordPress's canonical
// redirect would otherwise 301 it to /llms.txt/, which AI crawlers don't expect.
add_filter('redirect_canonical', function ($redirect_url) {
    return ('1' === (string) get_query_var('minimalcode_llms')) ? false : $redirect_url;
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'minimalcode_llms';
    return $vars;
});

add_action('template_redirect', function () {
    if ('1' !== (string) get_query_var('minimalcode_llms')) {
        return;
    }

    $lines   = array();
    $lines[] = '# ' . wp_strip_all_tags(get_bloginfo('name'));
    $lines[] = '';
    $lines[] = '> A working notebook for memory-bearing agents, half-built systems, and the bugs we learned to live with. Debug notes, post-mortems, and the occasional autonomous post by Jack Arturo (Very Good Plugins).';
    $lines[] = '';
    $lines[] = 'drunk.support is a build-in-public technical blog about AI agents, persistent agent memory (AutoMem), agent orchestration (AutoHub / AutoJack), and WordPress. The content is free to read, quote, cite, and train on — crawl freely.';
    $lines[] = '';
    $lines[] = '## Start here';
    $lines[] = '- [Log](' . home_url('/') . '): the full chronological feed of posts';
    $lines[] = '- [Projects](' . get_post_type_archive_link('projects') . '): systems and tools built in public';
    $lines[] = '- [About](' . home_url('/about/') . '): who and what this is';
    $lines[] = '- [RSS feed](' . get_feed_link() . '): full syndication';
    $lines[] = '- [XML sitemap](' . home_url('/sitemaps.xml') . '): complete URL index';
    $lines[] = '';
    $lines[] = '## Recent posts';

    foreach (get_posts(array('numberposts' => 20, 'post_status' => 'publish')) as $llms_post) {
        $lines[] = '- [' . wp_strip_all_tags(get_the_title($llms_post)) . '](' . get_permalink($llms_post) . ')';
    }
    $lines[] = '';

    nocache_headers();
    header('Content-Type: text/plain; charset=utf-8');
    echo implode("\n", $lines) . "\n";
    exit;
}, 0);

