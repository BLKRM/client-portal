<?php
/**
 * Activation / deactivation.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Install;

use BLKRM\Portal\Plugin;
use BLKRM\Portal\Portal\Router;
use BLKRM\Portal\Storage\KeyProvider;
use BLKRM\Portal\Storage\LocalPrivateAdapter;

defined( 'ABSPATH' ) || exit;

/**
 * Lifecycle hooks.
 */
final class Activator {

	/**
	 * Activate: schema, roles, encryption key, private storage, rewrites.
	 */
	public static function activate() {
		Migrator::maybe_migrate();
		KeyProvider::ensure_key();
		LocalPrivateAdapter::ensure_base_dir();

		( new Router() )->add_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Deactivate: unschedule cron and flush rewrites. Data stays.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( Plugin::CRON_CLEANUP );
		flush_rewrite_rules();
	}
}
