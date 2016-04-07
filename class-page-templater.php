<?php
/*
A useful bit of code from Harri Bell-Thomas you can use to dynamically create WordPress Page Templates with PHP.
*/

class PageTemplater {

        /**
         * A Unique Identifier
         */
        protected $plugin_slug;

        /**
         * A reference to an instance of this class.
         */
        private static $instance;

        /**
         * The array of templates that this plugin tracks.
         */
        protected $templates;


        /**
         * Returns an instance of this class. 
         */
        public static function get_instance() {

                if( null == self::$instance ) {
                        self::$instance = new PageTemplater();
                } 

                return self::$instance;

        } 

        /**
         * Initializes the plugin by setting filters and administration functions.
         */
        private function __construct() {

                $this->templates = array();


                // Add a filter to the attributes metabox to inject template into the cache.
                add_filter(
					'page_attributes_dropdown_pages_args',
					 array( $this, 'register_project_templates' ) 
				);


                // Add a filter to the Quick Edit dropdown menu to inject template into the cache.
                add_filter(
					'quick_edit_dropdown_pages_args',
					 array( $this, 'register_project_templates' ) 
				);


                // Add a filter to the save post to inject out template into the page cache
                add_filter(
					'wp_insert_post_data', 
					array( $this, 'register_project_templates' ) 
				);


                // Add a filter to the template include to determine if the page has our 
				// template assigned and return it's path
                add_filter(
					'template_include', 
					array( $this, 'view_project_template') 
				);


                // Add your templates to this array.
                $this->templates = array(
                        'acceptance.php'     => 'HumCORE Terms Acceptance',
                        'deposit.php'        => 'HumCORE Deposit',
                        'faq.php'            => 'HumCORE FAQ',
                        'welcome.php'        => 'HumCORE Welcome',
                );
				
        } 


        /**
         * Adds our template to the pages cache in order to trick WordPress
         * into thinking the template file exists where it doens't really exist.
         *
         */

        public function register_project_templates( $atts ) {

		// Get theme object
		$theme = wp_get_theme();

                // Create the key used for the themes cache
                $cache_key = 'page_templates-' . md5( $theme->get_theme_root() . '/' . $theme->get_stylesheet() );

                // Retrieve existing page templates
		$templates = $theme->get_page_templates();

                // Add our template(s) to the list of existing templates by merging the arrays
                $templates = array_merge( $templates, $this->templates );

                // Replace existing value in cache
                wp_cache_set( $cache_key, $templates, 'themes', 1800 );

                return $atts;

        } 

        /**
         * Checks if the template is assigned to the page
         */
        public function view_project_template( $template ) {

                global $post;

                if (!isset($this->templates[get_post_meta( 
					$post->ID, '_wp_page_template', true 
				)] ) ) {
					
                        return $template;
						
                } 

                $file = plugin_dir_path(__FILE__) . 'templates/deposits/' . get_post_meta( 
					$post->ID, '_wp_page_template', true 
				);
				
                // Just to be safe, we check if the file exist first
                if( file_exists( $file ) ) {
                        return $file;
                } 
				else { echo $file; }

                return $template;

        } 


} 

add_action( 'init', array( 'PageTemplater', 'get_instance' ) );
