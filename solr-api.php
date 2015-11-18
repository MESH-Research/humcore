<?php
/**
 * API to access SOLR.
 *
 * @package HumCORE
 * @subpackage Deposits
 */

/**
 * Class using Solarium to access the SOLR API.
 */
class Humcore_Deposit_Solr_Api {

	protected $config;
	public $client;
	public $select_query;
	public $servername_hash;
	public $service_status;
	public $namespace;
	public $tempDir;

	/**
	 * Initialize SOLR API settings.
	 */
	public function __construct() {

		$path = dirname( __FILE__ ) . '/vendor/autoload.php';
		if ( is_file($path) ) {
			require_once $path;
		}

		$humcoreSettings = get_option( 'humcore-deposits-humcore-settings' );
                if ( defined( 'CORE_SOLR_HOST' ) && ! empty( CORE_SOLR_HOST ) ) { // Better have a value if defined.
                        $this->servername_hash = md5( $humcoreSettings['servername'] );
                } else {
                        $this->servername_hash = $humcoreSettings['servername_hash'];
                }
                
                $this->service_status = $humcoreSettings['service_status'];
                
                if ( defined( 'CORE_HUMCORE_NAMESPACE' ) && ! empty( CORE_HUMCORE_NAMESPACE ) ) {
                        $this->namespace = CORE_HUMCORE_NAMESPACE;
                } else {
                        $this->namespace = $humcoreSettings['namespace'];
                }
                if ( defined( 'CORE_HUMCORE_TEMP_DIR' ) && ! empty( CORE_HUMCORE_TEMP_DIR ) ) { 
                        $this->tempDir = CORE_HUMCORE_TEMP_DIR;
                } else {
                        $this->tempDir = $humcoreSettings['tempdir'];
                }

		$solrSettings = get_option( 'humcore-deposits-solr-settings' );

                if ( defined( 'CORE_SOLR_PROTOCOL' ) ) { 
                        $solrSettings['scheme'] = preg_replace( '~://$~', '', CORE_SOLR_PROTOCOL );
                }
                if ( defined( 'CORE_SOLR_HOST' ) ) { 
                        $solrSettings['host'] = CORE_SOLR_HOST;
                }
                if ( defined( 'CORE_SOLR_PORT' ) ) { 
                        $solrSettings['port'] = CORE_SOLR_PORT;
                }
                if ( defined( 'CORE_SOLR_PATH' ) ) { 
                        $solrSettings['path'] = CORE_SOLR_PATH;
                }
                if ( defined( 'CORE_SOLR_CORE' ) ) { 
                        $solrSettings['core'] = CORE_SOLR_CORE;
                }
		$config = array(
			'endpoint' => array(
				'solrhost' => array(
					'scheme' => preg_replace( '~://$~', '', $solrSettings['protocol'] ),
					'host' => $solrSettings['host'],
					'port' => $solrSettings['port'],
					'path' => $solrSettings['path'] . '/',
					'core' => $solrSettings['core'],
					'timeout' => 60,
				),
			),
		);

		// Prevent copying prod config data to dev.
		if ( ! empty( $this->servername_hash ) && $this->servername_hash != md5( $_SERVER['SERVER_NAME'] ) ) {
			$config['endpoint']['solrhost']['host'] = '';
		}

		$this->client = new Solarium\Client( $config );

	}

	/**
	 * Get the server status.
	 */
	public function get_solr_status() {

		$client = $this->client;
		$ping = $client->createPing();

		try {
			$result = $client->ping( $ping );

			// Begin debug.
			$query_info = $result->getQuery();
			$endpoint = $client->getEndpoint( 'solrhost' );

			$info = array(
				'url'             => $endpoint->getBaseUri(),
				'handler'         => $query_info->getHandler(),
				'status'          => $result->getStatus(),
				'response'        => $result->getData(),
			);
			if ( defined( 'HUMCORE_DEBUG' ) && HUMCORE_DEBUG && defined( 'HUMCORE_DEBUG_LOG' ) && '' != HUMCORE_DEBUG_LOG ) {
				ini_set( 'log_errors_max_len', '0' );
				error_log( '[' . date( 'd-M-Y H:i:s T' ) . '] solr debug: ' . var_export( $info, true ), 3, HUMCORE_DEBUG_LOG );
			}
			// End of debug.
			$res = $result->getData();
			if ( 'OK' !== $res['status'] ) {
				return new WP_Error( 'solrServerError', 'Solr server is not okay.', var_export( $res, true ) );
			}
			return $res;
		} catch ( Exception $e ) {

			// Begin debug.
			$endpoint = $client->getEndpoint( 'solrhost' );

			$info = array(
				'url'             => $endpoint->getBaseUri(),
				'handler'         => 'admin/ping',
				'status'          => $e->getCode(),
				'response'        => $e->getMessage(),
			);
			if ( defined( 'HUMCORE_DEBUG' ) && HUMCORE_DEBUG && defined( 'HUMCORE_DEBUG_LOG' ) && '' != HUMCORE_DEBUG_LOG ) {
				ini_set( 'log_errors_max_len', '0' );
				error_log( '[' . date( 'd-M-Y H:i:s T' ) . '] solr debug: ' . var_export( $info, true ), 3, HUMCORE_DEBUG_LOG );
			}
			// End of debug.
			return new WP_Error( $e->getCode(), $e->getStatusMessage(), $e->getMessage() );
		}

	}

	/**
	 * Create a select object.
	 */
	public function create_select( $select ) {

		$client = $this->client;

		try {
			$query = $client->createSelect( $select );
			return $query;
		} catch ( Exception $e ) {
			return 1;
		}

	}

	/**
	 * Perform a select.
	 */
	public function select( $query ) {

		$client = $this->client;

		try {
			$results = $client->select( $query );
			return $results;
		} catch ( Exception $e ) {
			return 1;
		}

	}

	/**
	 * Create a realtime get object.
	 */
	public function create_realtime_get( $get ) {

		$client = $this->client;

		try {
			$query = $client->createRealtimeGet( $get );
			return $query;
		} catch ( Exception $e ) {
			return 1;
		}

	}

	/**
	 * Perform a realtime get.
	 */
	public function realtime_get( $query ) {

		$client = $this->client;

		try {
			$results = $client->realtimeGet( $query );
			return $results;
		} catch ( Exception $e ) {
			return 1;
		}

	}

	/**
	 * Retrieve a specific document.
	 */
	public function get_humcore_document( $id ) {

		$client = $this->client;

		// Get a realtime query instance and add settings.
		$get = $client->createRealtimeGet();
		$get->addId( $id );

		try {
			$result = $client->realtimeGet( $get );
			if ( 1 == $result->getNumFound() ) {
				$document = $result->getDocument();
				$search_result = array();
				$record = array();
				$record['id'] = $document->id;
				$record['pid'] = $document->pid;
				$record['title'] = $document->title_display;
				$record['abstract'] = $document->abstract;
				$record['date'] = $document->pub_date_facet[0];
				$record['authors'] = $document->author_facet;
				$record['author_info'] = $document->author_info;
				$record['group'] = $document->group_facet;
				$record['organization'] = $document->organization_facet;
				$record['subject'] = $document->subject_facet;
				$record['keyword'] = $document->keyword_search;
				$record['handle'] = $document->handle;
				$record['genre'] = $document->genre_facet[0];
				$record['notes'] = implode( ' ', $document->notes );
				$record['book_journal_title'] = $document->book_journal_title;
				$record['book_author'] = $document->book_author;
				$record['publisher'] = $document->publisher;
				$record['isbn'] = $document->isbn;
				$record['issn'] = $document->issn;
				$record['doi'] = $document->doi;
				$record['volume'] = $document->volume;
				$record['date_issued'] = $document->date_issued;
				$record['issue'] = $document->issue;
				$record['start_page'] = $document->start_page;
				$record['end_page'] = $document->end_page;
				$record['language'] = $document->language;
				$record['type_of_resource'] = $document->type_of_resource_facet[0];
				$record['record_content_source'] = $document->record_content_source;
				$record['record_creation_date'] = $document->record_creation_date;
				$record['record_identifier'] = $document->record_identifier;
				$record['member_of'] = $document->member_of;
				$search_result['documents'][] = $record ;
				$search_result['total'] = 1;

				return $search_result;
			}
			return 1;
		} catch ( Exception $e ) {
			return 1;
		}
	}

	/**
	 * Create a document with full text extract.
	 */
	public function create_humcore_extract( $file, $metadata ) {

		$client = $this->client;

		// Get an extract query instance and add settings.
		$query = $client->createExtract();
		// Is this field mapping needed??
		$query->addFieldMapping( 'content', 'text' );
		$query->setUprefix( 'ignored_' );
		$query->setFile( $file );
		$query->setOmitHeader( false );
		$query->setCommit( true );
		// Add document.
		$doc = $query->createDocument();
		$doc->id = $metadata['id'];
		$doc->pid = $metadata['pid'];
		$doc->language = $metadata['language'];
		$doc->title_display = $metadata['title'];
		$doc->title_search = $metadata['title'];
		$author_uni = array();
		$author_fullname = array();
		foreach ( $metadata['authors'] as $author ) {
			$author_uni[] = $author['uni'];
			$author_fullname[] = $author['fullname'];
		}
		$doc->author_uni = array_filter( $author_uni );
		$doc->author_search = array_filter( $author_fullname );
		$doc->author_facet = array_filter( $author_fullname );
		$doc->author_display = implode( ', ', array_filter( $author_fullname ) );
		$doc->author_info = $metadata['author_info'];
		$doc->organization_facet = array( $metadata['organization'] );
		// Genre is not an array in MODS record.
		if ( ! empty( $metadata['genre'] ) ) {
			$doc->genre_facet = array( $metadata['genre'] );
			$doc->genre_search = array( $metadata['genre'] );
		}
		$doc->group_facet = $metadata['group'];
		if ( ! empty( $metadata['subject'] ) ) {
			$doc->subject_facet = $metadata['subject'];
			$doc->subject_search = $metadata['subject'];
		}
		if ( ! empty( $metadata['keyword'] ) ) {
			$doc->keyword_display = implode( ', ', $metadata['keyword'] );
			$doc->keyword_search = array_map( 'strtolower', $metadata['keyword'] );
		}
		$doc->abstract = $metadata['abstract'];
		$doc->handle = $metadata['handle'];
		$doc->notes = array( $metadata['notes'] );
		if ( ! empty( $metadata['book_journal_title'] ) ) {$doc->book_journal_title = $metadata['book_journal_title']; }
		if ( ! empty( $metadata['book_author'] ) ) { $doc->book_author = array( $metadata['book_author'] ); } //TODO fix in UI
		if ( ! empty( $metadata['publisher'] ) ) { $doc->publisher = $metadata['publisher']; }
		if ( ! empty( $metadata['isbn'] ) ) { $doc->isbn = $metadata['isbn']; }
		if ( ! empty( $metadata['issn'] ) ) { $doc->issn = $metadata['issn']; }
		if ( ! empty( $metadata['doi'] ) ) { $doc->doi = $metadata['doi']; }
		if ( ! empty( $metadata['volume'] ) ) { $doc->volume = $metadata['volume']; }
		if ( ! empty( $metadata['date'] ) ) { $doc->date = $metadata['date']; }
		if ( ! empty( $metadata['issue'] ) ) { $doc->issue = $metadata['issue']; }
		if ( ! empty( $metadata['start_page'] ) ) { $doc->start_page = $metadata['start_page']; }
		if ( ! empty( $metadata['end_page'] ) ) { $doc->end_page = $metadata['end_page']; }
		$doc->date_issued = $metadata['date_issued'];
		$doc->pub_date_facet = array( $metadata['date_issued'] );
		if ( ! empty( $metadata['type_of_resource'] ) ) {
			// Type_of_resource is not an array in MODS record.
			$doc->type_of_resource_mods = array( strtolower( $metadata['type_of_resource'] ) );
			$doc->type_of_resource_facet = array( $metadata['type_of_resource'] );
		}
		$doc->record_content_source = $metadata['record_content_source'];
		$doc->record_creation_date = $metadata['record_creation_date'];
		$doc->record_identifier = $metadata['record_identifier'];
		$doc->member_of = $metadata['member_of'];

		$query->setDocument( $doc );

		// This executes the query and returns the result.
		try {
			$result = $client->extract( $query );
		} catch ( Exception $e ) {
			error_log( '***Error trying to create Solr Document using Extract***' . var_export( $e->getMessage(), true ) );

			// Begin debug.
			$query_info = $result->getQuery();
			$endpoint = $client->getEndpoint( 'solrhost' );

			$info = array(
				'url'             => $endpoint->getBaseUri(),
				'handler'         => $query_info->getHandler(),
				'file'            => $query_info->getOption( 'file' ),
				'fields'          => $doc->getFields(),
				'status'          => $result->getStatus(),
				'time'            => $result->getQueryTime(),
			);
			if ( defined( 'HUMCORE_DEBUG' ) && HUMCORE_DEBUG && defined( 'HUMCORE_DEBUG_LOG' ) && '' != HUMCORE_DEBUG_LOG ) {
				ini_set( 'log_errors_max_len', '0' );
				error_log( '[' . date( 'd-M-Y H:i:s T' ) . '] solr debug: ' . var_export( $info, true ), 3, HUMCORE_DEBUG_LOG );
			}
			// End of debug.
			throw $e;
		}

		error_log( '***Create Solr Document using Extract***' . var_export( $result->getData(), true ) );

		// Begin debug.
		$query_info = $result->getQuery();
		$endpoint = $client->getEndpoint( 'solrhost' );

		$info = array(
			'url'             => $endpoint->getBaseUri(),
			'handler'         => $query_info->getHandler(),
			'file'            => $query_info->getOption( 'file' ),
			'fields'          => $doc->getFields(),
			'status'          => $result->getStatus(),
			'time'            => $result->getQueryTime(),
		);
		if ( defined( 'HUMCORE_DEBUG' ) && HUMCORE_DEBUG && defined( 'HUMCORE_DEBUG_LOG' ) && '' != HUMCORE_DEBUG_LOG ) {
			ini_set( 'log_errors_max_len', '0' );
			error_log( '[' . date( 'd-M-Y H:i:s T' ) . '] solr debug: ' . var_export( $info, true ), 3, HUMCORE_DEBUG_LOG );
		}
		// End of debug.
		return true;

	}

	/**
	 * Create a document without text extract.
	 */
	public function create_humcore_document( $file, $metadata ) {

		$client = $this->client;

		// Get an update query instance.
		$query = $client->createUpdate();
		// Add document.
		$doc = $query->createDocument();
		$doc->id = $metadata['id'];
		$doc->pid = $metadata['pid'];
		if ( ! empty( $file ) ) {
			$doc->content = file_get_contents( $file );
		}
		$doc->language = $metadata['language'];
		$doc->title_display = $metadata['title'];
		$doc->title_search = $metadata['title'];
		$author_uni = array();
		$author_fullname = array();
		foreach ( $metadata['authors'] as $author ) {
			$author_uni[] = $author['uni'];
			$author_fullname[] = $author['fullname'];
		}
		$doc->author_uni = array_filter( $author_uni );
		$doc->author_search = array_filter( $author_fullname );
		$doc->author_facet = array_filter( $author_fullname );
		$doc->author_display = implode( ', ', array_filter( $author_fullname ) );
		$doc->author_info = $metadata['author_info'];
		$doc->organization_facet = array( $metadata['organization'] );
		// Genre is not an array in MODS record.
		if ( ! empty( $metadata['genre'] ) ) {
			$doc->genre_facet = array( $metadata['genre'] );
			$doc->genre_search = array( $metadata['genre'] );
		}
		$doc->group_facet = $metadata['group'];
		if ( ! empty( $metadata['subject'] ) ) {
			$doc->subject_facet = $metadata['subject'];
			$doc->subject_search = $metadata['subject'];
		}
		if ( ! empty( $metadata['keyword'] ) ) {
			$doc->keyword_display = implode( ', ', $metadata['keyword'] );
			$doc->keyword_search = array_map( 'strtolower', $metadata['keyword'] );
		}
		$doc->abstract = $metadata['abstract'];
		$doc->handle = $metadata['handle'];
		$doc->notes = array( $metadata['notes'] );
		if ( ! empty( $metadata['book_journal_title'] ) ) { $doc->book_journal_title = $metadata['book_journal_title']; }
		if ( ! empty( $metadata['book_author'] ) ) { $doc->book_author = array( $metadata['book_author'] ); } //TODO fix in UI
		if ( ! empty( $metadata['publisher'] ) ) { $doc->publisher = $metadata['publisher']; }
		if ( ! empty( $metadata['isbn'] ) ) { $doc->isbn = $metadata['isbn']; }
		if ( ! empty( $metadata['issn'] ) ) { $doc->issn = $metadata['issn']; }
		if ( ! empty( $metadata['doi'] ) ) { $doc->doi = $metadata['doi']; }
		if ( ! empty( $metadata['volume'] ) ) { $doc->volume = $metadata['volume']; }
		if ( ! empty( $metadata['date'] ) ) { $doc->date = $metadata['date']; }
		if ( ! empty( $metadata['issue'] ) ) { $doc->issue = $metadata['issue']; }
		if ( ! empty( $metadata['start_page'] ) ) { $doc->start_page = $metadata['start_page']; }
		if ( ! empty( $metadata['end_page'] ) ) { $doc->end_page = $metadata['end_page']; }
		$doc->date_issued = $metadata['date_issued'];
		$doc->pub_date_facet = array( $metadata['date_issued'] );
		if ( ! empty( $metadata['type_of_resource'] ) ) {
			// Type_of_resource is not an array in MODS record.
			$doc->type_of_resource_mods = array( strtolower( $metadata['type_of_resource'] ) );
			$doc->type_of_resource_facet = array( $metadata['type_of_resource'] );
		}
		$doc->record_content_source = $metadata['record_content_source'];
		$doc->record_creation_date = $metadata['record_creation_date'];
		$doc->record_identifier = $metadata['record_identifier'];
		$doc->member_of = $metadata['member_of'];

		$query->addDocuments( array( $doc ) );
		$query->addCommit();

		// This executes the query and returns the result.
		$result = $client->update( $query );

		error_log( '***Create Solr Document***' . var_export( $result->getData(), true ) );

		// Begin debug.
		$query_info = $result->getQuery();
		$endpoint = $client->getEndpoint( 'solrhost' );

		$info = array(
			'url'             => $endpoint->getBaseUri(),
			'handler'         => $query_info->getHandler(),
			'file'            => $query_info->getOption( 'file' ),
			'fields'          => $doc->getFields(),
			'status'          => $result->getStatus(),
			'time'            => $result->getQueryTime(),
		);
		if ( defined( 'HUMCORE_DEBUG' ) && HUMCORE_DEBUG && defined( 'HUMCORE_DEBUG_LOG' ) && '' != HUMCORE_DEBUG_LOG ) {
			ini_set( 'log_errors_max_len', '0' );
			error_log( '[' . date( 'd-M-Y H:i:s T' ) . '] solr debug: ' . var_export( $info, true ), 3, HUMCORE_DEBUG_LOG );
		}
		// End of debug.
		return true;
	}

	/**
	 * Delete an existing.
	 */
	public function delete_humcore_document( $id ) {

		$client = $this->client;
		$deleteQuery = $client->createUpdate();
		$deleteQuery->addDeleteById( $id );
		$deleteQuery->addCommit();
		$result = $client->update( $deleteQuery );
		$res = $result->getData();

		error_log( '***Delete Solr Document***' . var_export( $result->getData(), true ) );
		return isset( $res['status'] ) ? $res['status'] : '';

	}

	/**
	 * Returns array of results.
	 *
	 * Result['spellchecker']= Spellchecker-Did you mean
	 * Result['facets']= Array of Facets
	 * Result['total']= No of documents found
	 * Result['documents']= Array of documents
	 * Result['info']=Result info
	 */
	public function get_search_results( $term, $facet_options, $start, $sort ) {

		$search_result = array();
		$fac_count = -1; // All the facet values.
		$number_of_res = 25; // Per_page
		$lucene_reserved_characters = preg_quote( '+-&|!(){}[]^"~*?:\\' );
		$facets_array = array(
			'author_facet', 'organization_facet', 'group_facet', 'subject_facet', 'genre_facet', 'pub_date_facet', 'type_of_resource_facet',
		);

		if ( ! empty( $facet_options ) && ! is_array( $facet_options ) ) {
			$facet_options_parsed = wp_parse_args( $facet_options );
			$facet_options = $facet_options_parsed['facets'];
		}

		$msg = '';
		$client = $this->client;
		$query = $client->createSelect();
		$edismax = $query->getEDisMax();
		$query->setQuery( $term );

		$query->setFields( array(
			'id', 'pid', 'title_display', 'abstract', 'pub_date_facet', 'date', 'author_display', 'author_facet', 'author_uni', 'author_info',
			'organization_facet', 'group_facet', 'subject_facet', 'keyword_search', 'handle', 'genre_facet', 'notes',
			'book_journal_title', 'book_author', 'publisher', 'isbn', 'issn', 'doi', 'volume', 'date_issued', 'issue', 'start_page', 'end_page',
			'language', 'type_of_resource_facet', 'record_content_source', 'record_creation_date', 'record_identifier', 'member_of', 'score',
		) );

		if ( null != $sort ) {
			if ( 'date' == $sort ) {
				$sort_field = 'record_creation_date';
				$sort_value = $query::SORT_DESC;
			} else if ( 'author' == $sort ) {
				$sort_field = 'author_sort';
				$sort_value = $query::SORT_ASC;
			} else if ( 'title' == $sort ) {
				$sort_field = 'title_sort';
				$sort_value = $query::SORT_ASC;
			} else {
				$sort_field = 'record_creation_date';
				$sort_value = $query::SORT_DESC;
			}
		} else {
			$sort_field = 'record_creation_date';
			$sort_value = $query::SORT_DESC;
		}

		$query->addSort( $sort_field, $sort_value );
		$query->setQueryDefaultOperator( 'AND' );

		if ( 'NOTspellchecker' == 'spellchecker' ) { // Disabled for now.

			$spellChk = $query->getSpellcheck();
			$spellChk->setCount( 10 );
			$spellChk->setCollate( true );
			$spellChk->setExtendedResults( true );
			$spellChk->setCollateExtendedResults( true );
			$resultset = $client->select( $query );

			$spell_msg = '';
			$spellChkResult = $resultset->getSpellcheck();

			if ( ! $spellChkResult->getCorrectlySpelled() ) {
				$collations = $spellChkResult->getCollations();
				$term = '';
				foreach ( $collations as $collation ) {
					foreach ( $collation->getCorrections() as $input => $correction ) {
						$term .= $correction;
					}
				}
				if ( strlen( $term ) > 0 ) {
					$err_msg = 'Did you mean: <b>' . $term . '</b><br />';
					$query->setQuery( $term );
				}
				$search_result['spellchecker'] = $err_msg;
			} else {
				$search_result['spellchecker'] = '';
			}
		} else {
			$search_result['spellchecker'] = '';
		}

		if ( ! empty( $facets_array ) ) {

			$facetSet = $query->getFacetSet();
			$facetSet->setMinCount( 1 );
			foreach ( $facets_array as $facet ) {
				$facetSet->createFacetField( $facet )->setField( $facet )->setLimit( $fac_count );
			}
		}

		$bound = '';

		if ( ! empty( $facet_options ) ) {

			foreach ( $facet_options as $facet_key => $facet_values ) {

				$value_list = implode( ',', $facet_values );
				if ( ! empty( $facet_values ) ) {
					foreach ( $facet_values as $i => $facet_value ) {
						$escaped_facet_value = preg_replace_callback(
							'/([' . $lucene_reserved_characters . '])/',
							function($matches) {
								return '\\' . $matches[0];
							},
							trim( $facet_value )
						);
						$query->addFilterQuery( array(
							'key' => $facet_key . '-' . $i,
							'query' => $facet_key . ':' . str_replace( ' ', '\ ', $escaped_facet_value ),
						) );
					}
				}
			}
		}

		if ( 0 == $start || 1 == $start ) {
			$st = 0;
		} else {
			$st = ( ( $start - 1 ) * $number_of_res );
		}

		if ( '' != $bound && $bound < $number_of_res ) {
				 $query->setStart( $st )->setRows( $bound );
		} else {
			$query->setStart( $st )->setRows( $number_of_res );
		}

		$resultset = $client->select( $query );

		if ( ! empty( $facets_array ) ) {
			$output = array();
			foreach ( $facets_array as $facet ) {
				$facet_results = $resultset->getFacetSet()->getFacet( $facet );
				foreach ( $facet_results as $value => $count ) {
					$output[ $facet ]['counts'][] = array( $value, $count );
				}
			}
			$search_result['facets'] = $output;
		} else {
			$search_result['facets'] = '';
		}

		$found = $resultset->getNumFound();

		if ( '' != $bound ) {
			$search_result['total'] = $bound;
		} else {
			$search_result['total'] = $found;
		}

		$hl = $query->getHighlighting();
		$hl->getField( 'title_display' )->setSimplePrefix( '<b>' )->setSimplePostfix( '</b>' );
		$hl->getField( 'abstract' )->setSimplePrefix( '<b>' )->setSimplePostfix( '</b>' );
		$resultSet = '';
		$resultSet = $client->select( $query );
		$results = array();
		$highlighting = $resultSet->getHighlighting();
		$i = 1;
		$cat_arr = array();

		foreach ( $resultSet as $document ) {
			$record = array();
			$record['id'] = $document->id;
			$record['pid'] = $document->pid;
			$record['title'] = $document->title_display;
			$record['abstract'] = $document->abstract;
			$record['date'] = $document->pub_date_facet[0];
			$record['authors'] = $document->author_facet;
			$record['author_info'] = $document->author_info;
			$record['organization'] = $document->organization_facet;
			$record['group'] = $document->group_facet;
			$record['subject'] = $document->subject_facet;
			$record['keyword'] = $document->keyword_search;
			$record['handle'] = $document->handle;
			$record['genre'] = $document->genre_facet[0];
			$record['notes'] = implode( ' ', $document->notes );
			$record['book_journal_title'] = $document->book_journal_title;
			$record['book_author'] = $document->book_author;
			$record['publisher'] = $document->publisher;
			$record['isbn'] = $document->isbn;
			$record['issn'] = $document->issn;
			$record['doi'] = $document->doi;
			$record['volume'] = $document->volume;
			$record['date_issued'] = $document->date_issued;
			$record['issue'] = $document->issue;
			$record['start_page'] = $document->start_page;
			$record['end_page'] = $document->end_page;
			$record['language'] = $document->language;
			$record['type_of_resource'] = $document->type_of_resource_facet[0];
			$record['record_content_source'] = $document->record_content_source;
			$record['record_creation_date'] = $document->record_creation_date;
			$record['record_identifier'] = $document->record_identifier;
			$record['member_of'] = $document->member_of;
			array_push( $results, $record );
			$i = $i + 1;
		}

		if ( count( $results ) < 0 ) {
			$search_result['documents'] = '';
		} else {
			$search_result['documents'] = $results;
		}

		$first = $st + 1;
		$last = $st + $number_of_res;

		if ( $last > $found ) {
			$last = $found;
		} else {
			$last = $st + $number_of_res;
		}

		$search_result['info'] = "<span class='infor'>Showing $first to $last results out of $found</span>";
		return $search_result;

	}

	/**
	 * Call the autosuggester (not implemented).
	 */
	public function auto_complete_suggestions( $input ) {

		$res = array();
		$client = $this->client;
		$suggestqry = $client->createSuggester();
		$suggestqry->setHandler( 'suggest' );
		$suggestqry->setDictionary( 'suggest' );
		$suggestqry->setQuery( $input );
		$suggestqry->setCount( 5 );
		$suggestqry->setCollate( true );
		$suggestqry->setOnlyMorePopular( true );

		$resultset = $client->suggester( $suggestqry );

		foreach ( $resultset as $term => $termResult ) {

			// $msg.='<strong>' . $term . '</strong><br/>';
			foreach ( $termResult as $result ) {
				array_push( $res, $wd );
			}
		}

		$result = json_encode( $res );
		return $result;
	}

}
