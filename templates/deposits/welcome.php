<?php
/**
 * Template Name: HumCORE Welcome
 */
        if ( is_user_logged_in() ) { 
		$user_id = bp_loggedin_user_id();
		$core_acceptance = get_the_author_meta( 'accepted_core_terms', $user_id );
		if ( 'Yes' == $core_acceptance ) {
			wp_redirect( '/deposits/' );
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
		<?php
			do_action( 'close_page' );
			do_action( 'close_content' );
		?>
	</div>
<?php
	infinity_get_footer();
?>
