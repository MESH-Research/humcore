<!DOCTYPE html>
<?php
/**
 * Template Name: HumCORE Welcome
 */

	wp_head();
//get_header();
?>
<div class="wp-site-blocks">
<header class="wp-block-template-part site-header">
<?php block_header_area(); ?>
</header>
<!-- Frontpage Slider -->
<?php get_template_part( 'content', 'slides' ); ?>

<?php do_action( 'core_custom_slider' ); ?>

<?php if ( is_active_sidebar( 'core-welcome-right' ) ) : ?>
		<div class="page-right-sidebar">
		<?php else : ?>
				<div class="page-full-width">
				<?php endif; ?>

				<div id="primary" class="site-content">

						<div id="content" role="main">

								<?php
								while ( have_posts() ) :
									the_post();
?>

										<?php if ( is_home() ) : ?>
												<?php get_template_part( 'content' ); ?>
										<?php else : ?>
												<?php // get_template_part( 'content', 'only' ); ?>
												<?php the_content(); ?></div>
										<?php endif; ?>

										<?php comments_template( '', true ); ?>

								<?php endwhile; // end of the loop. ?>

				<div class="pagination-below">
				</div>

						</div><!-- #content -->
				</div><!-- #primary -->

				<?php
				if ( is_active_sidebar( 'core-welcome-right' ) ) :
				?>
			<div id="secondary" class="widget-area" role="complementary">
			<aside id="core-welcome-right" role="complementary">
			<?php dynamic_sidebar( 'core-welcome-right' ); ?>
			</aside>
			</div><!-- #secondary -->
				<?php
				endif;
				?>


		</div><!-- .page-right-sidebar -->


<footer class="wp-block-template-part site-footer">
<?php block_footer_area(); ?>
</footer>
</div>
<?php
	wp_footer();
?>
