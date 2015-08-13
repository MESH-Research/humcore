<?php
/**
 * Template Name: HumCORE Search Results
 */

get_header( 'buddypress' ); ?>

<?php do_action( 'bp_before_deposits_results_page' ); ?>

	<div id="content" role="main" class="<?php do_action( 'content_class' ); ?>">
		<div class="padder">

		<?php do_action( 'bp_before_deposits_results' ); ?>

		<form action="" method="post" id="deposits-directory-form" class="dir-form">

			<h3><?php _e( 'CORE Search Results ', 'humcore_domain' ); ?></h3>
			<!-- <?php do_action( 'bp_before_deposits_results_content' ); ?> -->

			<div id="deposits-dir-search" class="dir-search" role="search">

				<?php humcore_deposits_search_form(); ?>

			</div><!-- #deposits-dir-search -->

			<div class="item-list-tabs" id="subnav" role="navigation">
		        <div id="search-again-link"><a href="/deposits/" class="button">Start Search Over</a></div>
				<ul id="search-results-header">

					<?php do_action( 'humcore_deposits_results_deposit_sub_types' ); ?>

					<li id="deposits-order-select" class="last filter">

						<span><?php _e( 'Order By:', 'humcore_domain' ); ?>
						<select id="deposits-order-by">
							<option value="date"><?php _e( 'Newest Deposits', 'humcore_domain' ); ?></option>
							<option value="author"><?php _e( 'Primary Author', 'humcore_domain' ); ?></option>
							<option value="title"><?php _e( 'Title', 'humcore_domain' ); ?></option>

							<?php do_action( 'humcore_deposits_results_order_options' ); ?>

						</select>
					</span></li>
				</ul>
			</div>

			<div id="deposits-dir-list" class="deposits dir-list">

			<?php locate_template( array( 'deposits/deposits-loop.php' ), true ); ?>

			</div><!-- #deposits-dir-list -->

			<?php do_action( 'humcore_deposits_results_content' ); ?>

			<?php wp_nonce_field( 'directory_deposits', '_wpnonce-deposit-filter' ); ?>

			<?php do_action( 'bp_after_deposits_results_content' ); ?>

		</form><!-- #deposits-directory-form -->

		<?php do_action( 'bp_after_deposits_results' ); ?>

		</div><!-- .padder -->
	</div><!-- #content -->

<?php dynamic_sidebar( 'deposits-search-sidebar' ); ?>

<?php do_action( 'bp_after_deposits_results_page' ); ?>

<?php get_footer( 'buddypress' ); ?>
