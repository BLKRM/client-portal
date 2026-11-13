<?php
/**
 * End-to-end smoke test against a running WordPress site with the plugin
 * active and seeded (see tests/smoke/README.md). Exercises the real HTTP
 * surface: login, chunked + resumed upload, downloads with Range, and
 * isolation between client organizations.
 *
 * Usage: php tests/smoke/e2e.php http://127.0.0.1:8899 <project_id_org_a> <project_id_org_b>
 *
 * @package BLKRM\Portal
 */

$base   = rtrim( $argv[1] ?? 'http://127.0.0.1:8899', '/' );
$p_mine = (int) ( $argv[2] ?? 1 );
$p_other = (int) ( $argv[3] ?? 2 );
$fails  = 0;
$tmp    = sys_get_temp_dir() . '/blkrm-e2e-' . getmypid();
@mkdir( $tmp );

function check( $cond, $label ) {
	global $fails;
	echo ( $cond ? "  PASS  " : "  FAIL  " ) . $label . "\n";
	if ( ! $cond ) {
		$fails++;
	}
}

function http( $jar, $method, $url, $body = null, array $headers = array(), $follow = false ) {
	$ch = curl_init( $url );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_COOKIEJAR      => $jar,
			CURLOPT_COOKIEFILE     => $jar,
			CURLOPT_FOLLOWLOCATION => $follow,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_TIMEOUT        => 120,
		)
	);
	if ( null !== $body ) {
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
	}
	$raw  = curl_exec( $ch );
	$code = curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	$hlen = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
	curl_close( $ch );
	return array( 'code' => $code, 'headers' => substr( $raw, 0, $hlen ), 'body' => substr( $raw, $hlen ) );
}

function login( $base, $user, $pass, $jar_name = null ) {
	global $tmp;
	$jar  = $tmp . '/' . ( $jar_name ? $jar_name : $user ) . '.cookies';
	$page = http( $jar, 'GET', "$base/portal/login/" );
	preg_match( '/name="_blkrm_login" value="([^"]+)"/', $page['body'], $m );
	$r = http( $jar, 'POST', "$base/portal/login/", http_build_query( array( 'log' => $user, 'pwd' => $pass, '_blkrm_login' => $m[1] ?? '' ) ) );
	return array( $jar, $r );
}

function rest_nonce( $base, $jar, $project ) {
	$page = http( $jar, 'GET', "$base/portal/project/$project/" );
	preg_match( '/"nonce":"([a-f0-9]+)"/', $page['body'], $m );
	return array( $m[1] ?? '', $page );
}

function api( $base, $jar, $nonce, $method, $path, $body = null, $extra = array() ) {
	$headers = array_merge( array( 'X-WP-Nonce: ' . $nonce ), $extra );
	if ( is_array( $body ) ) {
		$body      = json_encode( $body );
		$headers[] = 'Content-Type: application/json';
	}
	$r         = http( $jar, $method, "$base/wp-json/blkrm/v1/$path", $body, $headers );
	$r['json'] = json_decode( $r['body'], true );
	return $r;
}

echo "== Login\n";
list( $maya, $r ) = login( $base, 'maya', 'pass-maya' );
check( 302 === $r['code'] && false !== strpos( $r['headers'], '/portal/' ), 'client signs in and is redirected to the portal' );
list( $bad, $r ) = login( $base, 'maya', 'wrong', 'maya-wrong' );
check( 200 === $r['code'] && false !== strpos( $r['body'], 'isn&#039;t right' ), 'wrong password shows a generic error' );
list( $sam ) = login( $base, 'sam', 'pass-sam' );
list( $alex ) = login( $base, 'alex', 'pass-alex' );

echo "== Pages + isolation\n";
list( $nonce, $page ) = rest_nonce( $base, $maya, $p_mine );
check( 200 === $page['code'] && false !== strpos( $page['body'], 'PATIENT WELCOME KIT' ), 'client sees own project' );
check( false === strpos( $page['body'], 'wp-content/themes' ), 'portal page loads no theme assets' );
$other = http( $maya, 'GET', "$base/portal/project/$p_other/" );
check( 404 === $other['code'], 'client gets 404 for another client\'s project' );
$staff_page = http( $alex, 'GET', "$base/portal/project/$p_other/" );
check( 200 === $staff_page['code'], 'staff can open any project' );

echo "== Chunked upload (out of order + resume)\n";
$size  = 20 * 1024 * 1024 + 12345;
$data  = "%PDF-1.7\n" . random_bytes( $size - 9 );
$start = api( $base, $maya, $nonce, 'POST', 'uploads', array( 'project_id' => $p_mine, 'name' => 'Welcome Kit PRINT.pdf', 'size' => $size ) );
check( 201 === $start['code'], 'upload session starts' );
$id    = $start['json']['upload_id'];
$cs    = $start['json']['chunk_size'];
$total = $start['json']['total_chunks'];
check( $total === (int) ceil( $size / $cs ), "server picked $total chunks of " . round( $cs / 1048576, 1 ) . ' MB' );

$send = function ( $i, $hash = null ) use ( $base, $maya, $nonce, $id, $cs, $data ) {
	$chunk = substr( $data, $i * $cs, $cs );
	return api( $base, $maya, $nonce, 'PUT', "uploads/$id/chunks/$i", $chunk, array( 'Content-Type: application/octet-stream', 'X-Chunk-SHA256: ' . ( $hash ?? hash( 'sha256', $chunk ) ) ) );
};
$r = $send( $total - 1 );
check( 200 === $r['code'], 'last chunk accepted first (any order)' );
$r = $send( 0, str_repeat( '0', 64 ) );
check( 400 === $r['code'] && 'blkrm_chunk_checksum' === $r['json']['code'], 'corrupted chunk (bad checksum) rejected' );
$r = $send( 0 );
check( 200 === $r['code'], 'chunk 0 accepted' );
$early = api( $base, $maya, $nonce, 'POST', "uploads/$id/complete", array() );
check( 409 === $early['code'], 'complete refused while chunks are missing' );
$status = api( $base, $maya, $nonce, 'GET', "uploads/$id" );
check( isset( $status['json']['received'] ) && count( $status['json']['received'] ) === 2, 'status reports received chunks (resume point)' );
$sam_nonce = rest_nonce( $base, $sam, $p_other )[0];
$hijack    = api( $base, $sam, $sam_nonce, 'GET', "uploads/$id" );
check( 404 === $hijack['code'], 'another user cannot see this upload session' );
for ( $i = 1; $i < $total - 1; $i++ ) {
	$send( $i );
}
$done = api( $base, $maya, $nonce, 'POST', "uploads/$id/complete", array() );
check( 200 === $done['code'] && 1 === $done['json']['version'], 'upload completes as v1' );
$file_id = $done['json']['file_id'];
$again   = api( $base, $maya, $nonce, 'POST', "uploads/$id/complete", array() );
check( 409 === $again['code'], 'double complete is refused' );

echo "== Downloads\n";
$link = api( $base, $maya, $nonce, 'POST', "files/$file_id/download-link", array() );
check( 200 === $link['code'] && false !== strpos( $link['json']['url'], '/portal/download/' ), 'signed link issued' );
$url  = $link['json']['url'];
$full = http( $maya, 'GET', $url );
check( 200 === $full['code'] && hash( 'sha256', $full['body'] ) === hash( 'sha256', $data ), 'full download matches original byte-for-byte (' . strlen( $full['body'] ) . ' bytes)' );
check( false !== stripos( $full['headers'], 'Content-Disposition: attachment' ) && false !== stripos( $full['headers'], 'nosniff' ), 'served as attachment with nosniff' );
$from  = 8 * 1024 * 1024 - 100;
$range = http( $maya, 'GET', $url, null, array( 'Range: bytes=' . $from . '-' . ( $from + 299 ) ) );
check( 206 === $range['code'] && substr( $data, $from, 300 ) === $range['body'], 'Range request across a chunk boundary returns exact bytes' );
$tail = http( $maya, 'GET', $url, null, array( 'Range: bytes=-500' ) );
check( 206 === $tail['code'] && substr( $data, -500 ) === $tail['body'], 'suffix Range returns the last bytes' );
$stolen = http( $sam, 'GET', $url );
check( 403 === $stolen['code'], 'a link used by a different signed-in user is refused' );
$anon = http( "$tmp/anon.cookies", 'GET', $url );
check( 403 === $anon['code'], 'a link opened without signing in is refused' );
$tampered = http( $maya, 'GET', preg_replace( '/\.([A-Za-z0-9_-]{4})/', '.AAAA', $url, 1 ) );
check( 403 === $tampered['code'], 'a tampered link is refused' );
$sam_link = api( $base, $sam, $sam_nonce, 'POST', "files/$file_id/download-link", array() );
check( 404 === $sam_link['code'], 'another client cannot get a link to this file' );
$staff_nonce = rest_nonce( $base, $alex, $p_mine )[0];
$staff_link  = api( $base, $alex, $staff_nonce, 'POST', "files/$file_id/download-link", array() );
check( 200 === $staff_link['code'], 'staff can get a link' );

echo "== New version + type checks\n";
$small = "%PDF-1.4\nsecond version";
$v2    = api( $base, $maya, $nonce, 'POST', 'uploads', array( 'project_id' => $p_mine, 'name' => 'Welcome Kit PRINT.pdf', 'size' => strlen( $small ), 'file_id' => $file_id ) );
api( $base, $maya, $nonce, 'PUT', 'uploads/' . $v2['json']['upload_id'] . '/chunks/0', $small, array( 'Content-Type: application/octet-stream' ) );
$v2d = api( $base, $maya, $nonce, 'POST', 'uploads/' . $v2['json']['upload_id'] . '/complete', array() );
check( 200 === $v2d['code'] && 2 === $v2d['json']['version'] && $file_id === $v2d['json']['file_id'], 'second upload to the same file becomes v2' );
$vers = api( $base, $maya, $nonce, 'GET', "files/$file_id/versions" );
check( 2 === count( $vers['json'] ?? array() ), 'version history lists v1 + v2' );

$evil = '<html><script>alert(1)</script></html>';
$e    = api( $base, $maya, $nonce, 'POST', 'uploads', array( 'project_id' => $p_mine, 'name' => 'invoice.pdf', 'size' => strlen( $evil ) ) );
$er   = api( $base, $maya, $nonce, 'PUT', 'uploads/' . $e['json']['upload_id'] . '/chunks/0', $evil, array( 'Content-Type: application/octet-stream' ) );
check( 400 === $er['code'], 'HTML disguised as .pdf is rejected on content' );
$php = api( $base, $maya, $nonce, 'POST', 'uploads', array( 'project_id' => $p_mine, 'name' => 'shell.php', 'size' => 10 ) );
check( 400 === $php['code'], '.php upload refused by name' );
$cross = api( $base, $maya, $nonce, 'POST', 'uploads', array( 'project_id' => $p_other, 'name' => 'x.pdf', 'size' => 10 ) );
check( 403 === $cross['code'], 'client cannot upload into another client\'s project' );
$nononce = api( $base, $maya, 'bad', 'POST', 'uploads', array( 'project_id' => $p_mine, 'name' => 'x.pdf', 'size' => 10 ) );
check( in_array( $nononce['code'], array( 401, 403 ), true ), 'REST calls without a valid nonce are refused' );

echo "== Sign out\n";
$home = http( $maya, 'GET', "$base/portal/" );
preg_match( '#href="([^"]*/portal/logout/[^"]*)"#', $home['body'], $m );
$out = http( $maya, 'GET', html_entity_decode( $m[1] ?? '' ) );
$after = http( $maya, 'GET', "$base/portal/" );
check( 302 === $after['code'], 'signed out: portal redirects to login' );
$csrf = http( $alex, 'GET', "$base/portal/logout/" );
$still = http( $alex, 'GET', "$base/portal/" );
check( 200 === $still['code'], 'logout without nonce (CSRF) does not sign out' );

exec( 'rm -rf ' . escapeshellarg( $tmp ) );
echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
exit( $fails ? 1 : 0 );
