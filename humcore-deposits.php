<?php
/**
 * The HumCORE Deposits Plugin
 *
 * HumCORE Deposits is a Wordpress / Buddypress plugin to connect the Commons-In-A-Box (CBOX) social network platform to a Fedora-based institutional repository system.
 *
 * @package HumCORE
 * @subpackage Deposits
 */

/**
 * Plugin Name: HumCORE Deposits
 * Description: HumCORE Deposits is a Wordpress / Buddypress plugin to connect the Commons-In-A-Box (CBOX) social network platform to a Fedora-based institutional repository system.
 * Version: 1.0
 * Author: MLA
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// HumCORE: CLI Commands

if ( defined('WP_CLI') && WP_CLI ) {
    require_once dirname( __FILE__ ) . '/ezid-cli.php';
    require_once dirname( __FILE__ ) . '/fedora-cli.php';
    require_once dirname( __FILE__ ) . '/solr-cli.php';
}

/**
 * Register the humcore_deposit custom post type.
 */
function humcore_register_post_type() {

	// Define the labels to be used by the post type humcore_deposits.
	$labels = array(
		// 'name'               => _x( 'HumCORE Deposits', 'post type general name', 'humcore_domain' ),
		'singular_name'      => _x( 'Deposit', 'post type singular name', 'humcore_domain' ),
		'menu_name'          => _x( 'HumCORE Deposits', 'admin menu', 'humcore_domain' ),
		'name_admin_bar'     => _x( 'Deposit', 'add new on admin bar', 'humcore_domain' ),
		'add_new'            => _x( 'Add New', 'add new', 'humcore_domain' ),
		'add_new_item'       => __( 'Add New Deposits', 'humcore_domain' ),
		'new_item'           => __( 'New Deposit', 'humcore_domain' ),
		'edit_item'          => __( 'Edit Deposit', 'humcore_domain' ),
		'view_item'          => __( 'View Deposit', 'humcore_domain' ),
		'all_items'          => __( 'All Deposits', 'humcore_domain' ),
		'search_items'       => __( 'Search Deposits', 'humcore_domain' ),
		'not_found'          => __( 'No Deposits found', 'humcore_domain' ),
		'not_found_in_trash' => __( 'No Deposits found in Trash', 'humcore_domain' ),
		'parent_item_colon'  => '',
	);

	$post_type_args = array(
		'label'              => __( 'HumCORE Deposits', 'humcore_domain' ),
		'labels'             => $labels,
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_admin_bar'  => false,
		'query_var'          => false,
		'rewrite'            => array(
			'slug'           => 'humcore_deposit',
			'with_front'     => true,
		),
		'capability_type'    => 'post',
		'has_archive'        => false,
		'hierarchical'       => true,
		'menu_position'      => null,
		'supports'           => array( 'title', 'author', 'excerpt' ),
		// 'supports'           => array( 'title', 'author', 'excerpt', 'custom-fields', revisions', 'page-attributes' ),
		'register_meta_box_cb' => 'humcore_add_post_type_metabox',
	);

	register_post_type( 'humcore_deposit', $post_type_args );
}
// Hook into the init action and call humcore_register_post_type when init fires.
add_action( 'init', 'humcore_register_post_type' );

/**
 * Create two taxonomies, humcore_deposit_subjects and humcore_deposit_tags for the post type "humcore_deposit".
 */
function humcore_create_taxonomies() {
	// Add new taxonomy, make it hierarchical (like categories).
	$labels = array(
		'name'              => _x( 'Subjects', 'taxonomy general name', 'humcore_domain' ),
		'singular_name'     => _x( 'Subject', 'taxonomy singular name', 'humcore_domain' ),
		'search_items'      => __( 'Search Subjects', 'humcore_domain' ),
		'all_items'         => __( 'All Subjects', 'humcore_domain' ),
		'parent_item'       => __( 'Parent Subject', 'humcore_domain' ),
		'parent_item_colon' => __( 'Parent Subject:', 'humcore_domain' ),
		'edit_item'         => __( 'Edit Subject', 'humcore_domain' ),
		'update_item'       => __( 'Update Subject', 'humcore_domain' ),
		'add_new_item'      => __( 'Add New Subject', 'humcore_domain' ),
		'new_item_name'     => __( 'New Subject Name', 'humcore_domain' ),
		'menu_name'         => __( 'Subjects', 'humcore_domain' ),
	);

	$args = array(
		'public'            => false,
		'hierarchical'      => true,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => false,
		'query_var'         => false,
		'rewrite'           => false,
	);

	register_taxonomy( 'humcore_deposit_subject', array( 'humcore_deposit' ), $args );
	register_taxonomy_for_object_type( 'humcore_deposit_subject', 'humcore_deposit' );

	// Add new taxonomy, NOT hierarchical (like tags).
	$labels = array(
		'name'                       => _x( 'Tags', 'taxonomy general name', 'humcore_domain' ),
		'singular_name'              => _x( 'Tag', 'taxonomy singular name', 'humcore_domain' ),
		'search_items'               => __( 'Search Tags', 'humcore_domain' ),
		'popular_items'              => __( 'Popular Tags', 'humcore_domain' ),
		'all_items'                  => __( 'All Tags', 'humcore_domain' ),
		'parent_item'                => null,
		'parent_item_colon'          => null,
		'edit_item'                  => __( 'Edit Tag', 'humcore_domain' ),
		'update_item'                => __( 'Update Tag', 'humcore_domain' ),
		'add_new_item'               => __( 'Add New Tag', 'humcore_domain' ),
		'new_item_name'              => __( 'New Tag Name', 'humcore_domain' ),
		'separate_items_with_commas' => __( 'Separate tags with commas', 'humcore_domain' ),
		'add_or_remove_items'        => __( 'Add or remove tags', 'humcore_domain' ),
		'choose_from_most_used'      => __( 'Choose from the most used tags', 'humcore_domain' ),
		'not_found'                  => __( 'No tags found.', 'humcore_domain' ),
		'menu_name'                  => __( 'Tags', 'humcore_domain' ),
	);

	$args = array(
		'public'                => false,
		'hierarchical'          => false,
		'labels'                => $labels,
		'show_ui'               => true,
		'show_admin_column'     => false,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => false,
		'rewrite'               => false,
	);

	register_taxonomy( 'humcore_deposit_tag', array( 'humcore_deposit' ), $args );
	register_taxonomy_for_object_type( 'humcore_deposit_tag', 'humcore_deposit' );

}
// Hook into the init action and call humcore_create_taxonomies when init fires.
add_action( 'init', 'humcore_create_taxonomies' );

/**
 * Remove the custom taxonomy meta boxes.
 */
function humcore_remove_meta_boxes() {

	remove_meta_box( 'humcore_deposit_subjectdiv', 'humcore_deposit', 'side' );
	remove_meta_box( 'tagsdiv-humcore_deposit_tag', 'humcore_deposit', 'side' );

}
// Hook into the admin_menu action and call humcore_remove_meta_boxes when admin_menu fires.
add_action( 'admin_menu', 'humcore_remove_meta_boxes' );

/**
 * Register two sidebars for the deposits index and search results pages.
 */
function humcore_register_sidebars() {

	register_sidebar( array(
		'name' => 'Deposits Directory Sidebar',
		'id' => 'deposits-directory-sidebar',
		'description' => __( 'The Deposits directory widget area', 'humcore_domain' ),
		'before_widget' => '',
		'after_widget' => '',
		'before_title' => '',
		'after_title' => '',
	) );

	register_sidebar( array(
		'name' => 'Deposits Search Sidebar',
		'id' => 'deposits-search-sidebar',
		'description' => __( 'The Deposits faceted search widget area', 'humcore_domain' ),
		'before_widget' => '',
		'after_widget' => '',
		'before_title' => '<h4>',
		'after_title' => '</h4>',
	) );

}
// Hook into the init action and call humcore_register_sidebars when init fires.
add_action( 'init', 'humcore_register_sidebars' );

/**
 * Check for cURL upon activation.
 */
function humcore_check_dependencies() {

	if ( ! in_array( 'curl', get_loaded_extensions() ) ) {
		trigger_error( __( 'cURL is not installed on your server. In order to make this plugin work, you need to install cURL on your server.', 'humcore_domain' ), E_USER_ERROR );
	}

}

/**
 * Register post type and flush rewrite rules upon activation.
 */
function humcore_activate() {

	humcore_check_dependencies();

	humcore_register_post_type();
	humcore_add_rewrite_rule();
	global $wp_rewrite;
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'humcore_activate' );

/**
 * Cleanup upon deactivation.
 */
function humcore_deactivate() {

	global $wp_rewrite;
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'humcore_deactivate' );

/**
 * Add custom rewrite rules.
 */
function humcore_add_rewrite_rule() {

	add_rewrite_rule(
		'(deposits/item)/([^/]+)(/(review))?/?$',
		'index.php?pagename=$matches[1]&deposits_item=$matches[2]&deposits_command=$matches[4]',
		'top'
	);

	add_rewrite_rule(
		'(deposits/download)/([^/]+)/([^/]+)/([^/]+)/?$',
		'index.php?pagename=$matches[1]&deposits_item=$matches[2]&deposits_datastream=$matches[3]&deposits_filename=$matches[4]',
		'top'
	);

	add_rewrite_rule(
		'(deposits/view)/([^/]+)/([^/]+)/([^/]+)/?$',
		'index.php?pagename=$matches[1]&deposits_item=$matches[2]&deposits_datastream=$matches[3]&deposits_filename=$matches[4]',
		'top'
	);

}
// Hook into the init action and call humcore_add_rewrite_rule when init fires.
add_action( 'init', 'humcore_add_rewrite_rule' );

/**
 * Add custom rewrite tags.
 */
function humcore_add_rewrite_tag() {

	add_rewrite_tag( '%deposits_item%', '([^/]*)' );
	add_rewrite_tag( '%deposits_datastream%', '([^/]*)' );
	add_rewrite_tag( '%deposits_filename%', '([^/]*)' );
	add_rewrite_tag( '%deposits_command%', '([^/]*)' );
	add_rewrite_tag( '%facets%', '([^&]*)' );

}
// Hook into the init action and call humcore_add_rewrite_tag when init fires.
add_action( 'init', 'humcore_add_rewrite_tag' );

/**
 * Add support for additional mime types.
 */
function humcore_deposit_upload_mimes( $existing_mimes ) {

	$existing_mimes['dng'] = 'image/x-adobe-dng';

	$existing_mimes['cr2'] = 'image/x-dcraw';
	$existing_mimes['crw'] = 'image/x-dcraw';
	$existing_mimes['nef'] = 'image/x-dcraw';

	return $existing_mimes;
}
// Hook into the mime_types filter and call humcore_deposit_upload_mimes when mime_types fires.
/**
 * Disabled for now.
 * add_filter( 'mime_types', 'humcore_deposit_upload_mimes' );
 * TODO We could support storage of raw images without any additional config. We would need imagick to support conversion.
 */

/**
 * Initialize the API classes.
 */
function humcore_deposit_api_classes_init() {

	global $ezid_api, $fedora_api, $solr_client;

	// Create an ezid client instance.
	require_once dirname( __FILE__ ) . '/ezid-api.php';
	$ezid_api = new Humcore_Deposit_Ezid_Api;

	// Create a fedora client instance.
	require_once dirname( __FILE__ ) . '/fedora-api.php';
	$fedora_api = new Humcore_Deposit_Fedora_Api;

	// Create a solr client instance.
	require_once dirname( __FILE__ ) . '/solr-api.php';
	$solr_client = new Humcore_Deposit_Solr_Api;

}
// Hook into the plugins_loaded action and call humcore_deposit_api_classes_init when plugins_loaded fires.
add_action( 'plugins_loaded', 'humcore_deposit_api_classes_init' );

/**
 * Add custom user meta field.
 */
function humcore_deposit_show_user_fields( $user ) {
?>
        <h3>CORE Details</h3>

        <table class="form-table">
            <tbody>
        <tr>
            <th><label>Accepted Terms</label></th>
            <td><?php if ( get_the_author_meta( 'accepted_core_terms', $user->ID ) == 'Yes' ) { echo 'Yes'; } else { echo 'No'; } ?></td>
        </tr>
            </tbody>
        </table>
<?php

}
// Hook into the show_user_profile, edit_user_profile actions and call humcore_deposit_show_user_fields when they fire.
add_action( 'show_user_profile', 'humcore_deposit_show_user_fields' );
add_action( 'edit_user_profile', 'humcore_deposit_show_user_fields' );

/**
 * Load only when BuddyPress is present.
 */
function humcore_deposit_component_include() {

	require( dirname( __FILE__ ) . '/component-loader.php' );

}
// Hook into the bp_include action and call humcore_deposit_component_include when bp_include fires.
add_action( 'bp_include', 'humcore_deposit_component_include' );

/**
 * Include the faceted search results widgets.
 */
require( dirname( __FILE__ ) . '/widgets.php' );

// Include the settings page and custom post admin screen.
if ( is_admin() ) {
	require( dirname( __FILE__ ) . '/settings.php' );
	require( dirname( __FILE__ ) . '/admin-screens.php' );
}

/**
 * Writes wp http data to a custom log file if debugging is active.
 */
function humcore_http_api_debug( $response = null, $state = null, $class = null, $args = null, $url = null ) {

	if ( stripos( $url, 'wordpress.' ) !== false || stripos( $url, 'akismet.' ) !== false ||
			stripos( $url, 'commons.' ) !== false ) {
		return;
	}

	$info = array(
		'state'           => $state,
		'transport_class' => $class,
		'args'            => $args,
		'url'             => $url,
		'response'        => $response,
	);
	ini_set( 'log_errors_max_len', '0' );
	error_log( '[' . date( 'd-M-Y H:i:s T' ) . '] http api debug: ' . var_export( $info, true ), 3, HUMCORE_DEBUG_LOG );

}

/**
 * Conditionally activate the http debug action.
 */
function humcore_http_api_debug_action() {

	if ( defined( 'HUMCORE_DEBUG' ) && HUMCORE_DEBUG && defined( 'HUMCORE_DEBUG_LOG' ) && '' != HUMCORE_DEBUG_LOG ) {
		// Hook into the http_api_debug action and call humcore_http_api_debug when http_api_debug fires.
		add_action( 'http_api_debug', 'humcore_http_api_debug', 1000, 5 );
	}
}
// Hook into the init action and call humcore_http_api_debug_action when init fires.
add_action( 'init', 'humcore_http_api_debug_action' );
