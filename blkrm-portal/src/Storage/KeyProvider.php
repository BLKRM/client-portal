<?php
/**
 * Master key management.
 *
 * The master key encrypts every stored file (through per-file subkeys) and
 * signs download links. Preferred source: a constant in wp-config.php, so a
 * database dump alone cannot decrypt files:
 *
 *     define( 'BLKRM_PORTAL_ENCRYPTION_KEY', 'base64:…' );
 *
 * Without the constant, a key is generated on activation and kept in the
 * options table (autoload off). Settings → Storage shows which is in use.
 * Losing the key means losing access to stored files: back it up.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Key derivation.
 */
final class KeyProvider {

	const OPTION = 'blkrm_portal_master_key';

	/**
	 * Generate and store a key if none exists and no constant is defined.
	 */
	public static function ensure_key() {
		if ( self::from_constant() ) {
			return;
		}
		if ( get_option( self::OPTION ) ) {
			return;
		}
		add_option( self::OPTION, 'base64:' . base64_encode( random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES ) ), '', 'no' );
	}

	/**
	 * Where the key comes from: 'constant', 'database' or 'missing'.
	 *
	 * @return string
	 */
	public static function source() {
		if ( self::from_constant() ) {
			return 'constant';
		}
		return self::decode( (string) get_option( self::OPTION, '' ) ) ? 'database' : 'missing';
	}

	/**
	 * The 32-byte master key.
	 *
	 * @return string
	 * @throws \RuntimeException When no valid key is configured.
	 */
	public static function master() {
		$key = self::from_constant();
		if ( ! $key ) {
			$key = self::decode( (string) get_option( self::OPTION, '' ) );
		}
		if ( ! $key ) {
			throw new \RuntimeException( 'BLKRM Portal encryption key is missing or invalid.' );
		}
		return $key;
	}

	/**
	 * Subkey for one stored object. A different key per file limits the
	 * impact of any single key or nonce problem to that file.
	 *
	 * @param string $storage_key Object key.
	 * @return string 32 bytes.
	 */
	public static function file_key( $storage_key ) {
		return sodium_crypto_generichash( 'blkrm-file:' . $storage_key, self::master(), 32 );
	}

	/**
	 * HMAC key for signed download links.
	 *
	 * @return string 32 bytes.
	 */
	public static function signing_key() {
		return sodium_crypto_generichash( 'blkrm-download-links', self::master(), 32 );
	}

	/**
	 * Key from BLKRM_PORTAL_ENCRYPTION_KEY, if defined and valid.
	 *
	 * @return string|null
	 */
	private static function from_constant() {
		if ( ! defined( 'BLKRM_PORTAL_ENCRYPTION_KEY' ) ) {
			return null;
		}
		return self::decode( (string) constant( 'BLKRM_PORTAL_ENCRYPTION_KEY' ) );
	}

	/**
	 * Decode a "base64:..." key string.
	 *
	 * @param string $value Encoded key.
	 * @return string|null
	 */
	public static function decode( $value ) {
		if ( 0 === strpos( $value, 'base64:' ) ) {
			$value = substr( $value, 7 );
		}
		$raw = base64_decode( $value, true );
		return ( false !== $raw && SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES === strlen( $raw ) ) ? $raw : null;
	}
}
