<?php
/**
 * Plugin Name:       Media Attached Filter
 * Description:       Adds a new filter for media library to filter for files attached to posts or pages.
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Version:           1.0.0
 * Author:            Thomas Zwirner
 * Author URI:        https://www.thomaszwirner.de
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       media-attached-filter
 *
 * @package media-attached-filter
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

// do nothing if PHP-version is not 8.0 or newer.
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	return;
}

/**
 * Add filter field in media library.
 *
 * @return void
 */
function media_attached_filter_add_filter(): void {
	// bail if get_current_screen is not available.
	if ( ! function_exists( 'get_current_screen' ) ) {
		return;
	}

	// get the actual screen.
	$screen = get_current_screen();

	// bail if screen is not media library.
	if ( 'upload' !== $screen->base ) {
		return;
	}

	// get actual value from request.
	$attached = filter_input( INPUT_GET, 'maf_attached', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

	// show filter with AJAX-function to search.
	?>
	<!--suppress HtmlFormInputWithoutLabel -->
	<input list="maf_attached_list" id="maf_attached" name="maf_attached" value="<?php echo esc_attr( $attached ); ?>" placeholder="<?php echo esc_attr__( 'Attached to ..', 'media-attached-filter' ); ?>" autocomplete="off" />
	<datalist id="maf_attached_list"></datalist>

	<?php
}
add_action( 'restrict_manage_posts', 'media_attached_filter_add_filter' );

/**
 * Add own CSS and JS for backend.
 *
 * @return void
 */
function media_attached_filter_add_files(): void {
	// admin-specific styles.
	wp_enqueue_style(
		'maf-admin',
		plugin_dir_url( __FILE__ ) . '/admin/styles.css',
		array(),
		filemtime( plugin_dir_path( __FILE__ ) . '/admin/styles.css' ),
	);

	// backend-JS.
	wp_enqueue_script(
		'maf-admin',
		plugins_url( '/admin/js.js', __FILE__ ),
		array( 'jquery' ),
		filemtime( plugin_dir_path( __FILE__ ) . '/admin/js.js' ),
		true
	);

	// add php-vars to our js-script.
	wp_localize_script(
		'maf-admin',
		'mafJsVars',
		array(
			'ajax_url'         => admin_url( 'admin-ajax.php' ),
			'maf_search_nonce' => wp_create_nonce( 'maf-search' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'media_attached_filter_add_files' );

/**
 * Run search for entries with given keyword and return resulting limited list.
 *
 * @return void
 */
function media_attached_filter_search_ajax(): void {
	// check nonce.
	check_ajax_referer( 'maf-search', 'nonce' );

	// get requested keyword.
	$keyword = filter_input( INPUT_POST, 'keyword', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

	// bail if keyword is not given.
	if ( is_null( $keyword ) ) {
		wp_send_json( array( 'success' => false ) );
	}

	// define query.
	$query  = array(
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => 'any',
		's'              => $keyword,
		'fields'         => 'ids',
		'search_columns' => array( 'post_title' ),
	);
	$result = new WP_Query( $query );

	// get results.
	$list = array();
	foreach ( $result->posts as $post_id ) {
		$list[ $post_id ] = get_the_title( $post_id );
	}

	// return resulting list.
	wp_send_json(
		array(
			'success' => ! empty( $list ),
			'results' => $list,
		)
	);
}
add_action( 'wp_ajax_maf_search', 'media_attached_filter_search_ajax' );

/**
 * Run the filter.
 *
 * @param WP_Query $query The WP_Query object which will be run.
 *
 * @return void
 */
function media_attached_filter_run_filter( WP_Query $query ): void {
	if ( is_admin() && $query->is_main_query() ) {
		$attached = filter_input( INPUT_GET, 'maf_attached', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! empty( $attached ) ) {
			$query_to_get_post_id = array(
				'post_type'   => array( 'post', 'page' ),
				'post_status' => 'any',
				'title'       => $attached,
				'fields'      => 'ids',
			);
			$results              = new WP_Query( $query_to_get_post_id );
			if ( 1 === $results->post_count ) {
				$query->set( 'post_parent', $results->posts[0] );
			} else {
				// let the query return nothing.
				$query->set( 'post_parent', -1 );
			}
		}
	}
}
add_action( 'pre_get_posts', 'media_attached_filter_run_filter' );
