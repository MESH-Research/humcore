<?php

/**
 * Plugin support functions.
 *
 * @package HumCORE
 * @subpackage Deposits
 */

/**
 * Register the activity actions for Humanities CORE.
 */
function humcore_deposits_register_activity_actions() {

	if ( ! bp_is_active( 'activity' ) ) {
		return false;
	}
	$bp = buddypress();
	bp_activity_set_action(
		$bp->humcore_deposits->id,
		'new_deposit',
		__( 'New Deposits', 'humcore_domain' ),
		'humcore_format_activity_action_new_deposit',
		__( 'New Deposits', 'humcore_domain' ),
		array( 'activity', 'member', 'groups' )
	);
	bp_activity_set_action(
		$bp->groups->id,
		'new_group_deposit',
		__( 'New Group Deposits', 'humcore_domain' ),
		'humcore_format_activity_action_new_group_deposit',
		__( 'New Group Deposits', 'humcore_domain' ),
		array( 'member_groups', 'groups' )
	);
	do_action( 'humcore_deposits_register_activity_actions' );
}
add_action( 'bp_register_activity_actions', 'humcore_deposits_register_activity_actions' );

/**
 * Format 'new_deposit' activity action.
 *
 * @param object $activity Activity data.
 * @return string $action Formatted activity action.
 */
function humcore_format_activity_action_new_deposit( $action, $activity ) {

		$item_post = get_post( $activity->secondary_item_id );
		$item_link = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $activity->primary_link ), esc_html( $item_post->post_title ) );
	        $post_metadata = json_decode( get_post_meta( $activity->secondary_item_id, '_deposit_metadata', true ), true );
		if ( ! empty( $post_metadata['committee_id'] ) ) {
			$committee = groups_get_group( array( 'group_id' => $post_metadata['committee_id'] ) );
			$initiator_url = trailingslashit( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . $committee->slug . '/' );
			$initiator_name = $committee->name;
			$initiator_link = sprintf( '<a href="%1$sdeposits/">%2$s</a>', esc_url( $initiator_url ), esc_html( $initiator_name ) );
		} else {
			$initiator_url = bp_core_get_userlink( $activity->user_id, false, true );
			$initiator_name = bp_core_get_userlink( $activity->user_id, true, false );
			$initiator_link = sprintf( '<a href="%1$sdeposits/">%2$s</a>', esc_url( $initiator_url ), esc_html( $initiator_name ) );
		}
		$action = sprintf( __( '%1$s deposited %2$s', 'humcore_domain' ), $initiator_link, $item_link );
		return apply_filters( 'humcore_format_activity_action_new_deposit', $action, $activity );
}

/**
 * Format 'new_group_deposit' activity action.
 *
 * @param object $activity Activity data.
 * @return string $action Formatted activity action.
 */
function humcore_format_activity_action_new_group_deposit( $action, $activity ) {

		$item_post = get_post( $activity->secondary_item_id );
		$item_link = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $activity->primary_link ), esc_html( $item_post->post_title ) );
	        $post_metadata = json_decode( get_post_meta( $activity->secondary_item_id, '_deposit_metadata', true ), true );
		if ( ! empty( $post_metadata['committee_id'] ) ) {
			$committee = groups_get_group( array( 'group_id' => $post_metadata['committee_id'] ) );
			$initiator_url = trailingslashit( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . $committee->slug . '/' );
			$initiator_name = $committee->name;
			$initiator_link = sprintf( '<a href="%1$sdeposits/">%2$s</a>', esc_url( $initiator_url ), esc_html( $initiator_name ) );
		} else {
			$initiator_url = bp_core_get_userlink( $activity->user_id, false, true );
			$initiator_name = bp_core_get_userlink( $activity->user_id, true, false );
			$initiator_link = sprintf( '<a href="%1$sdeposits/">%2$s</a>', esc_url( $initiator_url ), esc_html( $initiator_name ) );
		}
		$group = groups_get_group( array( 'group_id' => $activity->item_id ) );
		$group_link = sprintf( '<a href="%1$sdeposits/">%2$s</a>', esc_url( bp_get_group_permalink( $group ) ), esc_html( $group->name ) );
		$action = sprintf( __( '%1$s deposited %2$s in the group %3$s', 'humcore_domain' ), $initiator_link, $item_link, $group_link );
		return apply_filters( 'humcore_format_activity_action_new_group_deposit', $action, $activity );
}

/**
 * Add a filter option to the filter select box on group activity pages.
 */
function humcore_activity_action_group_deposit_dropdown() {
?>
		<option value="new_group_deposit"><?php _e( 'New Group Deposits', 'humcore_domain' ) ?></option><?php

}
add_action( 'bp_group_activity_filter_options', 'humcore_activity_action_group_deposit_dropdown' );
add_action( 'bp_member_activity_filter_options', 'humcore_activity_action_group_deposit_dropdown' );
add_action( 'bp_activity_filter_options', 'humcore_activity_action_group_deposit_dropdown' );

/**
 * Create a new deposit activity record.
 */
function humcore_new_deposit_activity( $deposit_id, $deposit_content = '', $deposit_link = '' ) {

	if ( ! bp_is_active( 'activity' ) ) {
		return false;
	}

	$bp = buddypress();
	$user_id = bp_loggedin_user_id();
	$userlink = bp_core_get_userlink( $user_id );
	$activity_ID = bp_activity_add(
		array(
			'user_id' => $user_id,
			'secondary_item_id' => $deposit_id,
			'action' => '',
			'component' => $bp->humcore_deposits->id,
			'content' => $deposit_content,
			'primary_link' => $deposit_link,
			'type' => 'new_deposit',
		)
	);

	// Update the last activity date of the members or committee.
	$post_metadata = json_decode( get_post_meta( $deposit_id, '_deposit_metadata', true ), true );
	if ( ! empty( $post_metadata['committee_id'] ) ) {
		groups_update_last_activity( $post_metadata['committee_id'] );
	} else {
		bp_update_user_last_activity( $user_id );
	}

	return $activity_ID;
}

/**
 * Create a new group deposity activity record.
 */
function humcore_new_group_deposit_activity( $deposit_id, $group_id, $deposit_content = '', $deposit_link = '' ) {

	if ( ! bp_is_active( 'activity' ) ) {
		return false;
	}

	$bp = buddypress();
	$user_id = bp_loggedin_user_id();
	$userlink = bp_core_get_userlink( $user_id );

	$group = groups_get_group( $group_id );

	if ( isset( $group->status ) && 'public' != $group->status ) {
		$hide_sitewide = true;
	} else {
		$hide_sitewide = false;
	}

	$activity_ID = bp_activity_add(
		array(
			'user_id' => $user_id,
			'item_id' => $group_id,
			'secondary_item_id' => $deposit_id,
			'action' => '',
			'component' => $bp->groups->id,
			'content' => $deposit_content,
			'primary_link' => $deposit_link,
			'type' => 'new_group_deposit',
			'hide_sitewide' => $hide_sitewide,
		)
	);

	// Update the group's last activity
	groups_update_last_activity( $group_id );

	return $activity_ID;
}

/**
 * Get the post id or parent post id for a post slug.
 */
function humcore_get_deposit_post_id( $post_name ) {

	$args = array(
		'name'           => $post_name,
		'post_type'      => 'humcore_deposit',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
	);

	$deposit_post = get_posts( $args );

	if ( 0 == $deposit_post[0]->post_parent ) {
		return $deposit_post[0]->ID;
	} else {
		return $deposit_post[0]->post_parent;
	}

}

/**
 * Format the page head meta fields.
 */
function humcore_deposit_item_search_meta() {

	while ( humcore_deposits() ) :
		humcore_the_deposit();
	endwhile; // Should fetch one record.
	$metadata = (array) humcore_get_current_deposit();

	printf( '<meta name="description" content="%1$s">' . "\n\r", htmlentities( $metadata['abstract'] ) );
	printf( '<meta name="citation_title" content="%1$s">' . "\n\r", htmlentities( $metadata['title'] ) );
	printf( '<meta name="citation_publication_date" content="%1$s">' ."\n\r", htmlentities( $metadata['date'] ) ); // Format date yyyy/mm/dd.
	if ( ! empty( $metadata['publisher'] ) ) {
		printf( '<meta name="citation_publisher" content="%1$s">' . "\n\r", htmlentities( $metadata['publisher'] ) );
	}

	foreach ( $metadata['authors']  as $author ) {
		printf( '<meta name="citation_author" content="%1$s">' . "\n\r", htmlentities( $author ) );
	}

	if ( ! empty( $metadata['genre'] ) && in_array( $metadata['genre'], array( 'Dissertation', 'Thesis' ) ) && ! empty( $metadata['institution'][0] ) ) {
		printf( '<meta name="citation_dissertation_institution" content="%1$s">' . "\n\r", htmlentities( $metadata['institution'][0] ) );
	}
	if ( ! empty( $metadata['genre'] ) && 'Technical report' == $metadata['genre'] && ! empty( $metadata['institution'] ) ) {
		printf( '<meta name="citation_technical_report_institution" content="%1$s">' . "\n\r", htmlentities( $metadata['institution'] ) );
	}
	if ( ! empty( $metadata['genre'] ) && ( 'Conference paper' == $metadata['genre'] || 'Conference proceeding' == $metadata['genre'] ) && ! empty( $metadata['conference_title'] ) ) {
		printf( '<meta name="citation_conference_title" content="%1$s">' . "\n\r", htmlentities( $metadata['conference_title'] ) );
	}
	if ( ! empty( $metadata['book_journal_title'] ) ) {
		printf( '<meta name="citation_journal_title" content="%1$s">' . "\n\r", htmlentities( $metadata['book_journal_title'] ) );
	}
	if ( ! empty( $metadata['volume'] ) ) {
		printf( '<meta name="citation_volume" content="%1$s">' . "\n\r", htmlentities( $metadata['volume'] ) );
	}
	if ( ! empty( $metadata['issue'] ) ) {
		printf( '<meta name="citation_issue" content="%1$s">' . "\n\r", htmlentities( $metadata['issue'] ) );
	}
	if ( ! empty( $metadata['start_page'] ) ) {
		printf( '<meta name="citation_firstpage" content="%1$s">' . "\n\r", htmlentities( $metadata['start_page'] ) );
	}
	if ( ! empty( $metadata['end_page'] ) ) {
		printf( '<meta name="citation_lastpage" content="%1$s">' . "\n\r", htmlentities( $metadata['end_page'] ) );
	}
	if ( ! empty( $metadata['doi'] ) ) {
		printf( '<meta name="citation_doi" content="%1$s">' . "\n\r", htmlentities( $metadata['doi'] ) );
	}
	if ( ! empty( $metadata['handle'] ) ) {
		printf( '<meta name="citation_handle_id" content="%1$s">' . "\n\r", htmlentities( $metadata['handle'] ) );
	}
	if ( ! empty( $metadata['issn'] ) ) {
		printf( '<meta name="citation_issn" content="%1$s">' . "\n\r", htmlentities( $metadata['issn'] ) );
	}
	if ( ! empty( $metadata['chapter'] ) ) {
		printf( '<meta name="citation_chapter" content="%1$s">' . "\n\r", htmlentities( $metadata['chapter'] ) );
	}
	if ( ! empty( $metadata['isbn'] ) ) {
		printf( '<meta name="citation_isbn" content="%1$s">' . "\n\r", htmlentities( $metadata['isbn'] ) );
	}

	if ( ! empty( $metadata['subject'] ) ) {
		$full_subject_list = $metadata['subject'];
		foreach ( $full_subject_list as $subject ) {
			printf( '<meta name="citation_keywords" content="%1$s">' . "\n\r", htmlentities( $subject ) );
		}
	}

	printf( '<meta name="citation_abstract_html_url" content="%1$s/deposits/item/%2$s/">' . "\n\r", bp_get_root_domain(), htmlentities( $metadata['pid'] ) );

	$post_metadata = json_decode( get_post_meta( $metadata['record_identifier'], '_deposit_file_metadata', true ), true );
	printf( '<meta name="citation_pdf_url" content="%1$s/deposits/download/%2$s/%3$s/%4$s/">' . "\n\r",
		bp_get_root_domain(),
		htmlentities( $post_metadata['files'][0]['pid'] ),
		htmlentities( $post_metadata['files'][0]['datastream_id'] ),
		htmlentities( $post_metadata['files'][0]['filename'] )
	);

}

/**
 * Is this a search request?
 *
 * @return true If the current request is a search request.
 */
function humcore_is_deposit_search() {

	global $wp;
	if ( 'deposits' == $wp->query_vars['pagename'] && ! empty( $wp->query_vars['s'] ) || ! empty( $wp->query_vars['facets'] ) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Is the current page the deposit directory?
 *
 * @return true If the current page is the deposit directory.
 */
function humcore_is_deposit_directory() {

	global $wp;
	if ( 'deposits' == $wp->query_vars['pagename'] && ! is_feed() ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Is the current page the deposit feed?
 *
 * @return true If the current page is the deposit feed.
 */
function humcore_is_deposit_feed() {

        global $wp;
        if ( 'deposits' === $wp->query_vars['pagename'] && is_feed() ) {
                return true;
        } else {
                return false;
        }
}

/**
 * Is the current page the deposit list?
 *
 * @return true If the current page is the deposit list.
 */
function humcore_is_deposit_list() {

        global $wp;
        if ( 'deposits/list' === $wp->query_vars['pagename'] ) {
                return true;
        } else {
                return false;
        }
}

/**
 * Is the current page the deposit item?
 *
 * @return true If the current page is a deposit item.
 */
function humcore_is_deposit_item() {

	global $wp;
	if ( 'deposits/item' == $wp->query_vars['pagename'] ) {
		if ( 'new' != $wp->query_vars['deposits_item']  && 'review' !== $wp->query_vars['deposits_command'] ) {
			return true;
		} else {
			return false;
		}
	} else {
		return false;
	}
}

/**
 * Is the current page the deposit item review?
 *
 * @return true If the current page is a deposit item review page.
 */
function humcore_is_deposit_item_review() {

	global $wp;
	if ( 'deposits/item' === $wp->query_vars['pagename'] ) {
		if ( 'new' !== $wp->query_vars['deposits_item']  && 'review' === $wp->query_vars['deposits_command'] ) {
			return true;
		} else {
			return false;
		}
	} else {
		return false;
	}
}

/**
 * Is the current page the new deposit page?
 *
 * @return true If the current page is the new deposit page.
 */
function humcore_is_deposit_new_page() {

	global $wp;
	if ( 'deposits/item' == $wp->query_vars['pagename'] ) {
		if ( 'new' == $wp->query_vars['deposits_item'] ) {
			return true;
		} else {
			return false;
		}
	} else {
		return false;
	}
}

/**
 * Is this a download request?
 *
 * @return true If the current request is a download request.
 */
function humcore_is_deposit_download() {

	global $wp;
	if ( 'deposits/download' == $wp->query_vars['pagename'] ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Is this a view request?
 *
 * @return true If the current request is a view request.
 */
function humcore_is_deposit_view() {

	global $wp;
	if ( 'deposits/view' == $wp->query_vars['pagename'] ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Is this a bot request?
 *
 * @return true If the current request from a bot.
 */
function humcore_is_bot_user_agent() {

	if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		$ua = $_SERVER['HTTP_USER_AGENT'];
	} else {
		return false;
	}
	$bot_agents = array(
		'alexa', 'altavista', 'ask jeeves', 'attentio', 'baiduspider', 'bingbot', 'chtml generic', 'crawler', 'fastmobilecrawl',
		'feedfetcher-google', 'firefly', 'froogle', 'gigabot', 'googlebot', 'googlebot-mobile', 'heritrix', 'ia_archiver', 'irlbot',
		'iescholar', 'infoseek', 'jumpbot', 'lycos', 'mediapartners', 'mediobot', 'motionbot', 'msnbot', 'mshots', 'openbot',
		'pss-webkit-request', 'pythumbnail', 'scooter', 'slurp', 'snapbot', 'spider', 'taptubot', 'technoratisnoop',
		'teoma', 'twiceler', 'yahooseeker', 'yahooysmcm', 'yammybot', 'ahrefsbot', 'pingdom.com_bot', 'kraken', 'yandexbot',
		'twitterbot', 'tweetmemebot', 'openhosebot', 'queryseekerspider', 'linkdexbot', 'grokkit-crawler',
		'livelapbot', 'germcrawler', 'domaintunocrawler', 'grapeshotcrawler', 'cloudflare-alwaysonline',
		'applebot', 'paperlibot', 'duckduckbot', 'seznambot', 'naverbot', 'scoutjet', 'gurujibot', 'exabot', 'solbot',
		'voilabot', 'daumoa', 'architextspider', 'socscibot', 'coccoc', 'browsershots',
	);

	foreach ( $bot_agents as $bot_agent ) {
		if ( false !== stripos( $ua, $bot_agent ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Check the status of the external systems.
 */
function humcore_check_externals() {

	global $ezid_api, $fedora_api, $solr_client;

	if ( 'down' == $ezid_api->service_status ) {
		return false;
	}

	$sStatus = $solr_client->get_solr_status();
	if ( is_wp_error( $sStatus ) ) {
		humcore_write_error_log( 'error', sprintf( '*****HumCORE Status Error***** - solr server status : %1$s-%2$s', $sStatus->get_error_code(), $sStatus->get_error_message() ) );
		return false;
	}

	$fStatus = $fedora_api->describe();
	if ( is_wp_error( $fStatus ) ) {
		humcore_write_error_log( 'error', sprintf( '*****HumCORE Status Error***** - fedora server status :  %1$s-%2$s',  $fStatus->get_error_code(), $fStatus->get_error_message() ) );
		return false;
	}

	$eStatus = $ezid_api->server_status();
	if ( is_wp_error( $eStatus ) ) {
		humcore_write_error_log( sprintf( 'error', '*****HumCORE Status Error***** - ezid server status :  %1$s-%2$s',  $eStatus->get_error_code(), $eStatus->get_error_message() ) );
		return false;
	}

	return true;

}

/**
 * Reserve a DOI using EZID API.
 */
function humcore_create_handle( $title, $pid, $creator, $type, $date, $publisher ) {

	global $ezid_api;

	$eStatus = $ezid_api->mint_identifier( array(
		'dc.title' => $title,
		'_target' => sprintf( bp_get_root_domain() . '/deposits/item/%s/', $pid ),
		'dc.creator' => $creator,
		'dc.type' => $type,
		'dc.date' => $date,
		'dc.publisher' => $publisher,
	) );

	if ( is_wp_error( $eStatus ) ) {
		echo 'Error - ezid mint doi : ' . esc_html( $eStatus->get_error_code() ) . '-' . esc_html( $eStatus->get_error_message() );
		humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - ezid mint doi :  %1$s-%2$s',  $eStatus->get_error_code(), $eStatus->get_error_message() ) );
		return false;
	}

	return $eStatus;

}

/**
 * Publish a DOI using EZID API.
 */
function humcore_publish_handle( $humcore_doi ) {

	global $ezid_api;

	$eStatus = $ezid_api->modify_identifier( array(
		'doi' => $humcore_doi,
		'_status' => 'public',
		'_export' => 'yes',
	) );

	if ( is_wp_error( $eStatus ) ) {
		echo 'Error - ezid publish : ' . esc_html( $eStatus->get_error_code() ) . '-' . esc_html( $eStatus->get_error_message() );
		humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - ezid publish :  %1$s-%2$s',  $eStatus->get_error_code(), $eStatus->get_error_message() ) );
		return false;
	}

	return $eStatus;

}

/**
 * Modify DOI metdata using EZID API.
 */
function humcore_modify_handle( $humcore_doi, $title, $creator, $type, $date, $publisher ) {

	global $ezid_api;

	$eStatus = $ezid_api->modify_identifier( array(
		'doi' => $humcore_doi,
		'dc.title' => $title,
		'dc.creator' => $creator,
		'dc.type' => $type,
		'dc.date' => $date,
		'dc.publisher' => $publisher,
	) );

	if ( is_wp_error( $eStatus ) ) {
		echo 'Error - ezid modify : ' . esc_html( $eStatus->get_error_code() ) . '-' . esc_html( $eStatus->get_error_message() );
		humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - ezid modify :  %1$s-%2$s',  $eStatus->get_error_code(), $eStatus->get_error_message() ) );
		return false;
	}

	return $eStatus;

}

/**
 * Register the location of the plugin templates.
 */
function humcore_register_template_location() {

	return dirname( __FILE__ ) . '/templates/';
}

/**
 * Add specific CSS class by filter (filter added in humcore_deposits_search_screen).
 */
function humcore_search_page_class_names( $classes ) {

	$classes[] = 'search-page';
	return $classes;
}

/**
 * Add specific CSS class by filter (filter added in humcore_deposits_screen_index).
 */
function humcore_deposit_directory_page_class_names( $classes ) {

	$classes[] = 'deposits-directory-page';
	return $classes;
}

/**
 * Add specific CSS class by filter (filter added in humcore_deposits_new_item_screen).
 */
function humcore_deposit_new_item_page_class_names( $classes ) {

	$classes[] = 'deposits-new-item-page';
	return $classes;
}

/**
 * Load the Search Results template.
 */
function humcore_deposits_search_screen() {
	if ( humcore_is_deposit_search() ) {
		bp_update_is_directory( false, 'humcore_deposits' );
		add_filter( 'body_class', 'humcore_search_page_class_names' );
		$extended_query_string = humcore_get_search_request_querystring( 'facets' );
		if ( ! empty( $extended_query_string ) ) {
			setcookie( 'bp-deposits-extras', $extended_query_string, 0, '/' );
		}
		do_action( 'humcore_deposits_search_screen' );
		bp_core_load_template( apply_filters( 'humcore_deposits_search_screen', 'deposits/search' ) );
	}
}
add_action( 'bp_screens', 'humcore_deposits_search_screen' );

/**
 * Load the Deposits directory.
 *
 * @uses humcore_is_deposit_directory()
 * @uses bp_update_is_directory()
 * @uses do_action() To call the 'humcore_deposits_screen_index' hook.
 * @uses bp_core_load_template()
 * @uses apply_filters() To call the 'humcore_deposits_screen_index' hook.
 */
function humcore_deposits_screen_index() {
	if ( humcore_is_deposit_directory() ) {
		bp_update_is_directory( true, 'humcore_deposits' );
		add_filter( 'body_class', 'humcore_deposit_directory_page_class_names' );
		setcookie( 'bp-deposits-extras', false, 0, '/' );
		do_action( 'humcore_deposits_screen_index' );
		bp_core_load_template( apply_filters( 'humcore_deposits_screen_index', 'deposits/deposits-index' ) );
	}
}
add_action( 'bp_screens', 'humcore_deposits_screen_index' );

/**
 * Load the Deposits feed.
 */
function humcore_deposits_feed() {
	if ( humcore_is_deposit_feed() ) {
		bp_update_is_directory( false, 'humcore_deposits' );
		do_action( 'humcore_deposits_feed' );
		bp_core_load_template( apply_filters( 'humcore_deposits_feed', 'deposits/deposits-feed' ) );
	}
}
add_action( 'bp_screens', 'humcore_deposits_feed' );

/**
 * Load the Deposits list page.
 */
function humcore_deposits_list_screen() {
	if ( humcore_is_deposit_list() ) {
		bp_update_is_directory( false, 'humcore_deposits' );
		do_action( 'humcore_deposits_list_screen' );
		bp_core_load_template( apply_filters( 'humcore_deposits_list_screen', 'deposits/deposits-list' ) );
	}
}
add_action( 'bp_screens', 'humcore_deposits_list_screen' );

/**
 * Load the Deposits item screen.
 */
function humcore_deposits_item_screen() {

	global $wp;
	if ( humcore_is_deposit_item() ) {
		bp_update_is_directory( false, 'humcore_deposits' );
		$deposit_id = $wp->query_vars['deposits_item'];
		if ( empty( $deposit_id ) ) {
			bp_do_404();
			//bp_core_load_template( apply_filters( 'humcore_deposits_item_screen', 'deposits/404' ) );
			return;
		}
		$item_found = humcore_has_deposits( 'include=' . $deposit_id );
		if ( $item_found) { 
			do_action( 'humcore_deposits_item_screen' );
			add_action( 'wp_head', 'humcore_deposit_item_search_meta' );
			bp_core_load_template( apply_filters( 'humcore_deposits_item_screen', 'deposits/single/item' ) );
		} else {
			//bp_core_load_template( apply_filters( 'humcore_deposits_item_screen', 'deposits/404' ) );
			bp_do_404();
		}
	}
}
add_action( 'bp_screens', 'humcore_deposits_item_screen' );

/**
 * Load the Deposits item review screen.
 */
function humcore_deposits_item_review_screen() {

	global $wp;
	if ( humcore_is_deposit_item_review() ) {
		bp_update_is_directory( false, 'humcore_deposits' );
		$deposit_id = $wp->query_vars['deposits_item'];
		if ( empty( $deposit_id ) ) {
			bp_do_404();
			return;
		}
		$item_found = humcore_has_deposits( 'include=' . $deposit_id );
		if ( $item_found) { 
			do_action( 'humcore_deposits_item_review_screen' );
			add_action( 'wp_head', 'humcore_deposit_item_search_meta' );
			bp_core_load_template( apply_filters( 'humcore_deposits_item_review_screen', 'deposits/single/review' ) );
		} else {
			bp_do_404();
		}
	}
}
add_action( 'bp_screens', 'humcore_deposits_item_review_screen' );

/**
 * Load the Deposits new item screen.
 */
function humcore_deposits_new_item_screen() {

	if ( humcore_is_deposit_new_page() ) {
		if ( ! is_user_logged_in() ) { auth_redirect(); }
		$user_id = bp_loggedin_user_id();
		$core_acceptance = get_the_author_meta( 'accepted_core_terms', $user_id );
		if ( 'Yes' != $core_acceptance ) {
			wp_redirect( '/core/terms/' );
			exit();
		}
		bp_update_is_directory( false, 'humcore_deposits' );
		add_filter( 'body_class', 'humcore_deposit_new_item_page_class_names' );
		do_action( 'humcore_deposits_new_item_screen' );
		add_action( 'bp_template_content', 'humcore_deposit_form' );
		ob_start(); // we might redirect in the action so capture any output.
		bp_core_load_template( apply_filters( 'humcore_deposits_new_item_screen', 'deposits/single/new' ) );
	}
}
add_action( 'bp_screens', 'humcore_deposits_new_item_screen' );

/**
 * Redirect the download request.
 */
function humcore_deposits_download() {

	global $wp;
	if ( humcore_is_deposit_download() ) {
		bp_update_is_directory( false, 'humcore_deposits' );
		do_action( 'humcore_deposits_download' );
		$deposit_id = $wp->query_vars['deposits_item'];
		$deposit_datastream = $wp->query_vars['deposits_datastream'];
		if ( empty( $deposit_id ) || empty( $deposit_datastream ) ) {
			bp_do_404();
			return;
		}
		$deposit_filename = $wp->query_vars['deposits_filename'];
		$download_param = ( 'xml' == $deposit_filename ) ? '' : '?download=true';
		$downloads_meta_key = sprintf( '_total_downloads_%s_%s', $deposit_datastream, $deposit_id );
		$deposit_post_id = humcore_get_deposit_post_id( $deposit_id );
        	$post_data = get_post( $deposit_post_id );
		$total_downloads = get_post_meta( $deposit_post_id, $downloads_meta_key, true ) + 1; // Downloads counted at file level.
	        if ( $post_data->post_author != bp_loggedin_user_id() && ! humcore_is_bot_user_agent() ) {
			$post_meta_ID = update_post_meta( $deposit_post_id, $downloads_meta_key, $total_downloads );
		}
		$download_url = sprintf( '/deposits/objects/%1$s/datastreams/%2$s/content%3$s', $deposit_id, $deposit_datastream, $download_param );

		wp_redirect( $download_url );
		exit();
	}
}
add_action( 'bp_screens', 'humcore_deposits_download' );

/**
 * Redirect the view request.
 */
function humcore_deposits_view() {

	global $wp;
	if ( humcore_is_deposit_view() ) {
		bp_update_is_directory( false, 'humcore_deposits' );
		do_action( 'humcore_deposits_view' );
		$deposit_id = $wp->query_vars['deposits_item'];
		$deposit_datastream = $wp->query_vars['deposits_datastream'];
                if ( empty( $deposit_id ) || empty( $deposit_datastream ) ) {
                        bp_do_404();
                        return;
                }
		$deposit_filename = $wp->query_vars['deposits_filename'];
		$views_meta_key = sprintf( '_total_views_%s_%s', $deposit_datastream, $deposit_id );
		$deposit_post_id = humcore_get_deposit_post_id( $deposit_id );
        	$post_data = get_post( $deposit_post_id );
		$total_views = get_post_meta( $deposit_post_id, $views_meta_key, true ) + 1; // views counted at file level
	        if ( $post_data->post_author != bp_loggedin_user_id() && ! humcore_is_bot_user_agent() ) {
			$post_meta_ID = update_post_meta( $deposit_post_id, $views_meta_key, $total_views );
		}
		$view_url = sprintf( '/deposits/objects/%1$s/datastreams/%2$s/content', $deposit_id, $deposit_datastream );

		wp_redirect( $view_url );
		exit();
	}
}
add_action( 'bp_screens', 'humcore_deposits_view' );

/**
 * Is this group a forum?
 *
 * @return true If the group is a forum.
 */
function humcore_is_group_forum( $group_id = 0 ) {
        // use the current group if we're not passed one.
        if ( 0 == $group_id ) $group_id = bp_get_current_group_id();

        // if mla_oid starts with "M," it's a committee, if "D","G" it's a forum
        return in_array( substr( groups_get_groupmeta( $group_id, 'mla_oid' ), 0, 1 ), array( 'D', 'G' ) );
}

/**
 * Returns group with link.
 * @return string
 */
function humcore_linkify_group( $group, $link_type = 'facet' ) {

	if ( 'facet' != $link_type && function_exists( 'bp_is_active' ) ) {
		if ( bp_is_active( 'groups' ) ) {
			$group_slug = humcore_get_slug_from_name( $group );
			$linked_group = sprintf( '<a href="/groups/%s/deposits">%s</a>', urlencode( $group_slug ), esc_html( $group ) );
			return $linked_group;
		}
	}

	$linked_group = sprintf( '<a href="/deposits/?facets[group_facet][]=%s">%s</a>', urlencode( $group ), esc_html( $group ) );
	return $linked_group;
}

/**
 * Returns subject with link.
 *
 * @return string
 */
function humcore_linkify_subject( $subject ) {

	$linked_subject = sprintf( '<a href="/deposits/?facets[subject_facet][]=%s">%s</a>', urlencode( $subject ), $subject );
	return $linked_subject;
}

/**
 * Returns tag with link.
 *
 * @return string
 */
function humcore_linkify_tag( $tag ) {

	$linked_tag = sprintf( '<a href="/deposits/?tag=%s">%s</a>', urlencode( $tag ), $tag );
	return $linked_tag;
}

/**
 * Returns author with facet link and optional link to profile.
 *
 * @return string
 */
function humcore_linkify_author( $author, $author_meta, $author_type ) {

	$displayed_username = bp_get_displayed_user_username();

	if ( 'creator' === $author_type ) {
		$page_type = 'groups';
	} else {
		$page_type = 'members';
	}

	if ( ( ! empty( $author_meta ) && 'null' != $author_meta ) &&
		( ( 'members' === $page_type && $displayed_username != $author_meta ) ||
		( 'groups' === $page_type && ! bp_is_group() ) ) ) {
		$profile = sprintf( ' <a href="/%s/%s/deposits/">(see profile)</a> ', $page_type, $author_meta );
	} else {
		$profile = '';
	}
	$linked_author = sprintf( '<a href="/deposits/?facets[author_facet][]=%s">%s</a>%s', urlencode( $author ) , $author, $profile );

	return $linked_author;

}

/**
 * Returns license with link.
 *
 * @return string
 */
function humcore_linkify_license( $license ) {


$license_link_list = array();

        $license_link_list['All Rights Reserved'] = '';
        $license_link_list['Attribution'] = 'https://creativecommons.org/licenses/by/4.0/';
        $license_link_list['Attribution-NonCommercial'] = 'https://creativecommons.org/licenses/by-nc/4.0/';
        $license_link_list['Attribution-ShareAlike'] = 'https://creativecommons.org/licenses/by-sa/4.0/';
        $license_link_list['Attribution-NonCommercial-ShareAlike'] = 'https://creativecommons.org/licenses/by-nc-sa/4.0/';
        $license_link_list['Attribution-NoDerivatives'] = 'https://creativecommons.org/licenses/by-nd/4.0/';
        $license_link_list['Attribution-NonCommercial-NoDerivatives'] = 'https://creativecommons.org/licenses/by-nc-nd/4.0/';
        $license_link_list['All-Rights-Granted'] = 'https://creativecommons.org/publicdomain/zero/1.0/';

	if ( ! empty( $license_link_list[$license] ) ) {
        	return sprintf( '<a onclick="target=' . "'" . '_blank' . "'" . '" href="%s">%s</a>', $license_link_list[$license], $license );
	} else {
        	return $license;
	}

}

/**
 * Returns group slug for a given group name.
 *
 * @return string
 */
function humcore_get_slug_from_name( $group_name ) {

	// Check cache for group slug.
	$group_slug = wp_cache_get( $group_name, 'humcore_get_slug_from_name' );

	if ( false === $group_slug ) {
		$group = BP_Groups_Group::search_groups( $group_name, 1 );
		$group_slug = groups_get_slug( $group['groups'][0]->group_id );
		wp_cache_set( $group_name, $group_slug, 'humcore_get_slug_from_name' );
	}

	return $group_slug;
}

/**
 * Returns group id for a given group name.
 *
 * @return string
 */
function humcore_get_id_from_name( $group_name ) {

	// Check cache for group slug.
	$group_id = wp_cache_get( $group_name, 'humcore_get_id_from_name' );

	if ( false === $group_id ) {
		$group = BP_Groups_Group::search_groups( $group_name, 1 );
		$group_id = $group['groups'][0]->group_id;
		wp_cache_set( $group_name, $group_id, 'humcore_get_id_from_name' );
	}

	return $group_id;
}

/**
 * Return the formatted author_info.
 *
 * @return string
 */
function humcore_deposits_format_author_info( $authors ) {

	$author_info = array();
	foreach ( $authors as $author ) {
		if ( ! empty( $author['given'] ) && ! empty( $author['family'] ) ) {
			$author_name = $author['family'] . ', ' . $author['given'];
		} else {
			$author_name = $author['fullname'];
		}
		if ( 'creator' === $author['role'] && ! empty( $author['uni'] ) ) {
			$author_id = $author['uni'] . ' : group : ' . $author['role'];
		} else if ( ! empty( $author['uni'] ) ) {
			$author_id = $author['uni'] . ' : personal : ' . $author['role'];
		} else {
			$author_id = 'null : personal : ' . $author['role'];
		}
		if ( ! empty( $author['affiliation'] ) ) {
			$author_org = $author['affiliation'];
		} else {
			$author_org = '';
		}
		if ( ! empty( $author['fullname'] ) ) {
			$author_info[] = implode( ' : ', array( $author_name, $author_id, $author_org ) ) . '; ';
		}
	}
	$formatted_author_info = implode( ' ', $author_info );

	return apply_filters( 'humcore_deposits_format_author_info', $formatted_author_info );

}

/**
 * Return the genre list.
 *
 * @return array
 */
function humcore_deposits_genre_list() {

	$genre_list = array();

	$genre_list['Abstract'] = 'Abstract';
	$genre_list['Article'] = 'Article';
	$genre_list['Bibliography'] = 'Bibliography';
	$genre_list['Blog Post'] = 'Blog post';
	$genre_list['Book'] = 'Book';
	$genre_list['Book chapter'] = 'Book chapter';
	$genre_list['Catalog'] = 'Catalog';
	$genre_list['Chart'] = 'Chart';
	$genre_list['Code or software'] = 'Code or software';
	$genre_list['Conference paper'] = 'Conference paper';
	$genre_list['Conference proceeding'] = 'Conference proceeding';
	$genre_list['Course material or learning objects'] = 'Course material or learning objects';
	$genre_list['Data set'] = 'Data set';
	$genre_list['Dissertation'] = 'Dissertation';
	$genre_list['Documentary'] = 'Documentary';
	$genre_list['Essay'] = 'Essay';
	$genre_list['Fictional work'] = 'Fictional work';
	$genre_list['Finding aid'] = 'Finding aid';
	$genre_list['Image'] = 'Image';
	$genre_list['Interview'] = 'Interview';
	$genre_list['Map'] = 'Map';
	$genre_list['Music'] = 'Music';
	$genre_list['Performance'] = 'Performance';
	$genre_list['Photograph'] = 'Photograph';
	$genre_list['Poetry'] = 'Poetry';
	$genre_list['Presentation'] = 'Presentation';
	$genre_list['Report'] = 'Report';
	$genre_list['Review'] = 'Review';
	$genre_list['Syllabus'] = 'Syllabus';
	$genre_list['Technical report'] = 'Technical report';
	$genre_list['Thesis'] = 'Thesis';
	$genre_list['Translation'] = 'Translation';
	$genre_list['Video essay'] = 'Video essay';
	$genre_list['Visual art'] = 'Visual art';
	$genre_list['White paper'] = 'White paper';
	$genre_list['Other'] = 'Other';

	return apply_filters( 'bp_humcore_deposits_genre_list', $genre_list );

}

/**
 * Return the group list.
 *
 * @return array
 */
function humcore_deposits_group_list() {

	/**
	 * Groups meta_query with relation OR is very slow, merge two sets results until this gets fixed.
	 */
	$groups_list = array();

	$args = array(
		'type' => 'alphabetical',
		'meta_query' => array(
			array(
				'key' => 'mla_oid',
				'value' => 'D',
				'compare' => 'LIKE',
			),
		),
		'per_page' => '500',
	);

	$d_groups = groups_get_groups( $args );

	foreach ( $d_groups['groups'] as $group ) {
		$groups_list[ $group->id ] = htmlspecialchars( stripslashes( $group->name ) );
	}

	$args = array(
		'type' => 'alphabetical',
		'meta_query' => array(
			array(
				'key' => 'mla_oid',
				'value' => 'G',
				'compare' => 'LIKE',
			),
		),
		'per_page' => '500',
	);

	$g_groups = groups_get_groups( $args );

	foreach ( $g_groups['groups'] as $group ) {
		$groups_list[ $group->id ] = htmlspecialchars( stripslashes( $group->name ) );
	}

	natcasesort( $groups_list );

	return apply_filters( 'humcore_deposits_group_list', $groups_list );

}

/**
 * Return the committee list the user is a member of.
 *
 * @param string $user_id User ID.
 * @return array
 */
function humcore_deposits_user_committee_list( $user_id ) {

	$committees_list = array();

	$args = array(
		'user_id' => $user_id,
		'type' => 'alphabetical',
		'meta_query' => array(
			array(
				'key' => 'mla_oid',
				'value' => 'M',
				'compare' => 'LIKE',
			),
		),
		'per_page' => '500',
	);

	$m_groups = groups_get_groups( $args );

	foreach ( $m_groups['groups'] as $group ) {
		$committees_list[ $group->id ] = strip_tags( stripslashes( $group->name ) );
	}

	return apply_filters( 'humcore_deposits_user_committee_list', $committees_list );

}

/**
 * Return the subject list.
 *
 * @return array
 */
function humcore_deposits_subject_list() {

	$subjects_list = array();

	$subject_terms = get_terms(
		'humcore_deposit_subject',
		array(
			'orderby' => 'name',
			'fields' => 'names',
			'hide_empty' => 0,
		)
	);
	foreach ( $subject_terms as $term ) {
		$subjects_list[ $term ] = $term;
	}

	natcasesort( $subjects_list );

	return apply_filters( 'bp_humcore_deposits_subject_list', $subjects_list );

}

/**
 * Return the keyword list.
 *
 * @return array
 */
function humcore_deposits_keyword_list() {

	$keywords_list = array();

	$keyword_terms = get_terms(
		'humcore_deposit_tag',
		array(
			'orderby' => 'name',
			'fields' => 'names',
			'hide_empty' => 0,
		)
	);
	foreach ( $keyword_terms as $term ) {
		$keywords_list[ $term ] = $term;
	}

	natcasesort( $keywords_list );

	return apply_filters( 'bp_humcore_deposits_keyword_list', $keywords_list );

}

/**
 * Return the license type list.
 *
 * @return array
 */
function humcore_deposits_license_type_list() {

	$license_type_list = array();

	$license_type_list['All Rights Reserved'] = 'All Rights Reserved';
	$license_type_list['Attribution'] = 'Attribution';
	$license_type_list['Attribution-NonCommercial'] = 'Attribution-NonCommercial';
	$license_type_list['Attribution-ShareAlike'] = 'Attribution-ShareAlike';
	$license_type_list['Attribution-NonCommercial-ShareAlike'] = 'Attribution-NonCommercial-ShareAlike';
	$license_type_list['Attribution-NoDerivatives'] = 'Attribution-NoDerivatives';
	$license_type_list['Attribution-NonCommercial-NoDerivatives'] = 'Attribution-NonCommercial-NoDerivatives';
	$license_type_list['All-Rights-Granted'] = 'All Rights Granted';

	return apply_filters( 'bp_humcore_deposits_license_type_list', $license_type_list );

}

/**
 * Return the resource type list
 *
 * @return array
 */
function humcore_deposits_resource_type_list() {

	$resource_type_list = array();

	$resource_type_list['Audio'] = 'Audio';
	$resource_type_list['Image'] = 'Image';
	$resource_type_list['Mixed material'] = 'Mixed material';
	$resource_type_list['Software'] = 'Software';
	$resource_type_list['Text'] = 'Text';
	$resource_type_list['Video'] = 'Video';

	return apply_filters( 'bp_humcore_deposits_resource_type_list', $resource_type_list );

}

/**
 * Return the search request querystring.
 *
 * @return string
 */
function humcore_get_search_request_querystring( $query_key = '' ) {

	if ( ! empty( $_POST ) ) {
		$current_request = $_POST;
	} else {
		$current_request = $_GET;
	}

	$request_params = array();
	if ( ! empty( $current_request ) ) {
		foreach ( $current_request as $key => $param ) {
			if ( empty( $query_key ) || $query_key == $key ) {
				if ( ! is_array( $param ) ) {
					if ( 'facets' == $key && ! empty( $_POST[ $key ] ) ) {
						// Facets from form post and facets from url query string are formatted differently.
						$request_params[] = $param;
					} else {
						$request_params[] = sprintf( '%1$s=%2$s', $key, $param );
					}
				} else {
					foreach ( $param as $param_key => $param_values ) {
						foreach ( $param_values as $param_value ) {
							$request_params[] = sprintf( '%1$s[%2$s][]=%3$s', $key, $param_key, urlencode( $param_value ) );
						}
					}
				}
			}
		}
	}

	return implode( '&', $request_params );

}

/**
 * Return a test DOI for 14 days only.
 *
 * @return object deposit
 */
function humcore_check_test_handle( $deposit_record ) {

	if ( ! is_array( $deposit_record ) && false !== strpos( $deposit_record->handle, '10.5072/FK2' ) &&
			date_create( '14 days ago' ) > date_create( $deposit_record->record_creation_date ) ) {
		$deposit_record->handle = sprintf( '%1$s/deposits/item/%2$s', bp_get_root_domain(), $deposit_record->pid );
	}
	return $deposit_record;

}
add_action( 'humcore_get_current_deposit', 'humcore_check_test_handle' );


/**
 * Return the author name and username.
 *
 * @return array author_meta
 */
function humcore_deposit_parse_author_info( $author_info, $element = 1 ) {

	$author_meta = array();
	$each_author_array = explode( ';', $author_info );

	foreach ( $each_author_array as $each_author_info ) {
		$author_fields = explode( ' : ', $each_author_info );
		if ( 5 == count( $author_fields ) ) {
			$author_meta[] = $author_fields[$element];
		}
	}

	return $author_meta;

}
