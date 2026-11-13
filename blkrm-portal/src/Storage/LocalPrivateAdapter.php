<?php
/**
 * Local private storage on the web server's disk.
 *
 * Location, in order of preference:
 *   1. BLKRM_PORTAL_STORAGE_DIR constant (set in wp-config.php).
 *   2. The directory chosen on activation (option), which is the first
 *      writable candidate of:
 *        a. a folder one level ABOVE the WordPress root (not web-served);
 *        b. a randomly named folder inside wp-content with deny rules.
 *
 * Even when (b) is used, e.g. on hosts that ignore .htaccess such as
 * WordPress.com, files stay safe: names are random, contents encrypted,
 * and nothing in the portal ever links to the raw path.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Filesystem adapter.
 */
class LocalPrivateAdapter implements StorageAdapter {

	const OPTION = 'blkrm_portal_storage_dir';

	/**
	 * Base directory (no trailing slash).
	 *
	 * @var string
	 */
	private $base;

	/**
	 * Constructor.
	 *
	 * @param string|null $base Base directory; resolved automatically if null.
	 */
	public function __construct( $base = null ) {
		$this->base = untrailingslashit( null === $base ? self::base_dir() : $base );
	}

	/**
	 * Configured base directory.
	 *
	 * @return string
	 */
	public static function base_dir() {
		if ( defined( 'BLKRM_PORTAL_STORAGE_DIR' ) ) {
			return untrailingslashit( (string) constant( 'BLKRM_PORTAL_STORAGE_DIR' ) );
		}
		$dir = (string) get_option( self::OPTION, '' );
		if ( '' === $dir ) {
			$dir = self::ensure_base_dir();
		}
		return $dir;
	}

	/**
	 * Choose, create and protect the base directory. Stores the choice.
	 *
	 * @return string Chosen directory ('' if none writable).
	 */
	public static function ensure_base_dir() {
		if ( defined( 'BLKRM_PORTAL_STORAGE_DIR' ) ) {
			$dir = untrailingslashit( (string) constant( 'BLKRM_PORTAL_STORAGE_DIR' ) );
			self::prepare( $dir );
			return $dir;
		}

		$existing = (string) get_option( self::OPTION, '' );
		if ( '' !== $existing && self::prepare( $existing ) ) {
			return $existing;
		}

		$candidates = array(
			dirname( untrailingslashit( ABSPATH ) ) . '/blkrm-portal-private',
			untrailingslashit( WP_CONTENT_DIR ) . '/blkrm-private-' . bin2hex( random_bytes( 12 ) ),
		);
		foreach ( $candidates as $dir ) {
			if ( self::prepare( $dir ) ) {
				update_option( self::OPTION, $dir, false );
				return $dir;
			}
		}
		return '';
	}

	/**
	 * Create a directory, add deny rules and confirm it is writable.
	 *
	 * @param string $dir Directory.
	 * @return bool
	 */
	private static function prepare( $dir ) {
		// Suppress warnings from open_basedir restrictions on probe paths.
		if ( ! @is_dir( $dir ) && ! @wp_mkdir_p( $dir ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false;
		}
		if ( ! @is_writable( $dir ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false;
		}
		$deny = array(
			'.htaccess'  => "# BLKRM Portal private storage\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'index.php'  => "<?php\n// Silence is golden.\n",
		);
		foreach ( $deny as $name => $contents ) {
			$path = $dir . '/' . $name;
			if ( ! file_exists( $path ) ) {
				file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
		return true;
	}

	/**
	 * Whether the base directory sits inside the public web root.
	 *
	 * @return bool
	 */
	public function is_inside_webroot() {
		$base = realpath( $this->base );
		$root = realpath( ABSPATH );
		return $base && $root && 0 === strpos( trailingslashit( $base ), trailingslashit( $root ) );
	}

	/**
	 * Base directory.
	 *
	 * @return string
	 */
	public function base() {
		return $this->base;
	}

	/**
	 * {@inheritDoc}
	 */
	public function store_part( $upload_id, $index, $bytes ) {
		$dir = $this->parts_dir( $upload_id );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			throw new \RuntimeException( 'Cannot create upload directory.' );
		}
		$final = $dir . '/' . (int) $index . '.part';
		$tmp   = $final . '.' . bin2hex( random_bytes( 4 ) ) . '.tmp';
		if ( false === file_put_contents( $tmp, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			throw new \RuntimeException( 'Cannot write upload part.' );
		}
		// Atomic: a retried chunk replaces the old one cleanly.
		if ( ! rename( $tmp, $final ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			@unlink( $tmp ); // phpcs:ignore
			throw new \RuntimeException( 'Cannot store upload part.' );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function received_parts( $upload_id ) {
		$dir = $this->parts_dir( $upload_id );
		if ( ! is_dir( $dir ) ) {
			return array();
		}
		$parts = array();
		foreach ( (array) scandir( $dir ) as $name ) {
			if ( preg_match( '/^(\d+)\.part$/', (string) $name, $m ) ) {
				$parts[] = (int) $m[1];
			}
		}
		sort( $parts );
		return $parts;
	}

	/**
	 * {@inheritDoc}
	 */
	public function finalize( $upload_id, $total_parts, $header, $key ) {
		$dest = $this->object_path( $key );
		$dir  = dirname( $dest );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			throw new \RuntimeException( 'Cannot create storage directory.' );
		}
		$tmp = $dest . '.tmp';
		$out = fopen( $tmp, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $out ) {
			throw new \RuntimeException( 'Cannot open storage file.' );
		}
		fwrite( $out, $header ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		for ( $i = 0; $i < $total_parts; $i++ ) {
			$part = $this->parts_dir( $upload_id ) . '/' . $i . '.part';
			$in   = @fopen( $part, 'rb' ); // phpcs:ignore
			if ( ! $in ) {
				fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				@unlink( $tmp ); // phpcs:ignore
				throw new \RuntimeException( sprintf( 'Upload part %d is missing.', $i ) );
			}
			stream_copy_to_stream( $in, $out );
			fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( ! rename( $tmp, $dest ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			@unlink( $tmp ); // phpcs:ignore
			throw new \RuntimeException( 'Cannot finalize storage file.' );
		}
		clearstatcache( true, $dest );
		return (int) filesize( $dest );
	}

	/**
	 * {@inheritDoc}
	 */
	public function discard_upload( $upload_id ) {
		$dir = $this->parts_dir( $upload_id );
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( (array) scandir( $dir ) as $name ) {
			if ( '.' !== $name && '..' !== $name ) {
				@unlink( $dir . '/' . $name ); // phpcs:ignore
			}
		}
		@rmdir( $dir ); // phpcs:ignore
	}

	/**
	 * {@inheritDoc}
	 */
	public function read( $key, $offset, $length ) {
		$fh = @fopen( $this->object_path( $key ), 'rb' ); // phpcs:ignore
		if ( ! $fh ) {
			throw new \RuntimeException( 'Stored file not found.' );
		}
		fseek( $fh, $offset );
		$data = '';
		while ( strlen( $data ) < $length && ! feof( $fh ) ) {
			$buf = fread( $fh, $length - strlen( $data ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			if ( false === $buf || '' === $buf ) {
				break;
			}
			$data .= $buf;
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $data;
	}

	/**
	 * {@inheritDoc}
	 */
	public function exists( $key ) {
		return is_file( $this->object_path( $key ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( $key ) {
		$path = $this->object_path( $key );
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Path of a stored object. Keys are random hex, fanned out over two
	 * directory levels to keep directories small.
	 *
	 * @param string $key Object key.
	 * @return string
	 */
	public function object_path( $key ) {
		self::assert_id( $key );
		return $this->base . '/objects/' . substr( $key, 0, 2 ) . '/' . substr( $key, 2, 2 ) . '/' . $key . '.blk';
	}

	/**
	 * Temporary parts directory for an upload.
	 *
	 * @param string $upload_id Upload ID.
	 * @return string
	 */
	private function parts_dir( $upload_id ) {
		self::assert_id( $upload_id );
		return $this->base . '/uploads/' . $upload_id;
	}

	/**
	 * Reject anything but lowercase hex IDs (no path traversal).
	 *
	 * @param string $id ID.
	 */
	private static function assert_id( $id ) {
		if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9]{32,64}$/', $id ) ) {
			throw new \InvalidArgumentException( 'Invalid storage identifier.' );
		}
	}
}
