<?php
/**
 * Template Name: HumCORE Deposits Directory
 */

Humcore_Theme_Compatibility::get_header(); ?>

<?php do_action( 'bp_before_directory_deposits_page' ); ?>

        <div class="page-right-sidebar">
	<div id="primary" class="site-content">
	<div id="content" role="main" class="<?php do_action( 'content_class' ); ?>">
		<div id="buddypress">

		<?php do_action( 'bp_before_directory_deposits' ); ?>

		<header class="deposits-header page-header">
		<h3 class="entry-title main-title"><?php printf( __( '%sCORE%s Deposits', 'humcore_domain' ), '<em>', '</em>' ); ?>
		<?php do_action( 'bp_before_directory_deposits_content' ); ?></h3>
		</header>

    <div class="filters">
        <div class="row">
            <div class="col-6">
                <div class="item-list-tabs" role="navigation">
                    <ul>
                        <?php do_action( 'humcore_deposits_directory_deposit_sub_types' ); ?>
                        <li id="deposits-order-select" class="filter">

                            <label for="deposits-order-by"><?php _e( 'Order By:', 'humcore_domain' ); ?></label>

                            <select id="deposits-order-by">
                                <option value="newest"><?php _e( 'Newest Deposits', 'humcore_domain' ); ?></option>
                                <option value="alphabetical"><?php _e( 'Title', 'humcore_domain' ); ?></option>

                                <?php do_action( 'humcore_deposits_directory_order_options' ); ?>
                            </select>
                        </li>
                    </ul>
                </div><!-- .item-list-tabs -->
            </div><!-- .col-6 -->
            <div class="col-6">
                <div id="deposits-dir-search" class="dir-search" role="search">
                    <?php humcore_deposits_search_form(); ?>
                </div><!-- #deposits-dir-search -->
            </div><!-- .col-6 -->
        </div><!-- .row -->
    </div><!-- .filters -->

		<form action="" method="post" id="deposits-directory-form" class="dir-form">

			<div class="item-list-tabs main-tabs" role="navigation">
				<ul>
					<li class="selected" id="deposits-all"><a href="<?php echo esc_attr( trailingslashit( bp_get_root_domain() . '/' . 'deposits' ) ); ?>"><?php printf( __( 'All Deposits <span>%s</span>', 'humcore_domain' ), humcore_get_deposit_count() ); ?></a></li>

					<?php do_action( 'humcore_deposits_directory_deposit_types' ); ?>

				</ul>
			</div><!-- .item-list-tabs -->


			<div id="deposits-dir-list" class="deposits dir-list">

			<?php bp_locate_template( array( 'deposits/deposits-loop.php' ), true ); ?>

			</div><!-- #deposits-dir-list -->

			<?php do_action( 'bp_directory_deposits_content' ); ?>

			<?php wp_nonce_field( 'directory_deposits', '_wpnonce-deposit-filter' ); ?>

			<?php do_action( 'bp_after_directory_deposits_content' ); ?>

		</form><!-- #deposits-directory-form -->

		<?php do_action( 'bp_after_directory_deposits' ); ?>

		</div><!-- #buddypress -->
	</div><!-- #content -->
	</div><!-- #primary -->

	<div id="secondary" class="widget-area" role="complementary">
	<aside id="deposits-sidebar" role="complementary">
	<?php dynamic_sidebar( 'deposits-directory-sidebar' ); ?>
	</aside>
	</div><!-- #secondary -->
	</div><!-- .page-right-sidebar -->
	<?php do_action( 'bp_after_directory_deposits_page' ); ?>

<?php Humcore_Theme_Compatibility::get_footer(); ?>
