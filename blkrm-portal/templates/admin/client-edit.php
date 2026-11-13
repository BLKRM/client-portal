<?php
/**
 * Admin client edit/create template.
 *
 * @package WPAgencyHub
 *
 * @var object|null $client Client record with user info, or null for new.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wpah-admin">
	<h1><?php echo $client ? esc_html__( 'Edit Client', 'wp-agency-hub' ) : esc_html__( 'New Client', 'wp-agency-hub' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'wpah_save_client' ); ?>
		<input type="hidden" name="action" value="wpah_save_client">
		<input type="hidden" name="client_id" value="<?php echo $client ? esc_attr( $client->id ) : '0'; ?>">

		<table class="form-table">
			<tr>
				<th scope="row"><label for="company_name"><?php esc_html_e( 'Company Name', 'wp-agency-hub' ); ?></label></th>
				<td><input type="text" id="company_name" name="company_name" value="<?php echo $client ? esc_attr( $client->company_name ) : ''; ?>" class="regular-text" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="display_name"><?php esc_html_e( 'Contact Name', 'wp-agency-hub' ); ?></label></th>
				<td><input type="text" id="display_name" name="display_name" value="<?php echo $client ? esc_attr( $client->display_name ) : ''; ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="contact_email"><?php esc_html_e( 'Email', 'wp-agency-hub' ); ?></label></th>
				<td>
					<input type="email" id="contact_email" name="contact_email" value="<?php echo $client ? esc_attr( $client->contact_email ?: $client->user_email ) : ''; ?>" class="regular-text" required>
					<?php if ( ! $client ) : ?>
						<p class="description"><?php esc_html_e( 'A WordPress user with the "Agency Client" role will be created. Login credentials will be emailed.', 'wp-agency-hub' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<?php submit_button( $client ? __( 'Update Client', 'wp-agency-hub' ) : __( 'Create Client', 'wp-agency-hub' ) ); ?>
	</form>

	<?php if ( $client ) : ?>
		<hr>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Delete this client and all their data?', 'wp-agency-hub' ); ?>');">
			<?php wp_nonce_field( 'wpah_delete_client' ); ?>
			<input type="hidden" name="action" value="wpah_delete_client">
			<input type="hidden" name="client_id" value="<?php echo esc_attr( $client->id ); ?>">
			<p>
				<label>
					<input type="checkbox" name="delete_user" value="1">
					<?php esc_html_e( 'Also delete the WordPress user account', 'wp-agency-hub' ); ?>
				</label>
			</p>
			<?php submit_button( __( 'Delete Client', 'wp-agency-hub' ), 'delete', 'submit', false ); ?>
		</form>
	<?php endif; ?>
</div>
