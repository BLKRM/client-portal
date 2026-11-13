<?php
/**
 * Storage backend contract. LocalPrivateAdapter ships first; an
 * S3-compatible adapter (Cloudflare R2 / S3 multipart) can implement the
 * same methods later without touching the portal UI or REST API.
 *
 * Objects are opaque encrypted blobs addressed by a random key.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Storage;

defined( 'ABSPATH' ) || exit;

interface StorageAdapter {

	/**
	 * Store one encrypted part of an in-progress upload.
	 *
	 * @param string $upload_id Upload session ID.
	 * @param int    $index     Part index.
	 * @param string $bytes     Encrypted bytes.
	 */
	public function store_part( $upload_id, $index, $bytes );

	/**
	 * Indexes of parts received so far.
	 *
	 * @param string $upload_id Upload session ID.
	 * @return int[]
	 */
	public function received_parts( $upload_id );

	/**
	 * Join header + all parts into the final object.
	 *
	 * @param string $upload_id   Upload session ID.
	 * @param int    $total_parts Number of parts.
	 * @param string $header      Object header.
	 * @param string $key         Destination object key.
	 * @return int Stored size in bytes.
	 */
	public function finalize( $upload_id, $total_parts, $header, $key );

	/**
	 * Remove an upload session's temporary parts.
	 *
	 * @param string $upload_id Upload session ID.
	 */
	public function discard_upload( $upload_id );

	/**
	 * Read a byte range of a stored object.
	 *
	 * @param string $key    Object key.
	 * @param int    $offset Start byte.
	 * @param int    $length Bytes to read.
	 * @return string
	 */
	public function read( $key, $offset, $length );

	/**
	 * Whether an object exists.
	 *
	 * @param string $key Object key.
	 * @return bool
	 */
	public function exists( $key );

	/**
	 * Delete an object.
	 *
	 * @param string $key Object key.
	 */
	public function delete( $key );
}
