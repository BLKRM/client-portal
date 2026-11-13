<?php
/**
 * Client-facing dashboard.
 *
 * @package WPAgencyHub\Portal
 */

namespace WPAgencyHub\Portal;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the client dashboard with projects and activity.
 */
class ClientDashboard {

	/**
	 * Render the dashboard.
	 *
	 * @param object|null $client  Client record (null for admin preview).
	 * @param bool        $is_admin Whether current user is admin.
	 */
	public function render( $client, $is_admin ) {
		global $wpdb;

		// Get projects — filtered by client_id for clients, all for admins.
		if ( $client ) {
			$projects = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ah_projects WHERE client_id = %d ORDER BY updated_at DESC",
					$client->id
				)
			);

			$activity = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT a.*, p.name AS project_name, u.display_name AS user_name
					FROM {$wpdb->prefix}ah_activity_log a
					INNER JOIN {$wpdb->prefix}ah_projects p ON a.project_id = p.id AND p.client_id = %d
					LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
					ORDER BY a.created_at DESC
					LIMIT 15",
					$client->id
				)
			);

			$pending_approvals = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ap.*, p.name AS project_name
					FROM {$wpdb->prefix}ah_approvals ap
					INNER JOIN {$wpdb->prefix}ah_projects p ON ap.project_id = p.id AND p.client_id = %d
					WHERE ap.status = 'pending_review'
					ORDER BY ap.created_at DESC",
					$client->id
				)
			);

			$unread_messages = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}ah_messages m
					INNER JOIN {$wpdb->prefix}ah_projects p ON m.project_id = p.id AND p.client_id = %d
					WHERE m.read_at IS NULL AND m.sender_id != %d",
					$client->id,
					get_current_user_id()
				)
			);
		} else {
			// Admin preview — show all.
			$projects = $wpdb->get_results(
				"SELECT p.*, c.company_name
				FROM {$wpdb->prefix}ah_projects p
				LEFT JOIN {$wpdb->prefix}ah_clients c ON p.client_id = c.id
				ORDER BY p.updated_at DESC LIMIT 20"
			);

			$activity          = array();
			$pending_approvals = array();
			$unread_messages   = 0;
		}

		PortalTemplate::header( __( 'Dashboard', 'wp-agency-hub' ), $client );
		include WPAH_PLUGIN_DIR . 'templates/portal/dashboard.php';
		PortalTemplate::footer();
	}
}
