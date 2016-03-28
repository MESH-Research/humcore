<?php
/*
 * Let's have it both ways.
 */

class Humcore_Theme_Compatibility {

        /**
         * We'll need to call get_header unless we are using a certain theme.
         *
         */
        public function get_header() {

		// Get theme object
		$theme = wp_get_theme();
		if ( 'levitin' === $theme->get_stylesheet() ) {
			return;
		} else {
			get_header( 'buddypress' );
			return;
		}

        } 

        /**
         * We'll need to call get_footer unless we are using a certain theme.
         *
         */
        public function get_footer() {

		// Get theme object
		$theme = wp_get_theme();
		if ( 'levitin' === $theme->get_stylesheet() ) {
			return;
		} else {
			get_footer( 'buddypress' );
			return;
		}

        } 

} 
