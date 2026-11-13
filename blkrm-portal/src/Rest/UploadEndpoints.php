<?php
/**
 * Chunked upload endpoints.
 *
 *   POST   /blkrm/v1/uploads                       start   {project_id, name, size, folder?, file_id?}
 *   GET    /blkrm/v1/uploads/<id>                  status  → which chunks arrived (resume)
 *   PUT    /blkrm/v1/uploads/<id>/chunks/<index>   chunk   raw body, X-Chunk-SHA256 header
 *   POST   /blkrm/v1/uploads/<id>/complete         finish  {note?}
 *   DELETE /blkrm/v1/uploads/<id>                  cancel
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Rest;

use BLKRM\Portal\Files\UploadService;

defined( 'ABSPATH' ) || exit;

/**
 * Upload REST controller.
 */
final class UploadEndpoints extends Controller {

	/**
	 * {@inheritDoc}
	 */
	public function routes() {
		$id = '(?P<id>[a-f0-9]{32})';

		register_rest_route(
			self::NS,
			'/uploads',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'start' ),
				'permission_callback' => array( $this, 'portal_user' ),
				'args'                => array(
					'project_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'name'       => array(
						'type'     => 'string',
						'required' => true,
					),
					'size'       => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 0,
					),
					'folder'     => array( 'type' => 'string' ),
					'file_id'    => array( 'type' => 'integer' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/uploads/' . $id,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'status' ),
					'permission_callback' => array( $this, 'portal_user' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'cancel' ),
					'permission_callback' => array( $this, 'portal_user' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/uploads/' . $id . '/chunks/(?P<index>\d+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'chunk' ),
				'permission_callback' => array( $this, 'portal_user' ),
			)
		);

		register_rest_route(
			self::NS,
			'/uploads/' . $id . '/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'complete' ),
				'permission_callback' => array( $this, 'portal_user' ),
			)
		);
	}

	/**
	 * Start.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function start( $request ) {
		return $this->respond(
			( new UploadService() )->start(
				get_current_user_id(),
				array(
					'project_id' => $request['project_id'],
					'name'       => $request['name'],
					'size'       => $request['size'],
					'folder'     => (string) $request['folder'],
					'file_id'    => (int) $request['file_id'],
				)
			),
			201
		);
	}

	/**
	 * Status.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function status( $request ) {
		return $this->respond( ( new UploadService() )->status( get_current_user_id(), $request['id'] ) );
	}

	/**
	 * Receive a chunk (raw request body).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function chunk( $request ) {
		return $this->respond(
			( new UploadService() )->receive(
				get_current_user_id(),
				$request['id'],
				(int) $request['index'],
				(string) $request->get_body(),
				$request->get_header( 'x_chunk_sha256' )
			)
		);
	}

	/**
	 * Complete.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function complete( $request ) {
		return $this->respond( ( new UploadService() )->complete( get_current_user_id(), $request['id'], (string) $request['note'] ) );
	}

	/**
	 * Cancel.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function cancel( $request ) {
		return $this->respond( ( new UploadService() )->cancel( get_current_user_id(), $request['id'] ) );
	}
}
