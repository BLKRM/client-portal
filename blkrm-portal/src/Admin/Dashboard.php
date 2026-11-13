<?php
/**
 * Agency admin dashboard.
 *
 * @package WPAgencyHub\Admin
 */

namespace WPAgencyHub\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard page in wp-admin.
 */
class Dashboard {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
	}

	/**
	 * Register admin menus.
	 */
	public function register_menus() {
		add_menu_page(
			__( 'Agency Hub', 'wp-agency-hub' ),
			__( 'Agency Hub', 'wp-agency-hub' ),
			'manage_options',
			'agency-hub',
			array( $this, 'render_dashboard' ),
			'dashicons-groups',
			30
		);

		add_submenu_page(
			'agency-hub',
			__( 'Dashboard', 'wp-agency-hub' ),
			__( 'Dashboard', 'wp-agency-hub' ),
			'manage_options',
			'agency-hub',
			array( $this, 'render_dashboard' )
		);
	}

	/**
	 * Render the admin dashboard.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wp-agency-hub' ) );
		}

		global $wpdb;

		$client_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ah_clients" );
		$project_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ah_projects" );
		$pending_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ah_approvals WHERE status = %s",
				'pending_review'
			)
		);
		$message_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ah_messages WHERE read_at IS NULL"
		);

		$recent_activity = $wpdb->get_results(
			"SELECT a.*, p.name AS project_name, u.display_name AS user_name
			FROM {$wpdb->prefix}ah_activity_log a
			LEFT JOIN {$wpdb->prefix}ah_projects p ON a.project_id = p.id
			LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
			ORDER BY a.created_at DESC
			LIMIT 20"
		);

		include WPAH_PLUGIN_DIR . 'templates/admin/dashboard.php';
	}
}
