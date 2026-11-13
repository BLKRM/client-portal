<?php
/**
 * Client CRUD in admin.
 *
 * @package WPAgencyHub\Admin
 */

namespace WPAgencyHub\Admin;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Client management admin page.
 */
class ClientManager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_wpah_save_client', array( $this, 'handle_save' ) );
		add_action( 'admin_post_wpah_delete_client', array( $this, 'handle_delete' ) );
	}

	/**
	 * Register submenu page.
	 */
	public function register_menu() {
		add_submenu_page(
			'agency-hub',
			__( 'Clients', 'wp-agency-hub' ),
			__( 'Clients', 'wp-agency-hub' ),
			'manage_options',
			'agency-hub-clients',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the clients admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wp-agency-hub' ) );
		}

		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display.
		$action    = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$client_id = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;

		if ( 'edit' === $action || 'new' === $action ) {
			$client = null;
			if ( $client_id ) {
				$client = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT c.*, u.user_login, u.user_email, u.display_name
						FROM {$wpdb->prefix}ah_clients c
						LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
						WHERE c.id = %d",
						$client_id
					)
				);
			}

			include WPAH_PLUGIN_DIR . 'templates/admin/client-edit.php';
			return;
		}

		$clients = $wpdb->get_results(
			"SELECT c.*, u.user_email, u.display_name,
				(SELECT COUNT(*) FROM {$wpdb->prefix}ah_projects WHERE client_id = c.id) AS project_count
			FROM {$wpdb->prefix}ah_clients c
			LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
			ORDER BY c.company_name ASC"
		);

		include WPAH_PLUGIN_DIR . 'templates/admin/clients.php';
	}

	/**
	 * Handle client save (create/update).
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wp-agency-hub' ) );
		}

		check_admin_referer( 'wpah_save_client' );

		global $wpdb;

		$client_id    = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;
		$company_name = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
		$email        = isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';
		$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';

		if ( empty( $email ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=agency-hub-clients&error=email_required' ) );
			exit;
		}

		if ( $client_id ) {
			// Update existing client.
			$client = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ah_clients WHERE id = %d",
					$client_id
				)
			);

			if ( ! $client ) {
				wp_die( esc_html__( 'Client not found.', 'wp-agency-hub' ) );
			}

			$wpdb->update(
				$wpdb->prefix . 'ah_clients',
				array(
					'company_name'  => $company_name,
					'contact_email' => $email,
				),
				array( 'id' => $client_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			// Update WP user.
			wp_update_user(
				array(
					'ID'           => $client->user_id,
					'user_email'   => $email,
					'display_name' => $display_name ?: $company_name,
				)
			);
		} else {
			// Create WP user with ah_client role.
			$username = sanitize_user( strtolower( str_replace( ' ', '', $company_name ) ) . '_' . wp_rand( 100, 999 ) );
			$password = wp_generate_password( 16, true, true );

			$user_id = wp_insert_user(
				array(
					'user_login'   => $username,
					'user_email'   => $email,
					'user_pass'    => $password,
					'display_name' => $display_name ?: $company_name,
					'role'         => 'ah_client',
				)
			);

			if ( is_wp_error( $user_id ) ) {
				wp_safe_redirect(
					admin_url( 'admin.php?page=agency-hub-clients&error=' . rawurlencode( $user_id->get_error_message() ) )
				);
				exit;
			}

			$wpdb->insert(
				$wpdb->prefix . 'ah_clients',
				array(
					'user_id'       => $user_id,
					'company_name'  => $company_name,
					'contact_email' => $email,
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s' )
			);

			// Send credentials email.
			$branding   = Plugin::get_branding();
			$portal_url = home_url( '/' . $branding->portal_slug . '/' );

			wp_mail(
				$email,
				/* translators: %s: site name */
				sprintf( __( 'Your client portal login — %s', 'wp-agency-hub' ), get_bloginfo( 'name' ) ),
				sprintf(
					/* translators: 1: display name, 2: portal URL, 3: username, 4: password */
					__(
						"Hello %1\$s,\n\nYour client portal is ready:\n%2\$s\n\nUsername: %3\$s\nPassword: %4\$s\n\nPlease change your password after your first login.\n\nBest regards",
						'wp-agency-hub'
					),
					$display_name ?: $company_name,
					$portal_url,
					$username,
					$password
				)
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=agency-hub-clients&updated=1' ) );
		exit;
	}

	/**
	 * Handle client deletion.
	 */
	public function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wp-agency-hub' ) );
		}

		check_admin_referer( 'wpah_delete_client' );

		global $wpdb;

		$client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

		if ( $client_id ) {
			$client = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ah_clients WHERE id = %d",
					$client_id
				)
			);

			if ( $client ) {
				// Delete related projects and cascade data.
				$project_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT id FROM {$wpdb->prefix}ah_projects WHERE client_id = %d",
						$client_id
					)
				);

				foreach ( $project_ids as $pid ) {
					$wpdb->delete( $wpdb->prefix . 'ah_milestones', array( 'project_id' => $pid ), array( '%d' ) );
					$wpdb->delete( $wpdb->prefix . 'ah_files', array( 'project_id' => $pid ), array( '%d' ) );
					$wpdb->delete( $wpdb->prefix . 'ah_approvals', array( 'project_id' => $pid ), array( '%d' ) );
					$wpdb->delete( $wpdb->prefix . 'ah_messages', array( 'project_id' => $pid ), array( '%d' ) );
					$wpdb->delete( $wpdb->prefix . 'ah_activity_log', array( 'project_id' => $pid ), array( '%d' ) );
				}

				$wpdb->delete( $wpdb->prefix . 'ah_projects', array( 'client_id' => $client_id ), array( '%d' ) );
				$wpdb->delete( $wpdb->prefix . 'ah_clients', array( 'id' => $client_id ), array( '%d' ) );

				// Optionally delete WP user.
				if ( isset( $_POST['delete_user'] ) && '1' === $_POST['delete_user'] ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
					wp_delete_user( $client->user_id );
				}
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=agency-hub-clients&deleted=1' ) );
		exit;
	}
}
