<?php
/**
 * Permission-checked, decrypting, Range-capable file downloads.
 *
 * Replaces the upstream plain REST link (which failed without a nonce and
 * pointed at publicly reachable files). Flow:
 *   1. Portal JS asks REST for a link → access check → 15-minute signed token.
 *   2. Browser opens /portal/download/<token>/ → token + login + access are
 *      re-checked → file is decrypted chunk by chunk and streamed.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Files;

use BLKRM\Portal\Audit\AuditLog;
use BLKRM\Portal\Auth\Access;
use BLKRM\Portal\Data\Files;
use BLKRM\Portal\Portal\Router;
use BLKRM\Portal\Storage\Crypto;
use BLKRM\Portal\Storage\KeyProvider;
use BLKRM\Portal\Storage\StorageAdapter;

defined( 'ABSPATH' ) || exit;

/**
 * Downloads.
 */
final class DownloadService {

	/**
	 * Storage backend.
	 *
	 * @var StorageAdapter
	 */
	private $storage;

	/**
	 * Constructor.
	 *
	 * @param StorageAdapter|null $storage Backend.
	 */
	public function __construct( ?StorageAdapter $storage = null ) {
		$this->storage = $storage ? $storage : UploadService::default_storage();
	}

	/**
	 * Whether a user may read a file.
	 *
	 * @param int    $user_id User ID.
	 * @param object $file    File row.
	 * @return bool
	 */
	public static function can_read( $user_id, $file ) {
		if ( (int) $file->project_id > 0 ) {
			return Access::can_view_project( $user_id, (int) $file->project_id );
		}
		return Access::can_view_org( $user_id, (int) $file->org_id );
	}

	/**
	 * Create a signed link for the current (or a given) version.
	 *
	 * @param int $user_id    User ID.
	 * @param int $file_id    File ID.
	 * @param int $version_id Version ID (0 = current).
	 * @return array{url:string,expires:int}|\WP_Error
	 */
	public function link( $user_id, $file_id, $version_id = 0 ) {
		$file = Files::get( $file_id );
		if ( ! $file || ! self::can_read( $user_id, $file ) ) {
			return new \WP_Error( 'blkrm_not_found', __( 'File not found.', 'blkrm-portal' ), array( 'status' => 404 ) );
		}
		$version_id = $version_id ? (int) $version_id : (int) $file->current_version_id;
		$version    = Files::version( $version_id );
		if ( ! $version || (int) $version->file_id !== (int) $file->id ) {
			return new \WP_Error( 'blkrm_not_found', __( 'File version not found.', 'blkrm-portal' ), array( 'status' => 404 ) );
		}
		$signed = SignedUrl::create( $version_id, $user_id );
		return array(
			'url'     => Router::url( 'download/' . $signed['token'] ),
			'expires' => $signed['expires'],
		);
	}

	/**
	 * Parse a single "bytes=" Range header.
	 *
	 * @param string $header Header value ('' if absent).
	 * @param int    $size   Total size.
	 * @return array{0:int,1:int}|null|false [start, end] inclusive; null = whole file; false = unsatisfiable.
	 */
	public static function parse_range( $header, $size ) {
		$header = trim( (string) $header );
		if ( '' === $header ) {
			return null;
		}
		if ( ! preg_match( '/^bytes=(\d*)-(\d*)$/', $header, $m ) || ( '' === $m[1] && '' === $m[2] ) ) {
			return null; // Multi-range or malformed: ignore and send everything.
		}
		if ( $size <= 0 ) {
			return false;
		}
		if ( '' === $m[1] ) { // Suffix: last N bytes.
			$len = (int) $m[2];
			if ( 0 === $len ) {
				return false;
			}
			return array( max( 0, $size - $len ), $size - 1 );
		}
		$start = (int) $m[1];
		$end   = '' === $m[2] ? $size - 1 : min( (int) $m[2], $size - 1 );
		if ( $start >= $size || $start > $end ) {
			return false;
		}
		return array( $start, $end );
	}

	/**
	 * Write plaintext bytes [start, end] of a stored object to a callback.
	 *
	 * @param string   $storage_key Object key.
	 * @param int      $start       First byte.
	 * @param int      $end         Last byte (inclusive).
	 * @param callable $write       Receives string pieces.
	 */
	public function stream_range( $storage_key, $start, $end, callable $write ) {
		$header = $this->storage->read( $storage_key, 0, Crypto::HEADER_LEN );
		$h      = Crypto::parse_header( $header );
		$key    = KeyProvider::file_key( $storage_key );
		$cs     = $h['chunk_size'];
		$first  = intdiv( $start, $cs );
		$last   = intdiv( $end, $cs );
		for ( $i = $first; $i <= $last; $i++ ) {
			$plain_len = Crypto::chunk_plain_len( $i, $h['size'], $cs );
			$sealed    = $this->storage->read( $storage_key, Crypto::chunk_offset( $i, $cs ), $plain_len + Crypto::TAG_LEN );
			$plain     = Crypto::decrypt_chunk( $key, $header, $i, $sealed );
			$from      = ( $i === $first ) ? $start - $i * $cs : 0;
			$to        = ( $i === $last ) ? $end - $i * $cs : $plain_len - 1;
			call_user_func( $write, substr( $plain, $from, $to - $from + 1 ) );
		}
	}

	/**
	 * Handle a /download/<token> request and exit.
	 *
	 * @param string $token Token.
	 */
	public function serve( $token ) {
		$claims = SignedUrl::verify( $token );
		if ( is_wp_error( $claims ) ) {
			$this->fail( $claims );
		}
		// The link only works for the person it was made for, while signed in.
		if ( get_current_user_id() !== $claims['user_id'] ) {
			$this->fail( new \WP_Error( 'blkrm_link_user', __( 'Sign in to the portal to download this file.', 'blkrm-portal' ), array( 'status' => 403 ) ) );
		}
		$version = Files::version( $claims['version_id'] );
		$file    = $version ? Files::get( (int) $version->file_id ) : null;
		if ( ! $version || ! $file || ! self::can_read( $claims['user_id'], $file ) ) {
			$this->fail( new \WP_Error( 'blkrm_not_found', __( 'File not found.', 'blkrm-portal' ), array( 'status' => 404 ) ) );
		}

		$size  = (int) $version->size;
		$range = self::parse_range( isset( $_SERVER['HTTP_RANGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) ) : '', $size );
		if ( false === $range ) {
			status_header( 416 );
			header( 'Content-Range: bytes */' . $size );
			exit;
		}
		list( $start, $end ) = null === $range ? array( 0, $size - 1 ) : $range;

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		status_header( null === $range ? 200 : 206 );
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'Content-Type: ' . ( $version->mime ? $version->mime : 'application/octet-stream' ) );
		header( 'Content-Disposition: ' . self::disposition( $file->name ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; sandbox" );
		header( 'Accept-Ranges: bytes' );
		header( 'Content-Length: ' . ( $size > 0 ? $end - $start + 1 : 0 ) );
		if ( null !== $range ) {
			header( sprintf( 'Content-Range: bytes %d-%d/%d', $start, $end, $size ) );
		}

		if ( 0 === $start ) {
			AuditLog::record(
				'file_downloaded',
				array(
					'user_id'     => $claims['user_id'],
					'org_id'      => (int) $file->org_id,
					'project_id'  => (int) $file->project_id,
					'object_type' => 'file',
					'object_id'   => (int) $file->id,
					'details'     => array( 'version' => (int) $version->version ),
				)
			);
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'HEAD' === $method || 0 === $size ) {
			exit;
		}

		try {
			$this->stream_range(
				$version->storage_key,
				$start,
				$end,
				static function ( $bytes ) {
					echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary file data.
					flush();
					if ( connection_aborted() ) {
						exit;
					}
				}
			);
		} catch ( \Exception $e ) {
			// Headers are already sent; the truncated body signals failure to the client.
			error_log( 'BLKRM Portal download failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		exit;
	}

	/**
	 * Content-Disposition with ASCII fallback + UTF-8 filename.
	 *
	 * @param string $name File name.
	 * @return string
	 */
	public static function disposition( $name ) {
		$ascii = preg_replace( '/[^A-Za-z0-9._ -]/', '_', $name );
		return sprintf( 'attachment; filename="%s"; filename*=UTF-8\'\'%s', $ascii, rawurlencode( $name ) );
	}

	/**
	 * Send an error page and exit.
	 *
	 * @param \WP_Error $error Error.
	 */
	private function fail( \WP_Error $error ) {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 403;
		wp_die( esc_html( $error->get_error_message() ), esc_html__( 'Download unavailable', 'blkrm-portal' ), array( 'response' => $status, 'back_link' => true ) );
	}
}
