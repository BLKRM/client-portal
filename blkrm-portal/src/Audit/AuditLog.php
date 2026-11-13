<?php
/**
 * Append-only audit trail: uploads, downloads, deletions, membership and
 * permission changes.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Audit;

use BLKRM\Portal\Install\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Audit log writer.
 */
final class AuditLog {

	/**
	 * Record an event.
	 *
	 * @param string $action  e.g. 'file_uploaded'.
	 * @param array  $context user_id, org_id, project_id, object_type, object_id, details (array).
	 */
	public static function record( $action, array $context = array() ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::table( 'audit_log' ),
			array(
				'user_id'     => (int) ( $context['user_id'] ?? get_current_user_id() ),
				'org_id'      => (int) ( $context['org_id'] ?? 0 ),
				'project_id'  => (int) ( $context['project_id'] ?? 0 ),
				'action'      => substr( sanitize_key( $action ), 0, 60 ),
				'object_type' => substr( sanitize_key( (string) ( $context['object_type'] ?? '' ) ), 0, 30 ),
				'object_id'   => (int) ( $context['object_id'] ?? 0 ),
				'ip'          => self::ip(),
				'details'     => wp_json_encode( $context['details'] ?? array() ),
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Recent events.
	 *
	 * @param int $limit Max rows.
	 * @return object[]
	 */
	public static function recent( $limit = 50 ) {
		global $wpdb;
		$t = Schema::table( 'audit_log' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT a.*, u.display_name FROM {$t} a LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id ORDER BY a.id DESC LIMIT %d", $limit ) ); // phpcs:ignore
	}

	/**
	 * Client IP (REMOTE_ADDR only; proxies' headers are spoofable).
	 *
	 * @return string
	 */
	private static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
