<?php
/**
 * wp-admin screens for BLKRM staff: Organizations, Projects, Settings.
 * (The client-facing experience is the portal itself; these screens are
 * the back office until staff tools move into the portal in later phases.)
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Admin;

use BLKRM\Portal\Auth\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Admin menu + form handlers.
 */
final class AdminMenu {

	/**
	 * Hook in.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		OrgsPage::hooks();
		ProjectsPage::hooks();
		SettingsPage::hooks();
	}

	/**
	 * Register pages.
	 */
	public function menu() {
		add_menu_page(
			__( 'BLKRM Portal', 'blkrm-portal' ),
			__( 'BLKRM Portal', 'blkrm-portal' ),
			Roles::CAP_MANAGE,
			'blkrm-portal',
			array( OrgsPage::class, 'render' ),
			'dashicons-lock',
			3
		);
		add_submenu_page( 'blkrm-portal', __( 'Clients', 'blkrm-portal' ), __( 'Clients', 'blkrm-portal' ), Roles::CAP_MANAGE, 'blkrm-portal', array( OrgsPage::class, 'render' ) );
		add_submenu_page( 'blkrm-portal', __( 'Projects', 'blkrm-portal' ), __( 'Projects', 'blkrm-portal' ), Roles::CAP_MANAGE, 'blkrm-portal-projects', array( ProjectsPage::class, 'render' ) );
		add_submenu_page( 'blkrm-portal', __( 'Settings', 'blkrm-portal' ), __( 'Settings + Storage', 'blkrm-portal' ), Roles::CAP_SETTINGS, 'blkrm-portal-settings', array( SettingsPage::class, 'render' ) );
	}

	/**
	 * Media picker for org logos.
	 *
	 * @param string $hook Screen hook.
	 */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'blkrm-portal' ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'blkrm-portal-admin', BLKRM_PORTAL_URL . 'assets/js/admin.js', array( 'jquery' ), BLKRM_PORTAL_VERSION, true );
		wp_enqueue_style( 'blkrm-portal-admin', BLKRM_PORTAL_URL . 'assets/css/admin.css', array(), BLKRM_PORTAL_VERSION );
	}

	/**
	 * Redirect back to an admin page with a notice code.
	 *
	 * @param string $page   Page slug.
	 * @param array  $args   Extra query args.
	 * @param string $notice Notice text.
	 * @param string $type   success|error.
	 */
	public static function back( $page, array $args, $notice, $type = 'success' ) {
		set_transient( 'blkrm_admin_notice_' . get_current_user_id(), array( 'text' => $notice, 'type' => $type ), 60 );
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Print a pending notice.
	 */
	public static function notice() {
		$key    = 'blkrm_admin_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			'error' === $notice['type'] ? 'error' : 'success',
			esc_html( $notice['text'] )
		);
	}
}
