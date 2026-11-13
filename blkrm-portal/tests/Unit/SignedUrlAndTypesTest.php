<?php
/**
 * Download tokens + file type policy.
 *
 * @package BLKRM\Portal
 */

use BLKRM\Portal\Files\FileTypes;
use BLKRM\Portal\Files\SignedUrl;
use PHPUnit\Framework\TestCase;

final class SignedUrlAndTypesTest extends TestCase {

	public function test_token_round_trip() {
		$t = SignedUrl::create( 42, 7, 900, 1000 );
		$c = SignedUrl::verify( $t['token'], 1500 );
		$this->assertSame( array( 'version_id' => 42, 'user_id' => 7, 'expires' => 1900 ), $c );
	}

	public function test_expired_token() {
		$t = SignedUrl::create( 42, 7, 900, 1000 );
		$this->assertSame( 'blkrm_link_expired', SignedUrl::verify( $t['token'], 1901 )->get_error_code() );
	}

	public function test_tampered_token() {
		$t = SignedUrl::create( 42, 7, 900, 1000 );
		list( $p, $s ) = explode( '.', $t['token'] );
		$forged = rtrim( strtr( base64_encode( 'v43.u7.e1900' ), '+/', '-_' ), '=' ) . '.' . $s;
		$this->assertSame( 'blkrm_link_invalid', SignedUrl::verify( $forged, 1500 )->get_error_code() );
		$this->assertSame( 'blkrm_link_invalid', SignedUrl::verify( 'garbage', 1500 )->get_error_code() );
		$this->assertSame( 'blkrm_link_invalid', SignedUrl::verify( $p . '.AAAA', 1500 )->get_error_code() );
	}

	public function test_allowed_names() {
		$ok = FileTypes::check_name( 'Booth Backwall 10x8.pdf' );
		$this->assertIsArray( $ok );
		$this->assertSame( 'application/pdf', $ok['mime'] );
		$this->assertIsArray( FileTypes::check_name( 'Layout.indd' ) );
		$this->assertIsArray( FileTypes::check_name( 'Photo.TIFF' ) );
	}

	public function test_blocked_names() {
		foreach ( array( 'shell.php', 'page.html', 'logo.svg', 'noext', 'evil.php.pdf', 'x.exe' ) as $name ) {
			$this->assertInstanceOf( WP_Error::class, FileTypes::check_name( $name ), $name );
		}
	}

	public function test_content_sniffing() {
		$this->assertTrue( FileTypes::check_content( 'pdf', "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n1 0 obj\n" ) );
		$this->assertInstanceOf( WP_Error::class, FileTypes::check_content( 'pdf', "<html><script>alert(1)</script></html>" ) );
		$this->assertInstanceOf( WP_Error::class, FileTypes::check_content( 'zip', "<?php system(\$_GET['c']); ?>" ) );
		$this->assertInstanceOf( WP_Error::class, FileTypes::check_content( 'ai', '<svg xmlns="http://www.w3.org/2000/svg"><script/></svg>' ) );
	}
}
