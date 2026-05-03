</main>

<footer class="footer">
    <div class="wrap">
        <div class="footer-grid">
            <div>
                <h4><?php esc_html_e( 'Notebook', 'minimalcode' ); ?></h4>
                <ul>
                    <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">log</a></li>
                    <li><a href="<?php echo esc_url( get_post_type_archive_link( 'projects' ) ); ?>">projects</a></li>
                    <li><a href="<?php echo esc_url( get_feed_link() ); ?>">rss</a></li>
                </ul>
            </div>
            <div>
                <h4><?php esc_html_e( 'Systems', 'minimalcode' ); ?></h4>
                <ul>
                    <?php
                    $systems = array(
                        'automem'  => array( 'post_tag', 'automem' ),
                        'autohub'  => array( 'post_tag', 'autohub' ),
                        'autojack' => array( 'category', 'autojack' ),
                    );
                    foreach ( $systems as $label => $term ) :
                        $term_obj = get_term_by( 'slug', $term[1], $term[0] );
                        if ( ! $term_obj || is_wp_error( $term_obj ) ) {
                            continue;
                        }
                        ?>
                        <li><a href="<?php echo esc_url( get_term_link( $term_obj ) ); ?>"><?php echo esc_html( $label ); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h4><?php esc_html_e( 'Elsewhere', 'minimalcode' ); ?></h4>
                <ul>
                    <?php $social_links = minimalcode_social_links(); ?>
                    <?php if ( $social_links['github'] ) : ?><li><a href="<?php echo esc_url( $social_links['github'] ); ?>">github</a></li><?php endif; ?>
                    <?php if ( $social_links['twitter'] ) : ?><li><a href="<?php echo esc_url( $social_links['twitter'] ); ?>">x/twitter</a></li><?php endif; ?>
                    <?php if ( $social_links['email'] ) : ?><li><a href="mailto:<?php echo esc_attr( $social_links['email'] ); ?>">email</a></li><?php endif; ?>
                </ul>
            </div>
            <div>
                <h4><?php esc_html_e( 'Colophon', 'minimalcode' ); ?></h4>
                <ul>
                    <li>wordpress</li>
                    <li>minimalcode</li>
                    <li>built in public</li>
                </ul>
            </div>
        </div>
        <div class="footer-foot">
            <span><?php echo esc_html( get_bloginfo( 'name' ) ); ?> © <?php echo esc_html( date( 'Y' ) ); ?></span>
            <span class="colophon"><?php esc_html_e( 'Just another Wordprussite.', 'minimalcode' ); ?></span>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

