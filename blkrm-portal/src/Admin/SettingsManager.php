<?php
/**
 * Settings manager for white-label configuration.
 *
 * @package WPAgencyHub\Admin
 */

namespace WPAgencyHub\Admin;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page in wp-admin.
 */
class SettingsManager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_wpah_save_settings', array( $this, 'handle_save' ) );
	}

	/**
	 * Register submenu page.
	 */
	public function register_menu() {
		add_submenu_page(
			'agency-hub',
			__( 'Settings', 'wp-agency-hub' ),
			__( 'Settings', 'wp-agency-hub' ),
			'manage_options',
			'agency-hub-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wp-agency-hub' ) );
		}

		$branding = Plugin::get_branding();

		include WPAH_PLUGIN_DIR . 'templates/admin/settings.php';
	}

	/**
	 * Handle settings save.
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wp-agency-hub' ) );
		}

		check_admin_referer( 'wpah_save_settings' );

		global $wpdb;

		$logo_url        = isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '';
		$primary_color   = isset( $_POST['primary_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['primary_color'] ) ) : '#007AFF';
		$secondary_color = isset( $_POST['secondary_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['secondary_color'] ) ) : '#5856D6';
		$custom_css      = isset( $_POST['custom_css'] ) ? wp_strip_all_tags( wp_unslash( $_POST['custom_css'] ) ) : '';
		$portal_slug     = isset( $_POST['portal_slug'] ) ? sanitize_title( wp_unslash( $_POST['portal_slug'] ) ) : 'client-portal';

		if ( empty( $primary_color ) ) {
			$primary_color = '#007AFF';
		}
		if ( empty( $secondary_color ) ) {
			$secondary_color = '#5856D6';
		}

		$data = array(
			'logo_url'        => $logo_url,
			'primary_color'   => $primary_color,
			'secondary_color' => $secondary_color,
			'custom_css'      => $custom_css,
			'portal_slug'     => $portal_slug,
			'updated_at'      => current_time( 'mysql' ),
		);

		$existing = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}ah_branding LIMIT 1" );

		if ( $existing ) {
			$wpdb->update(
				$wpdb->prefix . 'ah_branding',
				$data,
				array( 'id' => $existing ),
				array( '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert(
				$wpdb->prefix . 'ah_branding',
				$data,
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		// Invalidate branding cache.
		wp_cache_delete( 'wpah_branding' );

		// Flush rewrite rules since portal slug may have changed.
		flush_rewrite_rules();

		wp_safe_redirect( admin_url( 'admin.php?page=agency-hub-settings&updated=1' ) );
		exit;
	}
}
