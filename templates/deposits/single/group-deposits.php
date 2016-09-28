<?php

/**
 * BuddyPress - Users Plugins
 *
 * This is a fallback file that external plugins can use if the template they
 * need is not installed in the current theme. Use the actions in this template
 * to output everything your plugin needs.
 *
 * @package BuddyPress
 * @subpackage bp-default
 */

?>

<?php Humcore_Theme_Compatibility::get_header(); ?>

	<div class="page-right-sidebar group-single">
	<div id="buddypress">

		<?php if ( bp_has_groups( 'max=1' ) ) : while ( bp_groups() ) : bp_the_group(); ?>

			<div id="item-header">

				<?php bp_locate_template( array( 'groups/single/group-header.php' ), true ); ?>

			</div><!-- #item-header -->

	<div id="primary" class="site-content">
	<div id="content">

		<?php do_action( 'bp_before_group_deposits_content' ); ?>

                        <div class="full-width">
                        <div id="item-main-content">
                        <div id="item-nav">
                                <div id="object-nav" class="item-list-tabs no-ajax" role="navigation">
                                        <ul>
                                                <?php //bp_get_displayed_user_nav(); ?>
                                                <?php bp_get_options_nav(); ?>
                                                <?php //do_action( 'bp_group_deposits_options_nav' ); ?>
                                        </ul>
                                </div>
                        </div><!-- #item-nav -->

			<div id="item-body" role="main">

				<?php do_action( 'bp_before_group_deposits_body' ); ?>

				<?php

				$displayed_user_fullname = bp_get_displayed_user_fullname();

				if ( is_user_logged_in() && 'public' === bp_get_group_status() ) {
					echo '<a href="/deposits/item/new/" class="bp-deposits-deposit button" title="Deposit an Item" style="float: right;">Deposit an Item</a><p />';
				} ?>

				<div class="item-list-tabs no-ajax" id="subnav">
					<ul>
					<li class="current selected" id="deposits-groups"><h3>Group Deposits</h3></li>

					<li id="deposits-order-select" class="last filter">

						<label for="deposits-order-by"><?php _e( 'Order By:', 'humcore_domain' ); ?></label>
						<select id="deposits-order-by">
							<option value="date"><?php _e( 'Newest Deposits', 'humcore_domain' ); ?></option>
							<!-- <option value="author"><?php _e( 'Primary Author', 'humcore_domain' ); ?></option> -->
							<option value="title"><?php _e( 'Title', 'humcore_domain' ); ?></option>

							<?php do_action( 'humcore_deposits_directory_order_options' ); ?>

						</select>
					</li>

					</ul>
				</div><!-- .item-list-tabs -->

				<h3><?php do_action( 'bp_template_title' ); ?></h3>
				<div id="deposits-dir-list" class="deposits dir-list" style="display: block;">

				<?php do_action( 'bp_template_content' ); ?>

				</div>

				<?php do_action( 'bp_after_group_deposits_body' ); ?>

			</div><!-- #item-body -->
			</div><!-- .item-main-content -->
			</div><!-- #full-width -->

		<?php do_action( 'bp_after_group_deposits_content' ); ?>
		<?php endwhile; endif; ?>

	</div><!-- #content -->
	</div><!-- #primary -->

<?php Humcore_Theme_Compatibility::get_sidebar( 'buddypress' ); ?>

	</div><!-- #buddypress -->
	</div><!-- .page-right-sidebar -->

<?php Humcore_Theme_Compatibility::get_footer(); ?>
