<?php
/**
 * Main plugin class.
 *
 * @package WPAgencyHub
 */

namespace WPAgencyHub;

use WPAgencyHub\Admin\Dashboard;
use WPAgencyHub\Admin\ProjectManager;
use WPAgencyHub\Admin\ClientManager;
use WPAgencyHub\Admin\SettingsManager;
use WPAgencyHub\Portal\PortalRouter;
use WPAgencyHub\Portal\PortalTemplate;
use WPAgencyHub\API\ProjectEndpoints;
use WPAgencyHub\API\FileEndpoints;
use WPAgencyHub\API\MessageEndpoints;
use WPAgencyHub\API\ApprovalEndpoints;
use WPAgencyHub\Notifications\EmailNotifier;
use WPAgencyHub\WhiteLabel\BrandingManager;
use WPAgencyHub\WhiteLabel\LoginCustomizer;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin singleton.
 */
class Plugin {

	/**
	 * Database version for schema migrations.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — wire up all hooks.
	 */
	private function __construct() {
		$this->register_role();
		$this->init_components();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_portal_assets' ) );
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-agency-hub', false, dirname( plugin_basename( WPAH_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Register the ah_client role on init.
	 */
	private function register_role() {
		add_action(
			'init',
			function () {
				if ( ! get_role( 'ah_client' ) ) {
					add_role(
						'ah_client',
						__( 'Agency Client', 'wp-agency-hub' ),
						array(
							'read' => true,
						)
					);
				}
			}
		);
	}

	/**
	 * Initialize all components.
	 */
	private function init_components() {
		// Admin.
		new Dashboard();
		new ProjectManager();
		new ClientManager();
		new SettingsManager();

		// Portal.
		new PortalRouter();
		new PortalTemplate();

		// REST API.
		new ProjectEndpoints();
		new FileEndpoints();
		new MessageEndpoints();
		new ApprovalEndpoints();

		// Notifications.
		new EmailNotifier();

		// White-label.
		new BrandingManager();
		new LoginCustomizer();
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( $hook, 'agency-hub' ) ) {
			return;
		}

		wp_enqueue_style(
			'wpah-admin',
			WPAH_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WPAH_VERSION
		);

		wp_enqueue_script(
			'wpah-admin',
			WPAH_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			WPAH_VERSION,
			true
		);

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();

		wp_localize_script(
			'wpah-admin',
			'wpah_admin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'rest_url' => rest_url( 'wp-agency-hub/v1/' ),
				'nonce'    => wp_create_nonce( 'wpah_admin_nonce' ),
			)
		);
	}

	/**
	 * Enqueue portal assets.
	 */
	public function enqueue_portal_assets() {
		if ( ! get_query_var( 'ah_portal' ) ) {
			return;
		}

		wp_enqueue_style(
			'wpah-portal',
			WPAH_PLUGIN_URL . 'assets/css/portal.css',
			array(),
			WPAH_VERSION
		);

		wp_enqueue_script(
			'wpah-portal',
			WPAH_PLUGIN_URL . 'assets/js/portal.js',
			array( 'jquery' ),
			WPAH_VERSION,
			true
		);

		wp_localize_script(
			'wpah-portal',
			'wpah_portal',
			array(
				'rest_url' => rest_url( 'wp-agency-hub/v1/' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'max_file' => WPAH_MAX_FILE_SIZE,
			)
		);
	}

	/**
	 * Plugin activation — create tables and flush rewrite rules.
	 */
	public static function activate() {
		self::create_tables();
		self::create_upload_dir();

		// Register rewrite rules and flush.
		$router = new PortalRouter();
		$router->register_rewrites();
		flush_rewrite_rules();

		// Ensure role exists.
		if ( ! get_role( 'ah_client' ) ) {
			add_role(
				'ah_client',
				__( 'Agency Client', 'wp-agency-hub' ),
				array( 'read' => true )
			);
		}

		update_option( 'wpah_db_version', self::DB_VERSION );
	}

	/**
	 * Plugin deactivation — flush rewrite rules.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		$sql = "
		CREATE TABLE {$wpdb->prefix}ah_clients (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			company_name varchar(255) NOT NULL DEFAULT '',
			contact_email varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id)
		) {$charset};

		CREATE TABLE {$wpdb->prefix}ah_projects (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL,
			name varchar(255) NOT NULL DEFAULT '',
			status varchar(30) NOT NULL DEFAULT 'discovery',
			description longtext NOT NULL DEFAULT '',
			due_date date DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY client_id (client_id),
			KEY status (status)
		) {$charset};

		CREATE TABLE {$wpdb->prefix}ah_milestones (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL,
			name varchar(255) NOT NULL DEFAULT '',
			status varchar(30) NOT NULL DEFAULT 'pending',
			due_date date DEFAULT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY project_id (project_id)
		) {$charset};

		CREATE TABLE {$wpdb->prefix}ah_files (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL,
			uploaded_by bigint(20) unsigned NOT NULL,
			file_name varchar(255) NOT NULL DEFAULT '',
			file_path text NOT NULL,
			file_size bigint(20) unsigned NOT NULL DEFAULT 0,
			mime_type varchar(100) NOT NULL DEFAULT '',
			version int(11) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY project_id (project_id)
		) {$charset};

		CREATE TABLE {$wpdb->prefix}ah_approvals (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL,
			file_id bigint(20) unsigned DEFAULT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			status varchar(30) NOT NULL DEFAULT 'draft',
			requested_by bigint(20) unsigned NOT NULL,
			responded_by bigint(20) unsigned DEFAULT NULL,
			response_note longtext NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			responded_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY project_id (project_id),
			KEY status (status)
		) {$charset};

		CREATE TABLE {$wpdb->prefix}ah_messages (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL,
			sender_id bigint(20) unsigned NOT NULL,
			message longtext NOT NULL DEFAULT '',
			attachment_id bigint(20) unsigned DEFAULT NULL,
			read_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY project_id (project_id),
			KEY sender_id (sender_id)
		) {$charset};

		CREATE TABLE {$wpdb->prefix}ah_activity_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			action varchar(100) NOT NULL DEFAULT '',
			details longtext NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY project_id (project_id),
			KEY created_at (created_at)
		) {$charset};

		CREATE TABLE {$wpdb->prefix}ah_branding (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			logo_url text NOT NULL DEFAULT '',
			primary_color varchar(7) NOT NULL DEFAULT '#007AFF',
			secondary_color varchar(7) NOT NULL DEFAULT '#5856D6',
			custom_css longtext NOT NULL DEFAULT '',
			portal_slug varchar(255) NOT NULL DEFAULT 'client-portal',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) {$charset};
		";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create secure upload directory with .htaccess protection.
	 */
	private static function create_upload_dir() {
		$upload_dir = wp_upload_dir();
		$ah_dir     = $upload_dir['basedir'] . '/' . WPAH_UPLOAD_DIR;

		if ( ! file_exists( $ah_dir ) ) {
			wp_mkdir_p( $ah_dir );
		}

		// Deny direct access.
		$htaccess = $ah_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		// Index file.
		$index = $ah_dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Get branding settings from database.
	 *
	 * @return object
	 */
	public static function get_branding() {
		$cached = wp_cache_get( 'wpah_branding' );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ah_branding ORDER BY id ASC LIMIT %d",
				1
			)
		);

		if ( ! $row ) {
			$row = (object) array(
				'id'              => 0,
				'logo_url'        => '',
				'primary_color'   => '#007AFF',
				'secondary_color' => '#5856D6',
				'custom_css'      => '',
				'portal_slug'     => 'client-portal',
			);
		}

		wp_cache_set( 'wpah_branding', $row );

		return $row;
	}

	/**
	 * Get the client record for a given WP user ID.
	 *
	 * @param int $user_id WP user ID.
	 * @return object|null
	 */
	public static function get_client_by_user( $user_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ah_clients WHERE user_id = %d",
				$user_id
			)
		);
	}

	/**
	 * Check if a project belongs to a client.
	 *
	 * @param int $project_id Project ID.
	 * @param int $client_id  Client ID.
	 * @return bool
	 */
	public static function project_belongs_to_client( $project_id, $client_id ) {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ah_projects WHERE id = %d AND client_id = %d",
				$project_id,
				$client_id
			)
		);
	}

	/**
	 * Log an activity entry.
	 *
	 * @param int    $project_id Project ID.
	 * @param int    $user_id    User performing the action.
	 * @param string $action     Action identifier.
	 * @param string $details    JSON or text details.
	 */
	public static function log_activity( $project_id, $user_id, $action, $details = '' ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'ah_activity_log',
			array(
				'project_id' => $project_id,
				'user_id'    => $user_id,
				'action'     => $action,
				'details'    => $details,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
	}
}
