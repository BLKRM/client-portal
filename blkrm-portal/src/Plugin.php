<?php
/**
 * Plugin bootstrap: wires every component to WordPress hooks.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal;

use BLKRM\Portal\Admin\AdminMenu;
use BLKRM\Portal\Auth\Roles;
use BLKRM\Portal\Install\Migrator;
use BLKRM\Portal\Portal\Router;
use BLKRM\Portal\Rest\FileEndpoints;
use BLKRM\Portal\Rest\PreferenceEndpoints;
use BLKRM\Portal\Rest\UploadEndpoints;
use BLKRM\Portal\Files\UploadService;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton entry point.
 */
final class Plugin {

	/** Hourly cron hook that clears abandoned uploads. */
	const CRON_CLEANUP = 'blkrm_portal_cleanup_uploads';

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Booted flag.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks once.
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Run pending schema / role migrations (cheap no-op when current).
		Migrator::maybe_migrate();

		add_action( 'init', array( $this, 'load_textdomain' ) );

		( new Router() )->register();
		( new UploadEndpoints() )->register();
		( new FileEndpoints() )->register();
		( new PreferenceEndpoints() )->register();

		if ( is_admin() ) {
			( new AdminMenu() )->register();
		}

		// Keep clients out of wp-admin and hide the admin bar for them.
		add_action( 'admin_init', array( $this, 'redirect_clients_from_admin' ) );
		add_filter( 'show_admin_bar', array( $this, 'filter_admin_bar' ) );

		add_action( self::CRON_CLEANUP, array( UploadService::class, 'cleanup_expired' ) );
		if ( ! wp_next_scheduled( self::CRON_CLEANUP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_CLEANUP );
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'blkrm-portal', false, dirname( plugin_basename( BLKRM_PORTAL_FILE ) ) . '/languages' );
	}

	/**
	 * Clients never see wp-admin; send them to the portal.
	 */
	public function redirect_clients_from_admin() {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}
		$user = wp_get_current_user();
		if ( Roles::is_client( $user ) && ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_safe_redirect( Router::url() );
			exit;
		}
	}

	/**
	 * Hide the admin bar for clients.
	 *
	 * @param bool $show Current value.
	 * @return bool
	 */
	public function filter_admin_bar( $show ) {
		if ( is_user_logged_in() && Roles::is_client( wp_get_current_user() ) ) {
			return false;
		}
		return $show;
	}
}
