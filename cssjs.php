<?php
/**
 * Plugin css and js support.
 *
 * @package HumCORE
 * @subpackage Deposits
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the plugin css and js files.
 */
function humcore_deposits_front_cssjs() {

	wp_register_style( 'humcore_deposits_css', plugins_url( 'css/deposits.css', __FILE__ ), '', '122115' );
	wp_enqueue_style( 'humcore_deposits_css' );

	if ( humcore_is_deposit_new_page() ) {
		wp_enqueue_script( 'plupload', array( 'jquery' ) );

		wp_register_script( 'humcore_deposits_js', plugins_url( 'js/deposits.js', __FILE__ ), array( 'jquery' ), '010516', true );
		wp_enqueue_script( 'humcore_deposits_js' );

		wp_register_script( 'humcore_deposits_select2_js', plugins_url( 'select2/dist/js/select2.min.js', __FILE__ ), array( 'jquery' ), '060215', true );
		wp_enqueue_script( 'humcore_deposits_select2_js' );
		wp_register_style( 'humcore_deposits_select2_css', plugins_url( 'select2/dist/css/select2.min.css', __FILE__ ), '', '060215' );
		wp_enqueue_style( 'humcore_deposits_select2_css' );
	} else {
		wp_register_script( 'humcore_search_js', plugins_url( 'js/search.js', __FILE__ ), array( 'jquery' ), '043015', true );
		wp_enqueue_script( 'humcore_search_js' );
	}

}
add_action( 'wp_enqueue_scripts', 'humcore_deposits_front_cssjs' );
