<?php

	function humcore_deposit_edit_file () {

		if ( empty( $_POST ) ) {
			return false;
		}

		global $fedora_api, $solr_client;
		$tika_client = \Vaites\ApacheTika\Client::make('/srv/www/commons/current/vendor/tika/tika-app-1.16.jar'); // app mode 

		$curr_val = $_POST;
		//TODO post must exist and pid in post meta must match
		$deposit_post_id = sanitize_text_field( $curr_val['deposit_post_id'] );
		$deposit_post = get_post( $deposit_post_id );
		$resource_post_args = array(
			'post_parent'    => $deposit_post_id,
			'post_type'      => 'humcore_deposit',
			'posts_per_page' => 1,
		);
		$resource_post = get_posts( $resource_post_args );

		$deposit_post_metadata = json_decode( get_post_meta( $deposit_post_id, '_deposit_metadata', true ), true );
		$deposit_file_metadata = json_decode( get_post_meta( $deposit_post_id, '_deposit_file_metadata', true ), true );
		$file_metadata = $deposit_file_metadata;
		$fileloc = $deposit_file_metadata['files'][0]['fileloc'];
		$filetype = $deposit_file_metadata['files'][0]['filetype'];
		$filename = $deposit_file_metadata['files'][0]['filename'];
		$filesize = $deposit_file_metadata['files'][0]['filesize'];
		$prev_pathname = pathinfo( $deposit_file_metadata['files'][0]['fileloc'], PATHINFO_DIRNAME );
		$full_prev_tempname = pathinfo( $deposit_file_metadata['files'][0]['fileloc'], PATHINFO_BASENAME );
		$prev_tempname = str_replace( '.' . $deposit_file_metadata['files'][0]['filename'], '', $full_prev_tempname );
		$MODS_file = $prev_pathname . '/' . $prev_tempname . '.MODS.' . $filename . '.xml';
		$upload_error_message = '';
		if ( empty( $curr_val['selected_file_name'] ) ) {
			// Do something!
			$upload_error_message = __( 'No file was uploaded! Please press "Select File" and upload a file first.', 'humcore_domain' );
		} elseif ( 0 == $curr_val['selected_file_size'] ) {
			$upload_error_message = sprintf( __( '%1$s appears to be empty, please choose another file.', 'humcore_domain' ),
				sanitize_file_name( $curr_val['selected_file_name'] ) );
		}
		if ( ! empty( $upload_error_message ) ) {
			echo '<div id="message" class="info"><p>' . $upload_error_message . '</p></div>'; // XSS OK.
			return false;
		}
		$user = get_user_by( 'ID', sanitize_text_field( $deposit_post_metadata['submitter'] ) );
		$society_id = $deposit_post_metadata['society_id'];

		// Single file uploads at this point.
		$tempname = sanitize_file_name( $curr_val['selected_temp_name'] );
		$file_changed = false;
		if ( $prev_tempname !== $tempname ) {
			$file_changed = true;
			$time = current_time( 'mysql' );
			$y = substr( $time, 0, 4 );
			$m = substr( $time, 5, 2 );
			$yyyy_mm = "$y/$m";
			$fileloc = $fedora_api->tempDir . '/' . $yyyy_mm . '/' . $tempname;
			$filename = strtolower( sanitize_file_name( $curr_val['selected_file_name'] ) );
			$filesize = sanitize_text_field( $curr_val['selected_file_size'] );
			$renamed_file = pathinfo( $deposit_file_metadata['files'][0]['fileloc'], PATHINFO_BASENAME );
			$MODS_file = $fileloc . '.MODS.' . $filename . '.xml';
			$filename_dir = pathinfo( $renamed_file, PATHINFO_DIRNAME );
			$datastream_id = 'CONTENT';
			$thumb_datastream_id = 'THUMB';
			$generated_thumb_name = '';
			$renamed_file = $fileloc . '.' . $filename;
		}
		if ( $file_changed ) {
			// Make a usable unique filename.
			if ( file_exists( $fileloc ) ) {
				$file_rename_status = rename( $fileloc, $renamed_file );
			}
			// TODO handle file error.
			$check_filetype = wp_check_filetype( $filename, wp_get_mime_types() );
			$filetype = $check_filetype['type'];

			//TODO fix thumbs if ( preg_match( '~^image/|/pdf$~', $check_filetype['type'] ) ) {
			if ( preg_match( '~^image/$~', $filetype ) ) {
				$thumb_image = wp_get_image_editor( $renamed_file );
				if ( ! is_wp_error( $thumb_image ) ) {
					$current_size = $thumb_image->get_size();
					$thumb_image->resize( 150, 150, false );
					$thumb_image->set_quality( 95 );
					$thumb_filename = $thumb_image->generate_filename( 'thumb', $filename_dir . '/' . $yyyy_mm . '/', 'jpg' );
					$generated_thumb = $thumb_image->save( $thumb_filename, 'image/jpeg' );
					$generated_thumb_path = $generated_thumb['path'];
					$generated_thumb_name = str_replace( $tempname . '.', '', $generated_thumb['file'] );
					$generated_thumb_mime = $generated_thumb['mime-type'];
				} else {
					echo 'Error - thumb_image : ' . esc_html( $thumb_image->get_error_code() ) . '-' .
							esc_html( $thumb_image->get_error_message() );
					humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Edit Error***** - thumb_image : %1$s-%2$s',
								$thumb_image->get_error_code(), $thumb_image->get_error_message() ) );
				}
			}
		}

		humcore_write_error_log( 'info', 'HumCORE Deposit Edit started' );
		if ( $file_changed ) {
			humcore_write_error_log( 'info', 'HumCORE Deposit Edit - check_filetype ' . var_export( $check_filetype, true ) );
			if ( ! empty( $thumb_image ) ) {
				humcore_write_error_log( 'info', 'HumCORE Deposit Edit - thumb_image ' . var_export( $thumb_image, true ) );
			}
		}

		$nextPids = array();
		$nextPids[] = $deposit_post_metadata['pid'];
		$nextPids[] = $deposit_file_metadata['files'][0]['pid'];

		$metadata = prepare_user_entered_metadata( $user, $curr_val );
		$metadata['id'] = $nextPids[0];
		$metadata['pid'] = $nextPids[0];
		$metadata['creator'] = $deposit_post_metadata['creator'];
		$metadata['submitter'] = $deposit_post_metadata['submitter'];
		$metadata['society_id'] = $society_id;
		$metadata['handle'] = $deposit_post_metadata['handle'];
		$metadata['deposit_doi'] = $deposit_post_metadata['deposit_doi'];
		$metadata['member_of'] = $deposit_post_metadata['member_of'];
		$metadata['record_identifier'] = $deposit_post_metadata['record_identifier'];
		$metadata['record_content_source'] = $deposit_post_metadata['record_content_source'];
		$metadata['record_creation_date'] = $deposit_post_metadata['record_creation_date'];
		$metadata['record_change_date'] = gmdate( 'Y-m-d\TH:i:s\Z' );
		$current_embargo_flag = $deposit_post_metadata['embargoed'];
		$current_post_date = $deposit_post->post_date;
		$current_post_status = $deposit_post->post_status;

		//TODO set these to handle hcadmin and embargo?
		//$deposit_activity_needed = true;
		//$deposit_review_needed = false;
		$deposit_post_date = $deposit_post->post_date;
		$deposit_post_status = $deposit_post->post_status;

		if ( 'yes' === $metadata['embargoed'] ) {
			//recalc embargo end date using original date
			$metadata['embargo_end_date'] = date( 'm/d/Y', strtotime( $deposit_post_metadata['record_creation_date'] . '+' .
				sanitize_text_field( $curr_val['deposit-embargo-length'] ) ) );
			$deposit_post_date = date( 'Y-m-d', strtotime( $metadata['embargo_end_date'] ) );
			$deposit_post_status = 'future';
		} else if ( 'future' === $current_post_status ) {
			$metadata['embargo_end_date'] = '';
			$deposit_post_date = date( 'Y-m-d', strtotime( $deposit_post_metadata['record_creation_date'] ) );
			$deposit_post_status = 'draft';
		}

		$metadataMODS = create_mods_xml( $metadata );

		$resourceXml = create_resource_xml( $metadata, $filetype );

		// TODO handle file write error.
		$file_write_status = file_put_contents( $MODS_file, $metadataMODS );
		humcore_write_error_log( 'info', 'HumCORE Deposit Edit metadata complete' );

		/**
		 * Set object terms for subjects.
		 */
		if ( ! empty( $metadata['subject'] ) ) {
			$term_ids = array();
			foreach ( $metadata['subject'] as $subject ) {
				$term_key = wpmn_term_exists( $subject, 'humcore_deposit_subject' );
				if ( ! is_wp_error( $term_key ) && ! empty( $term_key ) ) {
					$term_ids[] = intval( $term_key['term_id'] );
				} else {
					humcore_write_error_log( 'error', '*****HumCORE Deposit Edit Error - bad subject*****' .
						var_export( $term_key, true ) );
				}
			}
			if ( ! empty( $term_ids ) ) {
				$term_object_id = str_replace( $fedora_api->namespace . ':', '', $nextPids[0] );
				$term_taxonomy_ids = wpmn_set_object_terms( $term_object_id, $term_ids, 'humcore_deposit_subject' );
				$metadata['subject_ids'] = $term_taxonomy_ids;
			}
		}

		/**
		 * Add any new keywords and set object terms for tags.
		 */
		if ( ! empty( $metadata['keyword'] ) ) {
			$term_ids = array();
			foreach ( $metadata['keyword'] as $keyword ) {
				$term_key = wpmn_term_exists( $keyword, 'humcore_deposit_tag' );
				if ( empty( $term_key ) ) {
					$term_key = wpmn_insert_term( sanitize_text_field( $keyword ), 'humcore_deposit_tag' );
				}
				if ( ! is_wp_error( $term_key ) ) {
					$term_ids[] = intval( $term_key['term_id'] );
				} else {
					humcore_write_error_log( 'error', '*****HumCORE Deposit Edit Error - bad tag*****' .
						var_export( $term_key, true ) );
				}
			}
			if ( ! empty( $term_ids ) ) {
				$term_object_id = str_replace( $fedora_api->namespace . ':', '', $nextPids[0] );
				$term_taxonomy_ids = wpmn_set_object_terms( $term_object_id, $term_ids, 'humcore_deposit_tag' );
				$metadata['keyword_ids'] = $term_taxonomy_ids;
			}
		}

		/**
		 * Extract text first if small. If Tika errors out we'll index without full text.
		 */
		if ( ! preg_match( '~^audio/|^image/|^video/~', $filetype ) && (int)$filesize < 1000000 ) {
			try {
				$tika_text = $tika_client->getText( $renamed_file );
				$content = $tika_text;
			} catch ( Exception $e ) {
				humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Edit Error***** - ' .
					'A Tika error occurred extracting text from the uploaded file. This deposit, %1$s, ' . 
					'will be indexed using only the web form metadata.', $nextPids[0] ) );
				humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Edit Error***** - Tika error message: ' .
					$e->getMessage(), var_export( $e, true ) ) );
				$content='';
			}
		}

		/**
		 * Index the deposit content and metadata in Solr.
		 */
		try {
			if ( preg_match( '~^audio/|^image/|^video/~', $filetype ) ) {
				$sResult = $solr_client->create_humcore_document( '', $metadata );
			} else {
				//$sResult = $solr_client->create_humcore_extract( $renamed_file, $metadata ); //no longer using tika on server
				$sResult = $solr_client->create_humcore_document( $content, $metadata );
			}
		} catch ( Exception $e ) {
			if ( '500' == $e->getCode() && strpos( $e->getMessage(), 'TikaException' ) ) { // Only happens if tika is on the solr server.
				try {
					$sResult = $solr_client->create_humcore_document( '', $metadata );
					humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Edit Error***** - ' .
						'A Tika error occurred extracting text from the uploaded file. This deposit, ' .
						'%1$s, will be indexed using only the web form metadata.', $nextPids[0] ) );
				} catch ( Exception $e ) {
					echo '<h3>', __( 'An error occurred while editing your deposit!', 'humcore_domain' ), '</h3>';
					humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Edit Error***** - solr : %1$s-%2$s',
						$e->getCode(), $e->getMessage() ) );
					return false;
				}
			} else {
				echo '<h3>', __( 'An error occurred while editing your deposit!', 'humcore_domain' ), '</h3>';
				humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Edit Error***** - solr : %1$s-%2$s',
						$e->getCode(), $e->getMessage() ) );
				return false;
			}
		}

		/**
		 * Update the aggregator post
		 */
		$deposit_post_data = array(
			'ID'           => $deposit_post_id,
			'post_title'   => $metadata['title'],
			'post_excerpt' => $metadata['abstract'],
			'post_status'  => $deposit_post_status,
			'post_date'    => $deposit_post_date,
		);
		humcore_write_error_log( 'info', 'HumCORE Deposit Edit post data ' . var_export( $deposit_post_data, true ) );
		$deposit_post_update_status = wp_update_post( $deposit_post_data, true );

		$json_metadata = json_encode( $metadata, JSON_HEX_APOS );
		if ( json_last_error() ) {
			humcore_write_error_log( 'error', '*****HumCORE Deposit Edit Error***** Post Meta Encoding Error - Post ID: ' .
				$deposit_post_id . ' - ' . json_last_error_msg() );
		}
		$post_meta_update_status = update_post_meta( $deposit_post_id, '_deposit_metadata', wp_slash( $json_metadata ) );
		humcore_write_error_log( 'info', 'HumCORE Deposit Edit - postmeta (1)', json_decode( $json_metadata, true ) );

		/**
		 * Update the resource post.
		 */
		$resource_post_data = array(
			'ID'           => $resource_post[0]->ID,
			'post_title'   => $filename,
		);
		$resource_post_update_status = wp_update_post( $resource_post_data );

		/**
		 * Update metadata and store in post meta.
		 */
		if ( $file_changed ) {
			$file_metadata['files'][0]['filename'] = $filename;
			$file_metadata['files'][0]['filetype'] = $filetype;
			$file_metadata['files'][0]['filesize'] = $filesize;
			$file_metadata['files'][0]['fileloc'] = $renamed_file;
			$file_metadata['files'][0]['thumb_datastream_id'] = ( ! empty( $generated_thumb_name ) ) ? $thumb_datastream_id : '';
			$file_metadata['files'][0]['thumb_filename'] = ( ! empty( $generated_thumb_name ) ) ? $generated_thumb_name : '';

			$json_metadata = json_encode( $file_metadata, JSON_HEX_APOS );
			if ( json_last_error() ) {
				humcore_write_error_log( 'error', '*****HumCORE Deposit Edit Error***** File Post Meta Encoding Error - Post ID: ' .
							$deposit_post_id . ' - ' . json_last_error_msg() );
			}
			$file_meta_update_status = update_post_meta( $deposit_post_id, '_deposit_file_metadata', wp_slash( $json_metadata ) );
			humcore_write_error_log( 'info', 'HumCORE Deposit Edit - postmeta (2)', json_decode( $json_metadata, true ) );
		}

		/**
		 * Upload the MODS file to the Fedora server temp file storage.
		 */
		$uploadMODS = $fedora_api->upload( array( 'file' => $MODS_file ) );
		if ( is_wp_error( $uploadMODS ) ) {
			echo 'Error - uploadMODS : ' . esc_html( $uploadMODS->get_error_message() );
			humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Edit Error***** - uploadMODS : %1$s-%2$s',
				$uploadMODS->get_error_code(), $uploadMODS->get_error_message() ) );
		}

		/**
		 * Update the descMetadata datastream for the aggregator object.
		 */
		$mContent = $fedora_api->modify_datastream( array(
			'pid' => $nextPids[0],
			'dsID' => 'descMetadata',
			'dsLocation' => $uploadMODS,
			'dsLabel' => $metadata['title'],
			'mimeType' => 'text/xml',
			'content' => false,
		) );
		if ( is_wp_error( $mContent ) ) {
			echo esc_html( 'Error - mContent : ' . $mContent->get_error_message() );
			humcore_write_error_log( 'error', sprintf( '*****WP HumCORE Deposit Edit Error***** - mContent : %1$s-%2$s',
				$mContent->get_error_code(), $mContent->get_error_message() ) );
		}

		/**
		 * Upload the deposit to the Fedora server temp file storage.
		 */
		if ( $file_changed ) {
			$uploadUrl = $fedora_api->upload( array( 'file' => $renamed_file, 'filename' => $filename, 'filetype' => $filetype ) );
			if ( is_wp_error( $uploadUrl ) ) {
				echo 'Error - uploadUrl : ' . esc_html( $uploadUrl->get_error_message() );
				humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Edit Error***** - uploadUrl (1) : %1$s-%2$s',
					$uploadUrl->get_error_code(), $uploadUrl->get_error_message() ) );
			}
		}

		/**
		 * Update the CONTENT datastream for the resource object.
		 */
		if ( $file_changed ) {
			$rContent = $fedora_api->modify_datastream( array(
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
				printf( '*****Error***** - rContent : %1$s-%2$s',  $rContent->get_error_code(), $rContent->get_error_message() );
				echo "\n\r";
			}
		}

		if ( $file_changed ) {
			$oContent = $fedora_api->modify_object( array(
				'pid' => $nextPids[1],
				'label' => $filename,
			) );
			if ( is_wp_error( $oContent ) ) {
				printf( '*****Error***** - oContent : %1$s-%2$s',  $oContent->get_error_code(), $oContent->get_error_message() );
			}
		}

		/**
		 * Modify the metadata datastream
		 */
		$rDCContent = $fedora_api->modify_datastream( array(
			'pid' => $nextPids[1],
			'dsID' => 'DC',
			'mimeType' => 'text/xml',
			'content' => $resourceXml,
		) );
		if ( is_wp_error( $rDCContent ) ) {
			echo 'Error - rDCContent : ' . esc_html( $rDCContent->get_error_message() );
			humcore_write_error_log( 'error', sprintf( '*****WP HumCORE Deposit Edit Error***** - rDCContent : %1$s-%2$s',
				$rDCContent->get_error_code(), $rDCContent->get_error_message() ) );
		}

		/**
		 * Upload the thumb to the Fedora server temp file storage if necessary.
		 */
		if ( $file_changed ) {
			//TODO fix thumbs if ( preg_match( '~^image/|/pdf$~', $filetype ) && ! empty( $generated_thumb_path ) ) {
			if ( preg_match( '~^image/$~', $filetype ) && ! empty( $generated_thumb_path ) ) {

				$uploadUrl = $fedora_api->upload( array( 'file' => $generated_thumb_path, 'filename' => $generated_thumb_name,
					'filetype' => $generated_thumb_mime ) );
				if ( is_wp_error( $uploadUrl ) ) {
					echo 'Error - uploadUrl : ' . esc_html( $uploadUrl->get_error_message() );
					humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Edit Error***** - uploadUrl (2) : %1$s-%2$s',
						$uploadUrl->get_error_code(), $uploadUrl->get_error_message() ) );
				}

				/**
				 * Update the THUMB datastream for the resource object if necessary.
				 */
				$tContent = $fedora_api->modify_datastream( array(
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
					humcore_write_error_log( 'error', sprintf( '*****HumCORE Deposit Edit Error***** - tContent : %1$s-%2$s',
						$tContent->get_error_code(), $tContent->get_error_message() ) );
				}
			}
		}

		humcore_write_error_log( 'info', 'HumCORE Deposit Edit fedora/solr writes complete' );

		/**
		 * Add the activity entry for the author.
		 */
/* not needed for update
		if ( $deposit_activity_needed ) {
			$activity_ID = humcore_new_deposit_activity( $deposit_post_id, $metadata['abstract'], $local_link, $user->ID );
		}
*/

		/**
		 * Handle doi metadata changes.
		 */
		if ( ! empty( $metadata['deposit_doi'] ) ) {
			$creators = array();
			foreach ( $metadata['authors'] as $author ) {
				if ( ( in_array( $author['role'], array( 'creator', 'author', 'editor', 'translator' ) ) ) &&
					! empty( $author['fullname'] ) ) {
					$creators[] = $author['fullname'];
				}
			}
			$creator_list = implode( ',', $creators );

			$eStatus = humcore_modify_handle(
				$metadata['deposit_doi'],
				$metadata['title'],
				$creator_list,
				$metadata['genre'],
				$metadata['date_issued'],
				$metadata['publisher']
			);
			if ( false === $eStatus ) {
				humcore_write_error_log( 'error', 'There was an EZID API error, the DOI was not sucessfully modified.' );
			}
		}

		/**
		 * Add POST variables needed for async tika extraction
		 */
		$_POST['aggregator-post-id'] = $deposit_post_id;

		/**
		 * Re-index larger text based deposits in the background.
		 */
		if ( ! preg_match( '~^audio/|^image/|^video/~', $filetype ) && (int)$filesize >= 1000000 ) {
			do_action( 'humcore_tika_text_extraction' );
		}

		humcore_write_error_log( 'info', 'HumCORE Deposit Edit transaction complete' );
		echo '<h3>', __( 'Deposit edit complete!', 'humcore_domain' ), '</h3><br />';
		return $nextPids[0];

	}

	function humcore_prepare_edit_page_metadata( $curr_val ) {

		$metadata['submitter'] = $curr_val['submitter'];
		$deposit_author_user = get_user_by( 'ID', $curr_val['submitter'] );

		$metadata['deposit-title-unchanged'] = $curr_val['title_unchanged'];
		if ( empty( $metadata['deposit-title-unchanged'] ) ) {
			$metadata['deposit-title-unchanged'] = $curr_val['title'];
		}
		$metadata['deposit-abstract-unchanged'] = $curr_val['abstract_unchanged'];
		if ( empty( $metadata['deposit-abstract-unchanged'] ) ) {
			$metadata['deposit-abstract-unchanged'] = $curr_val['abstract'];
		}
		$metadata['deposit-notes-unchanged'] = $curr_val['notes_unchanged'];
		if ( empty( $metadata['deposit-notes-unchanged'] ) ) {
			$metadata['deposit-notes-unchanged'] = $curr_val['notes'];
		}
		$metadata['deposit-genre'] = $curr_val['genre'];
		$metadata['deposit-on-behalf-flag'] = $curr_val['committee_deposit'];
		if ( ! empty( $curr_val['committee_id'] ) ) {
			$metadata['deposit-committee'] = $curr_val['committee_id'];
		} else {
			$metadata['deposit-committee'] = '';
		}

		if ( 'yes' === $curr_val['committee_deposit'] ) {
			$committee = groups_get_group( array( 'group_id' => $curr_val['committee_id'] ) );
			$metadata['organization'] = strtoupper( $society_id );
			$metadata['authors'][] = array(
				'fullname' => $committee->name,
				'given' => '',
				'family' => '',
				'uni' => $committee->slug,
				'role' => 'creator',
				'affiliation' => strtoupper( $society_id ),
			);
		}

		$author_count = 0;
		if ( ! empty( $curr_val['authors'] ) ) {
			foreach( $curr_val['authors'] as $author ) {
				if ( $deposit_author_user->user_login === $author['uni'] && 'submitter' !== $author['role'] ) {
					$metadata['deposit-author-first-name'] = $author['given'];
					$metadata['deposit-author-last-name'] = $author['family'];
					$metadata['deposit-author-role'] = $author['role'];
					$metadata['deposit-author-uni'] = $author['uni'];
				} else if ( 'submitter' !== $author['role'] ) {
					$metadata['deposit-other-authors-first-name'][$author_count] = $author['given'];
					$metadata['deposit-other-authors-last-name'][$author_count] = $author['family'];
					$metadata['deposit-other-authors-role'][$author_count] = $author['role'];
					$metadata['deposit-other-authors-uni'][$author_count] = $author['uni'];
					$author_count++;
				}
			}
		}
		$metadata['deposit-institution'] = $curr_val['institution'];
		$metadata['deposit-conference-title'] = $curr_val['conference_title'];
		$metadata['deposit-conference-organization'] = $curr_val['conference_organization'];
		$metadata['deposit-conference-location'] = $curr_val['conference_location'];
		$metadata['deposit-conference-date'] = $curr_val['conference_date'];
		$metadata['deposit-meeting-title'] = $curr_val['meeting_title'];
		$metadata['deposit-meeting-organization'] = $curr_val['meeting_organization'];
		$metadata['deposit-meeting-location'] = $curr_val['meeting_location'];
		$metadata['deposit-meeting-date'] = $curr_val['meeting_date'];
		if ( ! empty( $curr_val['group_ids'] ) ) {
			foreach( $curr_val['group_ids'] as $group_id ) {
				$metadata['deposit-group'][] = $group_id;
			}
		}
		//use ids to remake list
		if ( ! empty( $curr_val['subject'] ) ) {
			foreach( $curr_val['subject'] as $subject ) {
				$metadata['deposit-subject'][] = $subject;
			}
		}
		//use ids to remake list
		if ( ! empty( $curr_val['keyword'] ) ) {
			foreach( $curr_val['keyword'] as $keyword ) {
				$metadata['deposit-keyword'][] = $keyword;
			}
		}
		$metadata['deposit-resource-type'] = $curr_val['type_of_resource'];
		$metadata['deposit-language'] = $curr_val['language'];
		$metadata['deposit-license-type'] = $curr_val['type_of_license'];
		$metadata['deposit-published'] = $curr_val['published'];
		$metadata['deposit-publication-type'] = $curr_val['publication-type'];

		if ( 'book' == $curr_val['publication-type'] ) {
			$metadata['deposit-book-publisher'] = $curr_val['publisher'];
			$metadata['deposit-book-publish-date'] = $curr_val['date'];
			$metadata['deposit-book-edition'] = $curr_val['edition'];
			$metadata['deposit-book-volume'] = $curr_val['volume'];
			$metadata['deposit-book-isbn'] = $curr_val['isbn'];
			$metadata['deposit-book-doi'] = $curr_val['doi'];
		} elseif ( 'book-chapter' == $curr_val['publication-type'] ) {
			$metadata['deposit-book-chapter-publisher'] = $curr_val['publisher'];
			$metadata['deposit-book-chapter-publish-date'] = $curr_val['date'];
			$metadata['deposit-book-chapter-title'] = $curr_val['book_journal_title'];
			$metadata['deposit-book-chapter-author'] = $curr_val['book_author'];
			$metadata['deposit-book-chapter-chapter'] = $curr_val['chapter'];
			$metadata['deposit-book-chapter-start-page'] = $curr_val['start_page'];
			$metadata['deposit-book-chapter-end-page'] = $curr_val['end_page'];
			$metadata['deposit-book-chapter-isbn'] = $curr_val['isbn'];
			$metadata['deposit-book-chapter-doi'] = $curr_val['doi'];
		} elseif ( 'book-review' == $curr_val['publication-type'] ) {
			$metadata['deposit-book-review-publisher'] = $curr_val['publisher'];
			$metadata['deposit-book-review-publish-date'] = $curr_val['date'];
			$metadata['deposit-book-chapter-isbn'] = $curr_val['isbn'];
			$metadata['deposit-book-review-doi'] = $curr_val['doi'];
		} elseif ( 'book-section' == $curr_val['publication-type'] ) {
			$metadata['deposit-book-section-publisher'] = $curr_val['publisher'];
			$metadata['deposit-book-section-publish-date'] = $curr_val['date'];
			$metadata['deposit-book-section-title'] = $curr_val['book_journal_title'];
			$metadata['deposit-book-section-author'] = $curr_val['book_author'];
			$metadata['deposit-book-section-edition'] = $curr_val['edition'];
			$metadata['deposit-book-section-start-page'] = $curr_val['start_page'];
			$metadata['deposit-book-section-end-page'] = $curr_val['end_page'];
			$metadata['deposit-book-section-isbn'] = $curr_val['isbn'];
			$metadata['deposit-book-section-doi'] = $curr_val['doi'];
		} elseif ( 'journal-article' == $curr_val['publication-type'] ) {
			$metadata['deposit-journal-publisher'] = $curr_val['publisher'];
			$metadata['deposit-journal-publish-date'] = $curr_val['date'];
			$metadata['deposit-journal-title'] = $curr_val['book_journal_title'];
			$metadata['deposit-journal-author'] = $curr_val['book_author'];
			$metadata['deposit-journal-volume'] = $curr_val['volume'];
			$metadata['deposit-journal-issue'] = $curr_val['issue'];
			$metadata['deposit-journal-start-page'] = $curr_val['start_page'];
			$metadata['deposit-journal-end-page'] = $curr_val['end_page'];
			$metadata['deposit-journal-issn'] = $curr_val['issn'];
			$metadata['deposit-journal-doi'] = $curr_val['doi'];
		} elseif ( 'magazine-section' == $curr_val['publication-type'] ) {
			$metadata['deposit-magazine-section-publish-date'] = $curr_val['date'];
			$metadata['deposit-magazine-section-title'] = $curr_val['book_journal_title'];
			$metadata['deposit-magazine-section-volume'] = $curr_val['volume'];
			$metadata['deposit-magazine-section-start-page'] = $curr_val['start_page'];
			$metadata['deposit-magazine-section-end-page'] = $curr_val['end_page'];
			$metadata['deposit-magazine-section-url'] = $curr_val['url'];
		} elseif ( 'monograph' == $curr_val['publication-type'] ) {
			$metadata['deposit-monograph-publisher'] = $curr_val['publisher'];
			$metadata['deposit-monograph-publish-date'] = $curr_val['date'];
			$metadata['deposit-monograph-isbn'] = $curr_val['isbn'];
			$metadata['deposit-monograph-doi'] = $curr_val['doi'];
		} elseif ( 'newspaper-article' == $curr_val['publication-type'] ) {
			$metadata['deposit-newspaper-article-publish-date'] = $curr_val['date'];
			$metadata['deposit-newspaper-article-title'] = $curr_val['book_journal_title'];
			$metadata['deposit-newspaper-article-edition'] = $curr_val['edition'];
			$metadata['deposit-newspaper-article-volume'] = $curr_val['volume'];
			$metadata['deposit-newspaper-article-start-page'] = $curr_val['start_page'];
			$metadata['deposit-newspaper-article-end-page'] = $curr_val['end_page'];
			$metadata['deposit-newspaper-article-url'] = $curr_val['url'];
		} elseif ( 'online-publication' == $curr_val['publication-type'] ) {
			$metadata['deposit-online-publication-publisher'] = $curr_val['publisher'];
			$metadata['deposit-online-publication-publish-date'] = $curr_val['date'];
			$metadata['deposit-online-publication-title'] = $curr_val['book_journal_title'];
			$metadata['deposit-online-publication-edition'] = $curr_val['edition'];
			$metadata['deposit-online-publication-volume'] = $curr_val['volume'];
			$metadata['deposit-online-publication-url'] = $curr_val['url'];
		} elseif ( 'podcast' == $curr_val['publication-type'] ) {
			$metadata['deposit-podcast-publisher'] = $curr_val['publisher'];
			$metadata['deposit-podcast-publish-date'] = $curr_val['date'];
			$metadata['deposit-podcast-volume'] = $curr_val['volume'];
			$metadata['deposit-podcast-url'] = $curr_val['url'];
		} elseif ( 'proceedings-article' == $curr_val['publication-type'] ) {
			$metadata['deposit-proceedings-article-publisher'] = $curr_val['publisher'];
			$metadata['deposit-proceedings-article-publish-date'] = $curr_val['date'];
			$metadata['deposit-proceedings-article-title'] = $curr_val['book_journal_title'];
			$metadata['deposit-proceedings-article-start-page'] = $curr_val['start_page'];
			$metadata['deposit-proceedings-article-end-page'] = $curr_val['end_page'];
			$metadata['deposit-proceedings-article-doi'] = $curr_val['doi'];
		} elseif ( 'none' == $curr_val['publication-type'] ) {
			$metadata['deposit-non-published-date'] = $curr_val['date'];
		}
		$metadata['deposit-embargoed-flag'] = $curr_val['embargoed'];
		//calc embargo length from $curr_val['embargo_end_date'];
		if ( 'yes' === $curr_val['embargoed'] ) {
			$deposit_date = new DateTime( $curr_val['record_creation_date'] );
			$embargo_end_date = new DateTime( $curr_val['embargo_end_date'] );

			$calculated_embargo_length = sprintf(
				'%s months',
				$deposit_date->diff( $embargo_end_date )->m + ( $deposit_date->diff( $embargo_end_date )->y * 12 ) + 1
			);
		}
		$metadata['deposit-embargo-length'] = $calculated_embargo_length;
		$record_location = explode( '-', $curr_val['record_identifier'] );
		// handle legacy MLA Commons value
		if ( $record_location[0] === $curr_val['record_identifier'] ) {
			$record_location[0] = '1';
			$record_location[1] = $curr_val['record_identifier'];
		}
		$metadata['deposit_blog_id'] = $record_location[0];
		$metadata['deposit_post_id'] = $record_location[1];

		return $metadata;

	}
