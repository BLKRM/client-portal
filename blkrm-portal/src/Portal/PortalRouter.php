<?php
/**
 * Custom rewrite rules for the client portal.
 *
 * @package WPAgencyHub\Portal
 */

namespace WPAgencyHub\Portal;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers portal rewrite rules and routes requests.
 */
class PortalRouter {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_rewrites' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'route_request' ) );
	}

	/**
	 * Register rewrite rules for all portal routes.
	 */
	public function register_rewrites() {
		$branding = Plugin::get_branding();
		$slug     = trim( $branding->portal_slug, '/' );

		if ( empty( $slug ) ) {
			$slug = 'client-portal';
		}

		// Login.
		add_rewrite_rule(
			'^' . $slug . '/login/?$',
			'index.php?ah_portal=login',
			'top'
		);

		// Dashboard.
		add_rewrite_rule(
			'^' . $slug . '/?$',
			'index.php?ah_portal=dashboard',
			'top'
		);

		// Project detail.
		add_rewrite_rule(
			'^' . $slug . '/project/([0-9]+)/?$',
			'index.php?ah_portal=project&ah_project_id=$matches[1]',
			'top'
		);

		// Project files.
		add_rewrite_rule(
			'^' . $slug . '/project/([0-9]+)/files/?$',
			'index.php?ah_portal=files&ah_project_id=$matches[1]',
			'top'
		);

		// Project messages.
		add_rewrite_rule(
			'^' . $slug . '/project/([0-9]+)/messages/?$',
			'index.php?ah_portal=messages&ah_project_id=$matches[1]',
			'top'
		);

		// Approval.
		add_rewrite_rule(
			'^' . $slug . '/project/([0-9]+)/approval/([0-9]+)/?$',
			'index.php?ah_portal=approval&ah_project_id=$matches[1]&ah_approval_id=$matches[2]',
			'top'
		);

		// Logout.
		add_rewrite_rule(
			'^' . $slug . '/logout/?$',
			'index.php?ah_portal=logout',
			'top'
		);
	}

	/**
	 * Add custom query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'ah_portal';
		$vars[] = 'ah_project_id';
		$vars[] = 'ah_approval_id';
		return $vars;
	}

	/**
	 * Route portal requests to the correct handler.
	 */
	public function route_request() {
		$portal_page = get_query_var( 'ah_portal' );

		if ( empty( $portal_page ) ) {
			return;
		}

		// Handle logout.
		if ( 'logout' === $portal_page ) {
			wp_logout();
			$branding = Plugin::get_branding();
			wp_safe_redirect( home_url( '/' . $branding->portal_slug . '/login/' ) );
			exit;
		}

		// Handle login page (accessible when not logged in).
		if ( 'login' === $portal_page ) {
			$this->render_login();
			exit;
		}

		// All other pages require authentication.
		if ( ! is_user_logged_in() ) {
			$branding = Plugin::get_branding();
			wp_safe_redirect( home_url( '/' . $branding->portal_slug . '/login/' ) );
			exit;
		}

		$user = wp_get_current_user();

		// Admins can access the portal for previewing.
		$is_client = in_array( 'ah_client', (array) $user->roles, true );
		$is_admin  = current_user_can( 'manage_options' );

		if ( ! $is_client && ! $is_admin ) {
			wp_die( esc_html__( 'You do not have permission to access the client portal.', 'wp-agency-hub' ) );
		}

		// Get the client record for data isolation.
		$client = null;
		if ( $is_client ) {
			$client = Plugin::get_client_by_user( $user->ID );
			if ( ! $client ) {
				wp_die( esc_html__( 'Client account not found. Contact your agency.', 'wp-agency-hub' ) );
			}
		}

		$project_id  = absint( get_query_var( 'ah_project_id' ) );
		$approval_id = absint( get_query_var( 'ah_approval_id' ) );

		// Enforce client data isolation on project pages.
		if ( $project_id && $is_client ) {
			if ( ! Plugin::project_belongs_to_client( $project_id, $client->id ) ) {
				wp_die( esc_html__( 'You do not have permission to view this project.', 'wp-agency-hub' ) );
			}
		}

		switch ( $portal_page ) {
			case 'dashboard':
				$handler = new ClientDashboard();
				$handler->render( $client, $is_admin );
				break;

			case 'project':
				$handler = new ProjectView();
				$handler->render( $project_id, $client, $is_admin );
				break;

			case 'files':
				$handler = new FileManager();
				$handler->render( $project_id, $client, $is_admin );
				break;

			case 'messages':
				$handler = new MessageThread();
				$handler->render( $project_id, $client, $is_admin );
				break;

			case 'approval':
				$handler = new ApprovalFlow();
				$handler->render( $project_id, $approval_id, $client, $is_admin );
				break;

			default:
				wp_die( esc_html__( 'Page not found.', 'wp-agency-hub' ) );
		}

		exit;
	}

	/**
	 * Render the branded login page.
	 */
	private function render_login() {
		// If already logged in, redirect to dashboard.
		if ( is_user_logged_in() ) {
			$user      = wp_get_current_user();
			$is_client = in_array( 'ah_client', (array) $user->roles, true );
			$is_admin  = current_user_can( 'manage_options' );

			if ( $is_client || $is_admin ) {
				$branding = Plugin::get_branding();
				wp_safe_redirect( home_url( '/' . $branding->portal_slug . '/' ) );
				exit;
			}
		}

		// Handle login form submission.
		$error = '';
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wpah_portal_login' ) ) {
				$error = __( 'Security check failed. Please try again.', 'wp-agency-hub' );
			} else {
				$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
				$password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords must not be sanitized.

				$user = wp_signon(
					array(
						'user_login'    => $username,
						'user_password' => $password,
						'remember'      => true,
					)
				);

				if ( is_wp_error( $user ) ) {
					$error = __( 'Invalid username or password.', 'wp-agency-hub' );
				} else {
					$branding = Plugin::get_branding();
					wp_safe_redirect( home_url( '/' . $branding->portal_slug . '/' ) );
					exit;
				}
			}
		}

		$branding = Plugin::get_branding();

		include WPAH_PLUGIN_DIR . 'templates/portal/login.php';
	}
}
