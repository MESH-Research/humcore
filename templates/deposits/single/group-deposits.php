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

<?php get_header( 'buddypress' ); ?>

<?php get_sidebar( 'buddypress' ); ?>

	<div id="content">
		<div class="padder">

		<?php if ( bp_has_groups( 'max=1' ) ) : while ( bp_groups() ) : bp_the_group(); ?>

		<?php do_action( 'bp_before_group_deposits_content' ); ?>


			<div id="item-header">

				<?php locate_template( array( 'groups/single/group-header.php' ), true ); ?>

			</div><!-- #item-header -->

			<div id="item-nav">
				<div class="item-list-tabs no-ajax" id="object-nav" role="navigation">
					<ul>

						<?php bp_get_displayed_user_nav(); ?>

						<?php do_action( 'bp_group_deposits_options_nav' ); ?>

					</ul>
				</div>
			</div><!-- #item-nav -->

			<div id="item-body" role="main">

				<?php do_action( 'bp_before_group_deposits_body' ); ?>

				<?php

				$displayed_user_fullname = bp_get_displayed_user_fullname();

				if ( is_user_logged_in() ) {
					echo '<a href="/deposits/item/new/" class="bp-deposits-deposit button" title="Deposit an Item">Deposit an Item</a><p />';
				} ?>

				<div class="item-list-tabs no-ajax" id="subnav">
					<ul>
					<li class="current selected" id="deposits-groups"><a href="#"></a></li>

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

		<?php do_action( 'bp_after_group_deposits_content' ); ?>
		<?php endwhile; endif; ?>

		</div><!-- .padder -->
	</div><!-- #content -->

<?php //get_sidebar( 'buddypress' ); ?>
<?php get_footer( 'buddypress' ); ?>
