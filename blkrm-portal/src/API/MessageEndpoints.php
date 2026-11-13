<?php
/**
 * Message CRUD REST endpoints.
 *
 * @package WPAgencyHub\API
 */

namespace WPAgencyHub\API;

use WPAgencyHub\Plugin;
use WPAgencyHub\Notifications\EmailNotifier;

defined( 'ABSPATH' ) || exit;

/**
 * Message REST endpoints.
 */
class MessageEndpoints extends RestController {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/projects/(?P<project_id>\d+)/messages',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_messages' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'send_message' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);
	}

	/**
	 * List messages for a project.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_messages( $request ) {
		global $wpdb;

		$project_id = absint( $request['project_id'] );
		$access     = $this->verify_project_access( $project_id );

		if ( is_wp_error( $access ) ) {
			return $access;
		}

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, u.display_name AS sender_name
				FROM {$wpdb->prefix}ah_messages m
				LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
				WHERE m.project_id = %d
				ORDER BY m.created_at ASC",
				$project_id
			)
		);

		return $this->success( $messages );
	}

	/**
	 * Send a message to a project thread.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function send_message( $request ) {
		global $wpdb;

		$project_id = absint( $request['project_id'] );
		$access     = $this->verify_project_access( $project_id );

		if ( is_wp_error( $access ) ) {
			return $access;
		}

		$message = sanitize_textarea_field( $request->get_param( 'message' ) );

		if ( empty( $message ) ) {
			return $this->error( __( 'Message cannot be empty.', 'wp-agency-hub' ) );
		}

		$attachment_id = absint( $request->get_param( 'attachment_id' ) );

		$wpdb->insert(
			$wpdb->prefix . 'ah_messages',
			array(
				'project_id'    => $project_id,
				'sender_id'     => get_current_user_id(),
				'message'       => $message,
				'attachment_id' => $attachment_id ?: null,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s' )
		);

		$message_id = $wpdb->insert_id;

		Plugin::log_activity(
			$project_id,
			get_current_user_id(),
			'message_posted',
			wp_json_encode( array( 'message_id' => $message_id ) )
		);

		// Get project details for notification.
		$project = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.*, c.user_id AS client_user_id, c.contact_email AS client_email
				FROM {$wpdb->prefix}ah_projects p
				LEFT JOIN {$wpdb->prefix}ah_clients c ON p.client_id = c.id
				WHERE p.id = %d",
				$project_id
			)
		);

		if ( $project ) {
			$sender = wp_get_current_user();

			// If sender is the client, notify admin. If sender is admin, notify client.
			if ( (int) $sender->ID === (int) $project->client_user_id ) {
				$admin_email = get_option( 'admin_email' );
				EmailNotifier::send_new_message_notification( $admin_email, $project, $sender->display_name, $message );
			} else {
				EmailNotifier::send_new_message_notification( $project->client_email, $project, $sender->display_name, $message );
			}
		}

		return $this->success( array( 'message_id' => $message_id ) );
	}
}
