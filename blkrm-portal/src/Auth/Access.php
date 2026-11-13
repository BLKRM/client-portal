<?php
/**
 * Single permission service. Every portal page and REST route asks this
 * class, so data isolation between client organizations lives in one place.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Auth;

use BLKRM\Portal\Data\Orgs;
use BLKRM\Portal\Data\Projects;

defined( 'ABSPATH' ) || exit;

/**
 * Access checks.
 */
final class Access {

	/**
	 * Whether the user may use the portal.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_access_portal( $user_id ) {
		if ( ! $user_id || ! user_can( $user_id, Roles::CAP_ACCESS ) ) {
			return false;
		}
		if ( self::is_staff( $user_id ) ) {
			return true;
		}
		// A client must belong to an active organization.
		$org = Orgs::for_user( $user_id );
		return $org && 'active' === $org->status;
	}

	/**
	 * Whether the user is BLKRM staff (or an administrator).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_staff( $user_id ) {
		return $user_id && user_can( $user_id, Roles::CAP_MANAGE );
	}

	/**
	 * Organization ID the user belongs to, or 0 for staff / none.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function org_id( $user_id ) {
		$org = Orgs::for_user( $user_id );
		return $org ? (int) $org->id : 0;
	}

	/**
	 * Whether the user may see an organization's data.
	 *
	 * @param int $user_id User ID.
	 * @param int $org_id  Organization ID.
	 * @return bool
	 */
	public static function can_view_org( $user_id, $org_id ) {
		if ( ! self::can_access_portal( $user_id ) ) {
			return false;
		}
		if ( self::is_staff( $user_id ) ) {
			return true;
		}
		return $org_id > 0 && self::org_id( $user_id ) === (int) $org_id;
	}

	/**
	 * Whether the user may see a project (and its files).
	 *
	 * @param int $user_id    User ID.
	 * @param int $project_id Project ID.
	 * @return bool
	 */
	public static function can_view_project( $user_id, $project_id ) {
		$project = Projects::get( $project_id );
		return $project && self::can_view_org( $user_id, (int) $project->org_id );
	}

	/**
	 * Whether the user may upload into a project. Clients can upload to their
	 * own projects; staff to any.
	 *
	 * @param int $user_id    User ID.
	 * @param int $project_id Project ID.
	 * @return bool
	 */
	public static function can_upload_to_project( $user_id, $project_id ) {
		return self::can_view_project( $user_id, $project_id );
	}

	/**
	 * Whether the user may delete files or manage projects.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_manage( $user_id ) {
		return self::is_staff( $user_id );
	}
}
