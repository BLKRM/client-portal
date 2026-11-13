<?php
/**
 * Plugin Name:       BLKRM Client Portal
 * Plugin URI:        https://blkrm.com/
 * Description:       Password-protected client workspace for Black Room Creative: projects, files, approvals and requests, branded BLKRM.
 * Version:           0.1.1
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Black Room Creative
 * Author URI:        https://blkrm.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blkrm-portal
 * Domain Path:       /languages
 *
 * Forked from WP Agency Hub by Seth Shoultes (GPL-2.0-or-later),
 * https://github.com/sethshoultes/wp-agency-hub
 *
 * @package BLKRM\Portal
 */

defined( 'ABSPATH' ) || exit;

define( 'BLKRM_PORTAL_VERSION', '0.1.1' );
define( 'BLKRM_PORTAL_FILE', __FILE__ );
define( 'BLKRM_PORTAL_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLKRM_PORTAL_URL', plugin_dir_url( __FILE__ ) );

/*
 * PSR-4 autoloader for BLKRM\Portal\ → src/. Composer is only needed for
 * development tools, so a plain zip of this folder installs and runs.
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'BLKRM\\Portal\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = BLKRM_PORTAL_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, array( \BLKRM\Portal\Install\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \BLKRM\Portal\Install\Activator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\BLKRM\Portal\Plugin::instance()->boot();
	}
);
