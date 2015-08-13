<?php
/**
 * Template Name: HumCORE Terms Acceptance
 */
if ( ! empty( $_POST ) ) {
	if ( ! is_user_logged_in() ) { auth_redirect(); }
	$wp_nonce = $_POST['accept_core_terms_nonce'];
	if ( wp_verify_nonce( $wp_nonce, 'accept_core_terms' ) ) {
		$core_accept_terms = $_POST['core_accept_terms'];
		if ( ! empty( $core_accept_terms ) ) {
			$user_id = bp_loggedin_user_id();
			update_user_meta( $user_id, 'accepted_core_terms', $core_accept_terms);
			wp_redirect( '/deposits/item/new/' );
		}
	}
}
	infinity_get_header();
?>
	<div id="content" role="main" class="<?php do_action( 'content_class' ); ?>">
		<?php
			do_action( 'open_content' );
			do_action( 'open_page' );
		?>	
		<?php
			infinity_get_template_part( 'templates/loops/loop', 'page' );
		?>	
<div id="core-terms-entry-form">
<form id="core-terms-acceptance-form" class="standard-form" method="post" action="">
	<?php wp_nonce_field( 'accept_core_terms', 'accept_core_terms_nonce' ); ?>
	<div id="core-terms-entry" class="entry">
		<input type="checkbox" id="core-accept-terms" name="core_accept_terms" value="Yes" />
		<span class="description"><strong>I agree</strong></span> &nbsp; &nbsp; &nbsp; 
		<input id="core-accept-terms-continue" name="core_accept_terms_continue" class="button-large" type="submit" value="Continue" /> &nbsp; &nbsp; &nbsp; 
		<a href="/core/" id="core-accept-terms-cancel" class="button button-large">Cancel</a>
	</div>
</form>
</div>
		<?php
			do_action( 'close_page' );
			do_action( 'close_content' );
		?>
	</div>
<?php
	infinity_get_footer();
?>
