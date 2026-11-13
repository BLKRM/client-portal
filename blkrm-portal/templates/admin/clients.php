<?php
/**
 * Admin clients list template.
 *
 * @package WPAgencyHub
 *
 * @var array $clients Client records with user info and project count.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wpah-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Clients', 'wp-agency-hub' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=agency-hub-clients&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'wp-agency-hub' ); ?></a>
	<hr class="wp-header-end">

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Client saved.', 'wp-agency-hub' ); ?></p></div>
	<?php endif; ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Client deleted.', 'wp-agency-hub' ); ?></p></div>
	<?php endif; ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( empty( $clients ) ) : ?>
		<p><?php esc_html_e( 'No clients yet. Add your first client.', 'wp-agency-hub' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Company', 'wp-agency-hub' ); ?></th>
					<th><?php esc_html_e( 'Contact', 'wp-agency-hub' ); ?></th>
					<th><?php esc_html_e( 'Email', 'wp-agency-hub' ); ?></th>
					<th><?php esc_html_e( 'Projects', 'wp-agency-hub' ); ?></th>
					<th><?php esc_html_e( 'Since', 'wp-agency-hub' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $clients as $c ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=agency-hub-clients&action=edit&client_id=' . $c->id ) ); ?>">
								<strong><?php echo esc_html( $c->company_name ?: $c->display_name ); ?></strong>
							</a>
						</td>
						<td><?php echo esc_html( $c->display_name ); ?></td>
						<td><?php echo esc_html( $c->user_email ); ?></td>
						<td><?php echo esc_html( $c->project_count ); ?></td>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $c->created_at ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
