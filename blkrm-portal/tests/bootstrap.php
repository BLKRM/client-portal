<?php
/**
 * Unit-test bootstrap: minimal WordPress stubs so storage, crypto and token
 * code can be tested without a database. Integration behaviour is covered by
 * tests/smoke/run.sh against a real WordPress install.
 *
 * @package BLKRM\Portal
 */

define( 'ABSPATH', sys_get_temp_dir() . '/blkrm-wp-stub/' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MB_IN_BYTES', 1048576 );
define( 'GB_IN_BYTES', 1073741824 );

$GLOBALS['blkrm_test_options'] = array();

function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['blkrm_test_options'] ) ? $GLOBALS['blkrm_test_options'][ $k ] : $d;
}
function add_option( $k, $v ) {
	if ( ! array_key_exists( $k, $GLOBALS['blkrm_test_options'] ) ) {
		$GLOBALS['blkrm_test_options'][ $k ] = $v;
	}
	return true;
}
function update_option( $k, $v ) {
	$GLOBALS['blkrm_test_options'][ $k ] = $v;
	return true;
}
function apply_filters( $tag, $value ) {
	return $value;
}
function __( $s ) {
	return $s;
}
function untrailingslashit( $s ) {
	return rtrim( $s, '/\\' );
}
function trailingslashit( $s ) {
	return untrailingslashit( $s ) . '/';
}
function wp_mkdir_p( $dir ) {
	return is_dir( $dir ) || mkdir( $dir, 0777, true );
}
function wp_delete_file( $f ) {
	@unlink( $f );
}
function sanitize_file_name( $n ) {
	return preg_replace( '/[^A-Za-z0-9._-]/', '-', $n );
}
function wp_basename( $p ) {
	return basename( str_replace( '\\', '/', $p ) );
}
function is_wp_error( $x ) {
	return $x instanceof WP_Error;
}

class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
	public function get_error_data() {
		return $this->data;
	}
}

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'BLKRM\\Portal\\';
		if ( 0 === strpos( $class, $prefix ) ) {
			$file = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require $file;
			}
		}
	}
);

@mkdir( ABSPATH, 0777, true );
\BLKRM\Portal\Storage\KeyProvider::ensure_key();
