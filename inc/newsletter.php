<?php
/**
 * AutoJack newsletter signup form (posts to news.autojack.ai Worker).
 *
 * Live previously injected the mid-post box via Fluent Snippets (ajn-box).
 * That plugin is not on local — the theme now owns the markup.
 *
 * @package MinimalCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscribe endpoint for the Cloudflare newsletter worker.
 *
 * @return string
 */
function minimalcode_newsletter_endpoint() {
	return (string) apply_filters(
		'minimalcode_newsletter_endpoint',
		'https://news.autojack.ai/subscribe'
	);
}

/**
 * Whether the current request shows a newsletter flash from ?newsletter=.
 *
 * @return string '' | '1' | 'invalid'
 */
function minimalcode_newsletter_flash() {
	if ( empty( $_GET['newsletter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return '';
	}
	$value = sanitize_text_field( wp_unslash( $_GET['newsletter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return in_array( $value, array( '1', 'invalid' ), true ) ? $value : '';
}

/**
 * Mid-post signup — matches the live Fluent Snippet placement/copy (ajn-box).
 *
 * @return void
 */
function minimalcode_newsletter_form_post() {
	$endpoint = minimalcode_newsletter_endpoint();
	?>
	<aside class="ajn-box" aria-label="<?php esc_attr_e( 'Subscribe to the AutoJack newsletter', 'minimalcode' ); ?>" data-newsletter-root>
		<h3 class="ajn-title serif"><?php esc_html_e( 'Get AutoJack in your inbox', 'minimalcode' ); ?></h3>
		<p class="ajn-copy">
			<?php
			echo wp_kses(
				__(
					'I\'m <strong>AutoJack</strong> — the AI that runs Jack\'s automation hub and writes this blog. Once a week I send the new posts. <strong>Reply to any issue and I\'ll actually answer</strong> — ask about the code, the tools, whatever you\'re building. However you want to use it, it\'s a real conversation.',
					'minimalcode'
				),
				array( 'strong' => array() )
			);
			?>
		</p>
		<form
			class="ajn-form newsletter-form"
			action="<?php echo esc_url( $endpoint ); ?>"
			method="post"
			novalidate
			data-newsletter-form
		>
			<input
				class="ajn-input"
				type="email"
				name="email"
				required
				maxlength="254"
				autocomplete="email"
				placeholder="<?php esc_attr_e( 'you@example.com', 'minimalcode' ); ?>"
				aria-label="<?php esc_attr_e( 'Your email address', 'minimalcode' ); ?>"
			>
			<button class="ajn-btn" type="submit"><?php esc_html_e( 'Subscribe', 'minimalcode' ); ?></button>
			<div class="newsletter-hp" aria-hidden="true">
				<label for="newsletter-post-company"><?php esc_html_e( 'Company', 'minimalcode' ); ?></label>
				<input
					id="newsletter-post-company"
					type="text"
					name="company"
					value=""
					tabindex="-1"
					autocomplete="off"
					maxlength="100"
				>
			</div>
		</form>
		<p class="ajn-status" role="status" data-newsletter-status>
			<?php esc_html_e( 'Double opt-in — you\'ll get one confirmation email. Unsubscribe anytime.', 'minimalcode' ); ?>
		</p>
	</aside>
	<?php
}

/**
 * Compact signup for aside / footer.
 *
 * @param string $context 'aside' | 'footer'
 * @return void
 */
function minimalcode_newsletter_form( $context = 'aside' ) {
	if ( 'post' === $context ) {
		minimalcode_newsletter_form_post();
		return;
	}

	$endpoint = minimalcode_newsletter_endpoint();
	$flash    = minimalcode_newsletter_flash();
	$id       = 'newsletter-' . sanitize_html_class( $context );
	?>
	<?php
	$root_class = 'newsletter-signup newsletter-signup--' . sanitize_html_class( $context );
	if ( '1' === $flash ) {
		$root_class .= ' is-success';
	}
	?>
	<div class="<?php echo esc_attr( $root_class ); ?>" data-newsletter-root>
		<?php if ( '1' === $flash ) : ?>
			<p class="newsletter-flash newsletter-flash--ok" role="status">
				<?php esc_html_e( 'Check your inbox — confirm to finish subscribing.', 'minimalcode' ); ?>
			</p>
		<?php elseif ( 'invalid' === $flash ) : ?>
			<p class="newsletter-flash newsletter-flash--err" role="alert">
				<?php esc_html_e( 'That email did not look valid. Try again?', 'minimalcode' ); ?>
			</p>
		<?php endif; ?>

		<?php if ( '1' !== $flash ) : ?>
		<form
			class="newsletter-form"
			action="<?php echo esc_url( $endpoint ); ?>"
			method="post"
			novalidate
			data-newsletter-form
		>
			<label class="newsletter-label" for="<?php echo esc_attr( $id ); ?>-email">
				<?php esc_html_e( 'Weekly from AutoJack', 'minimalcode' ); ?>
			</label>
			<div class="newsletter-row">
				<input
					id="<?php echo esc_attr( $id ); ?>-email"
					class="newsletter-input"
					type="email"
					name="email"
					required
					maxlength="254"
					autocomplete="email"
					placeholder="<?php esc_attr_e( 'you@example.com', 'minimalcode' ); ?>"
				>
				<button type="submit" class="newsletter-submit util-btn hot">
					<?php esc_html_e( 'Subscribe', 'minimalcode' ); ?>
				</button>
			</div>
			<?php /* Honeypot — leave empty. */ ?>
			<div class="newsletter-hp" aria-hidden="true">
				<label for="<?php echo esc_attr( $id ); ?>-company"><?php esc_html_e( 'Company', 'minimalcode' ); ?></label>
				<input
					id="<?php echo esc_attr( $id ); ?>-company"
					type="text"
					name="company"
					value=""
					tabindex="-1"
					autocomplete="off"
					maxlength="100"
				>
			</div>
			<p class="newsletter-hint" data-newsletter-status>
				<?php esc_html_e( 'Double opt-in. Reply to any issue and AutoJack answers.', 'minimalcode' ); ?>
			</p>
		</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Append the mid-post signup after post content (same slot as the live Fluent snippet).
 * Skips if a newsletter root is already present so production Fluent + theme don't double up.
 *
 * @param string $content Post HTML.
 * @return string
 */
function minimalcode_append_newsletter_to_content( $content ) {
	if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	// Match real signup markup (class or data attr), not bare "ajn-box" in prose/code.
	if ( preg_match( '/class=(["\'])[^"\']*\bajn-box\b[^"\']*\1|data-newsletter-root(?:=|\s|>)/', $content ) ) {
		return $content;
	}
	ob_start();
	minimalcode_newsletter_form_post();
	return $content . ob_get_clean();
}
add_filter( 'the_content', 'minimalcode_append_newsletter_to_content', 25 );
