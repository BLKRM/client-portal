<?php
/**
 * Client organizations and their members.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Data;

use BLKRM\Portal\Auth\Roles;
use BLKRM\Portal\Install\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Organization repository.
 */
final class Orgs {

	const ROLE_OWNER  = 'owner';
	const ROLE_MEMBER = 'member';

	/**
	 * Per-request cache of user → org.
	 *
	 * @var array<int, object|null>
	 */
	private static $by_user = array();

	/**
	 * Get an organization.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$t = Schema::table( 'orgs' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get an organization by slug.
	 *
	 * @param string $slug Slug.
	 * @return object|null
	 */
	public static function get_by_slug( $slug ) {
		global $wpdb;
		$t = Schema::table( 'orgs' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE slug = %s", $slug ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * All organizations, by name.
	 *
	 * @return object[]
	 */
	public static function all() {
		global $wpdb;
		$t = Schema::table( 'orgs' );
		return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Organization a user belongs to.
	 *
	 * @param int $user_id User ID.
	 * @return object|null
	 */
	public static function for_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( array_key_exists( $user_id, self::$by_user ) ) {
			return self::$by_user[ $user_id ];
		}
		global $wpdb;
		$o = Schema::table( 'orgs' );
		$m = Schema::table( 'org_members' );
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT o.*, m.org_role FROM {$m} m JOIN {$o} o ON o.id = m.org_id WHERE m.user_id = %d", $user_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		self::$by_user[ $user_id ] = $row ? $row : null;
		return self::$by_user[ $user_id ];
	}

	/**
	 * Create an organization.
	 *
	 * @param array $data name, slug, seat_limit, accent_color, logo_attachment_id, quota_bytes.
	 * @return int|\WP_Error New ID.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$name = trim( (string) ( $data['name'] ?? '' ) );
		if ( '' === $name ) {
			return new \WP_Error( 'blkrm_org_name', __( 'Organization name is required.', 'blkrm-portal' ) );
		}
		$slug = sanitize_title( (string) ( $data['slug'] ?? '' ) );
		if ( '' === $slug ) {
			$slug = sanitize_title( $name );
		}
		if ( self::get_by_slug( $slug ) ) {
			return new \WP_Error( 'blkrm_org_slug', __( 'That login address is already used by another organization.', 'blkrm-portal' ) );
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::table( 'orgs' ),
			array(
				'name'               => $name,
				'slug'               => $slug,
				'seat_limit'         => max( 1, (int) ( $data['seat_limit'] ?? 4 ) ),
				'accent_color'       => (string) sanitize_hex_color( (string) ( $data['accent_color'] ?? '' ) ),
				'logo_attachment_id' => absint( $data['logo_attachment_id'] ?? 0 ),
				'quota_bytes'        => absint( $data['quota_bytes'] ?? 0 ),
				'status'             => 'active',
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : new \WP_Error( 'blkrm_db', __( 'Could not save the organization.', 'blkrm-portal' ) );
	}

	/**
	 * Update an organization.
	 *
	 * @param int   $id   ID.
	 * @param array $data Fields.
	 * @return true|\WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$fields = array();
		if ( isset( $data['name'] ) ) {
			$fields['name'] = trim( (string) $data['name'] );
		}
		if ( isset( $data['seat_limit'] ) ) {
			$fields['seat_limit'] = max( 1, (int) $data['seat_limit'] );
		}
		if ( isset( $data['accent_color'] ) ) {
			$fields['accent_color'] = (string) sanitize_hex_color( (string) $data['accent_color'] );
		}
		if ( isset( $data['logo_attachment_id'] ) ) {
			$fields['logo_attachment_id'] = absint( $data['logo_attachment_id'] );
		}
		if ( isset( $data['quota_bytes'] ) ) {
			$fields['quota_bytes'] = absint( $data['quota_bytes'] );
		}
		if ( isset( $data['status'] ) && in_array( $data['status'], array( 'active', 'archived' ), true ) ) {
			$fields['status'] = $data['status'];
		}
		$fields['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		$wpdb->update( Schema::table( 'orgs' ), $fields, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::$by_user = array();
		return true;
	}

	/**
	 * Members of an organization with user data.
	 *
	 * @param int $org_id Org ID.
	 * @return object[]
	 */
	public static function members( $org_id ) {
		global $wpdb;
		$m = Schema::table( 'org_members' );
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT m.*, u.user_email, u.display_name FROM {$m} m JOIN {$wpdb->users} u ON u.ID = m.user_id WHERE m.org_id = %d ORDER BY m.org_role = 'owner' DESC, u.display_name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$org_id
			)
		);
	}

	/**
	 * Add a WordPress user to an organization as a portal client.
	 * Enforces the seat limit and one-org-per-user.
	 *
	 * @param int    $org_id  Org ID.
	 * @param int    $user_id User ID.
	 * @param string $role    owner|member.
	 * @return true|\WP_Error
	 */
	public static function add_member( $org_id, $user_id, $role = self::ROLE_MEMBER ) {
		global $wpdb;
		$org = self::get( $org_id );
		if ( ! $org ) {
			return new \WP_Error( 'blkrm_org_missing', __( 'Organization not found.', 'blkrm-portal' ) );
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new \WP_Error( 'blkrm_user_missing', __( 'User not found.', 'blkrm-portal' ) );
		}
		if ( user_can( $user, Roles::CAP_MANAGE ) ) {
			return new \WP_Error( 'blkrm_staff_member', __( 'BLKRM staff and administrators cannot be added to a client organization.', 'blkrm-portal' ) );
		}
		// Only plain accounts may become clients, so adding a member can never
		// demote an editor, author, shop manager, etc.
		$other_roles = array_diff( (array) $user->roles, array( 'subscriber', Roles::CLIENT ) );
		if ( $other_roles ) {
			return new \WP_Error( 'blkrm_has_roles', __( 'This email belongs to a site account with other permissions. Use a different email for portal access.', 'blkrm-portal' ) );
		}
		$existing = self::for_user( $user_id );
		if ( $existing ) {
			return new \WP_Error( 'blkrm_already_member', __( 'This person already belongs to an organization.', 'blkrm-portal' ) );
		}
		if ( count( self::members( $org_id ) ) >= (int) $org->seat_limit ) {
			/* translators: %d: seat limit */
			return new \WP_Error( 'blkrm_seat_limit', sprintf( __( 'This organization is at its limit of %d people.', 'blkrm-portal' ), (int) $org->seat_limit ) );
		}
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::table( 'org_members' ),
			array(
				'org_id'     => (int) $org_id,
				'user_id'    => (int) $user_id,
				'org_role'   => self::ROLE_OWNER === $role ? self::ROLE_OWNER : self::ROLE_MEMBER,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%d', '%s', '%s' )
		);
		$user->set_role( Roles::CLIENT );
		unset( self::$by_user[ (int) $user_id ] );
		return true;
	}

	/**
	 * Remove a member. Their WordPress account loses portal access.
	 *
	 * @param int $org_id  Org ID.
	 * @param int $user_id User ID.
	 */
	public static function remove_member( $org_id, $user_id ) {
		global $wpdb;
		$wpdb->delete( Schema::table( 'org_members' ), array( 'org_id' => (int) $org_id, 'user_id' => (int) $user_id ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$user = get_userdata( $user_id );
		if ( $user && Roles::is_client( $user ) ) {
			$user->set_role( 'subscriber' );
		}
		unset( self::$by_user[ (int) $user_id ] );
	}

	/**
	 * Bytes stored for an organization (current versions only).
	 *
	 * @param int $org_id Org ID.
	 * @return int
	 */
	public static function storage_used( $org_id ) {
		global $wpdb;
		$f = Schema::table( 'files' );
		$v = Schema::table( 'file_versions' );
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT COALESCE(SUM(v.stored_size),0) FROM {$v} v JOIN {$f} f ON f.id = v.file_id WHERE f.org_id = %d AND f.deleted_at IS NULL", $org_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Reset the per-request cache (tests, after membership changes).
	 */
	public static function flush_cache() {
		self::$by_user = array();
	}
}
