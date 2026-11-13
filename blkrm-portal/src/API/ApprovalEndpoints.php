<?php
/**
 * Approval state transition REST endpoints.
 *
 * @package WPAgencyHub\API
 */

namespace WPAgencyHub\API;

use WPAgencyHub\Plugin;
use WPAgencyHub\Portal\ApprovalFlow;
use WPAgencyHub\Notifications\EmailNotifier;

defined( 'ABSPATH' ) || exit;

/**
 * Approval REST endpoints.
 */
class ApprovalEndpoints extends RestController {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/projects/(?P<project_id>\d+)/approvals',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_approvals' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_approval' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/approvals/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_approval' ),
				'permission_callback' => array( $this, 'check_auth' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/approvals/(?P<id>\d+)/transition',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'transition_approval' ),
				'permission_callback' => array( $this, 'check_auth' ),
			)
		);
	}

	/**
	 * List approvals for a project.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_approvals( $request ) {
		global $wpdb;

		$project_id = absint( $request['project_id'] );
		$access     = $this->verify_project_access( $project_id );

		if ( is_wp_error( $access ) ) {
			return $access;
		}

		$approvals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, f.file_name
				FROM {$wpdb->prefix}ah_approvals a
				LEFT JOIN {$wpdb->prefix}ah_files f ON a.file_id = f.id
				WHERE a.project_id = %d
				ORDER BY a.created_at DESC",
				$project_id
			)
		);

		return $this->success( $approvals );
	}

	/**
	 * Create an approval request (admin only).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create_approval( $request ) {
		global $wpdb;

		$project_id = absint( $request['project_id'] );
		$title      = sanitize_text_field( $request->get_param( 'title' ) );
		$file_id    = absint( $request->get_param( 'file_id' ) );

		if ( empty( $title ) ) {
			return $this->error( __( 'Title is required.', 'wp-agency-hub' ) );
		}

		$wpdb->insert(
			$wpdb->prefix . 'ah_approvals',
			array(
				'project_id'   => $project_id,
				'file_id'      => $file_id ?: null,
				'title'        => $title,
				'status'       => 'draft',
				'requested_by' => get_current_user_id(),
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		$approval_id = $wpdb->insert_id;

		Plugin::log_activity(
			$project_id,
			get_current_user_id(),
			'approval_created',
			wp_json_encode( array( 'approval_id' => $approval_id, 'title' => $title ) )
		);

		return $this->success( array( 'id' => $approval_id ) );
	}

	/**
	 * Get a single approval.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_approval( $request ) {
		global $wpdb;

		$approval_id = absint( $request['id'] );

		$approval = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, f.file_name, f.mime_type
				FROM {$wpdb->prefix}ah_approvals a
				LEFT JOIN {$wpdb->prefix}ah_files f ON a.file_id = f.id
				WHERE a.id = %d",
				$approval_id
			)
		);

		if ( ! $approval ) {
			return $this->error( __( 'Approval not found.', 'wp-agency-hub' ), 404 );
		}

		$access = $this->verify_project_access( $approval->project_id );
		if ( is_wp_error( $access ) ) {
			return $access;
		}

		return $this->success( $approval );
	}

	/**
	 * Transition an approval state.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function transition_approval( $request ) {
		global $wpdb;

		$approval_id = absint( $request['id'] );
		$new_status  = sanitize_text_field( $request->get_param( 'status' ) );
		$note        = sanitize_textarea_field( $request->get_param( 'note' ) );

		// Verify approval exists and get project_id for access check.
		$approval = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ah_approvals WHERE id = %d",
				$approval_id
			)
		);

		if ( ! $approval ) {
			return $this->error( __( 'Approval not found.', 'wp-agency-hub' ), 404 );
		}

		$access = $this->verify_project_access( $approval->project_id );
		if ( is_wp_error( $access ) ) {
			return $access;
		}

		// Only clients can approve/request changes, only admins can submit for review.
		$user     = wp_get_current_user();
		$is_admin = current_user_can( 'manage_options' );

		if ( 'pending_review' === $new_status && 'draft' === $approval->status && ! $is_admin ) {
			return $this->error( __( 'Only agency staff can submit for review.', 'wp-agency-hub' ), 403 );
		}

		if ( in_array( $new_status, array( 'approved', 'changes_requested' ), true ) && $is_admin ) {
			// Admins can also approve/request changes on behalf of client for flexibility.
		}

		$result = ApprovalFlow::transition( $approval_id, $new_status, get_current_user_id(), $note );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result->get_error_message() );
		}

		// Send email notification.
		$project = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.*, c.contact_email AS client_email
				FROM {$wpdb->prefix}ah_projects p
				LEFT JOIN {$wpdb->prefix}ah_clients c ON p.client_id = c.id
				WHERE p.id = %d",
				$approval->project_id
			)
		);

		if ( $project ) {
			if ( 'pending_review' === $new_status ) {
				EmailNotifier::send_approval_requested( $project->client_email, $project, $approval );
			} elseif ( in_array( $new_status, array( 'approved', 'changes_requested' ), true ) ) {
				$admin_email = get_option( 'admin_email' );
				EmailNotifier::send_approval_completed( $admin_email, $project, $approval, $new_status, $note );
			}
		}

		return $this->success( array( 'status' => $new_status ) );
	}
}
