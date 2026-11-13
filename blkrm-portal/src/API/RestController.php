<?php
/**
 * Base REST controller with shared utilities.
 *
 * @package WPAgencyHub\API
 */

namespace WPAgencyHub\API;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for all WP Agency Hub REST endpoints.
 */
abstract class RestController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'wp-agency-hub/v1';

	/**
	 * Get the client record for the current user, enforcing data isolation.
	 *
	 * @return object|null Client record or null if admin.
	 */
	protected function get_current_client() {
		$user = wp_get_current_user();

		if ( current_user_can( 'manage_options' ) ) {
			return null; // Admins see all.
		}

		if ( ! in_array( 'ah_client', (array) $user->roles, true ) ) {
			return false; // No access.
		}

		return Plugin::get_client_by_user( $user->ID );
	}

	/**
	 * Permission callback: logged-in user who is admin or ah_client.
	 *
	 * @return bool
	 */
	public function check_auth() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();
		return current_user_can( 'manage_options' ) || in_array( 'ah_client', (array) $user->roles, true );
	}

	/**
	 * Permission callback: admin only.
	 *
	 * @return bool
	 */
	public function check_admin() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Verify the current user can access a given project (data isolation).
	 *
	 * @param int $project_id Project ID.
	 * @return true|\WP_Error
	 */
	protected function verify_project_access( $project_id ) {
		$client = $this->get_current_client();

		// Admin — unrestricted.
		if ( null === $client ) {
			return true;
		}

		// Not a valid client.
		if ( false === $client ) {
			return new \WP_Error( 'unauthorized', __( 'Unauthorized.', 'wp-agency-hub' ), array( 'status' => 403 ) );
		}

		// Client data isolation.
		if ( ! Plugin::project_belongs_to_client( $project_id, $client->id ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot access this project.', 'wp-agency-hub' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Standard error response.
	 *
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return \WP_REST_Response
	 */
	protected function error( $message, $status = 400 ) {
		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Standard success response.
	 *
	 * @param mixed $data Response data.
	 * @return \WP_REST_Response
	 */
	protected function success( $data = null ) {
		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}
}
