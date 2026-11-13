<?php
/**
 * Plugin Name: WP Agency Hub
 * Plugin URI:  https://smartwebutah.com/wp-agency-hub
 * Description: White-label client portal for agencies. Project management, file sharing, approvals, and messaging — all branded as your own.
 * Version:     1.0.0
 * Author:      SmartWeb Utah
 * Author URI:  https://smartwebutah.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-agency-hub
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package WPAgencyHub
 */

defined( 'ABSPATH' ) || exit;

define( 'WPAH_VERSION', '1.0.0' );
define( 'WPAH_PLUGIN_FILE', __FILE__ );
define( 'WPAH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAH_UPLOAD_DIR', 'ah-files' );
define( 'WPAH_MAX_FILE_SIZE', 50 * 1024 * 1024 ); // 50MB

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Boot the plugin.
 */
function wpah_init() {
	return \WPAgencyHub\Plugin::get_instance();
}

add_action( 'plugins_loaded', 'wpah_init' );

register_activation_hook( __FILE__, array( \WPAgencyHub\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \WPAgencyHub\Plugin::class, 'deactivate' ) );
