<?php
/**
 * Template functions and deposits search results class.
 *
 * @package HumCORE
 * @subpackage Deposits
 */

/**
 * Set the ajax query string for deposits.
 */
function humcore_ajax_querystring_filter( $query ) {

	if ( ! empty( $_POST['action'] ) ) {
		if ( 'deposits_filter' == $_POST['action'] ) {

			$search_params = array();
			$search_field_cookie = $_COOKIE['bp-deposits-field'];
			if ( ! empty( $search_field_cookie ) and 'all' !== $search_field_cookie ) {
				$search_field = $search_field_cookie;
			} else {
				$search_field = 's';
			}
			if ( false != $_POST['search_terms'] && ! empty( $_POST['search_terms'] ) ) {
				$search_params[] = $search_field . '=' . $_POST['search_terms'];
			}
			if ( ! empty( $_POST['extras'] ) && 'undefined' !== $_POST['extras'] ) {
				$search_params[] = $_POST['extras'];
			}
			if ( ! empty( $_POST['page'] ) ) {
				$search_params[] = 'page' . '=' . $_POST['page'];
			}
			if ( ! empty( $_POST['filter'] ) && 'undefined' !== $_POST['filter'] ) {
				$search_params[] = 'sort' . '=' . $_POST['filter'];
			}

			if ( ! empty( $search_params ) ) {
				$query = '?' . implode( $search_params, '&' );
			} else {
				$query = '';
			}

		}
	}

	return $query;

}
add_filter( 'bp_ajax_querystring', 'humcore_ajax_querystring_filter', 999 );

/**
 * Return solr results when called from an ajax call.
 */
function humcore_ajax_return_solr_results() {

	ob_start();
	if ( bp_is_user() ) {
		bp_locate_template( array( 'deposits/user-deposits-loop.php' ), true );
	} else if ( bp_is_group() ) {
		bp_locate_template( array( 'deposits/group-deposits-loop.php' ), true );
	} else {
		bp_locate_template( array( 'deposits/deposits-loop.php' ), true );
	}

	$results = ob_get_contents();
	ob_end_clean();
	echo $results; // XSS OK.
	exit();

}
add_action( 'wp_ajax_nopriv_deposits_filter', 'humcore_ajax_return_solr_results' );
add_action( 'wp_ajax_deposits_filter', 'humcore_ajax_return_solr_results' );

/**
 * Return solr results when called from an ajax call.
 */
function humcore_before_has_deposits_parse_args( $retval ) {

	if ( ! empty( $retval['?tag'] ) ) {
		$retval['search_tag'] = $retval['?tag'];
	} else if ( ! empty( $retval['?title'] ) ) {
		$retval['search_title'] = $retval['?title'];
	} else if ( ! empty( $retval['?subject'] ) ) {
		$retval['search_subject'] = $retval['?subject'];
	} else if ( ! empty( $retval['?author'] ) ) {
		$retval['search_author'] = $retval['?author'];
	} else if ( ! empty( $retval['?username'] ) ) {
		$retval['search_username'] = $retval['?username'];
	} else if ( ! empty( $retval['?facets'] ) ) {
		$retval['search_facets'] = $retval['?facets'];
	} else if ( ! empty( $retval['?s'] ) ) {
		$retval['search_terms'] = $retval['?s'];
	} else if ( ! empty( $retval['?page'] ) ) {
		$retval['page'] = $retval['?page'];
	} else if ( ! empty( $retval['?sort'] ) ) {
		$retval['sort'] = $retval['?sort'];
	}
	return $retval;
}
add_action( 'bp_after_has_deposits_parse_args', 'humcore_before_has_deposits_parse_args' );

/**
 * Initialize the deposits loop.
 *
 * @param array $args
 *
 * @return bool Returns true when deposits are found, otherwise false.
 */
function humcore_has_deposits( $args = '' ) {

	global $deposits_results;
		// Note: any params used for filtering can be a single value, or multiple values comma separated.
	$defaults = array(
		'page_arg'          => 'page',
		'sort'              => 'newest',     // Sort date, author or title.
		'page'              => 1,            // Which page to load.
		'per_page'          => 25,           // Number of items per page.
		'max'               => false,        // Max number to return.
		'include'           => false,        // Specify pid to get.
		'search_by'         => false,        // Specify field to search 
		'search_tag'        => false,        // Specify tag to search for (keyword_search field).
		'search_subject'    => false,        // Specify subject to search for (subject_search field).
		'search_author'     => false,        // Specify author to search for (author_search field).
		'search_username'   => false,        // Specify username to search for (author_uni field).
		'search_terms'      => false,        // Specify terms to search on.
		'search_title'      => false,        // Specify title to search for an widlcard match (title_search field).
		'search_title_exact'=> false,        // Specify title to search for an exact match (title_search field).
		'search_facets'     => false,        // Specify facets to filter search on.
	);

	$params = bp_parse_args( $args, $defaults, 'has_deposits' );

	if ( empty( $params['search_tag'] ) && ! empty( $params['tag'] ) ) {
		$params['search_tag'] = $params['tag'];
	}

	if ( empty( $params['search_tag'] ) && ! empty( $_REQUEST['tag'] ) ) {
		$params['search_tag'] = $_REQUEST['tag'];
	}

	if ( empty( $params['search_subject'] ) && ! empty( $params['subject'] ) ) {
		$params['search_subject'] = $params['subject'];
	}

	if ( empty( $params['search_subject'] ) && ! empty( $_REQUEST['subject'] ) ) {
		$params['search_subject'] = $_REQUEST['subject'];
	}

	if ( empty( $params['search_author'] ) && ! empty( $params['author'] ) ) {
		$params['search_author'] = $params['author'];
	}

	if ( empty( $params['search_author'] ) && ! empty( $_REQUEST['author'] ) ) {
		$params['search_author'] = $_REQUEST['author'];
	}

	if ( empty( $params['search_username'] ) && ! empty( $params['username'] ) ) {
		$params['search_username'] = $params['username'];
	}

	if ( empty( $params['search_username'] ) && ! empty( $_REQUEST['username'] ) ) {
		$params['search_username'] = $_REQUEST['username'];
	}

	if ( empty( $params['search_terms'] ) && ! empty( $params['s'] ) ) {
		$params['search_terms'] = $params['s'];
	}

	if ( empty( $params['search_terms'] ) && ! empty( $_REQUEST['s'] ) ) {
		$params['search_terms'] = $_REQUEST['s'];
	}

	// TODO figure out how to remove this hack (copy date_issued to text in solr?).
	$params['search_terms'] = preg_replace( '/^(\d{4})$/', 'date_issued:$1', $params['search_terms'] );

	if ( empty( $params['search_title'] ) && ! empty( $params['title'] ) ) {
		$params['search_title'] = $params['title'];
	}

	if ( empty( $params['search_title'] ) && ! empty( $_REQUEST['title'] ) ) {
		$params['search_title'] = $_REQUEST['title'];
	}

	if ( empty( $params['search_title_exact'] ) && ! empty( $params['title_exact'] ) ) {
		$params['search_title_exact'] = $params['title_exact'];
	}

	if ( empty( $params['search_title_exact'] ) && ! empty( $_REQUEST['title_exact'] ) ) {
		$params['search_title_exact'] = $_REQUEST['title_exact'];
	}

	if ( empty( $params['search_facets'] ) && ! empty( $params['facets'] ) ) {
		$params['search_facets'] = $params['facets'];
	}

	if ( empty( $params['search_facets'] ) && ! empty( $_REQUEST['facets'] ) ) {
		$params['search_facets'] = $_REQUEST['facets'];
	}

	if ( ! empty( $_REQUEST['sort'] ) ) {
		$params['sort'] = esc_attr( $_REQUEST['sort'] );
	}

	// Do not exceed the maximum per page.
	if ( ! empty( $params['max'] ) && ( (int) $params['per_page'] > (int) $params['max'] ) ) {
		 $params['per_page'] = $params['max'];
	}

	$search_args = array(
		'page'              => $params['page'],
		'per_page'          => $params['per_page'],
		'page_arg'          => $params['page_arg'],
		'max'               => $params['max'],
		'sort'              => $params['sort'],
		'include'           => $params['include'],
		'search_tag'        => $params['search_tag'],
		'search_subject'    => $params['search_subject'],
		'search_author'     => $params['search_author'],
		'search_username'   => $params['search_username'],
		'search_terms'      => $params['search_terms'],
		'search_title'      => $params['search_title'],
		'search_title_exact'=> $params['search_title_exact'],
		'search_facets'     => $params['search_facets'],
	);

	$deposits_results = new Humcore_Deposit_Search_Results( $search_args );

	return apply_filters( 'humcore_has_deposits', $deposits_results->has_deposits(), $deposits_results, $search_args );

}

/**
 * Determine if there are still deposits left in the loop.
 *
 * @return bool Returns true when deposits are found.
 */
function humcore_deposits() {
	global $deposits_results;

	return $deposits_results->deposits();
}

/**
 * Get the current deposit object in the loop.
 *
 * @return object The current deposit within the loop.
 */
function humcore_the_deposit() {
	global $deposits_results;

	return $deposits_results->the_deposit();
}

/**
 * Return the curret deposit object.
 *
 * @return object The current deposit object.
 */
function humcore_get_current_deposit() {
	global $deposits_results;

	return apply_filters( 'humcore_get_current_deposit', $deposits_results->deposit );
}

/**
 * Output the deposit count.
 *
 * @uses humcore_get_deposit_count()
 */
function humcore_deposit_count() {
	echo humcore_get_deposit_count(); // XSS OK.
}

/**
 * Return the deposit count.
 *
 * @global object $deposits_results {@link Humcore_Deposit_Search_Results}
 * @uses apply_filters() To call the 'humcore_get_deposit_count' hook.
 *
 * @return int The deposit count.
 */
function humcore_get_deposit_count() {
	global $deposits_results;

	return apply_filters( 'humcore_get_deposit_count', (int) $deposits_results->total_deposit_count );
}

/**
 * Output the deposit id.
 *
 * @uses humcore_get_deposit_id()
 */
function humcore_deposit_id() {
	echo humcore_get_deposit_id(); // XSS OK.
}

/**
 * Return the deposit id.
 *
 * @global object $deposits_results {@link Humcore_Deposit_Search_Results}
 * @uses apply_filters() To call the 'humcore_get_deposit_id' hook.
 *
 * @return The deposit id.
 */
function humcore_get_deposit_id() {
	global $deposits_results;

	return apply_filters( 'humcore_get_deposit_id', $deposits_results->deposit->id );
}

/**
 * Return the facet counts.
 *
 * @global object $deposits_results {@link Humcore_Deposit_Search_Results}
 * @uses apply_filters() To call the 'humcore_get_facet_counts' hook.
 *
 * @return array Facets and counts.
 */
function humcore_get_facet_counts() {
	global $deposits_results;

	return apply_filters( 'humcore_get_facet_counts', (array) $deposits_results->facet_counts );
}

/**
 * Return the facet titles.
 *
 * @uses apply_filters() To call the 'humcore_get_facet_titles' hook.
 *
 * @return array Facets and titles.
 */
function humcore_get_facet_titles() {
	$facet_titles = array(
			'author_facet' => __( 'Author', 'humcore_domain' ),
			'group_facet' => __( 'Group', 'humcore_domain' ),
			'subject_facet' => __( 'Subject', 'humcore_domain' ),
			'genre_facet' => __( 'Item Type', 'humcore_domain' ),
			'pub_date_facet' => __( 'Date', 'humcore_domain' ),
			'type_of_resource_facet' => __( 'File Type', 'humcore_domain' ),
		);

	return apply_filters( 'humcore_get_facet_titles', $facet_titles );
}

/**
 * Output the deposit pagination count.
 */
function humcore_deposit_pagination_count() {
		echo humcore_get_deposit_pagination_count(); // XSS OK.
}

/**
 * Return the deposit pagination count.
 *
 * @global object $deposits_results {@link Humcore_Deposit_Search_Results}
 * @uses bp_core_number_format()
 *
 * @return string The pagination text.
 */
function humcore_get_deposit_pagination_count() {
	global $deposits_results;

	$start_num = intval( ( $deposits_results->pag_page - 1 ) * $deposits_results->pag_num ) + 1;
	$from_num  = bp_core_number_format( $start_num );
	$to_num = bp_core_number_format(
		( $start_num + ( $deposits_results->pag_num - 1 ) > $deposits_results->total_deposit_count )
		? $deposits_results->total_deposit_count
		: $start_num + ( $deposits_results->pag_num - 1 )
	);
	$total = bp_core_number_format( $deposits_results->total_deposit_count );

	return sprintf( _n( 'Viewing item %1$s to %2$s (of %3$s items)', 'Viewing item %1$s to %2$s (of %3$s items)', $total, 'humcore_domain' ), $from_num, $to_num, $total );
}

/**
 * Output the deposit pagination links.
 *
 * @uses humcore_get_deposit_pagination_links()
 */
function humcore_deposit_pagination_links() {
	echo humcore_get_deposit_pagination_links(); // XSS OK.
}

/**
 * Return the deposit pagination links.
 *
 * @global object $deposits_results {@link Humcore_Deposit_Search_Results}
 * @uses apply_filters() To call the 'humcore_get_deposit_pagination_links' hook.
 *
 * @return string The pagination links.
 */
function humcore_get_deposit_pagination_links() {
	global $deposits_results;

	return apply_filters( 'humcore_get_deposit_pagination_links', $deposits_results->pag_links );
}

/**
 * Return the deposit page number.
 *
 * @global object $deposits_results {@link Humcore_Deposit_Search_Results}
 * @uses apply_filters() To call the 'humcore_get_deposit_page_number' hook.
 *
 * @return string The page number.
 */
function humcore_get_deposit_page_number() {
        global $deposits_results;

        return apply_filters( 'humcore_get_deposit_page_number', $deposits_results->pag_page );
}

/**
 * Return true when there are more deposit items to be shown than currently appear.
 *
 * @global object $deposits_results {@link Humcore_Deposit_Search_Results}
 * @uses apply_filters() To call the 'humcore_deposit_has_more_items' hook.
 *
 * @return bool $has_more_items True if more items, false if not.
 */
function humcore_deposit_has_more_items() {
	global $deposits_results;

	$remaining_pages = 0;

	if ( ! empty( $deposits_results->pag_page ) ) {
		$remaining_pages = floor( ( $deposits_results->total_deposit_count - 1 ) / ( $deposits_results->pag_num * $deposits_results->pag_page ) );
	}

	$has_more_items  = (int) $remaining_pages ? true : false;

	return apply_filters( 'humcore_deposit_has_more_items', $has_more_items );
}

/**
 * Return the deposit post id.
 *
 * @global object $deposits_results {@link Humcore_Deposit_Search_Results}
 * @uses apply_filters() To call the 'humcore_get_deposit_record_identifier' hook.
 *
 * @return The deposit record identifier ( post_id ).
 */
function humcore_get_deposit_record_identifier() {
	global $deposits_results;

	return apply_filters( 'humcore_get_deposit_record_identifier', $deposits_results->deposit->record_identifier );
}

/**
 * Return the deposit activity id.
 *
 * @uses apply_filters() To call the 'humcore_get_deposit_activity_id' hook.
 *
 * @return The deposit id.
 */
function humcore_get_deposit_activity_id() {
	global $bp;

	$activity_id = bp_activity_get_activity_id( array(
		'type' => 'new_deposit',
		'component' => $bp->humcore_deposits->id,
		'secondary_item_id' => humcore_get_deposit_record_identifier(),
	) );

	return apply_filters( 'humcore_get_deposit_activity_id', $activity_id );
}

/**
 * Return true when the deposit activity is a favorite of the current user.
 *
 * @global object $activities_template {@link BP_Activity_Template}
 * @uses apply_filters() To call the 'humcore_deposit_activity_is_favorite' hook.
 *
 * @return bool $is_favorite True if favorite, false if not.
 */
function humcore_deposit_activity_is_favorite( $activity_id ) {
	// TODO activity component must be active.
	$user_favs = bp_activity_get_user_favorites( bp_loggedin_user_id() );

	return apply_filters( 'humcore_deposit_activity_is_favorite', in_array( $activity_id, (array) $user_favs ) );
}

/**
 * Output the deposit activity favorite link.
 *
 * @uses humcore_get_deposit_activity_favorite_link()
 */
function humcore_deposit_activity_favorite_link( $activity_id ) {
	echo humcore_get_deposit_activity_favorite_link( $activity_id ); // XSS OK.
}

/**
 * Return the deposit activity favorite link.
 *
 * @global object $activities_template {@link BP_Activity_Template}
 * @uses wp_nonce_url()
 * @uses home_url()
 * @uses bp_get_activity_root_slug()
 * @uses apply_filters() To call the 'humcore_get_deposit_activity_favorite_link' hook
 *
 * @return string The activity favorite link.
 */
function humcore_get_deposit_activity_favorite_link( $activity_id ) {
	global $activities_template;
	return apply_filters( 'humcore_get_deposit_activity_favorite_link', wp_nonce_url( home_url( bp_get_activity_root_slug() . '/favorite/' . $activity_id . '/' ), 'mark_favorite' ) );
}

/**
 * Output the deposit activity unfavorite link.
 *
 * @uses humcore_get_deposit_activity_unfavorite_link()
 */
function humcore_deposit_activity_unfavorite_link( $activity_id ) {
	echo humcore_get_deposit_activity_unfavorite_link( $activity_id ); // XSS OK.
}

/**
 * Return the deposit activity unfavorite link.
 *
 * @global object $activities_template {@link BP_Activity_Template}
 * @uses wp_nonce_url()
 * @uses home_url()
 * @uses bp_get_activity_root_slug()
 * @uses apply_filters() To call the 'humcore_get_deposit_activity_unfavorite_link' hook.
 *
 * @return string The activity unfavorite link.
 */
function humcore_get_deposit_activity_unfavorite_link( $activity_id ) {
	global $activities_template;
	return apply_filters( 'humcore_get_deposit_activity_unfavorite_link', wp_nonce_url( home_url( bp_get_activity_root_slug() . '/unfavorite/' . $activity_id . '/' ), 'unmark_favorite' ) );
}

/**
 * The main deposit search results loop class.
 *
 * This is responsible for loading a group of deposit items and displaying them.
 */
class Humcore_Deposit_Search_Results {

	var $current_deposit = -1;
	var $deposit_count;
	var $total_deposit_count;
	var $facet_counts = '';

	var $deposits;
	var $deposit;

	var $in_the_loop;

	var $pag_page;
	var $pag_num;
	var $pag_links;

	/**
	 * Constructor method.
	 *
	 * @param array $args Array of arguments.
	 */
	function __construct( $args ) {

		$defaults = array(
			'page'              => 1,
			'per_page'          => 25,
			'page_arg'          => 'page',
			'max'               => false,
			'sort'              => 'newest',
			'include'           => false,
			'search_tag'        => '',
			'search_subject'    => '',
			'search_author'     => '',
			'search_username'   => '',
			'search_terms'      => '',
			'search_title'      => '',
			'search_title_exact'=> '',
			'search_facets'     => '',
		);
		$r = wp_parse_args( $args, $defaults );
		$page = $r['page'];
		$per_page = $r['per_page'];
		$page_arg = $r['page_arg'];
		$max = $r['max'];
		$sort = $r['sort'];
		$include = $r['include'];
		$lucene_reserved_characters = preg_quote( '+-&|!(){}[]^"~*?:\\' );

		$search_tag = preg_replace_callback(
			'/([' . $lucene_reserved_characters . '])/',
			function($matches) {
				return '\\' . $matches[0];
			},
			trim( $r['search_tag'], '"' )
		);

		$search_tag = str_replace( ' ', '\ ', $search_tag );
		if ( false !== strpos( $search_tag, ' ' ) ) {
			$search_tag = '"' . $search_tag . '"';
		}

		if ( ! empty( $search_tag ) ) {
			$search_tag = 'keyword_search:' . $search_tag;
		}

		$search_subject = preg_replace_callback(
			'/([' . $lucene_reserved_characters . '])/',
			function($matches) {
				return '\\' . $matches[0];
			},
			trim( $r['search_subject'], '"' )
		);

		$search_subject = str_replace( ' ', '\ ', $search_subject );
		if ( false !== strpos( $search_subject, ' ' ) ) {
			$search_subject = '"' . $search_subject . '"';
		}

		if ( ! empty( $search_subject ) ) {
			$search_subject = 'subject_search:' . $search_subject;
		}

		$search_author = preg_replace_callback(
			'/([' . $lucene_reserved_characters . '])/',
			function($matches) {
				return '\\' . $matches[0];
			},
			trim( $r['search_author'], '"' )
		);

		$search_author = str_replace( ' ', '\ ', $search_author );
		if ( false !== strpos( $search_author, ' ' ) ) {
			$search_author = '"' . $search_author . '"';
		}

		if ( ! empty( $search_author ) ) {
			$search_author = 'author_search:' . $search_author;
		}

		$search_username = preg_replace_callback(
			'/([' . $lucene_reserved_characters . '])/',
			function($matches) {
				return '\\' . $matches[0];
			},
			trim( $r['search_username'], '"' )
		);

		$search_username = str_replace( ' ', '\ ', $search_username );
		if ( false !== strpos( $search_username, ' ' ) ) {
			$search_username = '"' . $search_username . '"';
		}

		if ( ! empty( $search_username ) ) {
			$search_username = 'author_uni:' . $search_username;
		}

		$search_terms = preg_replace_callback(
			'/([' . $lucene_reserved_characters . '])/',
			function($matches) {
				return '\\' . $matches[0];
			},
			trim( $r['search_terms'], '"' )
		);

		$search_terms = str_replace( ' ', '\ ', $search_terms );
		if ( false !== strpos( $search_terms, ' ' ) ) {
			$search_terms = '"' . $search_terms . '"';
		}

		$search_title = preg_replace_callback(
			'/([' . $lucene_reserved_characters . '])/',
			function($matches) {
				return '\\' . $matches[0];
			},
			trim( $r['search_title'], '"' )
		);

		$search_title = str_replace( ' ', '\ ', $search_title );
		if ( false !== strpos( $search_title, ' ' ) ) {
			$search_title = '"' . $search_title . '"';
		}

		if ( ! empty( $search_title ) ) {
			$search_title = 'title_search:' . $search_title;
		}

		$search_title_exact = preg_replace_callback(
			'/([' . $lucene_reserved_characters . '])/',
			function($matches) {
				return '\\' . $matches[0];
			},
			trim( $r['search_title_exact'], '"' )
		);

		$search_title_exact = str_replace( ' ', '\ ', $search_title_exact );
		if ( false !== strpos( $search_title_exact, ' ' ) ) {
			$search_title_exact = '"' . $search_title_exact . '"';
		}

		if ( ! empty( $search_title_exact ) ) {
			$search_title_exact = 'title_display:' . $search_title_exact;
		}

		$search_facets = $r['search_facets'];

		$this->pag_page = isset( $_REQUEST[ $page_arg ] ) ? intval( $_REQUEST[ $page_arg ] ) : $page;
		$this->pag_num  = isset( $_REQUEST['num'] ) ? intval( $_REQUEST['num'] ) : $per_page;

		global $fedora_api, $solr_client;

		// Hardcode two collections during HC beta period.
		//$query_collection = 'member_of:' . str_replace( ':', '\:', $fedora_api->collectionPid );
		$query_collection = '( member_of:' . str_replace( ':', '\:', 'hccollection:1' ) .
				' OR member_of:' . str_replace( ':', '\:', 'mlacollection:1' ) . ' )';

		if ( ! empty( $search_tag ) ) {
			$restricted_search_terms = implode( ' AND ', array( $query_collection, $search_tag ) );
		} else if ( ! empty( $search_subject ) ) {
			$restricted_search_terms = implode( ' AND ', array( $query_collection, $search_subject ) );
		} else if ( ! empty( $search_author ) ) {
			$restricted_search_terms = implode( ' AND ', array( $query_collection, $search_author ) );
		} else if ( ! empty( $search_username ) ) {
			$restricted_search_terms = implode( ' AND ', array( $query_collection, $search_username ) );
		} else if ( ! empty( $search_terms ) ) {
			$restricted_search_terms = implode( ' AND ', array( $query_collection, $search_terms ) );
		} else if ( ! empty( $search_title ) ) {
			$restricted_search_terms = implode( ' AND ', array( $query_collection, $search_title ) );
		} else if ( ! empty( $search_title_exact ) ) {
			$restricted_search_terms = implode( ' AND ', array( $query_collection, $search_title_exact ) );
		} else {
			$restricted_search_terms = $query_collection;
		}

		if ( ! $include ) {

			$cache_key = http_build_query( array( $restricted_search_terms, $search_facets, $this->pag_page, $sort, $this->pag_num ), 'param_' );
			// Check cache for search results.
			$results = wp_cache_get( $cache_key, 'humcore_solr_search_results' );
			if ( false === $results ) {
				try {
					$results = $solr_client->get_search_results( $restricted_search_terms, $search_facets, $this->pag_page, $sort, $this->pag_num );
					$cache_status = wp_cache_set( $cache_key, $results, 'humcore_solr_search_results', 3 );
				} catch ( Exception $e ) {
					$this->total_deposit_count = 0;
					$this->facet_counts = '';
					$this->deposits = '';
					humcore_write_error_log(
						'error',
						sprintf(
							'*****HumCORE Search***** - A Solr error occurred. %1$s - %2$s',
							$e->getCode(),
							$e->getMessage()
						)
					);

				}
			}
		} else {
			$cache_key = http_build_query( $include );
			$results = wp_cache_get( $cache_key, 'humcore_solr_search_results' );
			$results = $solr_client->get_humcore_document( $include );
			if ( false === $results ) {
				$results = $solr_client->get_humcore_document( $include );
				$cache_status = wp_cache_set( $cache_key, $results, 'humcore_solr_search_results', 3 );
			}
		}

		if ( ! $max || $max >= (int) $results['total'] ) {
			$this->total_deposit_count = (int) $results['total'];
		} else {
			$this->total_deposit_count = (int) $max;
		}

		$this->facet_counts = $results['facets'];
		$this->deposits = $results['documents'];

		if ( $max ) {
			if ( $max >= count( $this->deposits ) ) { // TODO count must be changed.
				$this->deposit_count = count( $this->deposits ); // TODO count must be changed.
			} else {
				$this->deposit_count = (int) $max; // TODO count must be changed.
			}
		} else {
			$this->deposit_count = count( $this->deposits ); // TODO count must be changed.
		}

		if ( (int) $this->total_deposit_count && (int) $this->pag_num ) {
			$this->pag_links = paginate_links( array(
				'base'      => add_query_arg( $page_arg, '%#%', '' ),
				'format'    => '',
				'total'     => ceil( (int) $this->total_deposit_count / (int) $this->pag_num ),
				'current'   => (int) $this->pag_page,
				'prev_text' => _x( '&larr;', 'Deposit pagination previous text', 'humcore_domain' ),
				'next_text' => _x( '&rarr;', 'Deposit pagination next text', 'humcore_domain' ),
				'mid_size'  => 1,
			) );
		}
	}

	/**
	 * Whether there are deposit items available in the loop.
	 *
	 * @return bool True if there are items in the loop, otherwise false.
	 */
	function has_deposits() {
		if ( $this->deposit_count ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Set up the next deposit item and iterate index.
	 *
	 * @return object The next deposit item to iterate over.
	 */
	function next_deposit() {
		$this->current_deposit++;
		$this->deposit = $this->deposits[ $this->current_deposit ];
		return $this->deposit;
	}

	/**
	 * Rewind the posts and reset post index.
	 */
	function rewind_deposits() {
		$this->current_deposit = -1;
		if ( $this->deposit_count > 0 ) {
			$this->deposit = $this->deposits[0];
		}
	}

	/**
	 * Whether there are deposit items left in the loop to iterate over.
	 *
	 * @return bool True if there are more deposit items to show,
	 *              otherwise false.
	 */
	function deposits() {
		if ( $this->current_deposit + 1 < $this->deposit_count ) {
			return true;
		} elseif ( $this->current_deposit + 1 == $this->deposit_count ) {
			do_action( 'deposit_loop_end' );
			// Do some cleaning up after the loop.
			$this->rewind_deposits();
		}

		$this->in_the_loop = false;
		return false;
	}

	/**
	 * Set up the current deposit item inside the loop.
	 */
	function the_deposit() {

		$this->in_the_loop = true;
		$this->deposit = $this->next_deposit();

		if ( is_array( $this->deposit ) ) {
			$this->deposit = (object) $this->deposit;
		}

		if ( 0 == $this->current_deposit ) { // Loop has just started.
			do_action( 'deposit_loop_start' );
		}
	}

	/**
	 * Return the array of facet counts.
	 *
	 * @return array The search results facet counts.
	 */
	function the_facets() {
		return $this->facet_counts;
	}

}
