<?php
/**
 * Chunked, resumable, encrypted uploads.
 *
 * Flow (see Rest\UploadEndpoints):
 *   1. start()    → session with chunk size + total chunks
 *   2. receive()  → each chunk (any order, retry-safe), checksum-verified,
 *                   type-checked (chunk 0) and encrypted on arrival
 *   3. status()   → which chunks the server has (to resume after a drop)
 *   4. complete() → join parts into the stored object; create file/version
 *
 * Each request stays small (default 8 MB), so 1 GB+ files fit under host
 * request-size and timeout limits.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Files;

use BLKRM\Portal\Audit\AuditLog;
use BLKRM\Portal\Auth\Access;
use BLKRM\Portal\Data\Files;
use BLKRM\Portal\Data\Orgs;
use BLKRM\Portal\Data\Projects;
use BLKRM\Portal\Install\Schema;
use BLKRM\Portal\Storage\Crypto;
use BLKRM\Portal\Storage\KeyProvider;
use BLKRM\Portal\Storage\LocalPrivateAdapter;
use BLKRM\Portal\Storage\StorageAdapter;

defined( 'ABSPATH' ) || exit;

/**
 * Upload sessions.
 */
final class UploadService {

	const DEFAULT_CHUNK = 8388608; // 8 MiB.
	const MAX_DEFAULT   = 2147483648; // 2 GiB.
	const SESSION_TTL   = DAY_IN_SECONDS;

	/**
	 * Storage backend.
	 *
	 * @var StorageAdapter
	 */
	private $storage;

	/**
	 * Constructor.
	 *
	 * @param StorageAdapter|null $storage Backend (local private by default).
	 */
	public function __construct( ?StorageAdapter $storage = null ) {
		$this->storage = $storage ? $storage : self::default_storage();
	}

	/**
	 * Default storage backend (filterable for a future R2/S3 adapter).
	 *
	 * @return StorageAdapter
	 */
	public static function default_storage() {
		$adapter = apply_filters( 'blkrm_portal_storage_adapter', null );
		return $adapter instanceof StorageAdapter ? $adapter : new LocalPrivateAdapter();
	}

	/**
	 * Largest single file accepted.
	 *
	 * @return int
	 */
	public static function max_file_bytes() {
		return (int) apply_filters( 'blkrm_portal_max_upload_bytes', self::MAX_DEFAULT );
	}

	/**
	 * Chunk size, kept under the server's request-body limit.
	 *
	 * @return int
	 */
	public static function chunk_bytes() {
		$chunk = (int) apply_filters( 'blkrm_portal_chunk_bytes', self::DEFAULT_CHUNK );
		$post  = wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) );
		if ( $post > 0 && $post - 65536 < $chunk ) {
			$chunk = max( 1048576, $post - 65536 );
		}
		return $chunk;
	}

	/**
	 * Start an upload session.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    project_id, name, size, folder, file_id (new version).
	 * @return array|\WP_Error
	 */
	public function start( $user_id, array $args ) {
		global $wpdb;
		$project_id = absint( $args['project_id'] ?? 0 );
		$project    = Projects::get( $project_id );
		if ( ! $project || ! Access::can_upload_to_project( $user_id, $project_id ) ) {
			return new \WP_Error( 'blkrm_forbidden', __( 'You can\'t upload to this project.', 'blkrm-portal' ), array( 'status' => 403 ) );
		}

		$type = FileTypes::check_name( (string) ( $args['name'] ?? '' ) );
		if ( is_wp_error( $type ) ) {
			return $type;
		}

		$size = isset( $args['size'] ) ? (int) $args['size'] : -1;
		if ( $size < 0 ) {
			return new \WP_Error( 'blkrm_size', __( 'File size is missing.', 'blkrm-portal' ) );
		}
		if ( $size > self::max_file_bytes() ) {
			/* translators: %s: max size */
			return new \WP_Error( 'blkrm_too_large', sprintf( __( 'Files can be up to %s.', 'blkrm-portal' ), size_format( self::max_file_bytes() ) ), array( 'status' => 413 ) );
		}

		$org = Orgs::get( (int) $project->org_id );
		if ( $org && (int) $org->quota_bytes > 0 && Orgs::storage_used( (int) $org->id ) + $size > (int) $org->quota_bytes ) {
			return new \WP_Error( 'blkrm_quota', __( 'This workspace is out of storage space. Contact BLKRM.', 'blkrm-portal' ), array( 'status' => 507 ) );
		}

		$file_id = absint( $args['file_id'] ?? 0 );
		if ( $file_id ) {
			$file = Files::get( $file_id );
			if ( ! $file || (int) $file->project_id !== $project_id ) {
				return new \WP_Error( 'blkrm_file_missing', __( 'File not found.', 'blkrm-portal' ), array( 'status' => 404 ) );
			}
		}

		$chunk  = self::chunk_bytes();
		$id     = bin2hex( random_bytes( 16 ) );
		$key    = bin2hex( random_bytes( 16 ) );
		$header = Crypto::make_header( $chunk, $size );
		$total  = Crypto::chunk_count( $size, $chunk );
		$now    = time();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::table( 'uploads' ),
			array(
				'id'           => $id,
				'user_id'      => (int) $user_id,
				'org_id'       => (int) $project->org_id,
				'project_id'   => $project_id,
				'file_id'      => $file_id,
				'folder'       => sanitize_text_field( (string) ( $args['folder'] ?? '' ) ),
				'name'         => $type['name'],
				'size'         => $size,
				'mime'         => $type['mime'],
				'chunk_size'   => $chunk,
				'total_chunks' => $total,
				'header_hex'   => bin2hex( $header ),
				'storage_key'  => $key,
				'status'       => 'open',
				'created_at'   => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at'   => gmdate( 'Y-m-d H:i:s', $now + self::SESSION_TTL ),
			)
		);

		return array(
			'upload_id'    => $id,
			'chunk_size'   => $chunk,
			'total_chunks' => $total,
			'received'     => array(),
		);
	}

	/**
	 * Receive one chunk.
	 *
	 * @param int         $user_id   User ID.
	 * @param string      $upload_id Session ID.
	 * @param int         $index     Chunk index.
	 * @param string      $bytes     Raw chunk bytes.
	 * @param string|null $sha256    Expected SHA-256 (hex) of the chunk, if sent.
	 * @return array|\WP_Error
	 */
	public function receive( $user_id, $upload_id, $index, $bytes, $sha256 = null ) {
		$session = $this->session( $user_id, $upload_id );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		$index = (int) $index;
		if ( $index < 0 || $index >= (int) $session->total_chunks ) {
			return new \WP_Error( 'blkrm_chunk_index', __( 'Invalid chunk.', 'blkrm-portal' ), array( 'status' => 400 ) );
		}
		$expected = Crypto::chunk_plain_len( $index, (int) $session->size, (int) $session->chunk_size );
		if ( strlen( $bytes ) !== $expected ) {
			return new \WP_Error( 'blkrm_chunk_length', __( 'Chunk arrived incomplete. Retrying should fix it.', 'blkrm-portal' ), array( 'status' => 400 ) );
		}
		if ( null !== $sha256 && '' !== $sha256 && ! hash_equals( strtolower( $sha256 ), hash( 'sha256', $bytes ) ) ) {
			return new \WP_Error( 'blkrm_chunk_checksum', __( 'Chunk was corrupted in transit. Retrying should fix it.', 'blkrm-portal' ), array( 'status' => 400 ) );
		}
		if ( 0 === $index ) {
			$ext   = strtolower( (string) pathinfo( $session->name, PATHINFO_EXTENSION ) );
			$check = FileTypes::check_content( $ext, substr( $bytes, 0, 262144 ) );
			if ( is_wp_error( $check ) ) {
				$this->cancel( $user_id, $upload_id );
				return $check;
			}
		}

		$header = hex2bin( $session->header_hex );
		$sealed = Crypto::encrypt_chunk( KeyProvider::file_key( $session->storage_key ), $header, $index, $bytes );
		$this->storage->store_part( $upload_id, $index, $sealed );

		return array(
			'received_count' => count( $this->storage->received_parts( $upload_id ) ),
			'total_chunks'   => (int) $session->total_chunks,
		);
	}

	/**
	 * Which chunks the server already has.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $upload_id Session ID.
	 * @return array|\WP_Error
	 */
	public function status( $user_id, $upload_id ) {
		$session = $this->session( $user_id, $upload_id );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		return array(
			'upload_id'    => $upload_id,
			'chunk_size'   => (int) $session->chunk_size,
			'total_chunks' => (int) $session->total_chunks,
			'received'     => $this->storage->received_parts( $upload_id ),
		);
	}

	/**
	 * Join all chunks into the stored object and record the file.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $upload_id Session ID.
	 * @param string $note      Optional version note.
	 * @return array|\WP_Error
	 */
	public function complete( $user_id, $upload_id, $note = '' ) {
		global $wpdb;
		$session = $this->session( $user_id, $upload_id );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		$total    = (int) $session->total_chunks;
		$received = $this->storage->received_parts( $upload_id );
		if ( range( 0, $total - 1 ) !== $received ) {
			return new \WP_Error(
				'blkrm_incomplete',
				__( 'Some chunks are still missing.', 'blkrm-portal' ),
				array(
					'status'   => 409,
					'received' => $received,
				)
			);
		}

		// Claim the session so a double "complete" can't create two files.
		$claimed = $wpdb->update( Schema::table( 'uploads' ), array( 'status' => 'finalizing' ), array( 'id' => $upload_id, 'status' => 'open' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( 1 !== (int) $claimed ) {
			return new \WP_Error( 'blkrm_upload_busy', __( 'This upload is already being finished.', 'blkrm-portal' ), array( 'status' => 409 ) );
		}

		try {
			$stored = $this->storage->finalize( $upload_id, $total, hex2bin( $session->header_hex ), $session->storage_key );
		} catch ( \Exception $e ) {
			$wpdb->update( Schema::table( 'uploads' ), array( 'status' => 'open' ), array( 'id' => $upload_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return new \WP_Error( 'blkrm_finalize', __( 'The file couldn\'t be saved. Please try again.', 'blkrm-portal' ), array( 'status' => 500 ) );
		}
		if ( Crypto::stored_size( (int) $session->size, (int) $session->chunk_size ) !== $stored ) {
			$this->storage->delete( $session->storage_key );
			$wpdb->update( Schema::table( 'uploads' ), array( 'status' => 'open' ), array( 'id' => $upload_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return new \WP_Error( 'blkrm_finalize_size', __( 'The saved file has the wrong size. Please try again.', 'blkrm-portal' ), array( 'status' => 500 ) );
		}

		$result = Files::record_upload(
			array(
				'org_id'      => (int) $session->org_id,
				'project_id'  => (int) $session->project_id,
				'folder'      => $session->folder,
				'name'        => $session->name,
				'file_id'     => (int) $session->file_id,
				'storage_key' => $session->storage_key,
				'size'        => (int) $session->size,
				'stored_size' => $stored,
				'mime'        => $session->mime,
				'note'        => sanitize_text_field( $note ),
				'user_id'     => (int) $user_id,
			)
		);

		$wpdb->update( Schema::table( 'uploads' ), array( 'status' => 'complete', 'file_id' => $result['file_id'] ), array( 'id' => $upload_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->storage->discard_upload( $upload_id );

		AuditLog::record(
			'file_uploaded',
			array(
				'user_id'     => $user_id,
				'org_id'      => (int) $session->org_id,
				'project_id'  => (int) $session->project_id,
				'object_type' => 'file',
				'object_id'   => $result['file_id'],
				'details'     => array(
					'name'    => $session->name,
					'size'    => (int) $session->size,
					'version' => $result['version'],
				),
			)
		);

		return $result + array(
			'name' => $session->name,
			'size' => (int) $session->size,
		);
	}

	/**
	 * Cancel a session and delete its parts.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $upload_id Session ID.
	 * @return true|\WP_Error
	 */
	public function cancel( $user_id, $upload_id ) {
		global $wpdb;
		$session = $this->session( $user_id, $upload_id );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		$this->storage->discard_upload( $upload_id );
		$wpdb->update( Schema::table( 'uploads' ), array( 'status' => 'cancelled' ), array( 'id' => $upload_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return true;
	}

	/**
	 * Load an open session owned by the user.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $upload_id Session ID.
	 * @return object|\WP_Error
	 */
	private function session( $user_id, $upload_id ) {
		global $wpdb;
		if ( ! is_string( $upload_id ) || ! preg_match( '/^[a-f0-9]{32}$/', $upload_id ) ) {
			return new \WP_Error( 'blkrm_upload_missing', __( 'Upload not found.', 'blkrm-portal' ), array( 'status' => 404 ) );
		}
		$t   = Schema::table( 'uploads' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %s", $upload_id ) ); // phpcs:ignore
		if ( ! $row || (int) $row->user_id !== (int) $user_id ) {
			return new \WP_Error( 'blkrm_upload_missing', __( 'Upload not found.', 'blkrm-portal' ), array( 'status' => 404 ) );
		}
		if ( 'open' !== $row->status ) {
			return new \WP_Error( 'blkrm_upload_closed', __( 'This upload is no longer open.', 'blkrm-portal' ), array( 'status' => 409 ) );
		}
		if ( strtotime( $row->expires_at . ' UTC' ) < time() ) {
			return new \WP_Error( 'blkrm_upload_expired', __( 'This upload expired. Please start it again.', 'blkrm-portal' ), array( 'status' => 410 ) );
		}
		// Access may have been revoked since the upload started.
		if ( ! Access::can_upload_to_project( $user_id, (int) $row->project_id ) ) {
			return new \WP_Error( 'blkrm_forbidden', __( 'You can\'t upload to this project.', 'blkrm-portal' ), array( 'status' => 403 ) );
		}
		return $row;
	}

	/**
	 * Cron: delete parts of abandoned sessions and prune old session rows.
	 */
	public static function cleanup_expired() {
		global $wpdb;
		$t       = Schema::table( 'uploads' );
		$storage = self::default_storage();
		$now     = gmdate( 'Y-m-d H:i:s' );
		$stale   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE status IN ('open','finalizing','cancelled') AND expires_at < %s", $now ) ); // phpcs:ignore
		foreach ( $stale as $id ) {
			try {
				$storage->discard_upload( $id );
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Invalid ID or storage hiccup: still drop the row below.
			}
			$wpdb->delete( $t, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE status = 'complete' AND created_at < %s", gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS ) ) ); // phpcs:ignore
	}
}
