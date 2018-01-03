<?php
/**
 * HumCORE EZID API commands.
 *
 * @package HumCORE
 * @subpackage Deposits
 */

class Ezid_Command extends WP_CLI_Command {

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
	 *     wp ezid delete --doi="doi"
	 *
	 * @synopsis --doi=<doi>
	 */
	public function delete( $args, $assoc_args ) {

		global $ezid_api;

		$id = $assoc_args['doi'];

		$e_status = $ezid_api->delete_identifier(
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
	 * Unpublish a published DOI.
	 *
	 * ## OPTIONS
	 *
	 * <doi>
	 * : The published DOI to be made unavailable.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ezid unpublish --doi="doi"
	 *
	 * @synopsis --doi=<doi>
	 */
	public function unpublish( $args, $assoc_args ) {

		global $ezid_api;

		$id = $assoc_args['doi'];

		$e_status = $ezid_api->modify_identifier(
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

WP_CLI::add_command( 'ezid', 'Ezid_Command' );
