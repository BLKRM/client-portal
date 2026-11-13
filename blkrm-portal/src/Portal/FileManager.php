<?php
/**
 * File upload/download/preview for portal.
 *
 * @package WPAgencyHub\Portal
 */

namespace WPAgencyHub\Portal;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the file manager page and handles downloads.
 */
class FileManager {

	/**
	 * Render the file manager for a project.
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

		PortalTemplate::header(
			/* translators: %s: project name */
			sprintf( __( 'Files — %s', 'wp-agency-hub' ), $project->name ),
			$client
		);
		include WPAH_PLUGIN_DIR . 'templates/portal/files.php';
		PortalTemplate::footer();
	}

	/**
	 * Validate an uploaded file.
	 *
	 * @param array $file $_FILES entry.
	 * @return true|\WP_Error
	 */
	public static function validate_upload( $file ) {
		if ( empty( $file['tmp_name'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file uploaded.', 'wp-agency-hub' ) );
		}

		if ( $file['size'] > WPAH_MAX_FILE_SIZE ) {
			return new \WP_Error(
				'file_too_large',
				/* translators: %s: max file size */
				sprintf( __( 'File exceeds maximum size of %s.', 'wp-agency-hub' ), size_format( WPAH_MAX_FILE_SIZE ) )
			);
		}

		// Check MIME type.
		$finfo     = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		$allowed_types = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'application/pdf',
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.ms-powerpoint',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'application/zip',
			'text/plain',
			'text/csv',
			'video/mp4',
			'video/quicktime',
			'audio/mpeg',
			'audio/wav',
		);

		/**
		 * Filter allowed MIME types for file uploads.
		 *
		 * @param array $allowed_types Allowed MIME types.
		 */
		$allowed_types = apply_filters( 'wpah_allowed_file_types', $allowed_types );

		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			return new \WP_Error( 'invalid_type', __( 'File type not allowed.', 'wp-agency-hub' ) );
		}

		return true;
	}

	/**
	 * Store an uploaded file securely.
	 *
	 * @param array $file       $_FILES entry.
	 * @param int   $project_id Project ID.
	 * @param int   $user_id    Uploading user ID.
	 * @return int|\WP_Error File record ID or error.
	 */
	public static function store_file( $file, $project_id, $user_id ) {
		$validation = self::validate_upload( $file );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$upload_dir  = wp_upload_dir();
		$project_dir = $upload_dir['basedir'] . '/' . WPAH_UPLOAD_DIR . '/' . $project_id;

		if ( ! file_exists( $project_dir ) ) {
			wp_mkdir_p( $project_dir );
		}

		// Sanitize filename.
		$original_name = sanitize_file_name( $file['name'] );
		$ext           = pathinfo( $original_name, PATHINFO_EXTENSION );
		$safe_name     = wp_unique_filename( $project_dir, $original_name );
		$dest_path     = $project_dir . '/' . $safe_name;

		if ( ! move_uploaded_file( $file['tmp_name'], $dest_path ) ) {
			return new \WP_Error( 'upload_failed', __( 'Failed to save uploaded file.', 'wp-agency-hub' ) );
		}

		// Detect MIME type.
		$finfo     = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $dest_path );
		finfo_close( $finfo );

		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'ah_files',
			array(
				'project_id'  => $project_id,
				'uploaded_by' => $user_id,
				'file_name'   => $original_name,
				'file_path'   => WPAH_UPLOAD_DIR . '/' . $project_id . '/' . $safe_name,
				'file_size'   => filesize( $dest_path ),
				'mime_type'   => $mime_type,
				'version'     => 1,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s' )
		);

		$file_id = $wpdb->insert_id;

		Plugin::log_activity(
			$project_id,
			$user_id,
			'file_uploaded',
			wp_json_encode( array( 'file_id' => $file_id, 'name' => $original_name ) )
		);

		return $file_id;
	}

	/**
	 * Serve a file download securely.
	 *
	 * @param int $file_id   File record ID.
	 * @param int $client_id Client ID for isolation check (0 for admins).
	 */
	public static function serve_download( $file_id, $client_id = 0 ) {
		global $wpdb;

		$file = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ah_files WHERE id = %d",
				$file_id
			)
		);

		if ( ! $file ) {
			wp_die( esc_html__( 'File not found.', 'wp-agency-hub' ) );
		}

		// Verify client owns this file's project.
		if ( $client_id && ! Plugin::project_belongs_to_client( $file->project_id, $client_id ) ) {
			wp_die( esc_html__( 'Unauthorized file access.', 'wp-agency-hub' ) );
		}

		$upload_dir = wp_upload_dir();
		$full_path  = $upload_dir['basedir'] . '/' . $file->file_path;

		if ( ! file_exists( $full_path ) ) {
			wp_die( esc_html__( 'File not found on disk.', 'wp-agency-hub' ) );
		}

		// Sanitize filename for Content-Disposition to prevent header injection.
		$safe_filename = str_replace( array( '"', "\r", "\n", "\0" ), '', sanitize_file_name( $file->file_name ) );

		header( 'Content-Type: ' . $file->mime_type );
		header( 'Content-Disposition: attachment; filename="' . $safe_filename . '"' );
		header( 'Content-Length: ' . filesize( $full_path ) );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );

		readfile( $full_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}
