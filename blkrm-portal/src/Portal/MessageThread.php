<?php
/**
 * Per-project async messaging.
 *
 * @package WPAgencyHub\Portal
 */

namespace WPAgencyHub\Portal;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the message thread for a project.
 */
class MessageThread {

	/**
	 * Render the message thread.
	 *
	 * @param int         $project_id Project ID.
	 * @param object|null $client     Client record.
	 * @param bool        $is_admin   Whether current user is admin.
	 */
	public function render( $project_id, $client, $is_admin ) {
		global $wpdb;

		$project = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ah_projects WHERE id = %d",
				$project_id
			)
		);

		if ( ! $project ) {
			wp_die( esc_html__( 'Project not found.', 'wp-agency-hub' ) );
		}

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, u.display_name AS sender_name
				FROM {$wpdb->prefix}ah_messages m
				LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
				WHERE m.project_id = %d
				ORDER BY m.created_at ASC",
				$project_id
			)
		);

		// Mark messages from the other party as read.
		$current_user_id = get_current_user_id();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}ah_messages
				SET read_at = %s
				WHERE project_id = %d AND sender_id != %d AND read_at IS NULL",
				current_time( 'mysql' ),
				$project_id,
				$current_user_id
			)
		);

		PortalTemplate::header(
			/* translators: %s: project name */
			sprintf( __( 'Messages — %s', 'wp-agency-hub' ), $project->name ),
			$client
		);
		include WPAH_PLUGIN_DIR . 'templates/portal/messages.php';
		PortalTemplate::footer();
	}
}
