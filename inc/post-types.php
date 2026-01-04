<?php
/**
 * Custom Post Types for MinimalCode Theme
 *
 * @package MinimalCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'minimalcode_register_post_types' );

/**
 * Register Projects post type
 */
function minimalcode_register_post_types() {

	register_post_type(
		'projects',
		array(
			'labels'       => array(
				'name'               => __( 'Projects', 'minimalcode' ),
				'singular_name'      => __( 'Project', 'minimalcode' ),
				'add_new'            => __( 'Add New Project', 'minimalcode' ),
				'add_new_item'       => __( 'Add New Project', 'minimalcode' ),
				'edit_item'          => __( 'Edit Project', 'minimalcode' ),
				'new_item'           => __( 'New Project', 'minimalcode' ),
				'view_item'          => __( 'View Project', 'minimalcode' ),
				'search_items'       => __( 'Search Projects', 'minimalcode' ),
				'not_found'          => __( 'No projects found', 'minimalcode' ),
				'not_found_in_trash' => __( 'No projects found in trash', 'minimalcode' ),
			),
			'public'       => true,
			'has_archive'  => true,
			'query_var'    => true,
			'rewrite'      => array( 'slug' => 'projects' ),
			'supports'     => array( 'title', 'excerpt', 'thumbnail', 'editor' ),
			'menu_icon'    => 'dashicons-portfolio',
			'show_in_rest' => true,
		)
	);
}

add_action( 'add_meta_boxes', 'minimalcode_project_meta_boxes' );

/**
 * Add meta boxes for project fields
 */
function minimalcode_project_meta_boxes() {
	add_meta_box(
		'minimalcode_project_details',
		__( 'Project Details', 'minimalcode' ),
		'minimalcode_project_details_callback',
		'projects',
		'normal',
		'high'
	);
}

/**
 * Render project details meta box
 */
function minimalcode_project_details_callback( $post ) {
	wp_nonce_field( 'minimalcode_project_details', 'minimalcode_project_nonce' );

	$project_url = get_post_meta( $post->ID, '_minimalcode_project_url', true );
	$github_url  = get_post_meta( $post->ID, '_minimalcode_github_url', true );
	$role        = get_post_meta( $post->ID, '_minimalcode_role', true );

	?>
	<p>
		<label for="minimalcode_project_url"><strong><?php esc_html_e( 'Project URL', 'minimalcode' ); ?></strong></label><br>
		<input type="url" id="minimalcode_project_url" name="minimalcode_project_url" value="<?php echo esc_url( $project_url ); ?>" class="widefat" placeholder="https://example.com">
	</p>
	<p>
		<label for="minimalcode_github_url"><strong><?php esc_html_e( 'GitHub URL', 'minimalcode' ); ?></strong></label><br>
		<input type="url" id="minimalcode_github_url" name="minimalcode_github_url" value="<?php echo esc_url( $github_url ); ?>" class="widefat" placeholder="https://github.com/username/repo">
	</p>
	<p>
		<label for="minimalcode_role"><strong><?php esc_html_e( 'Your Role', 'minimalcode' ); ?></strong></label><br>
		<select id="minimalcode_role" name="minimalcode_role" class="widefat">
			<option value=""><?php esc_html_e( 'Select Role', 'minimalcode' ); ?></option>
			<option value="creator" <?php selected( $role, 'creator' ); ?>><?php esc_html_e( 'Creator', 'minimalcode' ); ?></option>
			<option value="maintainer" <?php selected( $role, 'maintainer' ); ?>><?php esc_html_e( 'Maintainer', 'minimalcode' ); ?></option>
			<option value="contributor" <?php selected( $role, 'contributor' ); ?>><?php esc_html_e( 'Contributor', 'minimalcode' ); ?></option>
			<option value="technical-lead" <?php selected( $role, 'technical-lead' ); ?>><?php esc_html_e( 'Technical Lead', 'minimalcode' ); ?></option>
		</select>
	</p>
	<?php
}

add_action( 'save_post_projects', 'minimalcode_save_project_details' );

/**
 * Save project meta fields
 */
function minimalcode_save_project_details( $post_id ) {
	if ( ! isset( $_POST['minimalcode_project_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['minimalcode_project_nonce'], 'minimalcode_project_details' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['minimalcode_project_url'] ) ) {
		update_post_meta( $post_id, '_minimalcode_project_url', esc_url_raw( $_POST['minimalcode_project_url'] ) );
	}

	if ( isset( $_POST['minimalcode_github_url'] ) ) {
		$github_url = esc_url_raw( $_POST['minimalcode_github_url'] );
		update_post_meta( $post_id, '_minimalcode_github_url', $github_url );

		// Clear GitHub OG image cache when URL changes.
		if ( ! empty( $github_url ) ) {
			$cache_key = 'minimalcode_github_og_' . md5( $github_url );
			delete_transient( $cache_key );
		}
	}

	if ( isset( $_POST['minimalcode_role'] ) ) {
		update_post_meta( $post_id, '_minimalcode_role', sanitize_text_field( $_POST['minimalcode_role'] ) );
	}
}

/**
 * Get GitHub OG image URL with caching
 *
 * @param string $github_url The GitHub repository URL.
 * @return string|false The OG image URL or false if not available.
 */
function minimalcode_get_github_og_image( $github_url ) {
	if ( empty( $github_url ) || strpos( $github_url, 'github.com' ) === false ) {
		return false;
	}

	$cache_key = 'minimalcode_github_og_' . md5( $github_url );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	// Extract owner/repo from URL.
	$parsed = wp_parse_url( $github_url );
	if ( empty( $parsed['path'] ) ) {
		return false;
	}

	$path_parts = array_filter( explode( '/', trim( $parsed['path'], '/' ) ) );
	if ( count( $path_parts ) < 2 ) {
		return false;
	}

	$owner = $path_parts[0];
	$repo  = $path_parts[1];

	$og_image = 'https://opengraph.githubassets.com/1/' . $owner . '/' . $repo;

	// Cache for 24 hours.
	set_transient( $cache_key, $og_image, DAY_IN_SECONDS );

	return $og_image;
}

/**
 * Get project role display name
 *
 * @param string $role The role slug.
 * @return string The display name.
 */
function minimalcode_get_role_display( $role ) {
	$roles = array(
		'creator'        => __( 'Creator', 'minimalcode' ),
		'maintainer'     => __( 'Maintainer', 'minimalcode' ),
		'contributor'    => __( 'Contributor', 'minimalcode' ),
		'technical-lead' => __( 'Technical Lead', 'minimalcode' ),
	);

	return isset( $roles[ $role ] ) ? $roles[ $role ] : '';
}
