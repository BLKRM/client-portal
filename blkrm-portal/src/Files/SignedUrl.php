<?php
/**
 * Short-lived, signed download tokens bound to one user and one file version.
 *
 * Token = base64url(payload) "." base64url(HMAC-SHA256(payload))
 * payload = "v<version_id>.u<user_id>.e<expires_unix>"
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Files;

use BLKRM\Portal\Storage\KeyProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Token codec.
 */
final class SignedUrl {

	const DEFAULT_TTL = 900; // 15 minutes.

	/**
	 * Create a token.
	 *
	 * @param int      $version_id Version ID.
	 * @param int      $user_id    User the link is bound to.
	 * @param int|null $ttl        Lifetime in seconds.
	 * @param int|null $now        Current time (tests).
	 * @return array{token:string,expires:int}
	 */
	public static function create( $version_id, $user_id, $ttl = null, $now = null ) {
		$ttl     = null === $ttl ? (int) apply_filters( 'blkrm_portal_download_ttl', self::DEFAULT_TTL ) : (int) $ttl;
		$expires = ( null === $now ? time() : (int) $now ) + max( 1, $ttl );
		$payload = sprintf( 'v%d.u%d.e%d', $version_id, $user_id, $expires );
		return array(
			'token'   => self::b64( $payload ) . '.' . self::b64( self::sign( $payload ) ),
			'expires' => $expires,
		);
	}

	/**
	 * Verify a token.
	 *
	 * @param string   $token Token.
	 * @param int|null $now   Current time (tests).
	 * @return array{version_id:int,user_id:int,expires:int}|\WP_Error
	 */
	public static function verify( $token, $now = null ) {
		$invalid = new \WP_Error( 'blkrm_link_invalid', __( 'This download link isn\'t valid.', 'blkrm-portal' ), array( 'status' => 403 ) );
		if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token ) ) {
			return $invalid;
		}
		list( $p64, $s64 ) = explode( '.', $token, 2 );
		$payload           = self::unb64( $p64 );
		$sig               = self::unb64( $s64 );
		if ( false === $payload || false === $sig || ! hash_equals( self::sign( $payload ), $sig ) ) {
			return $invalid;
		}
		if ( ! preg_match( '/^v(\d+)\.u(\d+)\.e(\d+)$/', $payload, $m ) ) {
			return $invalid;
		}
		if ( (int) $m[3] < ( null === $now ? time() : (int) $now ) ) {
			return new \WP_Error( 'blkrm_link_expired', __( 'This download link has expired. Go back and click Download again.', 'blkrm-portal' ), array( 'status' => 410 ) );
		}
		return array(
			'version_id' => (int) $m[1],
			'user_id'    => (int) $m[2],
			'expires'    => (int) $m[3],
		);
	}

	/**
	 * HMAC.
	 *
	 * @param string $payload Payload.
	 * @return string Raw bytes.
	 */
	private static function sign( $payload ) {
		return hash_hmac( 'sha256', $payload, KeyProvider::signing_key(), true );
	}

	/**
	 * Base64url encode.
	 *
	 * @param string $s Bytes.
	 * @return string
	 */
	private static function b64( $s ) {
		return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64url decode.
	 *
	 * @param string $s Encoded.
	 * @return string|false
	 */
	private static function unb64( $s ) {
		return base64_decode( strtr( $s, '-_', '+/' ), true );
	}
}
