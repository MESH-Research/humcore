<?php
/**
 * Template Name: HumCORE Welcome
 */
        if ( is_user_logged_in() ) { 
		$user_id = bp_loggedin_user_id();
		$core_acceptance = get_the_author_meta( 'accepted_core_terms', $user_id );
		if ( 'Yes' == $core_acceptance ) {
			wp_redirect( '/deposits/' );
			exit();
		}
        }

	get_header( 'buddypress' );
?>
	<div id="content" role="main" class="<?php do_action( 'content_class' ); ?>">
		<?php
			do_action( 'bp_before_deposits_page_content' );
			do_action( 'bp_before_deposits_page' );
		?>	
		<?php
			bp_get_template_part( 'deposits/page', 'content' );
		?>	
		<?php
			do_action( 'bp_after_deposits_page' );
			do_action( 'bp_after_deposits_page_content' );
		?>
	</div>
<?php
	get_footer( 'buddypress' );
?>
