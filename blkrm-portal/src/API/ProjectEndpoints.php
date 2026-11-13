<?php
/**
 * Project CRUD REST endpoints.
 *
 * @package WPAgencyHub\API
 */

namespace WPAgencyHub\API;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Project REST endpoints.
 */
class ProjectEndpoints extends RestController {

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
			'/projects',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_projects' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_project' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/projects/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_project' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_project' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_project' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);
	}

	/**
	 * List projects (filtered by client for non-admins).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_projects( $request ) {
		global $wpdb;

		$client = $this->get_current_client();

		if ( false === $client ) {
			return $this->error( __( 'Unauthorized.', 'wp-agency-hub' ), 403 );
		}

		if ( $client ) {
			// Client — filtered.
			$projects = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ah_projects WHERE client_id = %d ORDER BY updated_at DESC",
					$client->id
				)
			);
		} else {
			// Admin — all projects.
			$projects = $wpdb->get_results(
				"SELECT p.*, c.company_name
				FROM {$wpdb->prefix}ah_projects p
				LEFT JOIN {$wpdb->prefix}ah_clients c ON p.client_id = c.id
				ORDER BY p.updated_at DESC"
			);
		}

		return $this->success( $projects );
	}

	/**
	 * Get a single project.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_project( $request ) {
		global $wpdb;

		$project_id = absint( $request['id'] );
		$access     = $this->verify_project_access( $project_id );

		if ( is_wp_error( $access ) ) {
			return $access;
		}

		$project = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ah_projects WHERE id = %d",
				$project_id
			)
		);

		if ( ! $project ) {
			return $this->error( __( 'Project not found.', 'wp-agency-hub' ), 404 );
		}

		// Attach milestones.
		$project->milestones = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ah_milestones WHERE project_id = %d ORDER BY sort_order ASC",
				$project_id
			)
		);

		return $this->success( $project );
	}

	/**
	 * Create a project (admin only).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create_project( $request ) {
		global $wpdb;

		$client_id   = absint( $request->get_param( 'client_id' ) );
		$name        = sanitize_text_field( $request->get_param( 'name' ) );
		$description = sanitize_textarea_field( $request->get_param( 'description' ) );
		$status      = sanitize_text_field( $request->get_param( 'status' ) ) ?: 'discovery';
		$due_date    = sanitize_text_field( $request->get_param( 'due_date' ) );

		if ( empty( $name ) || empty( $client_id ) ) {
			return $this->error( __( 'Name and client are required.', 'wp-agency-hub' ) );
		}

		$wpdb->insert(
			$wpdb->prefix . 'ah_projects',
			array(
				'client_id'   => $client_id,
				'name'        => $name,
				'status'      => $status,
				'description' => $description,
				'due_date'    => $due_date ?: null,
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$project_id = $wpdb->insert_id;

		Plugin::log_activity( $project_id, get_current_user_id(), 'project_created', $name );

		return $this->success( array( 'id' => $project_id ) );
	}

	/**
	 * Update a project (admin only).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_project( $request ) {
		global $wpdb;

		$project_id = absint( $request['id'] );

		$project = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ah_projects WHERE id = %d",
				$project_id
			)
		);

		if ( ! $project ) {
			return $this->error( __( 'Project not found.', 'wp-agency-hub' ), 404 );
		}

		$data   = array();
		$format = array();

		$fields = array(
			'name'        => 'sanitize_text_field',
			'status'      => 'sanitize_text_field',
			'description' => 'sanitize_textarea_field',
			'due_date'    => 'sanitize_text_field',
		);

		foreach ( $fields as $field => $sanitizer ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = $sanitizer( $value );
				$format[]       = '%s';
			}
		}

		if ( ! empty( $data ) ) {
			$data['updated_at'] = current_time( 'mysql' );
			$format[]           = '%s';

			$wpdb->update(
				$wpdb->prefix . 'ah_projects',
				$data,
				array( 'id' => $project_id ),
				$format,
				array( '%d' )
			);

			Plugin::log_activity( $project_id, get_current_user_id(), 'project_updated', wp_json_encode( $data ) );
		}

		return $this->success( array( 'id' => $project_id ) );
	}

	/**
	 * Delete a project (admin only).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_project( $request ) {
		global $wpdb;

		$project_id = absint( $request['id'] );

		// Delete physical files before removing database records.
		$files = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT file_path FROM {$wpdb->prefix}ah_files WHERE project_id = %d",
				$project_id
			)
		);

		if ( $files ) {
			$upload_dir = wp_upload_dir();
			foreach ( $files as $file ) {
				$full_path = $upload_dir['basedir'] . '/' . $file->file_path;
				if ( file_exists( $full_path ) ) {
					wp_delete_file( $full_path );
				}
			}

			// Remove the project upload directory if empty.
			$project_dir = $upload_dir['basedir'] . '/' . WPAH_UPLOAD_DIR . '/' . $project_id;
			if ( is_dir( $project_dir ) ) {
				@rmdir( $project_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		$wpdb->delete( $wpdb->prefix . 'ah_milestones', array( 'project_id' => $project_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'ah_files', array( 'project_id' => $project_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'ah_approvals', array( 'project_id' => $project_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'ah_messages', array( 'project_id' => $project_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'ah_activity_log', array( 'project_id' => $project_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'ah_projects', array( 'id' => $project_id ), array( '%d' ) );

		return $this->success();
	}
}
