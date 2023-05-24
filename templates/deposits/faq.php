<!DOCTYPE html>
<?php
/**
 * Template Name: HumCORE FAQ
 */

	wp_head();
?>
<div class="wp-site-blocks">
<header class="wp-block-template-part site-header">
<?php block_header_area(); ?>
</header>
	<div class="page-full-width">
	<div id="primary" class="site-content">
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
	</div><!-- #content -->
	</div><!-- #primary -->

	</div><!-- .page-full-width -->

	<footer class="wp-block-template-part site-footer">
<?php block_footer_area(); ?>
</footer>
</div>
<?php
	wp_footer();
?>