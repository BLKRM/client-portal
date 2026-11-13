<?php
/**
 * Project CRUD in admin.
 *
 * @package WPAgencyHub\Admin
 */

namespace WPAgencyHub\Admin;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Project management admin page.
 */
class ProjectManager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_wpah_save_project', array( $this, 'handle_save' ) );
		add_action( 'admin_post_wpah_delete_project', array( $this, 'handle_delete' ) );
	}

	/**
	 * Register submenu page.
	 */
	public function register_menu() {
		add_submenu_page(
			'agency-hub',
			__( 'Projects', 'wp-agency-hub' ),
			__( 'Projects', 'wp-agency-hub' ),
			'manage_options',
			'agency-hub-projects',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the projects admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wp-agency-hub' ) );
		}

		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display.
		$action     = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$project_id = isset( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0;

		if ( 'edit' === $action || 'new' === $action ) {
			$project = null;
			if ( $project_id ) {
				$project = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}ah_projects WHERE id = %d",
						$project_id
					)
				);
			}

			$clients = $wpdb->get_results(
				"SELECT c.id, c.company_name, u.display_name
				FROM {$wpdb->prefix}ah_clients c
				LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
				ORDER BY c.company_name ASC"
			);

			$milestones = array();
			if ( $project_id ) {
				$milestones = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}ah_milestones WHERE project_id = %d ORDER BY sort_order ASC",
						$project_id
					)
				);
			}

			include WPAH_PLUGIN_DIR . 'templates/admin/project-edit.php';
			return;
		}

		$projects = $wpdb->get_results(
			"SELECT p.*, c.company_name
			FROM {$wpdb->prefix}ah_projects p
			LEFT JOIN {$wpdb->prefix}ah_clients c ON p.client_id = c.id
			ORDER BY p.updated_at DESC"
		);

		include WPAH_PLUGIN_DIR . 'templates/admin/projects.php';
	}

	/**
	 * Handle project save (create/update).
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wp-agency-hub' ) );
		}

		check_admin_referer( 'wpah_save_project' );

		global $wpdb;

		$project_id  = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		$client_id   = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;
		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'discovery';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$due_date    = isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : null;

		$valid_statuses = array( 'discovery', 'in_progress', 'review', 'complete' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			$status = 'discovery';
		}

		$data = array(
			'client_id'   => $client_id,
			'name'        => $name,
			'status'      => $status,
			'description' => $description,
			'due_date'    => $due_date ?: null,
			'updated_at'  => current_time( 'mysql' ),
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s' );

		if ( $project_id ) {
			$wpdb->update(
				$wpdb->prefix . 'ah_projects',
				$data,
				array( 'id' => $project_id ),
				$format,
				array( '%d' )
			);

			Plugin::log_activity( $project_id, get_current_user_id(), 'project_updated', wp_json_encode( $data ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$format[]           = '%s';

			$wpdb->insert( $wpdb->prefix . 'ah_projects', $data, $format );
			$project_id = $wpdb->insert_id;

			Plugin::log_activity( $project_id, get_current_user_id(), 'project_created', $name );
		}

		// Save milestones.
		$milestone_names  = isset( $_POST['milestone_name'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['milestone_name'] ) ) : array();
		$milestone_dates  = isset( $_POST['milestone_due'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['milestone_due'] ) ) : array();
		$milestone_ids    = isset( $_POST['milestone_id'] ) ? array_map( 'absint', $_POST['milestone_id'] ) : array();
		$milestone_status = isset( $_POST['milestone_status'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['milestone_status'] ) ) : array();

		// Delete removed milestones.
		$existing_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ah_milestones WHERE project_id = %d",
				$project_id
			)
		);

		$keep_ids   = array_filter( $milestone_ids );
		$remove_ids = array_diff( $existing_ids, $keep_ids );
		foreach ( $remove_ids as $remove_id ) {
			$wpdb->delete(
				$wpdb->prefix . 'ah_milestones',
				array( 'id' => $remove_id ),
				array( '%d' )
			);
		}

		// Insert or update milestones.
		foreach ( $milestone_names as $i => $m_name ) {
			if ( empty( $m_name ) ) {
				continue;
			}

			$m_id     = isset( $milestone_ids[ $i ] ) ? absint( $milestone_ids[ $i ] ) : 0;
			$m_due    = isset( $milestone_dates[ $i ] ) ? $milestone_dates[ $i ] : null;
			$m_status = isset( $milestone_status[ $i ] ) ? $milestone_status[ $i ] : 'pending';
			$m_order  = $i;

			$m_data = array(
				'project_id' => $project_id,
				'name'       => $m_name,
				'status'     => $m_status,
				'due_date'   => $m_due ?: null,
				'sort_order' => $m_order,
			);

			if ( $m_id ) {
				$wpdb->update(
					$wpdb->prefix . 'ah_milestones',
					$m_data,
					array( 'id' => $m_id ),
					array( '%d', '%s', '%s', '%s', '%d' ),
					array( '%d' )
				);
			} else {
				$wpdb->insert(
					$wpdb->prefix . 'ah_milestones',
					$m_data,
					array( '%d', '%s', '%s', '%s', '%d' )
				);
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=agency-hub-projects&updated=1' ) );
		exit;
	}

	/**
	 * Handle project deletion.
	 */
	public function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wp-agency-hub' ) );
		}

		check_admin_referer( 'wpah_delete_project' );

		global $wpdb;

		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;

		if ( $project_id ) {
			// Cascade delete related data.
			$wpdb->delete( $wpdb->prefix . 'ah_milestones', array( 'project_id' => $project_id ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . 'ah_files', array( 'project_id' => $project_id ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . 'ah_approvals', array( 'project_id' => $project_id ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . 'ah_messages', array( 'project_id' => $project_id ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . 'ah_activity_log', array( 'project_id' => $project_id ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . 'ah_projects', array( 'id' => $project_id ), array( '%d' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=agency-hub-projects&deleted=1' ) );
		exit;
	}
}
