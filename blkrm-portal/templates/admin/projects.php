<?php
/**
 * Admin projects list template.
 *
 * @package WPAgencyHub
 *
 * @var array $projects Project records with company_name.
 */

defined( 'ABSPATH' ) || exit;

use WPAgencyHub\Portal\PortalTemplate;
?>
<div class="wrap wpah-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Projects', 'wp-agency-hub' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=agency-hub-projects&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'wp-agency-hub' ); ?></a>
	<hr class="wp-header-end">

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Project saved.', 'wp-agency-hub' ); ?></p></div>
	<?php endif; ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Project deleted.', 'wp-agency-hub' ); ?></p></div>
	<?php endif; ?>

	<?php if ( empty( $projects ) ) : ?>
		<p><?php esc_html_e( 'No projects yet. Create your first project.', 'wp-agency-hub' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'wp-agency-hub' ); ?></th>
					<th><?php esc_html_e( 'Client', 'wp-agency-hub' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-agency-hub' ); ?></th>
					<th><?php esc_html_e( 'Due Date', 'wp-agency-hub' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'wp-agency-hub' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $projects as $project ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=agency-hub-projects&action=edit&project_id=' . $project->id ) ); ?>">
								<strong><?php echo esc_html( $project->name ); ?></strong>
							</a>
						</td>
						<td><?php echo esc_html( $project->company_name ); ?></td>
						<td><?php echo esc_html( PortalTemplate::status_label( $project->status ) ); ?></td>
						<td><?php echo $project->due_date ? esc_html( wp_date( get_option( 'date_format' ), strtotime( $project->due_date ) ) ) : '&mdash;'; ?></td>
						<td><?php echo esc_html( human_time_diff( strtotime( $project->updated_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'wp-agency-hub' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
