<?php
/**
 * Files and their versions. A file is a stable record; each upload of a new
 * revision adds a version (v1, v2, …) and moves current_version_id.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Data;

use BLKRM\Portal\Install\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * File repository.
 */
final class Files {

	/**
	 * Get a file (with its current version's fields).
	 *
	 * @param int $id File ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$f = Schema::table( 'files' );
		$v = Schema::table( 'file_versions' );
		return $wpdb->get_row( // phpcs:ignore
			$wpdb->prepare(
				"SELECT f.*, v.version, v.size, v.mime, v.storage_key, v.uploaded_by, v.created_at AS version_created_at FROM {$f} f LEFT JOIN {$v} v ON v.id = f.current_version_id WHERE f.id = %d AND f.deleted_at IS NULL", // phpcs:ignore
				$id
			)
		);
	}

	/**
	 * Get one version.
	 *
	 * @param int $version_id Version ID.
	 * @return object|null
	 */
	public static function version( $version_id ) {
		global $wpdb;
		$v = Schema::table( 'file_versions' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$v} WHERE id = %d", $version_id ) ); // phpcs:ignore
	}

	/**
	 * All versions of a file, newest first.
	 *
	 * @param int $file_id File ID.
	 * @return object[]
	 */
	public static function versions( $file_id ) {
		global $wpdb;
		$v = Schema::table( 'file_versions' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$v} WHERE file_id = %d ORDER BY version DESC", $file_id ) ); // phpcs:ignore
	}

	/**
	 * Files in a project, or every project of an org, or everywhere (null).
	 *
	 * @param array $where project_id and/or org_id.
	 * @return object[]
	 */
	public static function list_for( array $where ) {
		global $wpdb;
		$f   = Schema::table( 'files' );
		$v   = Schema::table( 'file_versions' );
		$p   = Schema::table( 'projects' );
		$sql = "SELECT f.*, v.version, v.size, v.mime, v.created_at AS version_created_at, u.display_name AS uploader_name, p.name AS project_name
			FROM {$f} f
			LEFT JOIN {$v} v ON v.id = f.current_version_id
			LEFT JOIN {$p} p ON p.id = f.project_id
			LEFT JOIN {$wpdb->users} u ON u.ID = v.uploaded_by
			WHERE f.deleted_at IS NULL";
		$args = array();
		if ( isset( $where['project_id'] ) ) {
			$sql   .= ' AND f.project_id = %d';
			$args[] = (int) $where['project_id'];
		}
		if ( isset( $where['org_id'] ) ) {
			$sql   .= ' AND f.org_id = %d';
			$args[] = (int) $where['org_id'];
		}
		$sql .= ' ORDER BY f.updated_at DESC';
		return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore
	}

	/**
	 * Record a finished upload: new file, or new version of an existing file.
	 *
	 * @param array $data org_id, project_id, folder, name, file_id (optional), storage_key, size, stored_size, mime, user_id.
	 * @return array{file_id:int,version_id:int,version:int}
	 */
	public static function record_upload( array $data ) {
		global $wpdb;
		$now     = gmdate( 'Y-m-d H:i:s' );
		$files   = Schema::table( 'files' );
		$file_id = absint( $data['file_id'] ?? 0 );

		if ( $file_id ) {
			$version = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version) FROM ' . Schema::table( 'file_versions' ) . ' WHERE file_id = %d', $file_id ) ); // phpcs:ignore
		} else {
			$wpdb->insert( // phpcs:ignore
				$files,
				array(
					'org_id'     => (int) $data['org_id'],
					'project_id' => (int) $data['project_id'],
					'folder'     => (string) ( $data['folder'] ?? '' ),
					'name'       => (string) $data['name'],
					'created_by' => (int) $data['user_id'],
					'created_at' => $now,
					'updated_at' => $now,
				)
			);
			$file_id = (int) $wpdb->insert_id;
			$version = 1;
		}

		$wpdb->insert( // phpcs:ignore
			Schema::table( 'file_versions' ),
			array(
				'file_id'     => $file_id,
				'version'     => $version,
				'storage_key' => (string) $data['storage_key'],
				'size'        => (int) $data['size'],
				'stored_size' => (int) $data['stored_size'],
				'mime'        => (string) $data['mime'],
				'note'        => (string) ( $data['note'] ?? '' ),
				'uploaded_by' => (int) $data['user_id'],
				'created_at'  => $now,
			)
		);
		$version_id = (int) $wpdb->insert_id;

		$wpdb->update( $files, array( 'current_version_id' => $version_id, 'updated_at' => $now ), array( 'id' => $file_id ) ); // phpcs:ignore

		return array(
			'file_id'    => $file_id,
			'version_id' => $version_id,
			'version'    => $version,
		);
	}

	/**
	 * Soft-delete a file (versions and stored objects are purged separately).
	 *
	 * @param int $file_id File ID.
	 */
	public static function soft_delete( $file_id ) {
		global $wpdb;
		$wpdb->update( Schema::table( 'files' ), array( 'deleted_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => (int) $file_id ) ); // phpcs:ignore
	}
}
