<?php
/**
 * Template Name: HumCORE Deposits Directory
 */

get_header( 'buddypress' ); ?>

<?php do_action( 'bp_before_directory_deposits_page' ); ?>

	<div id="content" role="main" class="<?php do_action( 'content_class' ); ?>">
		<div class="padder">

		<?php do_action( 'bp_before_directory_deposits' ); ?>

		<form action="" method="post" id="deposits-directory-form" class="dir-form">

			<h3><?php _e( 'CORE Deposits Directory ', 'humcore_domain' ); ?>
			<?php do_action( 'bp_before_directory_deposits_content' ); ?></h3>

			<div id="deposits-dir-search" class="dir-search" role="search">

				<?php humcore_deposits_search_form(); ?>

			</div><!-- #deposits-dir-search -->

			<div class="item-list-tabs main-tabs" role="navigation">
				<ul>
					<li class="selected" id="deposits-all"><a href="<?php echo esc_attr( trailingslashit( bp_get_root_domain() . '/' . 'deposits' ) ); ?>"><?php printf( __( 'All Deposits <span>%s</span>', 'humcore_domain' ), humcore_get_deposit_count() ); ?></a></li>

					<?php do_action( 'humcore_deposits_directory_deposit_types' ); ?>

				</ul>
			</div><!-- .item-list-tabs -->

			<div class="item-list-tabs" id="subnav" role="navigation">
				<ul>

					<?php do_action( 'humcore_deposits_directory_deposit_sub_types' ); ?>

					<li id="deposits-order-select" class="last filter">

						<label for="deposits-order-by"><?php _e( 'Order By:', 'humcore_domain' ); ?></label>
						<select id="deposits-order-by">
							<option value="date"><?php _e( 'Newest Deposits', 'humcore_domain' ); ?></option>
							<option value="author"><?php _e( 'Primary Author', 'humcore_domain' ); ?></option>
							<option value="title"><?php _e( 'Title', 'humcore_domain' ); ?></option>

							<?php do_action( 'humcore_deposits_directory_order_options' ); ?>

						</select>
					</li>
				</ul>
			</div>

			<div id="deposits-dir-list" class="deposits dir-list">

			<?php locate_template( array( 'deposits/deposits-loop.php' ), true ); ?>

			</div><!-- #deposits-dir-list -->

			<?php do_action( 'bp_directory_deposits_content' ); ?>

			<?php wp_nonce_field( 'directory_deposits', '_wpnonce-deposit-filter' ); ?>

			<?php do_action( 'bp_after_directory_deposits_content' ); ?>

		</form><!-- #deposits-directory-form -->

		<?php do_action( 'bp_after_directory_deposits' ); ?>

		</div><!-- .padder -->
	</div><!-- #content -->

	<?php dynamic_sidebar( 'deposits-directory-sidebar' ); ?>

	<?php do_action( 'bp_after_directory_deposits_page' ); ?>

<?php get_footer( 'buddypress' ); ?>
