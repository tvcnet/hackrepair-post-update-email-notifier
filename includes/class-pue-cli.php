<?php
/**
 * WP-CLI commands.
 *
 * @package PostUpdateEmailNotifier
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	return;
}

/**
 * Test, send and inspect post update emails.
 */
class PUE_CLI extends WP_CLI_Command {

	/**
	 * Send a test email using the saved template.
	 *
	 * ## OPTIONS
	 *
	 * --to=<email>
	 * : Recipient address.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pue test --to=me@example.com
	 *
	 * @param array $args Positional.
	 * @param array $assoc Associative.
	 */
	public function test( $args, $assoc ) {
		$to = isset( $assoc['to'] ) ? sanitize_email( $assoc['to'] ) : '';
		if ( ! is_email( $to ) ) {
			WP_CLI::error( 'Provide a valid --to address.' );
		}
		if ( pue_send_test_email( $to, PUE_Defaults::option( 'pue_subject' ), PUE_Defaults::option( 'pue_message' ) ) ) {
			WP_CLI::success( "Accepted for sending to {$to}. Acceptance does not confirm delivery." );
		} else {
			WP_CLI::error( "The test email to {$to} was not accepted for sending." );
		}
	}

	/**
	 * Send the update notification for a published post now, ignoring the per-post flag and cooldown.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : Published post ID.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * @param array $args Positional.
	 * @param array $assoc Associative.
	 */
	public function send( $args, $assoc ) {
		$post = get_post( (int) $args[0] );
		if ( ! $post || 'publish' !== $post->post_status ) {
			WP_CLI::error( 'Post not found or not published.' );
		}
		$count = count( pue_get_recipients( (array) PUE_Defaults::option( 'pue_roles' ) ) );
		WP_CLI::confirm( "Email {$count} recipient(s) about \"{$post->post_title}\"?", $assoc );
		PUE_Notification_Service::instance()->send_for_post( $post->ID );
		WP_CLI::success( 'Notification processed. Check the log for the result.' );
	}

	/**
	 * Show how many people currently receive update emails.
	 *
	 * [--list]
	 * : Print the addresses.
	 *
	 * @param array $args Positional.
	 * @param array $assoc Associative.
	 */
	public function recipients( $args, $assoc ) {
		$emails = pue_get_recipients( (array) PUE_Defaults::option( 'pue_roles' ) );
		if ( ! empty( $assoc['list'] ) ) {
			foreach ( $emails as $email ) {
				WP_CLI::line( $email );
			}
		}
		WP_CLI::success( count( $emails ) . ' recipient(s) before developer filters.' );
	}

	/**
	 * Export or clear the email log.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : export or clear.
	 * ---
	 * options:
	 *   - export
	 *   - clear
	 * ---
	 *
	 * [--file=<path>]
	 * : Write the CSV to this file instead of standard output.
	 *
	 * @param array $args Positional.
	 * @param array $assoc Associative.
	 */
	public function log( $args, $assoc ) {
		if ( 'clear' === $args[0] ) {
			update_option( 'pue_logs', array(), false );
			WP_CLI::success( 'Log cleared.' );
			return;
		}
		$path = isset( $assoc['file'] ) ? (string) $assoc['file'] : 'php://stdout';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CLI output stream or a file the operator named.
		$out = fopen( $path, 'w' );
		if ( ! $out ) {
			WP_CLI::error( "Cannot write to {$path}." );
		}
		PUE_Logger::instance()->write_csv( $out );
		if ( 'php://stdout' !== $path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the stream opened above.
			fclose( $out );
			WP_CLI::success( "Log written to {$path}." );
		}
	}
}
