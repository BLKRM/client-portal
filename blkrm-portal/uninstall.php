<?php
/**
 * Uninstall. Removes roles and settings. Client data (tables + stored files)
 * is kept unless "BLKRM_PORTAL_DELETE_DATA" is defined true in wp-config.php,
 * so deleting the plugin by accident never destroys client files.
 *
 * @package BLKRM\Portal
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/src/Auth/Roles.php';
require_once __DIR__ . '/src/Install/Schema.php';

\BLKRM\Portal\Auth\Roles::uninstall();

wp_clear_scheduled_hook( 'blkrm_portal_cleanup_uploads' );

foreach ( array( 'blkrm_portal_slug', 'blkrm_portal_rewrites' ) as $option ) {
	delete_option( $option );
}

if ( defined( 'BLKRM_PORTAL_DELETE_DATA' ) && true === BLKRM_PORTAL_DELETE_DATA ) {
	global $wpdb;
	foreach ( \BLKRM\Portal\Install\Schema::tables() as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}
	$dir = (string) get_option( 'blkrm_portal_storage_dir', '' );
	if ( '' !== $dir && is_dir( $dir ) && false !== strpos( basename( $dir ), 'blkrm' ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() ); // phpcs:ignore
		}
		rmdir( $dir ); // phpcs:ignore
	}
	foreach ( array( 'blkrm_portal_db_version', 'blkrm_portal_storage_dir', 'blkrm_portal_master_key' ) as $option ) {
		delete_option( $option );
	}
	delete_metadata( 'user', 0, 'blkrm_portal_theme', '', true );
}
