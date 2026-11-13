<?php
/**
 * Project detail view for portal.
 *
 * @package WPAgencyHub\Portal
 */

namespace WPAgencyHub\Portal;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the project detail page with files, messages, approvals.
 */
class ProjectView {

	/**
	 * Render the project detail.
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

		$milestones = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ah_milestones WHERE project_id = %d ORDER BY sort_order ASC",
				$project_id
			)
		);

		$recent_files = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT f.*, u.display_name AS uploader_name
				FROM {$wpdb->prefix}ah_files f
				LEFT JOIN {$wpdb->users} u ON f.uploaded_by = u.ID
				WHERE f.project_id = %d
				ORDER BY f.created_at DESC
				LIMIT 5",
				$project_id
			)
		);

		$recent_messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, u.display_name AS sender_name
				FROM {$wpdb->prefix}ah_messages m
				LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
				WHERE m.project_id = %d
				ORDER BY m.created_at DESC
				LIMIT 5",
				$project_id
			)
		);

		$approvals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ah_approvals WHERE project_id = %d ORDER BY created_at DESC",
				$project_id
			)
		);

		$activity = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, u.display_name AS user_name
				FROM {$wpdb->prefix}ah_activity_log a
				LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
				WHERE a.project_id = %d
				ORDER BY a.created_at DESC
				LIMIT 20",
				$project_id
			)
		);

		PortalTemplate::header( $project->name, $client );
		include WPAH_PLUGIN_DIR . 'templates/portal/project.php';
		PortalTemplate::footer();
	}
}
