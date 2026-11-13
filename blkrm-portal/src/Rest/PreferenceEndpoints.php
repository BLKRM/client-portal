<?php
/**
 * Per-user preferences (light/dark theme for now).
 *
 *   POST /blkrm/v1/me/preferences  {theme: light|dark|system}
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Preferences REST controller.
 */
final class PreferenceEndpoints extends Controller {

	const META_THEME = 'blkrm_portal_theme';

	/**
	 * {@inheritDoc}
	 */
	public function routes() {
		register_rest_route(
			self::NS,
			'/me/preferences',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save' ),
				'permission_callback' => array( $this, 'portal_user' ),
				'args'                => array(
					'theme' => array(
						'type' => 'string',
						'enum' => array( 'light', 'dark', 'system' ),
					),
				),
			)
		);
	}

	/**
	 * Current user's theme.
	 *
	 * @param int $user_id User ID.
	 * @return string light|dark|system
	 */
	public static function theme( $user_id ) {
		$theme = (string) get_user_meta( $user_id, self::META_THEME, true );
		return in_array( $theme, array( 'light', 'dark' ), true ) ? $theme : 'system';
	}

	/**
	 * Save.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save( $request ) {
		if ( null !== $request['theme'] ) {
			update_user_meta( get_current_user_id(), self::META_THEME, $request['theme'] );
		}
		return rest_ensure_response( array( 'theme' => self::theme( get_current_user_id() ) ) );
	}
}
