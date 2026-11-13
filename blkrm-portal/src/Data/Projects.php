<?php
/**
 * Projects and the BLKRM stage pipeline.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Data;

use BLKRM\Portal\Install\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Project repository.
 */
final class Projects {

	/**
	 * Stages in order (Board columns).
	 *
	 * @return array<string, string>
	 */
	public static function stages() {
		return array(
			'discovery'  => __( 'Discovery', 'blkrm-portal' ),
			'scope'      => __( 'Scope + Brief', 'blkrm-portal' ),
			'design'     => __( 'Design', 'blkrm-portal' ),
			'proofing'   => __( 'Proofing', 'blkrm-portal' ),
			'production' => __( 'Production / Print', 'blkrm-portal' ),
			'delivered'  => __( 'Delivered', 'blkrm-portal' ),
		);
	}

	/**
	 * Project types (request form + filters).
	 *
	 * @return array<string, string>
	 */
	public static function types() {
		return array(
			'branding' => __( 'Branding + Strategy', 'blkrm-portal' ),
			'print'    => __( 'Graphic + Print', 'blkrm-portal' ),
			'digital'  => __( 'Digital', 'blkrm-portal' ),
			'consult'  => __( 'Creative Development + Consulting', 'blkrm-portal' ),
			'unsure'   => __( 'Not sure yet', 'blkrm-portal' ),
		);
	}

	/**
	 * Get a project.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$t = Schema::table( 'projects' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ); // phpcs:ignore
	}

	/**
	 * Projects visible to a scope: one org, or all when $org_id is null.
	 *
	 * @param int|null $org_id Org ID or null for all (staff).
	 * @return object[]
	 */
	public static function list_for( $org_id = null ) {
		global $wpdb;
		$p = Schema::table( 'projects' );
		$o = Schema::table( 'orgs' );
		$sql = "SELECT p.*, o.name AS org_name, o.slug AS org_slug FROM {$p} p JOIN {$o} o ON o.id = p.org_id";
		if ( null !== $org_id ) {
			$sql = $wpdb->prepare( $sql . ' WHERE p.org_id = %d', $org_id ); // phpcs:ignore
		}
		return $wpdb->get_results( $sql . ' ORDER BY p.due_date IS NULL, p.due_date ASC, p.name ASC' ); // phpcs:ignore
	}

	/**
	 * Create or update a project.
	 *
	 * @param array    $data Fields.
	 * @param int|null $id   Existing ID for update.
	 * @return int|\WP_Error Project ID.
	 */
	public static function save( array $data, $id = null ) {
		global $wpdb;
		$name = trim( (string) ( $data['name'] ?? '' ) );
		if ( '' === $name ) {
			return new \WP_Error( 'blkrm_project_name', __( 'Project name is required.', 'blkrm-portal' ) );
		}
		$org_id = absint( $data['org_id'] ?? 0 );
		if ( ! Orgs::get( $org_id ) ) {
			return new \WP_Error( 'blkrm_project_org', __( 'Choose a client organization.', 'blkrm-portal' ) );
		}
		$stage = (string) ( $data['stage'] ?? 'discovery' );
		if ( ! array_key_exists( $stage, self::stages() ) ) {
			$stage = 'discovery';
		}
		$type = (string) ( $data['type'] ?? '' );
		if ( ! array_key_exists( $type, self::types() ) ) {
			$type = 'unsure';
		}
		$fields = array(
			'org_id'       => $org_id,
			'name'         => $name,
			'type'         => $type,
			'stage'        => $stage,
			'description'  => wp_kses_post( (string) ( $data['description'] ?? '' ) ),
			'start_date'   => self::date_or_null( $data['start_date'] ?? '' ),
			'due_date'     => self::date_or_null( $data['due_date'] ?? '' ),
			'lead_user_id' => absint( $data['lead_user_id'] ?? 0 ),
			'updated_at'   => gmdate( 'Y-m-d H:i:s' ),
		);
		$t = Schema::table( 'projects' );
		if ( $id ) {
			$wpdb->update( $t, $fields, array( 'id' => (int) $id ) ); // phpcs:ignore
			return (int) $id;
		}
		$fields['created_at'] = $fields['updated_at'];
		$ok                   = $wpdb->insert( $t, $fields ); // phpcs:ignore
		return $ok ? (int) $wpdb->insert_id : new \WP_Error( 'blkrm_db', __( 'Could not save the project.', 'blkrm-portal' ) );
	}

	/**
	 * Validate a Y-m-d date.
	 *
	 * @param string $value Input.
	 * @return string|null
	 */
	private static function date_or_null( $value ) {
		$value = trim( (string) $value );
		$d     = \DateTime::createFromFormat( 'Y-m-d', $value );
		return ( $d && $d->format( 'Y-m-d' ) === $value ) ? $value : null;
	}
}
