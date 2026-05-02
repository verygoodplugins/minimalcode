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
                        <a href="mailto:<?php echo esc_attr($social_links['email']); ?>" aria-label="Email" title="Email"><?php minimalcode_the_icon('email', 20); ?></a>
                    <?php endif; ?>

                    <?php if ($social_links['twitter']) : ?>
                        <a href="<?php echo esc_url($social_links['twitter']); ?>" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)" title="X (Twitter)"><?php minimalcode_the_icon('twitter', 20); ?></a>
                    <?php endif; ?>

                    <?php if ($social_links['github']) : ?>
                        <a href="<?php echo esc_url($social_links['github']); ?>" target="_blank" rel="noopener noreferrer" aria-label="GitHub" title="GitHub"><?php minimalcode_the_icon('github', 20); ?></a>
                    <?php endif; ?>

                    <?php if ($social_links['linkedin']) : ?>
                        <a href="<?php echo esc_url($social_links['linkedin']); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" title="LinkedIn"><?php minimalcode_the_icon('linkedin', 20); ?></a>
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

