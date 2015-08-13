// Facet widget button control
jQuery( document ).ready( function( $ ) {

	$( '.widget_deposits_search_facets_widget .facet-display-button' ).on('click', function() {

		if ( $( this ).find( '.show-more' ).length ) {
			if ( $( this ).siblings( 'li:hidden' ).length > 10 ) {
				$( this ).parent().css( 'height', '625px' );
				$( this ).parent().css( 'overflow-y', 'scroll' );
				$( this ).siblings( 'li:hidden:lt(11)' ).show();
			} else {
				$( this ).siblings( 'li:hidden' ).show();
				$( this ).html( '<span class="show-less button white right">less>></span>' );
			}
		} else {
			$( this ).parent().css( 'height', 'auto' );
			$( this ).parent().css( 'overflow-y', 'hidden' );
			$( this ).siblings( 'li:gt(1)' ).hide();
			$( this ).html( '<span class="show-more button white right">more>></span>' );
		}

	} );

	$( '.widget_deposits_directory_sidebar_widget .facet-display-button' ).on('click', function() {

		if ( $( this ).find( '.show-more' ).length ) {
			if ( $( this ).siblings( 'li:hidden' ).length > 10 ) {
				$( this ).siblings( 'li:hidden:lt(11)' ).show();
			} else {
				$( this ).siblings( 'li:hidden' ).show();
				$( this ).html( '<span class="show-less button white right">less>></span>' );
			}
		} else {
			$( this ).siblings( 'li:gt(3)' ).hide();
			$( this ).html( '<span class="show-more button white right">more>></span>' );
		}

	} );

	$( 'a.facet-search-link' ).on('click', function() {
		var current_url = $( this ).attr( 'href' ).split( '?' );
		var facet_value = current_url[1];
		var cookie_value = $.cookie( 'bp-deposits-extras' );
		var search_term = $( '#search-deposits-term' ).val().trim();
		if ( !$( this ).find( 'span.facet-list-item-control ' ).length ) {
	 		$.cookie( 'bp-deposits-extras', facet_value, { path : '/' } );
		} else if ( $( this ).find( 'span.facet-list-item-control.selected' ).length ) {
			var combined_matches = cookie_value.replace( facet_value, '' ).replace( /\&\&/, '&' ).replace( /^\&|\&$/, '' );
 			$.cookie( 'bp-deposits-extras', combined_matches, { path : '/' } );
 			$( this ).siblings( 'span.count' ).show();
 			$( this ).find( 'span.facet-list-item-control' ).attr( 'style', 'display: none !important' );
 			if ( combined_matches.trim() ) {
				if ( search_term ) {
		  			var combined_matches = combined_matches.concat( '&', 's=' + search_term );
		  		}
	 			$( this ).attr( 'href', current_url[0] + '?' + combined_matches );
	 		} else {
				if ( search_term ) {
		  			var combined_matches = combined_matches.concat( '&', 's=' + search_term );
		 			$( this ).attr( 'href', current_url[0] + '?' + search_term );
		  		} else {
		 			$( this ).attr( 'href', current_url[0] );
		 		}
	 		}
	 	} else {
			if ( cookie_value.trim() ) {
		 		var combined_matches = facet_value.concat( '&', cookie_value );
			} else {
	 			var combined_matches = facet_value;
	 		}
			if ( search_term.trim() ) {
	  			var combined_matches = combined_matches.concat( '&', 's=' + search_term );
	  		}
	 		$.cookie( 'bp-deposits-extras', combined_matches, { path : '/' } );
	 		$( this ).attr( 'href', current_url[0] + '?' + combined_matches );
 		}

	} );

	$( '.search-page div.dir-search' ).off( 'click' );
	$( '.search-page div.dir-search' ).on( 'click', function() {
		var target = $( event.target );

		if ( target.attr( 'type' ) == 'submit' ) {
			var cookie_value = $.cookie( 'bp-deposits-extras' );
			if ( cookie_value != null ) {
				$( '#search-deposits-facets' ).val( cookie_value );
			}
			$( '#search-deposits-form' ).submit();
		}

	} );

	$('form#core-terms-acceptance-form input[type=submit][name=core_accept_terms_continue]').on('click', function(){
		if ( $('form#core-terms-acceptance-form input[type=checkbox][name=core_accept_terms]').is(':checked') ) {
			$('#core-terms-acceptance-form').submit();
		} else {
			alert('Please agree to the terms by checking the box next to "I agree".');
		}
	});

} );