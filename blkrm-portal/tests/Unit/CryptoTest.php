<?php
/**
 * Chunked encryption format.
 *
 * @package BLKRM\Portal
 */

use BLKRM\Portal\Storage\Crypto;
use PHPUnit\Framework\TestCase;

final class CryptoTest extends TestCase {

	private function key() {
		return random_bytes( 32 );
	}

	public function test_round_trip_all_chunks() {
		$key    = $this->key();
		$data   = random_bytes( 2500 );
		$header = Crypto::make_header( 1000, strlen( $data ) );
		$h      = Crypto::parse_header( $header );
		$this->assertSame( 3, $h['chunks'] );

		$out = '';
		for ( $i = 0; $i < 3; $i++ ) {
			$plain  = substr( $data, $i * 1000, Crypto::chunk_plain_len( $i, 2500, 1000 ) );
			$sealed = Crypto::encrypt_chunk( $key, $header, $i, $plain );
			$this->assertSame( strlen( $plain ) + Crypto::TAG_LEN, strlen( $sealed ) );
			$out .= Crypto::decrypt_chunk( $key, $header, $i, $sealed );
		}
		$this->assertSame( $data, $out );
		$this->assertSame( Crypto::HEADER_LEN + 2500 + 3 * Crypto::TAG_LEN, Crypto::stored_size( 2500, 1000 ) );
	}

	public function test_empty_file_has_one_empty_chunk() {
		$key    = $this->key();
		$header = Crypto::make_header( 1000, 0 );
		$sealed = Crypto::encrypt_chunk( $key, $header, 0, '' );
		$this->assertSame( '', Crypto::decrypt_chunk( $key, $header, 0, $sealed ) );
	}

	public function test_wrong_length_rejected() {
		$this->expectException( LengthException::class );
		$header = Crypto::make_header( 1000, 2500 );
		Crypto::encrypt_chunk( $this->key(), $header, 0, str_repeat( 'a', 999 ) );
	}

	public function test_tampered_chunk_fails() {
		$key    = $this->key();
		$header = Crypto::make_header( 1000, 1000 );
		$sealed = Crypto::encrypt_chunk( $key, $header, 0, str_repeat( 'x', 1000 ) );
		$sealed[10] = chr( ord( $sealed[10] ) ^ 1 );
		$this->expectException( RuntimeException::class );
		Crypto::decrypt_chunk( $key, $header, 0, $sealed );
	}

	public function test_swapped_chunks_fail() {
		$key    = $this->key();
		$header = Crypto::make_header( 4, 8 );
		$a      = Crypto::encrypt_chunk( $key, $header, 0, 'AAAA' );
		$this->expectException( RuntimeException::class );
		Crypto::decrypt_chunk( $key, $header, 1, $a );
	}

	public function test_chunk_from_another_file_fails() {
		$key = $this->key();
		$h1  = Crypto::make_header( 4, 4 );
		$h2  = Crypto::make_header( 4, 4 );
		$c   = Crypto::encrypt_chunk( $key, $h1, 0, 'AAAA' );
		$this->expectException( RuntimeException::class );
		Crypto::decrypt_chunk( $key, $h2, 0, $c );
	}

	public function test_truncation_detected_via_final_flag() {
		// A 3-chunk file's header claims 3 chunks; the chunk sealed as
		// non-final cannot be passed off as the last chunk of a shorter header.
		$key    = $this->key();
		$nonce  = random_bytes( 24 );
		$long   = Crypto::make_header( 4, 12, $nonce );
		$short  = Crypto::make_header( 4, 8, $nonce );
		$sealed = Crypto::encrypt_chunk( $key, $long, 1, 'BBBB' );
		$this->expectException( RuntimeException::class );
		Crypto::decrypt_chunk( $key, $short, 1, $sealed );
	}

	public function test_wrong_key_fails() {
		$header = Crypto::make_header( 4, 4 );
		$c      = Crypto::encrypt_chunk( $this->key(), $header, 0, 'AAAA' );
		$this->expectException( RuntimeException::class );
		Crypto::decrypt_chunk( $this->key(), $header, 0, $c );
	}

	public function test_bad_header_rejected() {
		$this->expectException( RuntimeException::class );
		Crypto::parse_header( str_repeat( 'x', Crypto::HEADER_LEN ) );
	}
}
