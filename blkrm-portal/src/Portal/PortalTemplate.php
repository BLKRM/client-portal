<?php
/**
 * Template loader for portal pages.
 *
 * @package WPAgencyHub\Portal
 */

namespace WPAgencyHub\Portal;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Provides shared template rendering functions for portal pages.
 */
class PortalTemplate {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// No hooks needed — used as utility by portal handlers.
	}

	/**
	 * Render the portal header.
	 *
	 * @param string      $page_title Current page title.
	 * @param object|null $client     Client record (null for admins).
	 */
	public static function header( $page_title = '', $client = null ) {
		$branding = Plugin::get_branding();
		$user     = wp_get_current_user();
		include WPAH_PLUGIN_DIR . 'templates/portal/partials/header.php';
	}

	/**
	 * Render the portal footer.
	 */
	public static function footer() {
		include WPAH_PLUGIN_DIR . 'templates/portal/partials/footer.php';
	}

	/**
	 * Generate portal URL for a given page.
	 *
	 * @param string $path Path relative to portal slug.
	 * @return string
	 */
	public static function portal_url( $path = '' ) {
		$branding = Plugin::get_branding();
		$base     = home_url( '/' . trim( $branding->portal_slug, '/' ) . '/' );

		if ( $path ) {
			$base .= ltrim( $path, '/' );
		}

		return $base;
	}

	/**
	 * Get human-readable status label.
	 *
	 * @param string $status Status slug.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'discovery'         => __( 'Discovery', 'wp-agency-hub' ),
			'in_progress'       => __( 'In Progress', 'wp-agency-hub' ),
			'review'            => __( 'Review', 'wp-agency-hub' ),
			'complete'          => __( 'Complete', 'wp-agency-hub' ),
			'pending'           => __( 'Pending', 'wp-agency-hub' ),
			'draft'             => __( 'Draft', 'wp-agency-hub' ),
			'pending_review'    => __( 'Pending Review', 'wp-agency-hub' ),
			'approved'          => __( 'Approved', 'wp-agency-hub' ),
			'changes_requested' => __( 'Changes Requested', 'wp-agency-hub' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( str_replace( '_', ' ', $status ) );
	}

	/**
	 * Get CSS class for a status badge.
	 *
	 * @param string $status Status slug.
	 * @return string
	 */
	public static function status_class( $status ) {
		$classes = array(
			'discovery'         => 'status-pending',
			'in_progress'       => 'status-progress',
			'review'            => 'status-progress',
			'complete'          => 'status-approved',
			'pending'           => 'status-pending',
			'draft'             => 'status-pending',
			'pending_review'    => 'status-progress',
			'approved'          => 'status-approved',
			'changes_requested' => 'status-danger',
		);

		return isset( $classes[ $status ] ) ? $classes[ $status ] : 'status-pending';
	}
}
