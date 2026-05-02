<?php
/**
 * Template Name: About Page
 * Description: About page in newspaper style — avatar + socials rail, prose body.
 *
 * @package MinimalCode
 */

get_header();

$social_links = minimalcode_social_links();
?>

<div class="single wrap about-wrap">
	<aside class="post-meta-rail about-rail">
		<a class="post-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">← back to log</a>

		<div class="about-portrait">
			<?php if ( have_posts() ) : ?>
				<?php
				rewind_posts();
				the_post();
				?>
				<?php if ( has_post_thumbnail() ) : ?>
					<?php the_post_thumbnail( 'thumbnail', array( 'class' => 'about-portrait-img' ) ); ?>
				<?php else : ?>
					<div class="about-portrait-fallback serif">JA</div>
				<?php endif; ?>
				<?php rewind_posts(); ?>
			<?php endif; ?>
		</div>

		<div class="row"><span class="k">name</span><span class="v">jack arturo</span></div>
		<div class="row"><span class="k">based</span><span class="v">MIA / LIS</span></div>
		<div class="row"><span class="k">since</span><span class="v">2009</span></div>
		<div class="row"><span class="k">role</span><span class="v">creator</span></div>

		<div class="about-rail-social">
			<?php if ( ! empty( $social_links['github'] ) ) : ?>
				<a class="about-rail-social-btn" href="<?php echo esc_url( $social_links['github'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="GitHub">
					<?php echo minimalcode_icon( 'github', 16 ); ?>
				</a>
			<?php endif; ?>
			<?php if ( ! empty( $social_links['twitter'] ) ) : ?>
				<a class="about-rail-social-btn" href="<?php echo esc_url( $social_links['twitter'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Twitter">
					<?php echo minimalcode_icon( 'twitter', 16 ); ?>
				</a>
			<?php endif; ?>
			<?php if ( ! empty( $social_links['linkedin'] ) ) : ?>
				<a class="about-rail-social-btn" href="<?php echo esc_url( $social_links['linkedin'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
					<?php echo minimalcode_icon( 'linkedin', 16 ); ?>
				</a>
			<?php endif; ?>
			<?php if ( ! empty( $social_links['email'] ) ) : ?>
				<a class="about-rail-social-btn" href="mailto:<?php echo esc_attr( $social_links['email'] ); ?>" aria-label="Email">
					<?php echo minimalcode_icon( 'email', 16 ); ?>
				</a>
			<?php endif; ?>
		</div>
	</aside>

	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'about-article' ); ?>>
			<div class="post-kicker">
				<span class="tag hot-tag"><?php esc_html_e( 'about', 'minimalcode' ); ?></span>
			</div>
			<h1 class="post-headline serif"><?php the_title(); ?></h1>

			<div class="post-body entry-content">
				<?php the_content(); ?>
			</div>
		</article>
	<?php endwhile; ?>

	<aside class="toc-rail about-toc">
		<div class="h">Elsewhere</div>
		<ol class="elsewhere-list">
			<li>about</li>
			<li><a href="<?php echo esc_url( get_post_type_archive_link( 'projects' ) ); ?>">projects</a></li>
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">log</a></li>
			<li class="muted"><a href="<?php echo esc_url( get_feed_link() ); ?>">subscribe.rss</a></li>
		</ol>
	</aside>
</div>

<?php
get_footer();
