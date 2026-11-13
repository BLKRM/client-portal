<?php
/**
 * Login page. BLKRM-branded by default; /portal/login/<org-slug>/ shows the
 * client's logo beside BLKRM's. Includes a basic failed-login lockout
 * (5 tries per 15 minutes per IP + username).
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Portal;

use BLKRM\Portal\Auth\Access;
use BLKRM\Portal\Data\Orgs;

defined( 'ABSPATH' ) || exit;

/**
 * Login controller.
 */
final class LoginPage {

	const MAX_ATTEMPTS = 5;
	const LOCK_SECONDS = 900;

	/**
	 * Show / process the login form.
	 *
	 * @param string $org_slug Optional client slug for co-branding.
	 */
	public function handle( $org_slug ) {
		if ( is_user_logged_in() && Access::can_access_portal( get_current_user_id() ) ) {
			wp_safe_redirect( Router::url() );
			exit;
		}

		$org = '' !== $org_slug ? Orgs::get_by_slug( sanitize_title( $org_slug ) ) : null;
		if ( $org && 'active' !== $org->status ) {
			$org = null;
		}

		$error    = '';
		$username = '';
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$username = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
			$error    = $this->process( $username );
		}

		$logo_url = ( $org && $org->logo_attachment_id ) ? wp_get_attachment_image_url( (int) $org->logo_attachment_id, 'medium' ) : '';
		$action   = $org ? Router::url( 'login/' . $org->slug ) : Router::url( 'login' );
		$theme    = 'system';

		Assets::print_document_start( __( 'Sign in', 'blkrm-portal' ), $theme, 'blkrm-login' );
		include BLKRM_PORTAL_DIR . 'templates/login.php';
		Assets::print_document_end();
	}

	/**
	 * Verify nonce, throttle and sign in. Redirects on success.
	 *
	 * @param string $username Username or email.
	 * @return string Error message.
	 */
	private function process( $username ) {
		$nonce = isset( $_POST['_blkrm_login'] ) ? sanitize_text_field( wp_unslash( $_POST['_blkrm_login'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'blkrm_login' ) ) {
			return __( 'Your session timed out. Please try again.', 'blkrm-portal' );
		}

		$lock_key = 'blkrm_login_' . md5( strtolower( $username ) . '|' . ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) );
		$attempts = (int) get_transient( $lock_key );
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			return __( 'Too many attempts. Please wait 15 minutes, or reset your password.', 'blkrm-portal' );
		}

		$user = wp_signon(
			array(
				'user_login'    => $username,
				'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitized.
				'remember'      => ! empty( $_POST['rememberme'] ),
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			set_transient( $lock_key, $attempts + 1, self::LOCK_SECONDS );
			return __( 'That email or password isn\'t right.', 'blkrm-portal' );
		}

		delete_transient( $lock_key );
		if ( ! Access::can_access_portal( $user->ID ) ) {
			wp_logout();
			return __( 'Your account doesn\'t have portal access yet. Contact your BLKRM lead.', 'blkrm-portal' );
		}
		wp_safe_redirect( Router::url() );
		exit;
	}
}
