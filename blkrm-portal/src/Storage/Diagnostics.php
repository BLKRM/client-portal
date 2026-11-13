<?php
/**
 * Storage self-check, shown in Portal Settings. Answers the Phase 0
 * question for WordPress.com: is the storage folder outside the public web
 * root, and if not, can its files be fetched over HTTP?
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Diagnostics.
 */
final class Diagnostics {

	/**
	 * Run all checks.
	 *
	 * @param bool $probe_http Whether to try fetching a canary file over HTTP.
	 * @return array<int, array{label:string,status:string,detail:string}> status: ok|warn|fail
	 */
	public static function run( $probe_http = false ) {
		$checks = array();

		$checks[] = array(
			'label'  => __( 'Encryption library (libsodium)', 'blkrm-portal' ),
			'status' => function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) ? 'ok' : 'fail',
			'detail' => function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) ? __( 'Available.', 'blkrm-portal' ) : __( 'Missing: PHP\'s sodium extension is required.', 'blkrm-portal' ),
		);

		$source   = KeyProvider::source();
		$checks[] = array(
			'label'  => __( 'Encryption key', 'blkrm-portal' ),
			'status' => 'constant' === $source ? 'ok' : ( 'database' === $source ? 'warn' : 'fail' ),
			'detail' => 'constant' === $source
				? __( 'Defined in wp-config.php (recommended).', 'blkrm-portal' )
				: ( 'database' === $source
					? __( 'Stored in the database. Works, but moving it to wp-config.php as BLKRM_PORTAL_ENCRYPTION_KEY means a database leak alone can\'t decrypt files. Keep a secure backup of the key: without it, stored files can\'t be opened.', 'blkrm-portal' )
					: __( 'Missing. Deactivate and reactivate the plugin to generate one.', 'blkrm-portal' ) ),
		);

		$adapter  = new LocalPrivateAdapter();
		$base     = $adapter->base();
		$writable = '' !== $base && is_dir( $base ) && is_writable( $base );
		$checks[] = array(
			'label'  => __( 'Storage folder', 'blkrm-portal' ),
			'status' => $writable ? 'ok' : 'fail',
			'detail' => $writable ? $base : __( 'No writable storage folder. Set BLKRM_PORTAL_STORAGE_DIR in wp-config.php.', 'blkrm-portal' ),
		);

		if ( $writable ) {
			$inside   = $adapter->is_inside_webroot();
			$checks[] = array(
				'label'  => __( 'Outside the public web root', 'blkrm-portal' ),
				'status' => $inside ? 'warn' : 'ok',
				'detail' => $inside
					? __( 'No: the folder is inside the site\'s web root. Files are still encrypted and randomly named, so a guessed URL returns unreadable data, but a folder outside the web root is better if your host allows one.', 'blkrm-portal' )
					: __( 'Yes: the web server cannot serve these files directly.', 'blkrm-portal' ),
			);

			if ( $inside && $probe_http ) {
				$checks[] = self::http_probe( $base );
			}

			$free     = function_exists( 'disk_free_space' ) ? @disk_free_space( $base ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$checks[] = array(
				'label'  => __( 'Free disk space', 'blkrm-portal' ),
				'status' => ( false === $free || $free > 10 * GB_IN_BYTES ) ? 'ok' : 'warn',
				'detail' => false === $free ? __( 'Host doesn\'t report it. Check your plan\'s storage page.', 'blkrm-portal' ) : size_format( $free, 1 ),
			);
		}

		$post     = wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) );
		$checks[] = array(
			'label'  => __( 'Request size limit', 'blkrm-portal' ),
			'status' => ( $post <= 0 || $post >= 2 * MB_IN_BYTES ) ? 'ok' : 'warn',
			/* translators: 1: post_max_size, 2: chunk size */
			'detail' => sprintf( __( 'post_max_size %1$s → upload chunks of %2$s each (large files are split automatically).', 'blkrm-portal' ), $post > 0 ? size_format( $post ) : __( 'unlimited', 'blkrm-portal' ), size_format( \BLKRM\Portal\Files\UploadService::chunk_bytes() ) ),
		);

		$checks[] = array(
			'label'  => __( 'PHP time limit', 'blkrm-portal' ),
			'status' => 'ok',
			/* translators: %s: seconds */
			'detail' => sprintf( __( 'max_execution_time %s s. Each chunk is a short request, so this doesn\'t limit file size.', 'blkrm-portal' ), (string) ini_get( 'max_execution_time' ) ),
		);

		return $checks;
	}

	/**
	 * Write a canary into storage and try to fetch it over HTTP.
	 *
	 * @param string $base Storage base dir.
	 * @return array{label:string,status:string,detail:string}
	 */
	private static function http_probe( $base ) {
		$label = __( 'Direct web access blocked', 'blkrm-portal' );
		$root  = untrailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
		$dir   = wp_normalize_path( (string) realpath( $base ) );
		$name  = 'probe-' . bin2hex( random_bytes( 8 ) ) . '.txt';
		$token = bin2hex( random_bytes( 16 ) );
		file_put_contents( $dir . '/' . $name, $token ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$url = site_url( substr( $dir, strlen( $root ) ) . '/' . $name );

		$res  = wp_remote_get( $url, array( 'timeout' => 10, 'redirection' => 0, 'sslverify' => false ) );
		$body = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		wp_delete_file( $dir . '/' . $name );

		if ( false !== strpos( $body, $token ) ) {
			return array(
				'label'  => $label,
				'status' => 'warn',
				'detail' => __( 'No: this host serves files from the storage folder (deny rules are ignored). Stored files remain encrypted with random names, so this is acceptable, but consider BLKRM_PORTAL_STORAGE_DIR outside the web root or the cloud storage adapter.', 'blkrm-portal' ),
			);
		}
		return array(
			'label'  => $label,
			'status' => 'ok',
			/* translators: %d: HTTP status */
			'detail' => $code ? sprintf( __( 'Yes (test request answered HTTP %d without the file).', 'blkrm-portal' ), $code ) : __( 'Yes (test request could not reach the file).', 'blkrm-portal' ),
		);
	}
}
