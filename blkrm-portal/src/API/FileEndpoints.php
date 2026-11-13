<?php
/**
 * File upload/download REST endpoints.
 *
 * @package WPAgencyHub\API
 */

namespace WPAgencyHub\API;

use WPAgencyHub\Plugin;
use WPAgencyHub\Portal\FileManager;

defined( 'ABSPATH' ) || exit;

/**
 * File REST endpoints.
 */
class FileEndpoints extends RestController {

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
			'/projects/(?P<project_id>\d+)/files',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_files' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'upload_file' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/files/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_file' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_file' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/files/(?P<id>\d+)/download',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'download_file' ),
				'permission_callback' => array( $this, 'check_auth' ),
			)
		);
	}

	/**
	 * List files for a project.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_files( $request ) {
		global $wpdb;

		$project_id = absint( $request['project_id'] );
		$access     = $this->verify_project_access( $project_id );

		if ( is_wp_error( $access ) ) {
			return $access;
		}

		$files = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT f.*, u.display_name AS uploader_name
				FROM {$wpdb->prefix}ah_files f
				LEFT JOIN {$wpdb->users} u ON f.uploaded_by = u.ID
				WHERE f.project_id = %d
				ORDER BY f.created_at DESC",
				$project_id
			)
		);

		return $this->success( $files );
	}

	/**
	 * Upload a file to a project.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function upload_file( $request ) {
		$project_id = absint( $request['project_id'] );
		$access     = $this->verify_project_access( $project_id );

		if ( is_wp_error( $access ) ) {
			return $access;
		}

		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return $this->error( __( 'No file provided.', 'wp-agency-hub' ) );
		}

		$result = FileManager::store_file( $files['file'], $project_id, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result->get_error_message() );
		}

		return $this->success( array( 'file_id' => $result ) );
	}

	/**
	 * Get file details.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_file( $request ) {
		global $wpdb;

		$file_id = absint( $request['id'] );

		$file = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ah_files WHERE id = %d",
				$file_id
			)
		);

		if ( ! $file ) {
			return $this->error( __( 'File not found.', 'wp-agency-hub' ), 404 );
		}

		$access = $this->verify_project_access( $file->project_id );
		if ( is_wp_error( $access ) ) {
			return $access;
		}

		return $this->success( $file );
	}

	/**
	 * Download a file.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|void
	 */
	public function download_file( $request ) {
		global $wpdb;

		$file_id = absint( $request['id'] );

		$file = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ah_files WHERE id = %d",
				$file_id
			)
		);

		if ( ! $file ) {
			return $this->error( __( 'File not found.', 'wp-agency-hub' ), 404 );
		}

		$access = $this->verify_project_access( $file->project_id );
		if ( is_wp_error( $access ) ) {
			return $access;
		}

		$client    = $this->get_current_client();
		$client_id = $client ? $client->id : 0;

		FileManager::serve_download( $file_id, $client_id );
	}

	/**
	 * Delete a file (admin only).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_file( $request ) {
		global $wpdb;

		$file_id = absint( $request['id'] );

		$file = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ah_files WHERE id = %d",
				$file_id
			)
		);

		if ( ! $file ) {
			return $this->error( __( 'File not found.', 'wp-agency-hub' ), 404 );
		}

		// Delete physical file.
		$upload_dir = wp_upload_dir();
		$full_path  = $upload_dir['basedir'] . '/' . $file->file_path;
		if ( file_exists( $full_path ) ) {
			wp_delete_file( $full_path );
		}

		$wpdb->delete( $wpdb->prefix . 'ah_files', array( 'id' => $file_id ), array( '%d' ) );

		Plugin::log_activity(
			$file->project_id,
			get_current_user_id(),
			'file_deleted',
			wp_json_encode( array( 'file_id' => $file_id, 'name' => $file->file_name ) )
		);

		return $this->success();
	}
}
