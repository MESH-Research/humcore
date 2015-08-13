<?php

/**
 * BuddyPress - New
 *
 */

?>

<?php get_header( 'buddypress' ); ?>

	<div id="content">
		<div class="padder">

			<?php do_action( 'bp_before_deposit_plugin_template' ); ?>

			<div id="item-header">

				<?php locate_template( array( 'deposits/single/deposit-header.php' ), true ); ?>

			</div><!-- #item-header -->

			<div id="item-nav">
				<div class="item-list-tabs no-ajax" id="object-nav" role="navigation">
					<ul>

						<?php bp_get_displayed_user_nav(); ?>

						<?php do_action( 'humcore_deposit_options_nav' ); ?>

					</ul>
				</div>
			</div><!-- #item-nav -->

			<div id="item-body" role="main">

				<?php do_action( 'bp_before_deposit_body' ); ?>

<!--				<div class="item-list-tabs no-ajax" id="subnav">
					<ul>

						<?php bp_get_options_nav(); ?>

						<?php do_action( 'humcore_deposit_plugin_options_nav' ); ?>

					</ul>
				</div>--><!-- .item-list-tabs -->

				<h3><?php do_action( 'bp_template_title' ); ?></h3>

				<?php do_action( 'bp_template_content' ); ?>

				<?php do_action( 'bp_after_deposit_body' ); ?>

			</div><!-- #item-body -->

			<?php do_action( 'bp_after_deposit_plugin_template' ); ?>

		</div><!-- .padder -->
	</div><!-- #content -->

<?php //get_sidebar( 'buddypress' ); ?>
<?php get_footer( 'buddypress' ); ?>
