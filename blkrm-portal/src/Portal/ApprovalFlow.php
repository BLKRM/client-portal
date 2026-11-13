<?php
/**
 * Deliverable approval flow.
 *
 * @package WPAgencyHub\Portal
 */

namespace WPAgencyHub\Portal;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles the approval flow state machine.
 *
 * States: draft -> pending_review -> approved | changes_requested -> pending_review (loop)
 */
class ApprovalFlow {

	/**
	 * Valid state transitions.
	 *
	 * @var array
	 */
	const TRANSITIONS = array(
		'draft'             => array( 'pending_review' ),
		'pending_review'    => array( 'approved', 'changes_requested' ),
		'changes_requested' => array( 'pending_review' ),
		'approved'          => array(), // Terminal state.
	);

	/**
	 * Render the approval detail page.
	 *
	 * @param int         $project_id  Project ID.
	 * @param int         $approval_id Approval ID.
	 * @param object|null $client      Client record.
	 * @param bool        $is_admin    Whether current user is admin.
	 */
	public function render( $project_id, $approval_id, $client, $is_admin ) {
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

		$approval = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, f.file_name, f.file_path, f.mime_type, f.file_size
				FROM {$wpdb->prefix}ah_approvals a
				LEFT JOIN {$wpdb->prefix}ah_files f ON a.file_id = f.id
				WHERE a.id = %d AND a.project_id = %d",
				$approval_id,
				$project_id
			)
		);

		if ( ! $approval ) {
			wp_die( esc_html__( 'Approval not found.', 'wp-agency-hub' ) );
		}

		// Get approval history from activity log.
		$history = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, u.display_name AS user_name
				FROM {$wpdb->prefix}ah_activity_log a
				LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
				WHERE a.project_id = %d AND a.action LIKE %s
				ORDER BY a.created_at ASC",
				$project_id,
				'%approval%'
			)
		);

		PortalTemplate::header(
			/* translators: %s: approval title */
			sprintf( __( 'Approval — %s', 'wp-agency-hub' ), $approval->title ),
			$client
		);
		include WPAH_PLUGIN_DIR . 'templates/portal/approval.php';
		PortalTemplate::footer();
	}

	/**
	 * Transition an approval to a new state.
	 *
	 * @param int    $approval_id Approval ID.
	 * @param string $new_status  Target status.
	 * @param int    $user_id     User performing the action.
	 * @param string $note        Optional response note.
	 * @return true|\WP_Error
	 */
	public static function transition( $approval_id, $new_status, $user_id, $note = '' ) {
		global $wpdb;

		$approval = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ah_approvals WHERE id = %d",
				$approval_id
			)
		);

		if ( ! $approval ) {
			return new \WP_Error( 'not_found', __( 'Approval not found.', 'wp-agency-hub' ) );
		}

		$current = $approval->status;
		$allowed = isset( self::TRANSITIONS[ $current ] ) ? self::TRANSITIONS[ $current ] : array();

		if ( ! in_array( $new_status, $allowed, true ) ) {
			return new \WP_Error(
				'invalid_transition',
				/* translators: 1: current status, 2: target status */
				sprintf( __( 'Cannot transition from "%1$s" to "%2$s".', 'wp-agency-hub' ), $current, $new_status )
			);
		}

		$data = array(
			'status'        => $new_status,
			'responded_by'  => $user_id,
			'response_note' => $note,
			'responded_at'  => current_time( 'mysql' ),
		);

		$wpdb->update(
			$wpdb->prefix . 'ah_approvals',
			$data,
			array( 'id' => $approval_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		// Log the transition.
		$action = 'approval_' . $new_status;
		Plugin::log_activity(
			$approval->project_id,
			$user_id,
			$action,
			wp_json_encode(
				array(
					'approval_id' => $approval_id,
					'from'        => $current,
					'to'          => $new_status,
					'note'        => $note,
				)
			)
		);

		/**
		 * Fires after an approval state transition.
		 *
		 * @param object $approval   The approval record (pre-transition).
		 * @param string $new_status The new status.
		 * @param int    $user_id    User who made the change.
		 * @param string $note       Response note.
		 */
		do_action( 'wpah_approval_transitioned', $approval, $new_status, $user_id, $note );

		return true;
	}
}
