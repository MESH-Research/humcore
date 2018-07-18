<?php
/**
 * API to access DOI.
 *
 * @package HumCORE
 * @subpackage Deposits
 */

/**
 * Class using WP_HTTP to access the EZIP API.
 */
class Humcore_Deposit_Doi_Api {

	private $doi_settings = array();
	private $base_url;
	private $options           = array();
	private $upload_filehandle = array(); // Handle the WP_HTTP inability to process file uploads by hooking curl settings.
	private $doi_path;
	private $doi_prefix;

	/* getting removed
	public $servername_hash;
	*/
	public $service_status;
	public $namespace;
	public $temp_dir;

	/**
	 * Initialize DOI API settings.
	 */
	public function __construct() {

		$humcore_settings = get_option( 'humcore-deposits-humcore-settings' );

		/* getting removed
		if ( defined( 'CORE_DOI_HOST' ) && ! empty( CORE_DOI_HOST ) ) { // Better have a value if defined.
			$this->servername_hash = md5( $humcore_settings['servername'] );
		} else {
			$this->servername_hash = $humcore_settings['servername_hash'];
		}
		*/

		$this->service_status = $humcore_settings['service_status'];

		if ( defined( 'CORE_HUMCORE_NAMESPACE' ) && ! empty( CORE_HUMCORE_NAMESPACE ) ) {
			$this->namespace = CORE_HUMCORE_NAMESPACE;
		} else {
			$this->namespace = $humcore_settings['namespace'];
		}
		if ( defined( 'CORE_HUMCORE_TEMP_DIR' ) && ! empty( CORE_HUMCORE_TEMP_DIR ) ) {
			$this->temp_dir = CORE_HUMCORE_TEMP_DIR;
		} else {
			$this->temp_dir = $humcore_settings['tempdir'];
		}

		$this->doi_settings = get_option( 'humcore-deposits-doi-settings' );

		if ( defined( 'CORE_DOI_PROTOCOL' ) ) {
				$this->doi_settings['protocol'] = CORE_DOI_PROTOCOL;
		}
		if ( defined( 'CORE_DOI_HOST' ) ) {
				$this->doi_settings['host'] = CORE_DOI_HOST;
		}
		if ( defined( 'CORE_DOI_PORT' ) ) {
				$this->doi_settings['port'] = CORE_DOI_PORT;
		}
		if ( defined( 'CORE_DOI_PATH' ) ) {
				$this->doi_settings['path'] = CORE_DOI_PATH;
		}
		if ( defined( 'CORE_DOI_LOGIN' ) ) {
				$this->doi_settings['login'] = CORE_DOI_LOGIN;
		}
		if ( defined( 'CORE_DOI_PASSWORD' ) ) {
				$this->doi_settings['password'] = CORE_DOI_PASSWORD;
		}
		if ( defined( 'CORE_DOI_PREFIX' ) ) {
				$this->doi_settings['prefix'] = CORE_DOI_PREFIX;
		}

		if ( ! empty( $this->doi_settings['port'] ) ) {
			$this->base_url = $this->doi_settings['protocol'] . $this->doi_settings['host'] . ':' . $this->doi_settings['port'];
		} else {
			$this->base_url = $this->doi_settings['protocol'] . $this->doi_settings['host'];
		}

		$this->doi_path = $this->doi_settings['path'];
		$this->doi_prefix                                     = $this->doi_settings['prefix'];
		$this->options['api-auth']['headers']['Authorization'] = 'Basic ' . base64_encode( $this->doi_settings['login'] . ':' . $this->doi_settings['password'] );
		$this->options['api-auth']['httpversion']              = '1.1';
		$this->options['api-auth']['sslverify']                = true;
		$this->options['api']['httpversion']                   = '1.1';
		$this->options['api']['sslverify']                     = true;

		/* getting removed
		// Prevent copying prod config data to dev.
		if ( ! empty( $this->servername_hash ) && $this->servername_hash != md5( $_SERVER['SERVER_NAME'] ) ) {
			$this->base_url = '';
			$this->options['api-auth']['headers']['Authorization'] = '';
		}
		*/

	}


	/**
	 * Get an identifier.
	 *
	 * @param array $args Array of arguments. Supports only the doi argument.
	 * @link http://ezid.cdlib.org/doc/apidoc.html#operation-get-identifier-metadata
	 * @return WP_Error|array identifier metadata
	 * @see wp_parse_args()
	 * @see wp_remote_request()
	 */
	public function get_identifier( $args ) {

		$defaults = array(
			'doi' => '',
		);

		$params = wp_parse_args( $args, $defaults );

		$doi = $params['doi'];

		if ( empty( $doi ) ) {
			return new WP_Error( 'missingArg', 'DOI is missing.' );
		}

		$url = sprintf( '%1$s/id/%2$s', $this->base_url, $doi );

		$request_args           = $this->options['api'];
		$request_args['method'] = 'GET';
echo var_export( $url, true ), "\n";
echo "REQUEST ARGS ",var_export( $request_args, true ), "\n";

		$response = wp_remote_request( $url, $request_args );
echo "RESPONSE ",var_export( $response, true ), "\n";
echo "RESPONSE BODY ",var_export( wp_remote_retrieve_response_body( $response ), true ), "\n";
		if ( is_wp_error( $response ) ) {
			return new WP_Error( $response->get_error_code(), $response->get_error_message(), $response->get_error_data( $response->get_error_code() ) );
		}

		$response_code    = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );
		$response_body    = wp_remote_retrieve_body( $response );

		if ( 200 != $response_code ) {
			return new WP_Error( $response_code, $response_message, $response_body );
		}

		$doi_response = explode( "\n", str_replace( "\r", '', $response_body ) );
		$doi_metadata = array();
		foreach ( $doi_response as $meta_row ) {
			$row_values = explode( ': ', $meta_row, 2 );
			if ( ! empty( $row_values[0] ) ) {
				$decoded_value = preg_replace_callback(
					'/\\\\u([0-9a-fA-F]{4})/',
					function ( $match ) {
						return mb_convert_encoding( pack( 'H*', $match[1] ), 'UTF-8', 'UCS-2BE' );
					},
					$row_values[1]
				);
				$doi_metadata[ $row_values[0] ] = $decoded_value;
			}
		}
		return $doi_metadata;

	}


	/**
	 * Create an identifier.
	 *
	 * @param array $args Array of arguments. Supports all arguments from apidoc.html#operation-create-identifier.
	 * @link http://ezid.cdlib.org/doc/apidoc.html#operation-create-identifier
	 * @return WP_Error|string body of the Response object
	 * @see wp_parse_args()
	 * @see wp_remote_request()
	 */
	public function create_identifier( array $args = array() ) {

		// bypass this function if host = 'none'
		if ( 'none' === $this->doi_settings['host'] ) {
			return trim( $this->doi_settings['host'] );
		}

		$defaults = array(
			'doi'          => '',
			'_status'      => 'reserved',
			'_export'      => 'no',
			'_profile'     => 'dc',
			'dc.publisher' => '',
			'_target'      => '',
			'dc.type'      => '',
			'dc.date'      => '',
			'dc.creator'   => '',
			'dc.title'     => '',
		);
		$params   = wp_parse_args( $args, $defaults );

		$doi = $params['doi'];
		unset( $params['doi'] ); // Leave out of the body.

		if ( empty( $doi ) ) {
			return new WP_Error( 'missingArg', 'DOI is missing.' );
		}
		if ( empty( $params['dc.publisher'] ) ) {
				$params['dc.publisher'] = 'Not provided.';
		}
		if ( empty( $params['_target'] ) ) {
				return new WP_Error( 'missingArg', 'Target URL is missing.' );
		}
		if ( empty( $params['dc.type'] ) ) {
				return new WP_Error( 'missingArg', 'Type is missing.' );
		}
		if ( empty( $params['dc.date'] ) ) {
				return new WP_Error( 'missingArg', 'Date is missing.' );
		}
		if ( empty( $params['dc.creator'] ) ) {
				return new WP_Error( 'missingArg', 'Creator is missing.' );
		}
		if ( empty( $params['dc.title'] ) ) {
				return new WP_Error( 'missingArg', 'Title is missing.' );
		}

		$url = sprintf( '%1$s/id/%2$s%3$s', $this->base_url, $this->doi_prefix, $doi );

		$content = '';
		foreach ( $params as $key => $value ) {
			if ( ! empty( $value ) ) {
				$encoded_value = str_replace( array( "\n", "\r", '%' ), array( '\u000A', '\u000D', '\u0025' ), $value );
				$content      .= $key . ': ' . $encoded_value . "\n";
			}
		}

		$request_args                            = $this->options['api-auth'];
		$request_args['method']                  = 'PUT';
		$request_args['headers']['Content-Type'] = 'text/plain';
		$request_args['body']                    = $content;

		$response = wp_remote_request( $url, $request_args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( $response->get_error_code(), $response->get_error_message(), $response->get_error_data( $response->get_error_code() ) );
		}

		$response_code    = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );
		$response_body    = wp_remote_retrieve_body( $response );

		if ( 201 != $response_code ) {
			return new WP_Error( $response_code, $response_message, $response_body );
		}

		humcore_write_error_log( 'info', 'Create DOI ', array( 'response' => $response_body ) );

		$response_array = explode( ':', $response_body, 2 );
		if ( 'success' == $response_array[0] ) {
			$doi = explode( '|', $response_array[1], 2 );
			return trim( $doi[0] );
		} else {
			return false;
		}

	}

	/**
	 * Prepare doi metadata.
	 *
	 * @return WP_Error|string body of the Response object
	 * @see wp_remote_request()
	 */
	public function prepare_doi_metadata( $metadata ) {
/*
                $metadata['title'],
                $metadata['pid'],
                $metadata['authors'],
                $metadata['type_of_resource'],
                $metadata['date_issued'],
                $metadata['publisher'],
                $metadata['subject'],
                $metadata['abstract'],
                $metadata['genre'],
                $metadata['language'],
                $metadata['license']
*/
        $resource_type_map = array();
        $resource_type_map['Audio']          = 'Sound';
        $resource_type_map['Image']          = 'Image';
        $resource_type_map['Mixed material'] = 'Other';
        $resource_type_map['Software']       = 'Software';
        $resource_type_map['Text']           = 'Text';
        $resource_type_map['Video']          = 'Audiovisual';

        $resource_type_general = $resource_type_map[$metadata['type_of_resource']];
        if ( empty( $resource_type_general ) ) {
                $resource_type_general = 'Other';
        }

        $doi_metadata = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8" ?>
         <resource></resource>'
        );

        $doi_metadata->addAttribute( 'xmlns', 'http://datacite.org/schema/kernel-3' );
        $doi_metadata->addAttribute( 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance' );
        $doi_metadata->addAttribute( 'xsi:schemaLocation', 'http://datacite.org/schema/kernel-3 http://schema.datacite.org/meta/kernel-3/metadata.xsd' );

	$doi_identifier = $doi_metadata->addChild( 'identifier', '(:tba)' );
	$doi_identifier->addAttribute( 'identifierType', 'DOI' );
        $doi_titles = $doi_metadata->addChild( 'titles' );
        $doi_title = $doi_titles->addChild( 'title', $metadata['title'] );
        $doi_publisher = $doi_metadata->addChild( 'publisher', 'Humanities Commons' );
        $doi_publication_year = $doi_metadata->addChild( 'publicationYear', $metadata['date_issued'] );
/*
  <dates>
    <date dateType="Created">2018-07-09</date>
    <date dateType="Updated">2018-07-09</date>
  </dates>
*/

        if ( ! empty( $metadata['authors'] ) ) {
                $doi_creators = $doi_metadata->addChild( 'creators' );
                foreach ( $metadata['authors'] as $creator ) {
                        if ( ( in_array( $creator['role'], array( 'creator', 'author', 'editor', 'translator' ) ) ) && ! empty( $creator['fullname'] ) ) {
                                $doi_creator = $doi_creators->addChild( 'creator' );
                                $doi_creator_name = $doi_creator->addChild( 'creatorName', $creator['family'] . ', ' . $creator['given'] );
                        }
                }
        }

        if ( ! empty( $metadata['subject'] ) ) {
                $doi_subjects = $doi_metadata->addChild( 'subjects' );
                foreach ( $metadata['subject'] as $subject ) {
                        $doi_subject = $doi_subjects->addChild( 'subject', $subject );
                }
        }

        $doi_resource_type = $doi_metadata->addChild( 'resourceType', $metadata['genre'] );
        $doi_resource_type->addAttribute( 'resourceTypeGeneral', $resource_type_general );
        $doi_descriptions = $doi_metadata->addChild( 'descriptions' );
        $doi_description = $doi_descriptions->addChild( 'description', $metadata['abstract'] );
        $doi_description->addAttribute( 'descriptionType', 'Abstract' );

	return $doi_metadata->asXML();
}

	/**
	 * Mint an identifier.
	 *
	 * @link http://ezid.cdlib.org/doc/apidoc.html#operation-mint-identifier
	 * @return WP_Error|string body of the Response object
	 * @see wp_remote_request()
	 */
	public function reserve_identifier( array $args = array() ) {

		// bypass this function if host = 'none'
		if ( 'none' === $this->doi_settings['host'] ) {
			return trim( $this->doi_settings['host'] );
		}

                $defaults = array(
                        '_target'  => '',
                        '_profile' => 'datacite',
                        'datacite' => '',
			'_status'  => 'reserved',
                );
                $params   = wp_parse_args( $args, $defaults );

		$url = sprintf( '%1$s/shoulder/%2$s', $this->base_url, $this->doi_prefix );

		$content = '';

                foreach ( $params as $key => $value ) {
                        if ( ! empty( $value ) ) {
                                $encoded_value = str_replace( array( "\n", "\r", '%' ), array( '\u000A', '\u000D', '\u0025' ), $value );
                                $content      .= $key . ': ' . $encoded_value . "\n";
                        }
                }

		$request_args                            = $this->options['api-auth'];
		$request_args['method']                  = 'POST';
		$request_args['headers']['Content-Type'] = 'text/plain';
		$request_args['body']                    = $content;

humcore_write_error_log( 'info', 'Reserve DOI ', array( 'request' => var_export( $request_args, true ) ) );
		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( $response->get_error_code(), $response->get_error_message(), $response->get_error_data( $response->get_error_code() ) );
		}

		$response_code    = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );
		$response_body    = wp_remote_retrieve_body( $response );

		if ( 200 != $response_code ) {
			return new WP_Error( $response_code, $response_message, $response_body );
		}

		humcore_write_error_log( 'info', 'Mint DOI ', array( 'response' => $response_body ) );

                $doi_response = explode( "\n", str_replace( "\r", '', $response_body ) );
                $doi_metadata = array();
                foreach ( $doi_response as $meta_row ) {
                        $row_values = explode( ': ', $meta_row, 2 );
                        if ( ! empty( $row_values[0] ) ) {
                                $decoded_value = preg_replace_callback(
                                        '/\\\\u([0-9a-fA-F]{4})/',
                                        function ( $match ) {
                                                return mb_convert_encoding( pack( 'H*', $match[1] ), 'UTF-8', 'UCS-2BE' );
                                        },
                                        $row_values[1]
                                );
                                $doi_metadata[ $row_values[0] ] = $decoded_value;
                        }
                }

		return $doi_metadata['success'];

	}


	/**
	 * Modify an identifier.
	 *
	 * @param array $args Array of arguments. Supports all arguments from apidoc.html#operation-modify-identifier.
	 * @link http://ezid.cdlib.org/doc/apidoc.html#operation-modify-identifier
	 * @return WP_Error|string body of the Response object
	 * @see wp_parse_args()
	 * @see wp_remote_request()
	 */
	public function modify_identifier( array $args = array() ) {

		// bypass this function if host = 'none'
		if ( 'none' === $this->doi_settings['host'] ) {
			return trim( $this->doi_settings['host'] );
		}

		$defaults = array(
			'doi'         => '',
			'_target'      => '',
			'_datacite'    => '',
		);
		$params   = wp_parse_args( $args, $defaults );

		$doi = $params['doi'];
		unset( $params['doi'] ); // Leave out of the body.

		if ( empty( $doi ) ) {
			return new WP_Error( 'missingArg', 'DOI is missing.' );
		}
		if ( empty( $params['_datacite'] ) ) {
				return new WP_Error( 'missingArg', 'Metadata is missing.' );
		}

		$url = sprintf( '%1$s/id/%2$s', $this->base_url, $doi );

		$content = '';
		foreach ( $params as $key => $value ) {
			if ( ! empty( $value ) ) {
				$encoded_value = str_replace( array( "\n", "\r", '%' ), array( '\u000A', '\u000D', '\u0025' ), $value );
				$content      .= $key . ': ' . $encoded_value . "\n";
			}
		}

		$request_args                            = $this->options['api-auth'];
		$request_args['method']                  = 'POST';
		$request_args['headers']['Content-Type'] = 'text/plain';
		$request_args['body']                    = $content;

		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( $response->get_error_code(), $response->get_error_message(), $response->get_error_data( $response->get_error_code() ) );
		}

		$response_code    = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );
		$response_body    = wp_remote_retrieve_body( $response );

		if ( 200 != $response_code ) {
			return new WP_Error( $response_code, $response_message, $response_body );
		}

		$response_array = explode( ':', $response_body, 2 );
		if ( 'success' == $response_array[0] ) {
			$doi = explode( '|', $response_array[1], 2 );
			return trim( $doi[0] );
		} else {
			return false;
		}

	}


	/**
	 * Delete an identifier.
	 *
	 * @param array $args Array of arguments. Supports only doi argument.
	 * @link http://ezid.cdlib.org/doc/apidoc.html#operation-delete-identifier
	 * @return WP_Error|string body of the Response object
	 * @see wp_parse_args()
	 * @see wp_remote_request()
	 */
	public function delete_identifier( array $args = array() ) {

		$defaults = array(
			'doi' => '',
		);
		$params   = wp_parse_args( $args, $defaults );

		$doi = $params['doi'];
		unset( $params['doi'] ); // Leave out of the body.

		if ( empty( $doi ) ) {
			return new WP_Error( 'missingArg', 'DOI is missing.' );
		}

		$url = sprintf( '%1$s/id/%2$s', $this->base_url, $doi );

		$request_args                            = $this->options['api-auth'];
		$request_args['method']                  = 'DELETE';
		$request_args['headers']['Content-Type'] = 'text/plain';

		$response = wp_remote_request( $url, $request_args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( $response->get_error_code(), $response->get_error_message(), $response->get_error_data( $response->get_error_code() ) );
		}

		$response_code    = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );
		$response_body    = wp_remote_retrieve_body( $response );

		if ( 200 != $response_code ) {
			return new WP_Error( $response_code, $response_message, $response_body );
		}

		$response_array = explode( ':', $response_body, 2 );
		if ( 'success' == $response_array[0] ) {
			$doi = explode( '|', $response_array[1], 2 );
			return trim( $doi[0] );
		} else {
			return false;
		}

	}


	/**
	 * Get the DOI server status
	 *
	 * @param array $args Array of arguments. Supports only subsystems argument.
	 * @link http://ezid.cdlib.org/doc/apidoc.html#server-status
	 * @return WP_Error|array subsystems status
	 * @see wp_parse_args()
	 * @see wp_remote_request()
	 */
	public function server_status( array $args = array() ) {
		// bypas this function if host == 'none'
		if ( 'none' === $this->doi_settings['host'] ) {
			return trim( $this->doi_settings['host'] );
		}

		$params = wp_parse_args( $args, $defaults );

		$url = sprintf( '%1$s/%2$s', $this->base_url, 'status' );

		$request_args           = $this->options['api'];
		$request_args['method'] = 'GET';


		$response = wp_remote_request( $url, $request_args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( $response->get_error_code(), $response->get_error_message(), $response->get_error_data( $response->get_error_code() ) );
		}

		$response_code    = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );
		$response_body    = wp_remote_retrieve_body( $response );

		if ( 200 != $response_code ) {
			return new WP_Error( $response_code, $response_message, $response_body );
		}

		$doi_response = explode( "\n", str_replace( "\r", '', $response_body ) );
		$doi_metadata = array();
		foreach ( $doi_response as $meta_row ) {
			$row_values = explode( ': ', $meta_row, 2 );
			if ( ! empty( $row_values[0] ) ) {
				$decoded_value = preg_replace_callback(
					'/\\\\u([0-9a-fA-F]{4})/',
					function ( $match ) {
						return mb_convert_encoding( pack( 'H*', $match[1] ), 'UTF-8', 'UCS-2BE' );
					},
					$row_values[1]
				);
				$doi_metadata[ $row_values[0] ] = $decoded_value;
			}
		}
		if ( 'API is up' !== $doi_metadata['success'] ) {
			return new WP_Error( 'doiServerError', 'DOI server is not okay.', var_export( $doi_metadata, true ) );
		}

		return $doi_metadata;

	}

}
