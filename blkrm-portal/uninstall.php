<?php
/**
 * WP Agency Hub uninstaller.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 *
 * @package WPAgencyHub
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop all plugin tables (order matters for foreign key safety).
$tables = array(
	'ah_activity_log',
	'ah_messages',
	'ah_approvals',
	'ah_files',
	'ah_milestones',
	'ah_branding',
	'ah_projects',
	'ah_clients',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

// Delete all plugin options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpah\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Remove the ah_client role.
remove_role( 'ah_client' );

// Optionally remove the upload directory.
$upload_dir = wp_upload_dir();
$ah_dir     = $upload_dir['basedir'] . '/ah-files';

if ( is_dir( $ah_dir ) ) {
	// Recursively delete the directory and its contents.
	$iterator = new RecursiveDirectoryIterator( $ah_dir, RecursiveDirectoryIterator::SKIP_DOTS );
	$files    = new RecursiveIteratorIterator( $iterator, RecursiveIteratorIterator::CHILD_FIRST );

	foreach ( $files as $file ) {
		if ( $file->isDir() ) {
			rmdir( $file->getRealPath() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		} else {
			unlink( $file->getRealPath() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}

	rmdir( $ah_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}
