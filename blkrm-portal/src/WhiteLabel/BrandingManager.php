<?php
/**
 * Branding manager — injects CSS custom properties.
 *
 * @package WPAgencyHub\WhiteLabel
 */

namespace WPAgencyHub\WhiteLabel;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Outputs CSS custom properties and custom CSS on portal pages.
 */
class BrandingManager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'inject_branding_css' ) );
	}

	/**
	 * Inject branding CSS custom properties into the page head.
	 */
	public function inject_branding_css() {
		if ( ! get_query_var( 'ah_portal' ) ) {
			return;
		}

		$branding = Plugin::get_branding();

		$primary   = esc_attr( $branding->primary_color ?: '#007AFF' );
		$secondary = esc_attr( $branding->secondary_color ?: '#5856D6' );
		$logo      = esc_url( $branding->logo_url );

		// Compute hover/active shades by darkening 20% and 35%.
		$primary_hover  = self::darken_color( $primary, 20 );
		$primary_active = self::darken_color( $primary, 35 );

		echo '<style id="wpah-branding-vars">
:root {
	--ah-primary: ' . $primary . ';
	--ah-primary-hover: ' . esc_attr( $primary_hover ) . ';
	--ah-primary-active: ' . esc_attr( $primary_active ) . ';
	--ah-secondary: ' . $secondary . ';
	--ah-logo: url("' . $logo . '");

	--ah-text-primary: #1A1A1A;
	--ah-text-secondary: #636366;
	--ah-surface-primary: #FFFFFF;
	--ah-surface-secondary: #F5F5F7;
	--ah-border: #E5E5E7;
	--ah-danger: #FF3B30;
	--ah-danger-hover: #CC2F26;
	--ah-success: #34C759;
	--ah-success-hover: #2AB948;
}
</style>' . "\n";

		if ( ! empty( $branding->custom_css ) ) {
			echo '<style id="wpah-custom-css">' . "\n";
			echo wp_strip_all_tags( $branding->custom_css ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS already stripped.
			echo '</style>' . "\n";
		}
	}

	/**
	 * Darken a hex color by a percentage.
	 *
	 * @param string $hex     Hex color string.
	 * @param int    $percent Percentage to darken (0-100).
	 * @return string Darkened hex color.
	 */
	public static function darken_color( $hex, $percent ) {
		$hex = ltrim( $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		$factor = 1 - ( $percent / 100 );

		$r = max( 0, (int) round( $r * $factor ) );
		$g = max( 0, (int) round( $g * $factor ) );
		$b = max( 0, (int) round( $b * $factor ) );

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}
}
