<?php
/**
 * Versioned migrations. Each step runs once, in order, and the stored
 * version moves forward only after a step succeeds. Later phases append
 * steps (e.g. approvals, to-dos, requests) without touching earlier ones.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Install;

use BLKRM\Portal\Auth\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Migration runner.
 */
final class Migrator {

	const OPTION = 'blkrm_portal_db_version';

	/**
	 * Ordered list of migrations: version => callable.
	 *
	 * @return array<int, callable>
	 */
	public static function migrations() {
		return array(
			1 => array( __CLASS__, 'migrate_1' ),
		);
	}

	/**
	 * Latest known version.
	 *
	 * @return int
	 */
	public static function latest() {
		$keys = array_keys( self::migrations() );
		return (int) max( $keys );
	}

	/**
	 * Run pending migrations if the stored version is behind.
	 */
	public static function maybe_migrate() {
		$current = (int) get_option( self::OPTION, 0 );
		if ( $current >= self::latest() ) {
			return;
		}
		foreach ( self::migrations() as $version => $callback ) {
			if ( $version <= $current ) {
				continue;
			}
			call_user_func( $callback );
			update_option( self::OPTION, $version, false );
			$current = $version;
		}
	}

	/**
	 * Base tables, roles and capabilities.
	 */
	public static function migrate_1() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( Schema::v1() );
		Roles::install();
	}
}
