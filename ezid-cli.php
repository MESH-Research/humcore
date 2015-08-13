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

        $eStatus = $ezid_api->delete_identifier( array(
        'doi' => $id,
        ) );

        if ( is_wp_error( $eStatus ) ) {
            WP_CLI::error( sprintf( 'Error deleting doi : %1$s, %2$s-%3$s', $id, $eStatus->get_error_code(), $eStatus->get_error_message() ) );
        } else {

            // Print a success message
            WP_CLI::success( sprintf( 'Deleted doi : %1$s!', $id ) );
        }
    }
}

WP_CLI::add_command( 'ezid', 'Ezid_Command' );
