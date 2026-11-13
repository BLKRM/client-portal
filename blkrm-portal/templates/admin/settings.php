<?php
/**
 * Admin settings template.
 *
 * @package WPAgencyHub
 *
 * @var object $branding Branding settings.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wpah-admin">
	<h1><?php esc_html_e( 'Agency Hub Settings', 'wp-agency-hub' ); ?></h1>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wp-agency-hub' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'wpah_save_settings' ); ?>
		<input type="hidden" name="action" value="wpah_save_settings">

		<h2><?php esc_html_e( 'Branding', 'wp-agency-hub' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="logo_url"><?php esc_html_e( 'Logo', 'wp-agency-hub' ); ?></label></th>
				<td>
					<input type="text" id="logo_url" name="logo_url" value="<?php echo esc_url( $branding->logo_url ); ?>" class="regular-text">
					<button type="button" class="button" id="wpah-upload-logo"><?php esc_html_e( 'Choose Image', 'wp-agency-hub' ); ?></button>
					<?php if ( ! empty( $branding->logo_url ) ) : ?>
						<p><img src="<?php echo esc_url( $branding->logo_url ); ?>" style="max-height:60px;margin-top:8px;"></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="primary_color"><?php esc_html_e( 'Primary Color', 'wp-agency-hub' ); ?></label></th>
				<td><input type="text" id="primary_color" name="primary_color" value="<?php echo esc_attr( $branding->primary_color ); ?>" class="wpah-color-picker"></td>
			</tr>
			<tr>
				<th scope="row"><label for="secondary_color"><?php esc_html_e( 'Secondary Color', 'wp-agency-hub' ); ?></label></th>
				<td><input type="text" id="secondary_color" name="secondary_color" value="<?php echo esc_attr( $branding->secondary_color ); ?>" class="wpah-color-picker"></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Portal', 'wp-agency-hub' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="portal_slug"><?php esc_html_e( 'Portal URL Slug', 'wp-agency-hub' ); ?></label></th>
				<td>
					<code><?php echo esc_html( home_url( '/' ) ); ?></code>
					<input type="text" id="portal_slug" name="portal_slug" value="<?php echo esc_attr( $branding->portal_slug ); ?>" class="regular-text">
					<code>/</code>
					<p class="description"><?php esc_html_e( 'The URL where clients access their portal. Default: client-portal', 'wp-agency-hub' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Custom CSS', 'wp-agency-hub' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="custom_css"><?php esc_html_e( 'Custom CSS', 'wp-agency-hub' ); ?></label></th>
				<td>
					<textarea id="custom_css" name="custom_css" rows="10" class="large-text code"><?php echo esc_textarea( $branding->custom_css ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Add custom CSS to style the client portal. Use CSS custom properties (--ah-primary, --ah-secondary) for theme-aware styling.', 'wp-agency-hub' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'wp-agency-hub' ) ); ?>
	</form>

	<hr>
	<h2><?php esc_html_e( 'Portal Preview', 'wp-agency-hub' ); ?></h2>
	<p>
		<?php
		$portal_url = home_url( '/' . $branding->portal_slug . '/' );
		printf(
			/* translators: %s: portal URL */
			wp_kses(
				__( 'Your client portal is available at: <a href="%s" target="_blank">%s</a>', 'wp-agency-hub' ),
				array( 'a' => array( 'href' => array(), 'target' => array() ) )
			),
			esc_url( $portal_url ),
			esc_html( $portal_url )
		);
		?>
	</p>
</div>
