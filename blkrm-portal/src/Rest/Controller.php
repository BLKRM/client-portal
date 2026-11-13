<?php
/**
 * Shared REST helpers. Namespace: blkrm/v1. Auth: WordPress cookie + the
 * wp_rest nonce sent by the portal's JavaScript (X-WP-Nonce).
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Rest;

use BLKRM\Portal\Auth\Access;

defined( 'ABSPATH' ) || exit;

/**
 * Base controller.
 */
abstract class Controller {

	const NS = 'blkrm/v1';

	/**
	 * Hook route registration.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Register routes.
	 */
	abstract public function routes();

	/**
	 * Permission callback: a signed-in portal user.
	 *
	 * @return bool
	 */
	public function portal_user() {
		return Access::can_access_portal( get_current_user_id() );
	}

	/**
	 * Permission callback: BLKRM staff.
	 *
	 * @return bool
	 */
	public function staff() {
		return Access::is_staff( get_current_user_id() );
	}

	/**
	 * Wrap a service result as a REST response.
	 *
	 * @param mixed $result Array/scalar or WP_Error.
	 * @param int   $status Success status.
	 * @return \WP_REST_Response|\WP_Error
	 */
	protected function respond( $result, $status = 200 ) {
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( ! is_array( $data ) || ! isset( $data['status'] ) ) {
				$result->add_data( array_merge( is_array( $data ) ? $data : array(), array( 'status' => 400 ) ) );
			}
			return $result;
		}
		return new \WP_REST_Response( $result, $status );
	}
}
