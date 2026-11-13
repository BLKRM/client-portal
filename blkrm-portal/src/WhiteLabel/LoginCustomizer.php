<?php
/**
 * Custom branded login page.
 *
 * @package WPAgencyHub\WhiteLabel
 */

namespace WPAgencyHub\WhiteLabel;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Customizes the WordPress login page when accessed via the portal login URL.
 */
class LoginCustomizer {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// The portal login is handled entirely by PortalRouter, not wp-login.php.
		// This class provides additional hooks for agencies that want wp-login.php branded too.
		add_action( 'login_enqueue_scripts', array( $this, 'maybe_brand_wp_login' ) );
		add_filter( 'login_headerurl', array( $this, 'login_header_url' ) );
		add_filter( 'login_headertext', array( $this, 'login_header_text' ) );
	}

	/**
	 * Conditionally brand wp-login.php if a query parameter is present.
	 */
	public function maybe_brand_wp_login() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
		if ( ! isset( $_GET['ah_branded'] ) ) {
			return;
		}

		$branding = Plugin::get_branding();
		$primary  = esc_attr( $branding->primary_color ?: '#007AFF' );
		$logo     = esc_url( $branding->logo_url );

		echo '<style>
body.login {
	background: #f5f5f7;
}
.login h1 a {
	' . ( $logo ? 'background-image: url("' . $logo . '");' : '' ) . '
	background-size: contain;
	width: 200px;
	height: 80px;
	background-repeat: no-repeat;
	background-position: center;
}
.login #loginform {
	border-radius: 8px;
	border: 1px solid #e5e5e7;
	box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.login .button-primary {
	background: ' . $primary . ' !important;
	border-color: ' . $primary . ' !important;
	border-radius: 6px !important;
}
.login .button-primary:hover {
	opacity: 0.9;
}
</style>';
	}

	/**
	 * Change the login header URL to the site URL.
	 *
	 * @return string
	 */
	public function login_header_url() {
		return home_url( '/' );
	}

	/**
	 * Change the login header text to the site name.
	 *
	 * @return string
	 */
	public function login_header_text() {
		return get_bloginfo( 'name' );
	}
}
