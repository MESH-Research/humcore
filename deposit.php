<?php
/**
 * Deposit transaction and related support functions.
 *
 * @package HumCORE
 * @subpackage Deposits
 */

	/**
	 * Process the uploaded file:
	 *
	 * Make a usable unique filename.
	 * Generate a thumb if necessary.
	 * Prepare the metadata sent to Fedora and Solr.
	 * Mint and reserve a DOI.
	 * For this uploaded file, we will create 2 objects in Fedora and 1 document in Solr and 2 posts.
	 * Get the next 2 object id values for Fedora.
	 * Add solr first, if Tika errors out we'll quit before updating Fedora and WordPress.
	 * Create the aggregator post so that we can reference the ID in the Solr document.
	 * Set object terms for subjects.
	 * Add any new keywords and set object terms for tags.
	 * Add to metadata and store in post meta.
	 * Index the deposit content and metadata in Solr.
	 * Create the aggregator Fedora object along with the DC and RELS-EXT datastreams.
	 * Upload the MODS file to the Fedora server temp file storage.
	 * Create the descMetadata datastream for the aggregator object.
	 * Upload the deposited file to the Fedora server temp file storage.
	 * Create the CONTENT datastream for the resource object.
	 * Upload the thumb to the Fedora server temp file storage if necessary.
	 * Prepare an array of post data for the resource post.
	 * Insert the resource post.
	 * Add the activity entry for the author.
	 * Publish the reserved DOI.
	 * Add any group activity entries.
	 */
	function humcore_deposit_file () {

		if ( empty( $_POST ) ) {
			return false;
		}

		global $fedora_api, $solr_client;

		$upload_error_message = '';
		if ( empty( $_POST['selected_file_name'] ) ) {
			// Do something!
			$upload_error_message = __( 'No file was uploaded! Please press "Select File" and upload a file first.', 'humcore_domain' );
		} elseif ( 0 == $_POST['selected_file_size'] ) {
			$upload_error_message = sprintf( __( '%1$s appears to be empty, please choose another file.', 'humcore_domain' ), sanitize_file_name( $_POST['selected_file_name'] ) );
		}
		if ( ! empty( $upload_error_message ) ) {
			echo '<div id="message" class="info"><p>' . $upload_error_message . '</p></div>'; // XSS OK.
			return false;
		}

		// Single file uploads at this point.
		$tempname = sanitize_file_name( $_POST['selected_temp_name'] );
		$fileloc = $fedora_api->tempDir . '/' . $tempname;
		$filename = strtolower( sanitize_file_name( $_POST['selected_file_name'] ) );
		$filesize = sanitize_text_field( $_POST['selected_file_size'] );
		$renamed_file = $fileloc . '.' . $filename;
		$MODS_file = $fileloc . '.MODS.' . $filename . '.xml';
		$datastream_id = 'CONTENT';
		$thumb_datastream_id = 'THUMB';
		$generated_thumb_name = '';

		// Make a usable unique filename.
		if ( file_exists( $fileloc ) ) {
			$file_rename_status = rename( $fileloc, $renamed_file );
		}
		// TODO handle file error.
		$check_filetype = wp_check_filetype( $filename, wp_get_mime_types() );
		$filetype = $check_filetype['type'];

		if ( preg_match( '~^image/~', $check_filetype['type'] ) ) {
			$thumb_image = wp_get_image_editor( $renamed_file );
			if ( ! is_wp_error( $thumb_image ) ) {
				$current_size = $thumb_image->get_size();
				$thumb_image->resize( 150, 150, false );
				$thumb_filename = $thumb_image->generate_filename( 'thumb', $fedora_api->tempDir . '/', 'jpg' );
				$generated_thumb = $thumb_image->save( $thumb_filename, 'image/jpeg' );
				$generated_thumb_path = $generated_thumb['path'];
				$generated_thumb_name = str_replace( $tempname . '.', '', $generated_thumb['file'] );
				$generated_thumb_mime = $generated_thumb['mime-type'];
			} else {
				echo 'Error - thumb_image : ' . esc_html( $thumb_image->get_error_code() ) . '-' . esc_html( $thumb_image->get_error_message() );
				humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - thumb_image : %1$s-%2$s',  $thumb_image->get_error_code(), $thumb_image->get_error_message() ) );
			}
		}

		humcore_write_error_log( 'info', 'HumCORE deposit started' );

		/**
		 * For this uploaded file, we will create 2 objects in Fedora and 1 document in Solr.
		 * Get the next 2 object id values for Fedora.
		 */
		$nextPids = $fedora_api->get_next_pid( array( 'numPIDs' => '2', 'namespace' => $fedora_api->namespace ) );
		if ( is_wp_error( $nextPids ) ) {
			echo 'Error - nextPids : ' . esc_html( $nextPids->get_error_code() ) . '-' . esc_html( $nextPids->get_error_message() );
			humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - nextPids : %1$s-%2$s',  $nextPids->get_error_code(), $nextPids->get_error_message() ) );
			return false;
		}


		$metadata = prepare_metadata( $nextPids );

		$aggregatorXml = create_aggregator_xml( array(
								'pid' => $nextPids[0],
								'creator' => $metadata['creator'],
						 ) );

		$aggregatorRdf = create_aggregator_rdf( array(
								'pid' => $nextPids[0],
								'collectionPid' => $fedora_api->collectionPid,
						 ) );

		$aggregatorFoxml = create_foxml( array(
								'pid' => $nextPids[0],
								'label' => '',
								'xmlContent' => $aggregatorXml,
								'state' => 'Active',
								'rdfContent' => $aggregatorRdf,
						   ) );

		$metadataMODS = create_mods_xml( $metadata );

		$resourceXml = create_resource_xml( $metadata, $filetype );

		$resourceRdf = create_resource_rdf( array(
								'aggregatorPid' => $nextPids[0],
								'resourcePid' => $nextPids[1],
						) );

		$resourceFoxml = create_foxml( array(
							'pid' => $nextPids[1],
							'label' => $filename,
							'xmlContent' => $resourceXml,
							'state' => 'Active',
							'rdfContent' => $resourceRdf,
						 ) );
		// TODO handle file write error.
		$file_write_status = file_put_contents( $MODS_file, $metadataMODS );
                humcore_write_error_log( 'info', 'HumCORE deposit metadata complete' );

		/**
		 * Create the aggregator post now so that we can reference the ID in the Solr document.
		 */
		$deposit_post_data = array(
			'post_title'   => $metadata['title'],
			'post_excerpt' => $metadata['abstract'],
			'post_status'  => 'publish',
			'post_type'    => 'humcore_deposit',
			'post_name'    => $nextPids[0],
			'post_author'  => bp_loggedin_user_id()
		);

		$deposit_post_ID = wp_insert_post( $deposit_post_data );
		$metadata['record_identifier'] = $deposit_post_ID;

		/**
		 * Set object terms for subjects.
		 */
		if ( ! empty( $_POST['deposit-subject'] ) ) {
			$term_ids = array();
			foreach ( $_POST['deposit-subject'] as $subject ) {
				$term_key = term_exists( $subject, 'humcore_deposit_subject' );
				if ( ! is_wp_error( $term_key ) && ! empty( $term_key ) ) {
					$term_ids[] = intval( $term_key['term_id'] );
				} else {
					humcore_write_error_log( 'error', '*****HumCORE Deposit Error - bad subject*****' . var_export( $term_key, true ) );
				}
			}
			if ( ! empty( $term_ids ) ) {
				$term_taxonomy_ids = wp_set_object_terms( $deposit_post_ID, $term_ids, 'humcore_deposit_subject' );
				$metadata['subject_ids'] = $term_taxonomy_ids;
			}
		}

		/**
		 * Add any new keywords and set object terms for tags.
		 */
		if ( ! empty( $_POST['deposit-keyword'] ) ) {
			$term_ids = array();
			foreach ( $_POST['deposit-keyword'] as $keyword ) {
				$term_key = term_exists( $keyword, 'humcore_deposit_tag' );
				if ( empty( $term_key ) ) {
					$term_key = wp_insert_term( sanitize_text_field( $keyword ), 'humcore_deposit_tag' );
				}
				if ( ! is_wp_error( $term_key ) ) {
					$term_ids[] = intval( $term_key['term_id'] );
				} else {
					humcore_write_error_log( 'error', '*****HumCORE Deposit Error - bad tag*****' . var_export( $term_key, true ) );
				}
			}
			if ( ! empty( $term_ids ) ) {
				$term_taxonomy_ids = wp_set_object_terms( $deposit_post_ID, $term_ids, 'humcore_deposit_tag' );
				$metadata['keyword_ids'] = $term_taxonomy_ids;
			}
		}

		$json_metadata = json_encode( $metadata, JSON_HEX_APOS );
		if ( json_last_error() ) {
			humcore_write_error_log( 'error', '*****HumCORE Deposit Error***** Post Meta Encoding Error - Post ID: ' . $deposit_post_ID . ' - ' . json_last_error_msg() );
		}
		$post_meta_ID = update_post_meta( $deposit_post_ID, '_deposit_metadata', wp_slash( $json_metadata ) );
                humcore_write_error_log( 'info', 'HumCORE deposit - postmeta (1)', json_decode( $json_metadata, true ) );

		/**
		 * Add to metadata and store in post meta.
		 */
		$post_metadata['files'][] = array(
			'pid' => $nextPids[1],
			'datastream_id' => $datastream_id,
			'filename' => $filename,
			'filetype' => $filetype,
			'filesize' => $filesize,
			'fileloc' => $renamed_file,
			'thumb_datastream_id' => ( ! empty( $generated_thumb_name ) ) ? $thumb_datastream_id : '',
			'thumb_filename' => ( ! empty( $generated_thumb_name ) ) ? $generated_thumb_name : '',
		);

		$json_metadata = json_encode( $post_metadata, JSON_HEX_APOS );
		if ( json_last_error() ) {
			humcore_write_error_log( 'error', '*****HumCORE Deposit Error***** File Post Meta Encoding Error - Post ID: ' . $deposit_post_ID . ' - ' . json_last_error_msg() );
		}
		$post_meta_ID = update_post_meta( $deposit_post_ID, '_deposit_file_metadata', wp_slash( $json_metadata ) );
                humcore_write_error_log( 'info', 'HumCORE deposit - postmeta (2)', json_decode( $json_metadata, true ) );

		/**
		 * Add solr first, if Tika errors out we'll quit before updating Fedora and WordPress.
		 *
		 * Index the deposit content and metadata in Solr.
		 */
		try {
			if ( preg_match( '~^audio/|^image/|^video/~', $check_filetype['type'] ) ) {
				$sResult = $solr_client->create_humcore_document( '', $metadata );
			} else {
				$sResult = $solr_client->create_humcore_extract( $renamed_file, $metadata );
			}
		} catch ( Exception $e ) {
			if ( '500' == $e->getCode() && strpos( $e->getMessage(), 'TikaException' ) ) {
				try {
					$sResult = $solr_client->create_humcore_document( '', $metadata );
					humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - A Tika error occurred extracting text from the uploaded file. This deposit, %1$s, will be indexed using only the web form metadata.', $nextPids[0] ) );
				} catch ( Exception $e ) {
					echo '<h3>', __( 'An error occurred while depositing your file!', 'humcore_domain' ), '</h3>';
					humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - solr : %1$s-%2$s',  $e->getCode(), $e->getMessage() ) );
					wp_delete_post( $deposit_post_ID );
					return false;
				}
			} else {
				echo '<h3>', __( 'An error occurred while depositing your file!', 'humcore_domain' ), '</h3>';
				humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - solr : %1$s-%2$s',  $e->getCode(), $e->getMessage() ) );
				wp_delete_post( $deposit_post_ID );
				return false;
			}
		}

		/**
		 * Create the aggregator Fedora object along with the DC and RELS-EXT datastreams.
		 */
		$aIngest = $fedora_api->ingest( array( 'xmlContent' => $aggregatorFoxml ) );
		if ( is_wp_error( $aIngest ) ) {
			echo 'Error - aIngest : ' . esc_html( $aIngest->get_error_message() );
			humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - aIngest : %1$s-%2$s',  $aIngest->get_error_code(), $aIngest->get_error_message() ) );
			return false;
		}

		/**
		 * Upload the MODS file to the Fedora server temp file storage.
		 */
		$uploadMODS = $fedora_api->upload( array( 'file' => $MODS_file ) );
		if ( is_wp_error( $uploadMODS ) ) {
			echo 'Error - uploadMODS : ' . esc_html( $uploadMODS->get_error_message() );
			humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - uploadMODS : %1$s-%2$s',  $uploadMODS->get_error_code(), $uploadMODS->get_error_message() ) );
		}

		/**
		 * Create the descMetadata datastream for the aggregator object.
		 */
		$mContent = $fedora_api->add_datastream( array(
						'pid' => $nextPids[0],
						'dsID' => 'descMetadata',
						'controlGroup' => 'M',
						'dsLocation' => $uploadMODS,
						'dsLabel' => $metadata['title'],
						'versionable' => true,
						'dsState' => 'A',
						'mimeType' => 'text/xml',
						'content' => false,
					) );
		if ( is_wp_error( $mContent ) ) {
			echo esc_html( 'Error - mContent : ' . $mContent->get_error_message() );
			humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - mContent : %1$s-%2$s',  $mContent->get_error_code(), $mContent->get_error_message() ) );
		}

		$rIngest = $fedora_api->ingest( array( 'xmlContent' => $resourceFoxml ) );
		if ( is_wp_error( $rIngest ) ) {
			echo esc_html( 'Error - rIngest : ' . $rIngest->get_error_message() );
			humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - rIngest : %1$s-%2$s',  $rIngest->get_error_code(), $rIngest->get_error_message() ) );
		}

		/**
		 * Upload the deposit to the Fedora server temp file storage.
		 */
		$uploadUrl = $fedora_api->upload( array( 'file' => $renamed_file, 'filename' => $filename, 'filetype' => $filetype ) );
		if ( is_wp_error( $uploadUrl ) ) {
			echo 'Error - uploadUrl : ' . esc_html( $uploadUrl->get_error_message() );
			humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - uploadUrl (1) : %1$s-%2$s',  $uploadUrl->get_error_code(), $uploadUrl->get_error_message() ) );
		}

		/**
		 * Create the CONTENT datastream for the resource object.
		 */
		$rContent = $fedora_api->add_datastream( array(
						'pid' => $nextPids[1],
						'dsID' => $datastream_id,
						'controlGroup' => 'M',
						'dsLocation' => $uploadUrl,
						'dsLabel' => $filename,
						'versionable' => true,
						'dsState' => 'A',
						'mimeType' => $filetype,
						'content' => false,
					) );
		if ( is_wp_error( $rContent ) ) {
			echo 'Error - rContent : ' . esc_html( $rContent->get_error_message() );
			humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - rContent : %1$s-%2$s',  $rContent->get_error_code(), $rContent->get_error_message() ) );
		}

		/**
		 * Upload the thumb to the Fedora server temp file storage if necessary.
		 */
		if ( preg_match( '~^image/~', $check_filetype['type'] ) ) {

			$uploadUrl = $fedora_api->upload( array( 'file' => $generated_thumb_path, 'filename' => $generated_thumb_name, 'filetype' => $generated_thumb_mime ) );
			if ( is_wp_error( $uploadUrl ) ) {
				echo 'Error - uploadUrl : ' . esc_html( $uploadUrl->get_error_message() );
				humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - uploadUrl (2) : %1$s-%2$s',  $uploadUrl->get_error_code(), $uploadUrl->get_error_message() ) );
			}

			/**
			 * Create the THUMB datastream for the resource object if necessary.
			 */
			$tContent = $fedora_api->add_datastream( array(
							'pid' => $nextPids[1],
							'dsID' => $thumb_datastream_id,
							'controlGroup' => 'M',
							'dsLocation' => $uploadUrl,
							'dsLabel' => $generated_thumb_name,
							'versionable' => true,
							'dsState' => 'A',
							'mimeType' => $generated_thumb_mime,
							'content' => false,
						) );
			if ( is_wp_error( $tContent ) ) {
				echo 'Error - tContent : ' . esc_html( $tContent->get_error_message() );
				humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Error***** - tContent : %1$s-%2$s',  $tContent->get_error_code(), $tContent->get_error_message() ) );
			}
		}
                humcore_write_error_log( 'info', 'HumCORE deposit fedora/solr writes complete' );

		/**
		 * Prepare an array of post data for the resource post.
		 */
		$resource_post_data = array(
			'post_title'     => $filename,
			'post_status'    => 'publish',
			'post_type'      => 'humcore_deposit',
			'post_name'      => $nextPids[1],
			'post_author'    => bp_loggedin_user_id(),
			'post_parent'    => $deposit_post_ID,
		);

		/**
		 * Insert the resource post.
		 */
		$resource_id = wp_insert_post( $resource_post_data );

		$local_link = sprintf( bp_get_root_domain() . '/deposits/item/%s/', $nextPids[0] );

		/**
		 * Add the activity entry for the author.
		 */
		$activity_ID = humcore_new_deposit_activity( $deposit_post_ID, $metadata['abstract'], $local_link );

		/**
		 * Publish the reserved DOI.
		 */
		if ( ! empty( $metadata['deposit_doi'] ) ) {
			$eStatus = humcore_publish_handle( $metadata['deposit_doi'] );
			if ( false === $eStatus ) {
				echo '<h3>', __( 'There was an EZID API error, the DOI was not sucessfully published.', 'humcore_domain' ), '</h3><br />';
			}
                        humcore_write_error_log( 'info', 'HumCORE deposit DOI published' );
		}

		/**
		 * Add any group activity entries.
		 */
		$group_activity_ids = array();
		// Moving here due to possible timeout issues.
		if ( ! empty( $_POST['deposit-group'] ) ) {
			foreach ( $_POST['deposit-group'] as $group_id ) {
				$group_activity_ids[] = humcore_new_group_deposit_activity( $deposit_post_ID, sanitize_text_field( $group_id ), $metadata['abstract'], $local_link );
			}
		}

                humcore_write_error_log( 'info', 'HumCORE deposit transaction complete' );
		echo '<h3>', __( 'Deposit complete!', 'humcore_domain' ), '</h3><br />';
		return $nextPids[0];

	}

	/**
	 * Prepare the metadata sent to Fedora and Solr from $_POST input.
	 *
	 * @param array $nextPids Array of fedora pids.
	 * @return array metadata content
	 */
	function prepare_metadata( $nextPids ) {

		global $fedora_api;

		/**
		 * Prepare the metadata to be sent to Fedora and Solr.
		 */
		$metadata = array();
		$metadata['id'] = $nextPids[0];
		$metadata['pid'] = $nextPids[0];
		$metadata['creator'] = 'HumCORE';
		$metadata['title'] = wp_strip_all_tags( stripslashes( $_POST['deposit-title-unchanged'] ) );
		$metadata['title_unchanged'] = wp_kses(
				stripslashes( $_POST['deposit-title-unchanged'] ),
				array( 'b' => array(), 'em' => array(), 'strong' => array() )
			);
		$metadata['abstract'] = wp_strip_all_tags( stripslashes( $_POST['deposit-abstract-unchanged'] ) );
		$metadata['abstract_unchanged'] = wp_kses(
				stripslashes( $_POST['deposit-abstract-unchanged'] ),
				array( 'b' => array(), 'em' => array(), 'strong' => array() )
			);
		$metadata['genre'] = sanitize_text_field( $_POST['deposit-genre'] );
		$metadata['committee_deposit'] = sanitize_text_field( $_POST['deposit-on-behalf-flag'] );
		$metadata['committee_id'] = sanitize_text_field( $_POST['deposit-committee'] );
		$metadata['submitter'] = bp_loggedin_user_id();

		/**
		 * Get committee or author metadata.
		 */

		if ( 'yes' === $metadata['committee_deposit'] ) {
			$committee = groups_get_group( array( 'group_id' => $metadata['committee_id'] ) );
			$metadata['organization'] = 'MLA';
			$metadata['authors'][] = array(
				'fullname' => $committee->name,
				'given' => '',
				'family' => '',
				'uni' => $committee->slug,
				'role' => 'creator',
				'affiliation' => 'MLA',
			);
		} else {
			$user_id = bp_loggedin_user_id();
			$user_firstname = get_the_author_meta( 'first_name', $user_id );
			$user_lastname = get_the_author_meta( 'last_name', $user_id );
			$user_affiliation = bp_get_profile_field_data( array( 'field' => 2, 'user_id' => $user_id ) );
			$metadata['organization'] = $user_affiliation;
			$metadata['authors'][] = array(
				'fullname' => bp_get_loggedin_user_fullname(),
				'given' => $user_firstname,
				'family' => $user_lastname,
				'uni' => bp_get_loggedin_user_username(),
				'role' => 'author',
				'affiliation' => $user_affiliation,
			);
		}

		if ( ( empty( $metadata['committee_deposit'] ) || 'yes' !== $metadata['committee_deposit'] ) &&
			( ! empty( $_POST['deposit-other-authors-first-name'] ) && ! empty( $_POST['deposit-other-authors-last-name'] ) ) ) {
			$other_authors = array_map( function ( $first_name, $last_name ) { return array( 'first_name' => sanitize_text_field( $first_name ), 'last_name' => sanitize_text_field( $last_name ) ); },
				$_POST['deposit-other-authors-first-name'], $_POST['deposit-other-authors-last-name']
			);
			foreach ( $other_authors as $author_array ) {
				if ( ! empty( $author_array['first_name'] ) && ! empty( $author_array['last_name'] ) ) {
					$mla_user = bp_activity_find_mentions( $author_array['first_name'] . $author_array['last_name'] );
					if ( ! empty( $mla_user ) ) {
						foreach ( $mla_user as $mla_userid => $mla_username ) {
							break;
						} // Only one, right?
						$author_name = bp_core_get_user_displayname( $mla_userid );
						$author_firstname = get_the_author_meta( 'first_name', $mla_userid );
						$author_lastname = get_the_author_meta( 'last_name', $mla_userid );
						$author_affiliation = bp_get_profile_field_data( array( 'field' => 2, 'user_id' => $mla_userid ) );
						$author_uni = $mla_username;
					} else {
						$author_firstname = $author_array['first_name'];
						$author_lastname = $author_array['last_name'];
						$author_name = trim( $author_firstname . ' ' . $author_lastname );
						$author_uni = '';
						$author_affiliation = '';
					}
					$metadata['authors'][] = array(
						'fullname' => $author_name,
						'given' => $author_firstname,
						'family' => $author_lastname,
						'uni' => $author_uni,
						'role' => 'author',
						'affiliation' => $author_affiliation,
					);
				}
			}
		}

		usort( $metadata['authors'], function( $a, $b ) {
			return strcasecmp( $a['family'], $b['family'] );
		} );

		/**
		 * Format author info for solr.
		 */
		$metadata['author_info'] = humcore_deposits_format_author_info( $metadata['authors'] );

		if ( ! empty( $metadata['genre'] ) && in_array( $metadata['genre'], array( 'Dissertation', 'Technical report', 'Thesis' ) ) && ! empty( $_POST['deposit-institution'] ) ) {
			$metadata['institution'] = sanitize_text_field( $_POST['deposit-institution'] );
		} else if ( ! empty( $metadata['genre'] ) && in_array( $metadata['genre'], array( 'Dissertation', 'Technical report', 'Thesis' ) ) && empty( $_POST['deposit-institution'] ) ) {
			$metadata['institution'] = $metadata['organization'];
		}

		if ( ! empty( $metadata['genre'] ) && ( 'Conference proceeding' == $metadata['genre'] || 'Conference paper' == $metadata['genre'] ) ) {
			$metadata['conference_title'] = sanitize_text_field( $_POST['deposit-conference-title'] );
			$metadata['conference_organization'] = sanitize_text_field( $_POST['deposit-conference-organization'] );
			$metadata['conference_location'] = sanitize_text_field( $_POST['deposit-conference-location'] );
			$metadata['conference_date'] = sanitize_text_field( $_POST['deposit-conference-date'] );
		}

		if ( ! empty( $metadata['genre'] ) && 'Presentation' == $metadata['genre'] ) {
			$metadata['meeting_title'] = sanitize_text_field( $_POST['deposit-meeting-title'] );
			$metadata['meeting_organization'] = sanitize_text_field( $_POST['deposit-meeting-organization'] );
			$metadata['meeting_location'] = sanitize_text_field( $_POST['deposit-meeting-location'] );
			$metadata['meeting_date'] = sanitize_text_field( $_POST['deposit-meeting-date'] );
		}

		$metadata['group'] = array();
		if ( ! empty( $_POST['deposit-group'] ) ) {
			foreach ( $_POST['deposit-group'] as $group_id ) {
				$group = groups_get_group( array( 'group_id' => sanitize_text_field( $group_id ) ) );
				$metadata['group'][] = $group->name;
				$metadata['group_ids'][] = $group_id;
			}
		}

		$metadata['subject'] = array();
		if ( ! empty( $_POST['deposit-subject'] ) ) {
			foreach ( $_POST['deposit-subject'] as $subject ) {
				$metadata['subject'][] = sanitize_text_field( stripslashes( $subject ) );
				// Subject ids will be set later.
			}
		}

		$metadata['keyword'] = array();
		if ( ! empty( $_POST['deposit-keyword'] ) ) {
			foreach ( $_POST['deposit-keyword'] as $keyword ) {
				$metadata['keyword'][] = sanitize_text_field( stripslashes( $keyword ) );
				// Keyword ids will be set later.
			}
		}

		$metadata['type_of_resource'] = sanitize_text_field( $_POST['deposit-resource-type'] );
		$metadata['language'] = 'English';
		$metadata['notes'] = sanitize_text_field( stripslashes( $_POST['deposit-notes-unchanged'] ) ); // Where do they go in MODS?
		$metadata['notes_unchanged'] = wp_kses(
				stripslashes( $_POST['deposit-notes-unchanged'] ),
				array( 'b' => array(), 'em' => array(), 'strong' => array() )
			);
		$metadata['type_of_license'] = sanitize_text_field( $_POST['deposit-license-type'] );
		$metadata['record_content_source'] = 'HumCORE';
		$metadata['record_creation_date'] = gmdate( 'Y-m-d\TH:i:s\Z' );
		$metadata['member_of'] = $fedora_api->collectionPid;
		$metadata['published'] = sanitize_text_field( $_POST['deposit-published'] ); // Not stored in solr.
		if ( ! empty( $_POST['deposit-publication-type'] ) ) {
			$metadata['publication-type'] = sanitize_text_field( $_POST['deposit-publication-type'] ); // Not stored in solr.
		} else {
			$metadata['publication-type'] = 'none';
		}

		if ( 'journal-article' == $metadata['publication-type'] ) {
			$metadata['publisher'] = sanitize_text_field( $_POST['deposit-journal-publisher'] );
			$metadata['date'] = sanitize_text_field( $_POST['deposit-journal-publish-date'] );
			if ( ! empty( $metadata['date'] ) ) {
				$metadata['date_issued'] = get_year_issued( $metadata['date'] );
			} else {
				$metadata['date_issued'] = date( 'Y', strtotime( 'today' ) );
			}
			$metadata['book_journal_title'] = sanitize_text_field( $_POST['deposit-journal-title'] );
			$metadata['volume'] = sanitize_text_field( $_POST['deposit-journal-volume'] );
			$metadata['issue'] = sanitize_text_field( $_POST['deposit-journal-issue'] );
			$metadata['start_page'] = sanitize_text_field( $_POST['deposit-journal-start-page'] );
			$metadata['end_page'] = sanitize_text_field( $_POST['deposit-journal-end-page'] );
			$metadata['issn'] = sanitize_text_field( $_POST['deposit-journal-issn'] );
			$metadata['doi'] = sanitize_text_field( $_POST['deposit-journal-doi'] );
		} elseif ( 'book-chapter' == $metadata['publication-type'] ) {
			$metadata['publisher'] = sanitize_text_field( $_POST['deposit-book-publisher'] );
			$metadata['date'] = sanitize_text_field( $_POST['deposit-book-publish-date'] );
			if ( ! empty( $metadata['date'] ) ) {
				$metadata['date_issued'] = get_year_issued( $metadata['date'] );
			} else {
				$metadata['date_issued'] = date( 'Y', strtotime( 'today' ) );
			}
			$metadata['book_journal_title'] = sanitize_text_field( $_POST['deposit-book-title'] );
			$metadata['book_author'] = sanitize_text_field( $_POST['deposit-book-author'] );
			$metadata['chapter'] = sanitize_text_field( $_POST['deposit-book-chapter'] );
			$metadata['start_page'] = sanitize_text_field( $_POST['deposit-book-start-page'] );
			$metadata['end_page'] = sanitize_text_field( $_POST['deposit-book-end-page'] );
			$metadata['isbn'] = sanitize_text_field( $_POST['deposit-book-isbn'] );
			$metadata['doi'] = sanitize_text_field( $_POST['deposit-book-doi'] );
		} elseif ( 'proceedings-article' == $metadata['publication-type'] ) {
			$metadata['publisher'] = sanitize_text_field( $_POST['deposit-proceeding-publisher'] );
			$metadata['date'] = sanitize_text_field( $_POST['deposit-proceeding-publish-date'] );
			if ( ! empty( $metadata['date'] ) ) {
				$metadata['date_issued'] = get_year_issued( $metadata['date'] );
			} else {
				$metadata['date_issued'] = date( 'Y', strtotime( 'today' ) );
			}
			$metadata['book_journal_title'] = sanitize_text_field( $_POST['deposit-proceeding-title'] );
			$metadata['start_page'] = sanitize_text_field( $_POST['deposit-proceeding-start-page'] );
			$metadata['end_page'] = sanitize_text_field( $_POST['deposit-proceeding-end-page'] );
			$metadata['doi'] = sanitize_text_field( $_POST['deposit-proceeding-doi'] );
		} elseif ( 'none' == $metadata['publication-type'] ) {
			$metadata['date'] = sanitize_text_field( $_POST['deposit-non-published-date'] );
			if ( ! empty( $metadata['date'] ) ) {
				$metadata['date_issued'] = get_year_issued( $metadata['date'] );
			} else {
				$metadata['date_issued'] = date( 'Y', strtotime( 'today' ) );
			}
		}

		/**
		 * Mint and reserve a DOI.
		 */
		$creators = array();
                foreach ( $metadata['authors'] as $author ) {
                        if ( ( 'author' === $author['role'] ) && ! empty( $author['fullname'] ) ) {
                                $creators[] = $author['fullname'];
                        }
                }
		$creator_list = implode( ',', $creators );

		$deposit_doi = humcore_create_handle(
				$metadata['title'],
				$nextPids[0],
				$creator_list,
				$metadata['genre'],
				$metadata['date_issued'],
				$metadata['publisher']
			);
		if ( ! $deposit_doi ) {
			$metadata['handle'] = sprintf( bp_get_root_domain() . '/deposits/item/%s/', $nextPids[0] );
			$metadata['deposit_doi'] = ''; // Not stored in solr.
		} else {
			$metadata['handle'] = 'http://dx.doi.org/' . str_replace( 'doi:', '', $deposit_doi );
			$metadata['deposit_doi'] = $deposit_doi; // Not stored in solr.
		}

		return $metadata;

	}

	/**
	 * Get the year from the date entered.
	 *
	 * @param string $date Date entered
	 * @return string Date in YYYY format
	 */
	function get_year_issued( $date_entered ) {

                $temp_date_entered = preg_replace(
			'~^(winter(?:/|)|spring(?:/|)|summer(?:/|)|fall(?:/|)|autumn(?:/|))+\s(\d{4})$~i',
			'Jan $2',
			$date_entered
		); // Custom publication date format.

                $temp_date_entered = preg_replace(
			'/^(\d{4})$/',
			'Jan $1',
			$temp_date_entered
		); // Workaround for when only YYYY is entered.

                $ambiguous_date = preg_match( '~^(\d{2})-(\d{2})-(\d{2}(?:\d{2})?)(?:\s.*?|)$~', $temp_date_entered, $matches );
                if ( 1 === $ambiguous_date ) { // Just deal with slashes.
                        $temp_date_entered = sprintf( '%1$s/%2$s/%3$s', $matches[1], $matches[2], $matches[3] );
                }

		$ambiguous_date = preg_match( '~^(\d{2})/(\d{2})/(\d{2}(?:\d{2})?)(?:\s.*?|)$~', $temp_date_entered, $matches );
		if ( 1 === $ambiguous_date && $matches[1] > 12 ) { // European date in d/m/y format will fail for dd > 12.
			$temp_date_entered = sprintf( '%1$s/%2$s/%3$s', $matches[2], $matches[1], $matches[3] );
		}

                $date_value = strtotime( $temp_date_entered );

                if ( false === $date_value ) {
			return date( 'Y', strtotime( 'today' ) ); // Give them something.
		}

                return date( 'Y', $date_value );

	}

	/**
	 * Format the xml used to create the DC datastream for the aggregator object.
	 *
	 * @param array $args Array of arguments.
	 * @return WP_Error|string xml content
	 * @see wp_parse_args()
	 */
	function create_aggregator_xml( $args ) {

		$defaults = array(
			'pid'               => '',
			'creator'           => 'HumCORE',
			'title'             => 'Generic Content Aggregator',
			'type'              => 'InteractiveResource',
		);
		$params = wp_parse_args( $args, $defaults );

		$pid = $params['pid'];
		$creator = $params['creator'];
		$title = $params['title'];
		$type = $params['type'];

		if ( empty( $pid ) ) {
			return new WP_Error( 'missingArg', 'PID is missing.' );
		}

		return '<oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
			xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd">
		  <dc:identifier>' . $pid . '</dc:identifier>
		  <dc:creator>' . $creator . '</dc:creator>
		  <dc:title>' . $title . '</dc:title>
		  <dc:type>' . $type . '</dc:type>
		</oai_dc:dc>';

	}

	/**
	 * Format the rdf used to create the RELS-EXT datastream for the aggregator object.
	 *
	 * @param array $args Array of arguments.
	 * @return WP_Error|string rdf content
	 * @see wp_parse_args()
	 */
	function create_aggregator_rdf( $args ) {

		$defaults = array(
			'pid'               => '',
			'collectionPid'     => '',
			'isCollection'      => false,
			'fedoraModel'       => 'ContentAggregator',
		);
		$params = wp_parse_args( $args, $defaults );

		$pid = $params['pid'];
		$collectionPid = $params['collectionPid'];
		$isCollection = $params['isCollection'];
		$fedoraModel = $params['fedoraModel'];

		if ( empty( $pid ) ) {
			return new WP_Error( 'missingArg', 'PID is missing.' );
		}

		$memberOfMarkup = '';
		if ( ! empty( $collectionPid ) ) {
			$memberOfMarkup = sprintf( '<pcdm:memberOf rdf:resource="info:fedora/%1$s"></pcdm:memberOf>', $collectionPid );
		}

		$isCollectionMarkup = $isCollectionXmlns = '';
		if ( $isCollection ) {
			$isCollectionMarkup = '<isCollection xmlns="info:fedora/fedora-system:def/relations-external#">true</isCollection>';
		}

		return '<rdf:RDF xmlns:fedora-model="info:fedora/fedora-system:def/model#"
			xmlns:ore="http://www.openarchives.org/ore/terms/"
			xmlns:pcdm="http://pcdm.org/models#"
			xmlns:cc="http://creativecommons.org/ns#"
			xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
		  <rdf:Description rdf:about="info:fedora/' . $pid . '">
			<fedora-model:hasModel rdf:resource="info:fedora/ldpd:' . $fedoraModel . '"></fedora-model:hasModel>
			<rdf:type rdf:resource="http://pcdm.org/models#Object"></rdf:type>
			' . $isCollectionMarkup . '
			' . $memberOfMarkup . '
			<cc:license rdf:resource="info:fedora/"></cc:license>
		   </rdf:Description>
		</rdf:RDF>';

	}

	/**
	 * Format the xml used to create the DC datastream for the resource object.
	 *
	 * @param array $args Array of arguments.
	 * @return WP_Error|string xml content
	 * @see wp_parse_args()
	 */
	function create_resource_xml( $metadata, $filetype = '' ) {

		if ( empty( $metadata ) ) {
			return new WP_Error( 'missingArg', 'metadata is missing.' );
		}
		$pid = $metadata['pid'];
		if ( empty( $pid ) ) {
			return new WP_Error( 'missingArg', 'PID is missing.' );
		}
		$title = htmlspecialchars( $metadata['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
 		$type = htmlspecialchars( $metadata['genre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
		$description = htmlspecialchars( $metadata['abstract'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
		$creator_list = '';
                foreach ( $metadata['authors'] as $author ) {
                        if ( ( 'author' === $author['role'] ) && ! empty( $author['fullname'] ) ) {
                                $creator_list .= '
                                  <dc:creator>' . $author['fullname'] . '</dc:creator>';
                        }
                }

                $subject_list = '';
                foreach ( $metadata['subject'] as $subject ) {
                        $subject_list .= '
                        <dc:subject>' . htmlspecialchars( $subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '</dc:subject>';
                }
		if ( ! empty( $metadata['publisher'] ) ) {
			$publisher = '<dc:publisher>' . htmlspecialchars( $metadata['publisher'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '</dc:publisher>';
		}
                if ( ! empty( $metadata['date_issued'] ) ) {
                        $date .= '
                        <dc:date encoding="w3cdtf">' . $metadata['date_issued'] . '</dc:date>';
                }

		return '<oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
			xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd">
		  <dc:identifier>' . $pid . '</dc:identifier>
		  ' .$creator_list . '
		  ' . $date . '
		  <dc:title>' . $title . '</dc:title>
		  <dc:description>' . $description . '</dc:description>
		  ' . $subject_list . '
		  ' . $publisher . '
		  <dc:type>' . $type . '</dc:type>
		  <dc:format>' . $filetype . '</dc:format>
		</oai_dc:dc>';

	}

	/**
	 * Format the rdf used to create the RELS-EXT datastream for the aggregator object.
	 *
	 * @param array $args Array of arguments.
	 * @return WP_Error|string rdf content
	 * @see wp_parse_args()
	 */
	function create_resource_rdf( $args ) {

		$defaults = array(
			'aggregatorPid'     => '',
			'resourcePid'       => '',
			'collectionPid'     => '',
		);
		$params = wp_parse_args( $args, $defaults );

		$aggregatorPid = $params['aggregatorPid'];
		$resourcePid = $params['resourcePid'];
		$collectionPid = $params['collectionPid'];
		$collectionMarkup = '';

		if ( empty( $aggregatorPid ) ) {
			return new WP_Error( 'missingArg', 'PID is missing.' );
		}

		if ( empty( $resourcePid ) ) {
			return new WP_Error( 'missingArg', 'PID is missing.' );
		}

		if ( ! empty( $collectionPid ) ) {
			$collection_markup = sprintf( '<pcdm:memberOf rdf:resource="info:fedora/%1$s"></pcdm:memberOf>', $collectionPid );
		}

		return '<rdf:RDF xmlns:fedora-model="info:fedora/fedora-system:def/model#"
			xmlns:dcmi="http://purl.org/dc/terms/"
			xmlns:pcdm="http://pcdm.org/models#"
			xmlns:rel="info:fedora/fedora-system:def/relations-external#"
			xmlns:cc="http://creativecommons.org/ns#"
			xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
		  <rdf:Description rdf:about="info:fedora/' . $resourcePid . '">
			<fedora-model:hasModel rdf:resource="info:fedora/ldpd:Resource"></fedora-model:hasModel>
			<rdf:type rdf:resource="http://pcdm.org/models#File"></rdf:type>
			<pcdm:memberOf rdf:resource="info:fedora/' . $aggregatorPid . '"></pcdm:memberOf>
			' . $collectionMarkup . '
			<cc:license rdf:resource="info:fedora/"></cc:license>
		  </rdf:Description>
		</rdf:RDF>';

	}

	/**
	 * Format the foxml used to create Fedora aggregator and resource objects.
	 *
	 * @param array $args Array of arguments.
	 * @return WP_Error|string foxml content
	 * @see wp_parse_args()
	 */
	function create_foxml( $args ) {

		$defaults = array(
			'pid'               => '',
			'label'             => '',
			'xmlContent'        => '',
			'state'             => 'Active',
			'rdfContent'        => '',
		);
		$params = wp_parse_args( $args, $defaults );

		$pid = $params['pid'];
		$label = $params['label'];
		$xmlContent = $params['xmlContent'];
		$state = $params['state'];
		$rdfContent = $params['rdfContent'];

		if ( empty( $pid ) ) {
			return new WP_Error( 'missingArg', 'PID is missing.' );
		}

		if ( empty( $xmlContent ) ) {
			return new WP_Error( 'missingArg', 'XML string is missing.' );
		}

		if ( empty( $rdfContent ) ) {
			return new WP_Error( 'missingArg', 'RDF string is missing.' );
		}

		$output = '<?xml version="1.0" encoding="UTF-8"?>
					<foxml:digitalObject VERSION="1.1" PID="' . $pid . '"
						xmlns:foxml="info:fedora/fedora-system:def/foxml#"
						xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
						xsi:schemaLocation="info:fedora/fedora-system:def/foxml# http://www.fedora.info/definitions/1/0/foxml1-1.xsd">
						<foxml:objectProperties>
							<foxml:property NAME="info:fedora/fedora-system:def/model#state" VALUE="' . $state . '"/>
							<foxml:property NAME="info:fedora/fedora-system:def/model#label" VALUE="' . $label . '"/>
						</foxml:objectProperties>
						<foxml:datastream ID="DC" STATE="A" CONTROL_GROUP="X" VERSIONABLE="true">
							<foxml:datastreamVersion ID="DC1.0" LABEL="Dublin Core Record for this object"
									CREATED="' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '" MIMETYPE="text/xml"
									FORMAT_URI="http://www.openarchives.org/OAI/2.0/oai_dc/" SIZE="' . strlen( $xmlContent ) . '">
								<foxml:xmlContent>' . $xmlContent . '</foxml:xmlContent>
							</foxml:datastreamVersion>
						</foxml:datastream>
						<foxml:datastream ID="RELS-EXT" STATE="A" CONTROL_GROUP="X" VERSIONABLE="true">
							<foxml:datastreamVersion ID="RELS-EXT1.0" LABEL="RDF Statements about this object"
									CREATED="' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '" MIMETYPE="application/rdf+xml"
									FORMAT_URI="info:fedora/fedora-system:FedoraRELSExt-1.0" SIZE="' . strlen( $rdfContent ) . '">
								<foxml:xmlContent>' . $rdfContent . '</foxml:xmlContent>
							</foxml:datastreamVersion>
						</foxml:datastream>
					</foxml:digitalObject>';

		$dom = new DOMDocument;
		$dom->preserveWhiteSpace = false;
		if ( false === $dom->loadXML( $output ) ) {
			humcore_write_error_log( 'error', '*****HumCORE Error - bad xml content*****' . var_export( $pid, true ) );
		}
		$dom->formatOutput = true;
		return $dom->saveXML();

	}

	/**
	 * Format the xml used to create the CONTENT datastream for the MODS metadata object.
	 *
	 * @param array $metadata
	 * @return WP_Error|string mods xml content
	 */
	function create_mods_xml( $metadata ) {

		/**
		 * Format MODS xml fragment for one or more authors.
		 */
		$authorMODS = '';
		foreach ( $metadata['authors'] as $author ) {

			if ( 'creator' === $author['role'] ) {
				$authorMODS .= '
				<name type="corporate">';
			} else {
				if ( ! empty( $author['uni'] ) ) {
					$authorMODS .= '
					<name type="personal" ID="' . $author['uni'] . '">';
				} else {
					$authorMODS .= '
					<name type="personal">';
				}
			}

			if ( ( 'author' === $author['role'] ) && ( ! empty( $author['family'] ) || ! empty( $author['given'] ) ) ) {
				$authorMODS .= '
				  <namePart type="family">' . $author['family'] . '</namePart>
				  <namePart type="given">' . $author['given'] . '</namePart>';
			} else {
				$authorMODS .= '
				<namePart>' . $author['fullname'] . '</namePart>';
			}

			if ( 'creator' === $author['role'] ) {
				$authorMODS .= '
					<role>
						<roleTerm type="text">creator</roleTerm>
					</role>';
			} else {
				$authorMODS .= '
					<role>
						<roleTerm type="text">author</roleTerm>
					</role>';
			}

			if ( ! empty( $author['affiliation'] ) ) {
				$authorMODS .= '
				  <affiliation>' . htmlspecialchars( $author['affiliation'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '</affiliation>';
			}

			$authorMODS .= '
				</name>';

		}

		/**
		 * Format MODS xml fragment for organization affiliation.
		 */
		$orgMODS = '';
		if ( ! empty( $metadata['genre'] ) && in_array( $metadata['genre'], array( 'Dissertation', 'Technical report', 'Thesis' ) ) && ! empty( $metadata['institution'] ) ) {
			$orgMODS .= '
				<name type="corporate">
				  <namePart>
					' . htmlspecialchars( $metadata['institution'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '
				  </namePart>
				  <role>
					<roleTerm type="text">originator</roleTerm>
				  </role>
				</name>';
		}

		/**
		 * Format MODS xml fragment for date issued.
		 */
		$dateIssuedMODS = '';
		if ( ! empty( $metadata['date_issued'] ) ) {
			$dateIssuedMODS = '
			<originInfo>
				<dateIssued encoding="w3cdtf" keyDate="yes">' . $metadata['date_issued'] . '</dateIssued>
			</originInfo>';
		}

		/**
		 * Format MODS xml fragment for resource type.
		 */
		$resourceTypeMODS = '';
		if ( ! empty( $metadata['type_of_resource'] ) ) {
			$resourceTypeMODS = '
			<typeOfResource>' . $metadata['type_of_resource'] . '</typeOfResource>';
		}

		/**
		 * Format MODS xml fragment for genre.
		 */
		$genreMODS = '';
		if ( ! empty( $metadata['genre'] ) ) {
			$genreMODS = '
			<genre>' . htmlspecialchars( $metadata['genre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '</genre>';
		}

		/**
		 * Format MODS xml fragment for one or more subjects.
		 */
		$full_subject_list = $metadata['subject'];
		$subjectMODS = '';
		foreach ( $full_subject_list as $subject ) {

			$subjectMODS .= '
			<subject>
				<topic>' . htmlspecialchars( $subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '</topic>
			</subject>';
		}

		$relatedItemMODS = '';
		if ( 'journal-article' == $metadata['publication-type'] ) {
			$relatedItemMODS = '
				<relatedItem type="host">
					<titleInfo>';
			if ( ! empty( $metadata['book_journal_title'] ) ) {
				$relatedItemMODS .= '
						<title>' . htmlspecialchars( $metadata['book_journal_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '</title>';
			} else {
				$relatedItemMODS .= '
						<title/>';
			}
			$relatedItemMODS .= '
					</titleInfo>';
			if ( ! empty( $metadata['publisher'] ) ) {
				$relatedItemMODS .= '
					<originInfo>
						<publisher>' . htmlspecialchars( $metadata['publisher'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '</publisher>';
				if ( ! empty( $metadata['date_issued'] ) ) {
					$relatedItemMODS .= '
						<dateIssued encoding="w3cdtf">' . $metadata['date_issued'] . '</dateIssued>';
				}
				$relatedItemMODS .= '
					</originInfo>';
			}
			$relatedItemMODS .= '
					<part>';
			if ( ! empty( $metadata['volume'] ) ) {
				$relatedItemMODS .= '
						<detail type="volume">
							<number>' . $metadata['volume'] . '</number>
						</detail>';
			}
			if ( ! empty( $metadata['issue'] ) ) {
				$relatedItemMODS .= '
						<detail type="issue">
							<number>' . $metadata['issue'] . '</number>
						</detail>';
			}
			if ( ! empty( $metadata['start_page'] ) ) {
				$relatedItemMODS .= '
						<extent unit="page">
							<start>' . $metadata['start_page'] . '</start>
							<end>' . $metadata['end_page'] . '</end>
						</extent>';
			}
			if ( ! empty( $metadata['date'] ) ) {
				$relatedItemMODS .= '
						<date>' . $metadata['date'] . '</date>';
			}
			$relatedItemMODS .= '
					</part>';
			if ( ! empty( $metadata['doi'] ) ) {
				$relatedItemMODS .= '
					<identifier type="doi">' . $metadata['doi'] . '</identifier>';
			}
			if ( ! empty( $metadata['issn'] ) ) {
				$relatedItemMODS .= '
					<identifier type="issn">' . $metadata['issn'] . '</identifier>';
			}
			$relatedItemMODS .= '
				</relatedItem>';
		} elseif ( 'book-chapter' == $metadata['publication-type'] ) {
			$relatedItemMODS = '
				<relatedItem type="host">
					<titleInfo>';
			if ( ! empty( $metadata['book_journal_title'] ) ) {
				$relatedItemMODS .= '
						<title>' . htmlspecialchars( $metadata['book_journal_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '</title>';
			} else {
				$relatedItemMODS .= '
						<title/>';
			}
			$relatedItemMODS .= '
					</titleInfo>';
			if ( ! empty( $metadata['book_author'] ) ) {
				$relatedItemMODS .= '
						<name type="personal">
						<namePart>' . $metadata['book_author'] . '</namePart>
						<role>
						<roleTerm type="text">editor</roleTerm>
						</role>
					</name>';
			}
			if ( ! empty( $metadata['publisher'] ) ) {
				$relatedItemMODS .= '
					<originInfo>
						<publisher>' . htmlspecialchars( $metadata['publisher'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '</publisher>';
				if ( ! empty( $metadata['date_issued'] ) ) {
					$relatedItemMODS .= '
						<dateIssued encoding="w3cdtf">' . $metadata['date_issued'] . '</dateIssued>';
				}
				$relatedItemMODS .= '
					</originInfo>';
			}
			$relatedItemMODS .= '
					<part>';
			if ( ! empty( $metadata['chapter'] ) ) {
				$relatedItemMODS .= '
						<detail type="chapter">
							<number>' . $metadata['chapter'] . '</number>
						</detail>';
			}
			if ( ! empty( $metadata['start_page'] ) ) {
				$relatedItemMODS .= '
						<extent unit="page">
							<start>' . $metadata['start_page'] . '</start>
							<end>' . $metadata['end_page'] . '</end>
						</extent>';
			}
			if ( ! empty( $metadata['date'] ) ) {
				$relatedItemMODS .= '
						<date>' . $metadata['date'] . '</date>';
			}
			$relatedItemMODS .= '
					</part>';
			if ( ! empty( $metadata['doi'] ) ) {
				$relatedItemMODS .= '
					<identifier type="doi">' . $metadata['doi'] . '</identifier>';
			}
			if ( ! empty( $metadata['isbn'] ) ) {
				$relatedItemMODS .= '
					<identifier type="isbn">' . $metadata['isbn'] . '</identifier>';
			}
			$relatedItemMODS .= '
				</relatedItem>';
		} elseif ( ! empty( $metadata['genre'] ) && ( 'Conference proceeding' == $metadata['genre'] || 'Conference paper' == $metadata['genre'] ) ) {
			$relatedItemMODS = '
				<relatedItem type="host">
					<titleInfo>';
			if ( ! empty( $metadata['conference_title'] ) ) {
				$relatedItemMODS .= '
						<title>' . htmlspecialchars( $metadata['conference_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '</title>';
			} else {
				$relatedItemMODS .= '
						<title/>';
			}
			$relatedItemMODS .= '
					</titleInfo>';
			if ( ! empty( $metadata['publisher'] ) ) {
				$relatedItemMODS .= '
					<originInfo>
						<publisher>' . htmlspecialchars( $metadata['publisher'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '</publisher>';
				if ( ! empty( $metadata['date_issued'] ) ) {
					$relatedItemMODS .= '
						<dateIssued encoding="w3cdtf">' . $metadata['date_issued'] . '</dateIssued>';
				}
				$relatedItemMODS .= '
					</originInfo>';
			}
			$relatedItemMODS .= '
				</relatedItem>';
		}

		/**
		 * Format the xml used to create the CONTENT datastream for the MODS metadata object.
		 */
		$metadataMODS = '<mods xmlns="http://www.loc.gov/mods/v3"
		  xmlns:xlink="http://www.w3.org/1999/xlink"
		  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		  xsi:schemaLocation="http://www.loc.gov/mods/v3 http://www.loc.gov/standards/mods/v3/mods-3-4.xsd">
			<titleInfo>
				<title>' . htmlspecialchars( $metadata['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '</title>
			</titleInfo>
			' . $authorMODS . '
			' . $orgMODS . '
			' . $resourceTypeMODS . '
			' . $genreMODS . '
			' . $dateIssuedMODS . '
			<language>
				<languageTerm type="text">' . $metadata['language'] . '</languageTerm>
			</language>
			<abstract>' . htmlspecialchars( $metadata['abstract'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '</abstract>
			' . $subjectMODS . '
			' . $relatedItemMODS . '
			<recordInfo>
				<recordCreationDate encoding="w3cdtf">' . date( 'Y-m-d H:i:s O' ) . '</recordCreationDate>
				<languageOfCataloging>
					<languageTerm authority="iso639-2b">eng</languageTerm>
				</languageOfCataloging>
			</recordInfo>
		</mods>';

		return $metadataMODS;

}

	/**
	 * Format and ingest the foxml used to create a Fedora collection object.
	 * Really only needed once per install.
	 *
	 * Example usage:
	 * $cStatus = create_collection_object();
	 * var_export( $cStatus, true );
	 *
	 * @global object $fedora_api {@link Humcore_Deposit_Fedora_Api}
	 * @return WP_Error|string status
	 * @see wp_parse_args()
	 */
	function create_collection_object() {

		global $fedora_api;

		$nextPids = $fedora_api->get_next_pid( array( 'numPIDs' => '1', 'namespace' => $fedora_api->namespace . 'collection' ) );
		if ( is_wp_error( $nextPids ) ) {
			echo 'Error - nextPids : ' . esc_html( $nextPids->get_error_code() ) . '-' . esc_html( $nextPids->get_error_message() );
			return $nextPids;
		}

		$collectionXml = create_aggregator_xml( array(
								'pid' => $nextPids[0],
								'title' => 'Collection parent object for ' . $fedora_api->namespace,
								'type' => 'Collection',
						 ) );

		$collectionRdf = create_aggregator_rdf( array(
								'pid' => $nextPids[0],
								'collectionPid' => $fedora_api->collectionPid,
								'isCollection' => true,
								'fedoraModel' => 'BagAggregator',
						 ) );

		$collectionFoxml = create_foxml( array(
								'pid' => $nextPids[0],
								'label' => '',
								'xmlContent' => $collectionXml,
								'state' => 'Active',
								'rdfContent' => $collectionRdf,
						   ) );

		$cIngest = $fedora_api->ingest( array( 'xmlContent' => $collectionFoxml ) );
		if ( is_wp_error( $cIngest ) ) {
			echo 'Error - cIngest : ' . esc_html( $cIngest->get_error_message() );
			return $cIngest;
		}

		echo '<br />', __( 'Object Created: ', 'humcore_domain' ), date( 'Y-m-d H:i:s' ), var_export( $cIngest, true );
		return $cIngest;

	}
