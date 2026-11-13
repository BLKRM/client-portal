<?php
/**
 * Local storage: out-of-order parts → finalize → ranged decrypting reads.
 *
 * @package BLKRM\Portal
 */

use BLKRM\Portal\Files\DownloadService;
use BLKRM\Portal\Storage\Crypto;
use BLKRM\Portal\Storage\KeyProvider;
use BLKRM\Portal\Storage\LocalPrivateAdapter;
use PHPUnit\Framework\TestCase;

final class StorageAndDownloadTest extends TestCase {

	private $dir;
	private $adapter;

	protected function setUp(): void {
		$this->dir     = sys_get_temp_dir() . '/blkrm-test-' . bin2hex( random_bytes( 4 ) );
		$this->adapter = new LocalPrivateAdapter( $this->dir );
	}

	protected function tearDown(): void {
		exec( 'rm -rf ' . escapeshellarg( $this->dir ) );
	}

	/**
	 * Upload $data in shuffled chunks and finalize; return storage key.
	 */
	private function store( $data, $chunk ) {
		$upload = bin2hex( random_bytes( 16 ) );
		$key    = bin2hex( random_bytes( 16 ) );
		$header = Crypto::make_header( $chunk, strlen( $data ) );
		$count  = Crypto::chunk_count( strlen( $data ), $chunk );
		$order  = range( 0, $count - 1 );
		shuffle( $order );
		foreach ( $order as $i ) {
			$plain = substr( $data, $i * $chunk, Crypto::chunk_plain_len( $i, strlen( $data ), $chunk ) );
			$this->adapter->store_part( $upload, $i, Crypto::encrypt_chunk( KeyProvider::file_key( $key ), $header, $i, $plain ) );
		}
		$this->assertSame( range( 0, $count - 1 ), $this->adapter->received_parts( $upload ) );
		$stored = $this->adapter->finalize( $upload, $count, $header, $key );
		$this->assertSame( Crypto::stored_size( strlen( $data ), $chunk ), $stored );
		$this->adapter->discard_upload( $upload );
		$this->assertSame( array(), $this->adapter->received_parts( $upload ) );
		return $key;
	}

	private function read( $key, $start, $end ) {
		$out = '';
		( new DownloadService( $this->adapter ) )->stream_range(
			$key,
			$start,
			$end,
			function ( $b ) use ( &$out ) {
				$out .= $b;
			}
		);
		return $out;
	}

	public function test_full_and_ranged_reads_match_plaintext() {
		$data = random_bytes( 10007 );
		$key  = $this->store( $data, 1024 );

		$this->assertSame( $data, $this->read( $key, 0, strlen( $data ) - 1 ) );
		foreach ( array( array( 0, 0 ), array( 1023, 1024 ), array( 5000, 9000 ), array( 10000, 10006 ), array( 2048, 3071 ) ) as $r ) {
			$this->assertSame( substr( $data, $r[0], $r[1] - $r[0] + 1 ), $this->read( $key, $r[0], $r[1] ), 'range ' . implode( '-', $r ) );
		}
	}

	public function test_stored_bytes_are_not_plaintext() {
		$data = str_repeat( 'CONFIDENTIAL PRINT FILE ', 200 );
		$key  = $this->store( $data, 1024 );
		$raw  = file_get_contents( $this->adapter->object_path( $key ) );
		$this->assertStringNotContainsString( 'CONFIDENTIAL', $raw );
		$this->assertStringStartsWith( Crypto::MAGIC, $raw );
	}

	public function test_finalize_fails_when_part_missing() {
		$upload = bin2hex( random_bytes( 16 ) );
		$header = Crypto::make_header( 4, 8 );
		$this->adapter->store_part( $upload, 0, 'xxxx' );
		$this->expectException( RuntimeException::class );
		$this->adapter->finalize( $upload, 2, $header, bin2hex( random_bytes( 16 ) ) );
	}

	public function test_rejects_path_traversal_ids() {
		$this->expectException( InvalidArgumentException::class );
		$this->adapter->store_part( '../../etc', 0, 'x' );
	}

	public function test_parse_range() {
		$this->assertNull( DownloadService::parse_range( '', 100 ) );
		$this->assertSame( array( 0, 99 ), DownloadService::parse_range( 'bytes=0-', 100 ) );
		$this->assertSame( array( 10, 19 ), DownloadService::parse_range( 'bytes=10-19', 100 ) );
		$this->assertSame( array( 90, 99 ), DownloadService::parse_range( 'bytes=-10', 100 ) );
		$this->assertSame( array( 50, 99 ), DownloadService::parse_range( 'bytes=50-500', 100 ) );
		$this->assertFalse( DownloadService::parse_range( 'bytes=100-', 100 ) );
		$this->assertFalse( DownloadService::parse_range( 'bytes=20-10', 100 ) );
		$this->assertNull( DownloadService::parse_range( 'bytes=0-1,5-6', 100 ) );
	}

	public function test_disposition_header_is_safe() {
		$h = DownloadService::disposition( "Brand \"Guide\"\r\nX: y é.pdf" );
		$this->assertStringNotContainsString( "\r", $h );
		$this->assertStringNotContainsString( "\n", $h );
		$this->assertStringContainsString( "filename*=UTF-8''", $h );
	}
}
