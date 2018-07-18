<?php
/**
 * HumCORE DOI API commands.
 *
 * @package HumCORE
 * @subpackage Deposits
 */

class Doi_Command extends WP_CLI_Command {

	/**
	 * Delete a reserved DOI.
	 *
	 * ## OPTIONS
	 *
	 * <doi>
	 * : The reserved DOI to be deleted.
	 *
	 * ## EXAMPLES
	 *
	 *     wp doi delete --doi="doi"
	 *
	 * @synopsis --doi=<doi>
	 */
	public function delete( $args, $assoc_args ) {

		global $doi_api;

		$id = $assoc_args['doi'];

		$e_status = $doi_api->delete_identifier(
			array(
				'doi' => $id,
			)
		);

		if ( is_wp_error( $e_status ) ) {
			WP_CLI::error( sprintf( 'Error deleting doi : %1$s, %2$s-%3$s', $id, $e_status->get_error_code(), $e_status->get_error_message() ) );
		} else {

			// Print a success message
			WP_CLI::success( sprintf( 'Deleted doi : %1$s!', $id ) );
		}
	}

	/**
	 * Get a DOI.
	 *
	 * ## OPTIONS
	 *
	 * <doi>
	 * : The DOI to be retrieve.
	 *
	 * ## EXAMPLES
	 *
	 *     wp doi get --doi="doi"
	 *
	 * @synopsis --doi=<doi>
	 */
	public function get( $args, $assoc_args ) {

		global $doi_api;

		$id = $assoc_args['doi'];

		$e_status = $doi_api->get_identifier(
			array(
				'doi' => $id,
			)
		);

		if ( is_wp_error( $e_status ) ) {
			WP_CLI::error( sprintf( 'Error getting doi : %1$s, %2$s-%3$s', $id, $e_status->get_error_code(), $e_status->get_error_message() ) );
		} else {

			// Print a success message
			WP_CLI::success( sprintf( 'retrieve doi : %1$s!', $id ) . '-' . var_export( $e_status, true ) );
		}
	}

	/**
	 * Check server status
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *     wp doi status
	 *
	 */
	public function status( $args ) {

		global $doi_api;

		$e_status = $doi_api->server_status;

		if ( is_wp_error( $e_status ) ) {
			WP_CLI::error( sprintf( 'Error checking DOI service status ', $e_status->get_error_code(), $e_status->get_error_message() ) );
		} else {

			// Print a success message
			WP_CLI::success( sprintf( 'DOI service is available.' ) );
		}

	}

	/**
	 * Unpublish a published DOI.
	 *
	 * ## OPTIONS
	 *
	 * <doi>
	 * : The published DOI to be made unavailable.
	 *
	 * ## EXAMPLES
	 *
	 *     wp doi unpublish --doi="doi"
	 *
	 * @synopsis --doi=<doi>
	 */
	public function unpublish( $args, $assoc_args ) {

		global $doi_api;

		$id = $assoc_args['doi'];

		$e_status = $doi_api->modify_identifier(
			array(
				'doi'     => $id,
				'_status' => 'unavailable|Created in error.',
			)
		);

		if ( is_wp_error( $e_status ) ) {
			WP_CLI::error( sprintf( 'Error modifying doi : %1$s, %2$s-%3$s', $id, $e_status->get_error_code(), $e_status->get_error_message() ) );
		} else {

			// Print a success message
			WP_CLI::success( sprintf( 'Doi : %1$s! is now unavailable.', $id ) );
		}
	}
}

WP_CLI::add_command( 'doi', 'Doi_Command' );
