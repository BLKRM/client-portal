<?php
/**
 * Roles and capabilities.
 *
 * - blkrm_client: a person at a client organization. Can only use the portal.
 * - blkrm_staff:  BLKRM team member. Manages orgs, projects and files without
 *                 being a full WordPress administrator.
 * - administrator: everything, including portal settings.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Role registry.
 */
final class Roles {

	const CLIENT = 'blkrm_client';
	const STAFF  = 'blkrm_staff';

	/** May sign in to the portal at all. */
	const CAP_ACCESS = 'blkrm_access_portal';

	/** May manage orgs, projects, files and to-dos for every client. */
	const CAP_MANAGE = 'blkrm_manage_portal';

	/** May change portal settings (storage, branding, slug). */
	const CAP_SETTINGS = 'blkrm_manage_settings';

	/**
	 * Create or update roles and grant capabilities. Idempotent.
	 */
	public static function install() {
		$client = get_role( self::CLIENT );
		if ( ! $client ) {
			$client = add_role( self::CLIENT, __( 'Portal Client', 'blkrm-portal' ), array( 'read' => true ) );
		}
		if ( $client ) {
			$client->add_cap( 'read' );
			$client->add_cap( self::CAP_ACCESS );
		}

		$staff = get_role( self::STAFF );
		if ( ! $staff ) {
			$staff = add_role( self::STAFF, __( 'BLKRM Staff', 'blkrm-portal' ), array( 'read' => true ) );
		}
		if ( $staff ) {
			foreach ( array( 'read', 'list_users', 'upload_files', self::CAP_ACCESS, self::CAP_MANAGE ) as $cap ) {
				$staff->add_cap( $cap );
			}
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array( self::CAP_ACCESS, self::CAP_MANAGE, self::CAP_SETTINGS ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove roles and custom caps (uninstall).
	 */
	public static function uninstall() {
		remove_role( self::CLIENT );
		remove_role( self::STAFF );
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array( self::CAP_ACCESS, self::CAP_MANAGE, self::CAP_SETTINGS ) as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}

	/**
	 * Whether a user is a portal client.
	 *
	 * @param \WP_User|null $user User.
	 * @return bool
	 */
	public static function is_client( $user ) {
		return $user instanceof \WP_User && in_array( self::CLIENT, (array) $user->roles, true );
	}
}
