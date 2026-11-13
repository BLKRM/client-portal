<?php
/**
 * Chunked authenticated encryption for stored files.
 *
 * Stored object layout:
 *
 *   header (45 bytes)
 *     "BLKRMENC"        8  magic
 *     format version    1  (1)
 *     chunk size        4  uint32 big-endian, plaintext bytes per chunk
 *     plaintext size    8  uint64 big-endian
 *     base nonce       24  random
 *   chunk 0 … chunk n-1    each = XChaCha20-Poly1305(plaintext) + 16-byte tag
 *
 * Each chunk is sealed independently with nonce = base nonce XOR index and
 * associated data = header ‖ index ‖ is-final. So:
 *   - chunks can be encrypted as they arrive, in any order (resumable uploads);
 *   - any byte range can be decrypted without reading the whole file
 *     (fast seeking / Range requests on 1 GB print files);
 *   - reordering, swapping files, truncating or extending is detected.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Pure crypto helpers. No WordPress dependencies beyond ABSPATH guard.
 */
final class Crypto {

	const MAGIC       = 'BLKRMENC';
	const FORMAT      = 1;
	const HEADER_LEN  = 45;
	const TAG_LEN     = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;
	const NONCE_LEN   = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

	/**
	 * Build a header for a new object.
	 *
	 * @param int         $chunk_size Plaintext bytes per chunk.
	 * @param int         $size       Total plaintext size.
	 * @param string|null $nonce      24-byte base nonce (random if null).
	 * @return string
	 */
	public static function make_header( $chunk_size, $size, $nonce = null ) {
		if ( $chunk_size < 1 ) {
			throw new \InvalidArgumentException( 'Chunk size must be positive.' );
		}
		if ( null === $nonce ) {
			$nonce = random_bytes( self::NONCE_LEN );
		}
		return self::MAGIC . chr( self::FORMAT ) . pack( 'N', $chunk_size ) . pack( 'J', $size ) . $nonce;
	}

	/**
	 * Parse and validate a header.
	 *
	 * @param string $header Raw header.
	 * @return array{chunk_size:int,size:int,nonce:string,chunks:int}
	 * @throws \RuntimeException On malformed input.
	 */
	public static function parse_header( $header ) {
		if ( strlen( $header ) !== self::HEADER_LEN || 0 !== strpos( $header, self::MAGIC ) || ord( $header[8] ) !== self::FORMAT ) {
			throw new \RuntimeException( 'Not a BLKRM encrypted object.' );
		}
		$chunk_size = unpack( 'N', substr( $header, 9, 4 ) )[1];
		$size       = unpack( 'J', substr( $header, 13, 8 ) )[1];
		if ( $chunk_size < 1 || $size < 0 ) {
			throw new \RuntimeException( 'Corrupt header.' );
		}
		return array(
			'chunk_size' => $chunk_size,
			'size'       => $size,
			'nonce'      => substr( $header, 21, self::NONCE_LEN ),
			'chunks'     => self::chunk_count( $size, $chunk_size ),
		);
	}

	/**
	 * Number of chunks for a plaintext size (an empty file has one empty chunk).
	 *
	 * @param int $size       Plaintext size.
	 * @param int $chunk_size Chunk size.
	 * @return int
	 */
	public static function chunk_count( $size, $chunk_size ) {
		return max( 1, (int) ceil( $size / $chunk_size ) );
	}

	/**
	 * Expected plaintext length of chunk $index.
	 *
	 * @param int $index      Chunk index.
	 * @param int $size       Plaintext size.
	 * @param int $chunk_size Chunk size.
	 * @return int
	 */
	public static function chunk_plain_len( $index, $size, $chunk_size ) {
		$count = self::chunk_count( $size, $chunk_size );
		if ( $index < 0 || $index >= $count ) {
			throw new \OutOfRangeException( 'Chunk index out of range.' );
		}
		if ( $index < $count - 1 ) {
			return $chunk_size;
		}
		return $size - ( $count - 1 ) * $chunk_size;
	}

	/**
	 * Byte offset of encrypted chunk $index inside the stored object.
	 *
	 * @param int $index      Chunk index.
	 * @param int $chunk_size Chunk size.
	 * @return int
	 */
	public static function chunk_offset( $index, $chunk_size ) {
		return self::HEADER_LEN + $index * ( $chunk_size + self::TAG_LEN );
	}

	/**
	 * Total stored size for a plaintext size.
	 *
	 * @param int $size       Plaintext size.
	 * @param int $chunk_size Chunk size.
	 * @return int
	 */
	public static function stored_size( $size, $chunk_size ) {
		return self::HEADER_LEN + $size + self::chunk_count( $size, $chunk_size ) * self::TAG_LEN;
	}

	/**
	 * Seal one chunk.
	 *
	 * @param string $key       32-byte file key.
	 * @param string $header    Object header.
	 * @param int    $index     Chunk index.
	 * @param string $plaintext Chunk bytes (must be the exact expected length).
	 * @return string Ciphertext + tag.
	 */
	public static function encrypt_chunk( $key, $header, $index, $plaintext ) {
		$h = self::parse_header( $header );
		if ( strlen( $plaintext ) !== self::chunk_plain_len( $index, $h['size'], $h['chunk_size'] ) ) {
			throw new \LengthException( 'Chunk has the wrong length.' );
		}
		return sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
			$plaintext,
			self::ad( $header, $index, $h['chunks'] ),
			self::nonce( $h['nonce'], $index ),
			$key
		);
	}

	/**
	 * Open one chunk.
	 *
	 * @param string $key        32-byte file key.
	 * @param string $header     Object header.
	 * @param int    $index      Chunk index.
	 * @param string $ciphertext Ciphertext + tag.
	 * @return string Plaintext.
	 * @throws \RuntimeException When authentication fails.
	 */
	public static function decrypt_chunk( $key, $header, $index, $ciphertext ) {
		$h     = self::parse_header( $header );
		$plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
			$ciphertext,
			self::ad( $header, $index, $h['chunks'] ),
			self::nonce( $h['nonce'], $index ),
			$key
		);
		if ( false === $plain ) {
			throw new \RuntimeException( 'File chunk failed authentication (tampered or wrong key).' );
		}
		return $plain;
	}

	/**
	 * Per-chunk nonce: base nonce with its last 8 bytes XORed with the index.
	 *
	 * @param string $base  24-byte base nonce.
	 * @param int    $index Chunk index.
	 * @return string
	 */
	private static function nonce( $base, $index ) {
		return substr( $base, 0, 16 ) . ( substr( $base, 16, 8 ) ^ pack( 'J', $index ) );
	}

	/**
	 * Associated data binding a chunk to its object, position and finality.
	 *
	 * @param string $header Header.
	 * @param int    $index  Chunk index.
	 * @param int    $count  Chunk count.
	 * @return string
	 */
	private static function ad( $header, $index, $count ) {
		return $header . pack( 'J', $index ) . ( $index === $count - 1 ? "\x01" : "\x00" );
	}
}
