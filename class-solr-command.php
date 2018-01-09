<?php
/**
 * HumCORE Solr API commands.
 *
 * @package HumCORE
 * @subpackage Deposits
 */

class Solr_Command extends WP_CLI_Command {

	/**
	 * Delete a PID.
	 *
	 * ## OPTIONS
	 *
	 * <doi>
	 * : The PID to be deleted.
	 *
	 * ## EXAMPLES
	 *
	 *     wp solr delete --pid="pid"
	 *
	 * @synopsis --pid=<pid>
	 */
	public function delete( $args, $assoc_args ) {

		global $solr_client;

		$id = $assoc_args['pid'];

		$s_status = $solr_client->delete_humcore_document( $id );

		if ( is_wp_error( $s_status ) ) {
			WP_CLI::error( sprintf( 'Error deleting pid : %1$s, %2$s-%3$s', $id, $s_status->get_error_code(), $s_status->get_error_message() ) );
		} else {

			// Print a success message
			WP_CLI::success( sprintf( 'Deleted pid : %1$s!', $id ) );
		}
	}

}

WP_CLI::add_command( 'solr', 'Solr_Command' );
