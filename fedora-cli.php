<?php
/**
 * HumCORE Fedora API commands.
 *
 * @package HumCORE
 * @subpackage Deposits
 */

class Fedora_Command extends WP_CLI_Command {

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
     *     wp fedora delete --pid="pid"
     *
     * @synopsis --pid=<pid>
     */
    public function delete( $args, $assoc_args ) {

        global $fedora_api;

        $id = $assoc_args['pid'];

        $fStatus = $fedora_api->purge_object( array(
            'pid' => $id,
        ) );

        if ( is_wp_error( $fStatus ) ) {
            WP_CLI::error( sprintf( 'Error deleting pid : %1$s, %2$s-%3$s', $id, $fStatus->get_error_code(), $fStatus->get_error_message() ) );
        } else {

            // Print a success message
            WP_CLI::success( sprintf( 'Deleted pid : %1$s!', $id ) );
        }
    }

    /**
     * Validate a PID.
     * 
     * ## OPTIONS
     * 
     * <pid>
     * : The PID to be validated.
     * 
     * ## EXAMPLES
     * 
     *     wp fedora validate --pid="pid"
     *
     * @synopsis --pid=<pid>
     */
    public function validate( $args, $assoc_args ) {

        global $fedora_api;

        $id = $assoc_args['pid'];

        $fStatus = $fedora_api->validate( array(
            'pid' => $id,
        ) );

        if ( is_wp_error( $fStatus ) ) {
            WP_CLI::error( sprintf( 'Error validating pid : %1$s, %2$s-%3$s', $id, $fStatus->get_error_code(), $fStatus->get_error_message() ) );
        } else {
            WP_CLI::line( var_export( $fStatus, true ) );
            // Print a success message
            WP_CLI::success( 'Done!' );
        }
    }
}

WP_CLI::add_command( 'fedora', 'Fedora_Command' );
