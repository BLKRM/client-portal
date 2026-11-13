<?php
/**
 * Portal URLs. The portal renders as its own full-screen app (no theme,
 * no Elementor), under a configurable slug (default /portal/):
 *
 *   /portal/                      Home
 *   /portal/login/                Login (BLKRM-branded)
 *   /portal/login/<org-slug>/     Login co-branded with a client's logo
 *   /portal/logout/               Sign out (nonce-protected)
 *   /portal/projects/             Projects
 *   /portal/project/<id>/         Project detail + files
 *   /portal/files/                Files across projects
 *   /portal/todos/, /requests/    (later phases)
 *   /portal/download/<token>/     Signed file download
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Portal;

use BLKRM\Portal\Auth\Access;
use BLKRM\Portal\Files\DownloadService;

defined( 'ABSPATH' ) || exit;

/**
 * Router.
 */
final class Router {

	const OPTION_SLUG     = 'blkrm_portal_slug';
	const OPTION_REWRITES = 'blkrm_portal_rewrites';
	const SECTIONS        = array( 'projects', 'todos', 'requests', 'files' );

	/**
	 * Portal slug.
	 *
	 * @return string
	 */
	public static function slug() {
		$slug = sanitize_title( (string) get_option( self::OPTION_SLUG, 'portal' ) );
		return '' === $slug ? 'portal' : $slug;
	}

	/**
	 * Absolute portal URL.
	 *
	 * @param string $path Path below the portal root, e.g. 'projects'.
	 * @return string
	 */
	public static function url( $path = '' ) {
		$path = trim( (string) $path, '/' );
		return home_url( user_trailingslashit( '/' . self::slug() . ( '' === $path ? '' : '/' . $path ) ) );
	}

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'dispatch' ), 0 );
	}

	/**
	 * Rewrite rules.
	 */
	public function add_rewrite_rules() {
		$s = preg_quote( self::slug(), '#' );
		add_rewrite_rule( "^{$s}/?$", 'index.php?blkrm_route=home', 'top' );
		add_rewrite_rule( "^{$s}/login/?$", 'index.php?blkrm_route=login', 'top' );
		add_rewrite_rule( "^{$s}/login/([a-z0-9-]+)/?$", 'index.php?blkrm_route=login&blkrm_arg=$matches[1]', 'top' );
		add_rewrite_rule( "^{$s}/logout/?$", 'index.php?blkrm_route=logout', 'top' );
		add_rewrite_rule( "^{$s}/(" . implode( '|', self::SECTIONS ) . ')/?$', 'index.php?blkrm_route=$matches[1]', 'top' );
		add_rewrite_rule( "^{$s}/project/([0-9]+)/?$", 'index.php?blkrm_route=project&blkrm_arg=$matches[1]', 'top' );
		add_rewrite_rule( "^{$s}/download/([A-Za-z0-9_.-]+)/?$", 'index.php?blkrm_route=download&blkrm_arg=$matches[1]', 'top' );
	}

	/**
	 * Flush rewrite rules once after the slug or rule set changes.
	 */
	public function maybe_flush() {
		$signature = self::slug() . '|' . BLKRM_PORTAL_VERSION;
		if ( get_option( self::OPTION_REWRITES ) !== $signature ) {
			flush_rewrite_rules( false );
			update_option( self::OPTION_REWRITES, $signature, true );
		}
	}

	/**
	 * Public query vars.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		$vars[] = 'blkrm_route';
		$vars[] = 'blkrm_arg';
		return $vars;
	}

	/**
	 * Route a portal request. Everything here ends with exit.
	 */
	public function dispatch() {
		$route = (string) get_query_var( 'blkrm_route' );
		if ( '' === $route ) {
			return;
		}
		$arg = (string) get_query_var( 'blkrm_arg' );

		// No caching, no framing, minimal referrer leakage on every portal page.
		nocache_headers();
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: same-origin' );
		header( 'X-Content-Type-Options: nosniff' );

		switch ( $route ) {
			case 'download':
				( new DownloadService() )->serve( $arg );
				exit;

			case 'login':
				( new LoginPage() )->handle( $arg );
				exit;

			case 'logout':
				$this->logout();
				exit;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_safe_redirect( self::url( 'login' ) );
			exit;
		}
		if ( ! Access::can_access_portal( $user_id ) ) {
			wp_die(
				esc_html__( 'Your account doesn\'t have portal access yet. Contact your BLKRM lead.', 'blkrm-portal' ),
				esc_html__( 'No portal access', 'blkrm-portal' ),
				array( 'response' => 403 )
			);
		}

		( new Pages() )->render( $route, $arg );
		exit;
	}

	/**
	 * Sign out (requires a nonce so other sites can't log people out).
	 */
	private function logout() {
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( is_user_logged_in() && wp_verify_nonce( $nonce, 'blkrm_logout' ) ) {
			wp_logout();
		}
		wp_safe_redirect( self::url( 'login' ) );
	}

	/**
	 * Logout URL with nonce.
	 *
	 * @return string
	 */
	public static function logout_url() {
		return wp_nonce_url( self::url( 'logout' ), 'blkrm_logout' );
	}
}
