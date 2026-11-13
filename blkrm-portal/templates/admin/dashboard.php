<?php
/**
 * Admin dashboard template.
 *
 * @package WPAgencyHub
 *
 * @var int   $client_count    Total clients.
 * @var int   $project_count   Total projects.
 * @var int   $pending_count   Pending approvals.
 * @var int   $message_count   Unread messages.
 * @var array $recent_activity Recent activity entries.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wpah-admin">
	<h1><?php esc_html_e( 'Agency Hub Dashboard', 'wp-agency-hub' ); ?></h1>

	<div class="wpah-admin-stats">
		<div class="wpah-admin-stat">
			<span class="wpah-admin-stat__number"><?php echo esc_html( $client_count ); ?></span>
			<span class="wpah-admin-stat__label"><?php esc_html_e( 'Clients', 'wp-agency-hub' ); ?></span>
		</div>
		<div class="wpah-admin-stat">
			<span class="wpah-admin-stat__number"><?php echo esc_html( $project_count ); ?></span>
			<span class="wpah-admin-stat__label"><?php esc_html_e( 'Projects', 'wp-agency-hub' ); ?></span>
		</div>
		<div class="wpah-admin-stat">
			<span class="wpah-admin-stat__number"><?php echo esc_html( $pending_count ); ?></span>
			<span class="wpah-admin-stat__label"><?php esc_html_e( 'Pending Approvals', 'wp-agency-hub' ); ?></span>
		</div>
		<div class="wpah-admin-stat">
			<span class="wpah-admin-stat__number"><?php echo esc_html( $message_count ); ?></span>
			<span class="wpah-admin-stat__label"><?php esc_html_e( 'Unread Messages', 'wp-agency-hub' ); ?></span>
		</div>
	</div>

	<div class="wpah-admin-quick-actions">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=agency-hub-clients&action=new' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Add Client', 'wp-agency-hub' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=agency-hub-projects&action=new' ) ); ?>" class="button button-primary"><?php esc_html_e( 'New Project', 'wp-agency-hub' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=agency-hub-settings' ) ); ?>" class="button"><?php esc_html_e( 'Settings', 'wp-agency-hub' ); ?></a>
	</div>

	<?php if ( ! empty( $recent_activity ) ) : ?>
		<h2><?php esc_html_e( 'Recent Activity', 'wp-agency-hub' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Action', 'wp-agency-hub' ); ?></th>
					<th><?php esc_html_e( 'User', 'wp-agency-hub' ); ?></th>
					<th><?php esc_html_e( 'Project', 'wp-agency-hub' ); ?></th>
					<th><?php esc_html_e( 'When', 'wp-agency-hub' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent_activity as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( str_replace( '_', ' ', $entry->action ) ); ?></td>
						<td><?php echo esc_html( $entry->user_name ); ?></td>
						<td><?php echo esc_html( $entry->project_name ); ?></td>
						<td><?php echo esc_html( human_time_diff( strtotime( $entry->created_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'wp-agency-hub' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
