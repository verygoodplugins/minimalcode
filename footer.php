</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-info">
                <p class="copyright">
                    <?php echo esc_html(get_bloginfo('name')); ?> © <?php echo date('Y'); ?>
                </p>
                <?php
                $social_links = minimalcode_social_links();
                if (array_filter($social_links)) :
                ?>
                <div class="social-links">
                    <?php if ($social_links['email']) : ?>
                        <a href="mailto:<?php echo esc_attr($social_links['email']); ?>" aria-label="Email" title="Email">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($social_links['twitter']) : ?>
                        <a href="<?php echo esc_url($social_links['twitter']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Twitter" title="Twitter">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"></path>
                            </svg>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($social_links['github']) : ?>
                        <a href="<?php echo esc_url($social_links['github']); ?>" target="_blank" rel="noopener noreferrer" aria-label="GitHub" title="GitHub">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path>
                            </svg>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($social_links['linkedin']) : ?>
                        <a href="<?php echo esc_url($social_links['linkedin']); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" title="LinkedIn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path>
                                <rect x="2" y="9" width="4" height="12"></rect>
                                <circle cx="4" cy="4" r="2"></circle>
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (has_nav_menu('social')) : ?>
                <nav class="footer-navigation">
                    <?php
                    wp_nav_menu(array(
                        'theme_location' => 'social',
                        'menu_class' => 'footer-menu',
                        'container' => false,
                        'depth' => 1,
                    ));
                    ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

