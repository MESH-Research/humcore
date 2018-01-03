<?php
/**
 * API to access EZID.
 *
 * @package HumCORE
 * @subpackage Deposits
 */

/**
 * Class using WP_HTTP to access the EZIP API.
 */
class Humcore_Deposit_Ezid_Api {

	private $ezid_settings = array();
	private $base_url;
	private $options           = array();
	private $upload_filehandle = array(); // Handle the WP_HTTP inability to process file uploads by hooking curl settings.
	private $ezid_path;
	private $ezid_mint_path;
	private $ezid_prefix;

	/* getting removed
	public $servername_hash;
	*/
	public $service_status;
	public $namespace;
	public $temp_dir;

	/**
	 * Initialize EZID API settings.
	 */
	public function __construct() {

		$humcore_settings = get_option( 'humcore-deposits-humcore-settings' );

		/* getting removed
		if ( defined( 'CORE_EZID_HOST' ) && ! empty( CORE_EZID_HOST ) ) { // Better have a value if defined.
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

		$this->ezid_settings = get_option( 'humcore-deposits-ezid-settings' );

		if ( defined( 'CORE_EZID_PROTOCOL' ) ) {
				$this->ezid_settings['protocol'] = CORE_EZID_PROTOCOL;
		}
		if ( defined( 'CORE_EZID_HOST' ) ) {
				$this->ezid_settings['host'] = CORE_EZID_HOST;
		}
		if ( defined( 'CORE_EZID_PORT' ) ) {
				$this->ezid_settings['port'] = CORE_EZID_PORT;
		}
		if ( defined( 'CORE_EZID_PATH' ) ) {
				$this->ezid_settings['path'] = CORE_EZID_PATH;
		}
		if ( defined( 'CORE_EZID_LOGIN' ) ) {
				$this->ezid_settings['login'] = CORE_EZID_LOGIN;
		}
		if ( defined( 'CORE_EZID_PASSWORD' ) ) {
				$this->ezid_settings['password'] = CORE_EZID_PASSWORD;
		}
		if ( defined( 'CORE_EZID_PREFIX' ) ) {
				$this->ezid_settings['prefix'] = CORE_EZID_PREFIX;
		}

		if ( ! empty( $this->ezid_settings['port'] ) ) {
			$this->base_url = $this->ezid_settings['protocol'] . $this->ezid_settings['host'] . ':' . $this->ezid_settings['port'];
		} else {
			$this->base_url = $this->ezid_settings['protocol'] . $this->ezid_settings['host'];
		}

		$this->ezid_path = $this->ezid_settings['path'];
		//      $this->ezid_mint_path = $this->ezid_settings['mintpath'];
		$this->ezid_prefix                                     = $this->ezid_settings['prefix'];
		$this->options['api-auth']['headers']['Authorization'] = 'Basic ' . base64_encode( $this->ezid_settings['login'] . ':' . $this->ezid_settings['password'] );
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

		$ezid_response = explode( "\n", str_replace( "\r", '', $response_body ) );
		$ezid_metadata = array();
		foreach ( $ezid_response as $meta_row ) {
			$row_values = explode( ': ', $meta_row, 2 );
			if ( ! empty( $row_values[0] ) ) {
				$decoded_value = preg_replace_callback(
					'/\\\\u([0-9a-fA-F]{4})/',
					function ( $match ) {
						return mb_convert_encoding( pack( 'H*', $match[1] ), 'UTF-8', 'UCS-2BE' );
					},
					$row_values[1]
				);
				$ezid_metadata[ $row_values[0] ] = $decoded_value;
			}
		}
		return $ezid_metadata;

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

		$url = sprintf( '%1$s/id/%2$s%3$s', $this->base_url, $this->ezid_prefix, $doi );

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
			$ezid = explode( '|', $response_array[1], 2 );
			return trim( $ezid[0] );
		} else {
			return false;
		}

	}


	/**
	 * Mint an identifier.
	 *
	 * @param array $args Array of arguments. Supports all arguments from apidoc.html#operation-mint-identifier.
	 * @link http://ezid.cdlib.org/doc/apidoc.html#operation-mint-identifier
	 * @return WP_Error|string body of the Response object
	 * @see wp_parse_args()
	 * @see wp_remote_request()
	 */
	public function mint_identifier( array $args = array() ) {

		$defaults = array(
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

		$url = sprintf( '%1$s/shoulder/%2$s', $this->base_url, $this->ezid_prefix );

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

		if ( 201 != $response_code ) {
			return new WP_Error( $response_code, $response_message, $response_body );
		}

		humcore_write_error_log( 'info', 'Mint DOI ', array( 'response' => $response_body ) );

		$response_array = explode( ':', $response_body, 2 );
		if ( 'success' == $response_array[0] ) {
			$ezid = explode( '|', $response_array[1], 2 );
			return trim( $ezid[0] );
		} else {
			return false;
		}

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

		$defaults = array(
			'doi'          => '',
			'_status'      => '',
			'_export'      => '',
			'_profile'     => '',
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
		if ( empty( $params ) ) {
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
			$ezid = explode( '|', $response_array[1], 2 );
			return trim( $ezid[0] );
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
			$ezid = explode( '|', $response_array[1], 2 );
			return trim( $ezid[0] );
		} else {
			return false;
		}

	}


	/**
	 * Get the EZID server status
	 *
	 * @param array $args Array of arguments. Supports only subsystems argument.
	 * @link http://ezid.cdlib.org/doc/apidoc.html#server-status
	 * @return WP_Error|array subsystems status
	 * @see wp_parse_args()
	 * @see wp_remote_request()
	 */
	public function server_status( array $args = array() ) {

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

		$ezid_response = explode( "\n", str_replace( "\r", '', $response_body ) );
		$ezid_metadata = array();
		foreach ( $ezid_response as $meta_row ) {
			$row_values = explode( ': ', $meta_row, 2 );
			if ( ! empty( $row_values[0] ) ) {
				$decoded_value = preg_replace_callback(
					'/\\\\u([0-9a-fA-F]{4})/',
					function ( $match ) {
						return mb_convert_encoding( pack( 'H*', $match[1] ), 'UTF-8', 'UCS-2BE' );
					},
					$row_values[1]
				);
				$ezid_metadata[ $row_values[0] ] = $decoded_value;
			}
		}
		if ( 'EZID is up' !== $ezid_metadata['success'] ) {
			return new WP_Error( 'ezidServerError', 'EZID server is not okay.', var_export( $ezid_metadata, true ) );
		}

		return $ezid_metadata;

	}

}
