<?php
/**
 * File endpoints.
 *
 *   GET    /blkrm/v1/projects/<id>/files       list files in a project
 *   GET    /blkrm/v1/files/<id>/versions        version history
 *   POST   /blkrm/v1/files/<id>/download-link   signed 15-minute link {version_id?}
 *   DELETE /blkrm/v1/files/<id>                 staff only (soft delete)
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Rest;

use BLKRM\Portal\Audit\AuditLog;
use BLKRM\Portal\Auth\Access;
use BLKRM\Portal\Data\Files;
use BLKRM\Portal\Files\DownloadService;

defined( 'ABSPATH' ) || exit;

/**
 * File REST controller.
 */
final class FileEndpoints extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function routes() {
		register_rest_route(
			self::NS,
			'/projects/(?P<id>\d+)/files',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_files' ),
				'permission_callback' => array( $this, 'portal_user' ),
			)
		);
		register_rest_route(
			self::NS,
			'/files/(?P<id>\d+)/versions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'versions' ),
				'permission_callback' => array( $this, 'portal_user' ),
			)
		);
		register_rest_route(
			self::NS,
			'/files/(?P<id>\d+)/download-link',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'download_link' ),
				'permission_callback' => array( $this, 'portal_user' ),
			)
		);
		register_rest_route(
			self::NS,
			'/files/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete' ),
				'permission_callback' => array( $this, 'staff' ),
			)
		);
	}

	/**
	 * Shape a file row for the client.
	 *
	 * @param object $f Row.
	 * @return array
	 */
	public static function shape( $f ) {
		return array(
			'id'         => (int) $f->id,
			'name'       => $f->name,
			'folder'     => $f->folder,
			'project_id' => (int) $f->project_id,
			'version'    => (int) $f->version,
			'size'       => (int) $f->size,
			'size_human' => size_format( (int) $f->size, 1 ),
			'updated'    => mysql2date( 'c', $f->updated_at . ' UTC' ),
			'uploader'   => isset( $f->uploader_name ) ? (string) $f->uploader_name : '',
		);
	}

	/**
	 * List files in a project.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function list_files( $request ) {
		$project_id = (int) $request['id'];
		if ( ! Access::can_view_project( get_current_user_id(), $project_id ) ) {
			return new \WP_Error( 'blkrm_forbidden', __( 'Not found.', 'blkrm-portal' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array_map( array( __CLASS__, 'shape' ), Files::list_for( array( 'project_id' => $project_id ) ) ) );
	}

	/**
	 * Version history.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function versions( $request ) {
		$file = Files::get( (int) $request['id'] );
		if ( ! $file || ! DownloadService::can_read( get_current_user_id(), $file ) ) {
			return new \WP_Error( 'blkrm_not_found', __( 'Not found.', 'blkrm-portal' ), array( 'status' => 404 ) );
		}
		$out = array();
		foreach ( Files::versions( (int) $file->id ) as $v ) {
			$user  = get_userdata( (int) $v->uploaded_by );
			$out[] = array(
				'id'       => (int) $v->id,
				'version'  => (int) $v->version,
				'size'     => (int) $v->size,
				'note'     => $v->note,
				'uploader' => $user ? $user->display_name : '',
				'created'  => mysql2date( 'c', $v->created_at . ' UTC' ),
				'current'  => (int) $v->id === (int) $file->current_version_id,
			);
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Signed download link.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function download_link( $request ) {
		return $this->respond( ( new DownloadService() )->link( get_current_user_id(), (int) $request['id'], (int) $request['version_id'] ) );
	}

	/**
	 * Soft-delete a file (staff).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete( $request ) {
		$file = Files::get( (int) $request['id'] );
		if ( ! $file ) {
			return new \WP_Error( 'blkrm_not_found', __( 'Not found.', 'blkrm-portal' ), array( 'status' => 404 ) );
		}
		Files::soft_delete( (int) $file->id );
		AuditLog::record(
			'file_deleted',
			array(
				'org_id'      => (int) $file->org_id,
				'project_id'  => (int) $file->project_id,
				'object_type' => 'file',
				'object_id'   => (int) $file->id,
				'details'     => array( 'name' => $file->name ),
			)
		);
		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
