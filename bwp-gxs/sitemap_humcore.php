<?php

/*
 * Add support for HumCORE to the Better WordPress Google XML Sitemaps plugin
 */

class BWP_GXS_MODULE_SITEMAP_HUMCORE extends BWP_GXS_MODULE {

	public function __construct() {
                $this->type = 'url';
		$this->perma_struct = get_option('permalink_structure');
	}

	protected function init_module_properties() {
		$this->post_type = get_post_type_object($this->requested);
	}

	/**
	 * This is the main function that generates our data.
	 *
	 * Since we are dealing with heavy queries here, it's better that you use
	 * generate_data() which will get called by build_data(). This way you will
	 * query for no more than the SQL limit configurable in this plugin's
	 * option page. If you happen to use LIMIT in your SQL statement for other
	 * reasons then use build_data() instead.
	 */
	protected function generate_data() {

		global $wpdb, $post;

		$deposits_post_query = "
			SELECT *
			FROM " . $wpdb->posts . "
			WHERE post_status = 'publish'
				AND post_type = 'humcore_deposit'
				AND post_parent = 0
			ORDER BY post_modified DESC";

		// Use $this->get_results instead of $wpdb->get_results.
		$deposits_posts = $this->get_results( $deposits_post_query );

		if ( ! isset( $deposits_posts ) || 0 == sizeof( $deposits_posts ) ) {
			return false;
		}

		$data = array();

		for ( $i = 0; $i < sizeof( $deposits_posts ); $i++ ) {
			$post = $deposits_posts[$i];
			$post_metadata = json_decode( get_post_meta( $post->ID, '_deposit_metadata', true ), true );

			$data = $this->init_data( $data );

			// We cannot use the WP get_permalink function.
			$data['location'] = sprintf( '%1$s/deposits/item/%2$s/', bp_get_root_domain(), $post_metadata['pid'] );
			$data['lastmod']  = $this->get_lastmod( $post );
			$data['freq']     = $this->cal_frequency( $post );
			$data['priority'] = $this->cal_priority( $post, $data['freq'] );
			$this->data[] = $data;
		}

		unset( $deposits_posts );

		return true;
	}
}
