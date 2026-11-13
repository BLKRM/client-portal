<?php
/**
 * Admin project edit/create template.
 *
 * @package WPAgencyHub
 *
 * @var object|null $project    Project record or null for new.
 * @var array       $clients    Client records.
 * @var array       $milestones Milestone records.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wpah-admin">
	<h1><?php echo $project ? esc_html__( 'Edit Project', 'wp-agency-hub' ) : esc_html__( 'New Project', 'wp-agency-hub' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'wpah_save_project' ); ?>
		<input type="hidden" name="action" value="wpah_save_project">
		<input type="hidden" name="project_id" value="<?php echo $project ? esc_attr( $project->id ) : '0'; ?>">

		<table class="form-table">
			<tr>
				<th scope="row"><label for="name"><?php esc_html_e( 'Project Name', 'wp-agency-hub' ); ?></label></th>
				<td><input type="text" id="name" name="name" value="<?php echo $project ? esc_attr( $project->name ) : ''; ?>" class="regular-text" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="client_id"><?php esc_html_e( 'Client', 'wp-agency-hub' ); ?></label></th>
				<td>
					<select id="client_id" name="client_id" required>
						<option value=""><?php esc_html_e( '-- Select Client --', 'wp-agency-hub' ); ?></option>
						<?php foreach ( $clients as $c ) : ?>
							<option value="<?php echo esc_attr( $c->id ); ?>" <?php selected( $project ? $project->client_id : 0, $c->id ); ?>>
								<?php echo esc_html( $c->company_name ?: $c->display_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="status"><?php esc_html_e( 'Status', 'wp-agency-hub' ); ?></label></th>
				<td>
					<select id="status" name="status">
						<?php
						$statuses = array(
							'discovery'   => __( 'Discovery', 'wp-agency-hub' ),
							'in_progress' => __( 'In Progress', 'wp-agency-hub' ),
							'review'      => __( 'Review', 'wp-agency-hub' ),
							'complete'    => __( 'Complete', 'wp-agency-hub' ),
						);
						foreach ( $statuses as $val => $label ) :
							?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $project ? $project->status : 'discovery', $val ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="description"><?php esc_html_e( 'Description', 'wp-agency-hub' ); ?></label></th>
				<td><textarea id="description" name="description" rows="5" class="large-text"><?php echo $project ? esc_textarea( $project->description ) : ''; ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="due_date"><?php esc_html_e( 'Due Date', 'wp-agency-hub' ); ?></label></th>
				<td><input type="date" id="due_date" name="due_date" value="<?php echo $project ? esc_attr( $project->due_date ) : ''; ?>"></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Milestones', 'wp-agency-hub' ); ?></h2>
		<table class="widefat" id="wpah-milestones-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'wp-agency-hub' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-agency-hub' ); ?></th>
					<th><?php esc_html_e( 'Due Date', 'wp-agency-hub' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-agency-hub' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $milestones ) ) : ?>
					<?php foreach ( $milestones as $i => $m ) : ?>
						<tr>
							<td>
								<input type="hidden" name="milestone_id[]" value="<?php echo esc_attr( $m->id ); ?>">
								<input type="text" name="milestone_name[]" value="<?php echo esc_attr( $m->name ); ?>" class="regular-text">
							</td>
							<td>
								<select name="milestone_status[]">
									<option value="pending" <?php selected( $m->status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'wp-agency-hub' ); ?></option>
									<option value="in_progress" <?php selected( $m->status, 'in_progress' ); ?>><?php esc_html_e( 'In Progress', 'wp-agency-hub' ); ?></option>
									<option value="complete" <?php selected( $m->status, 'complete' ); ?>><?php esc_html_e( 'Complete', 'wp-agency-hub' ); ?></option>
								</select>
							</td>
							<td><input type="date" name="milestone_due[]" value="<?php echo esc_attr( $m->due_date ); ?>"></td>
							<td><button type="button" class="button wpah-remove-milestone"><?php esc_html_e( 'Remove', 'wp-agency-hub' ); ?></button></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<p><button type="button" class="button" id="wpah-add-milestone"><?php esc_html_e( 'Add Milestone', 'wp-agency-hub' ); ?></button></p>

		<?php submit_button( $project ? __( 'Update Project', 'wp-agency-hub' ) : __( 'Create Project', 'wp-agency-hub' ) ); ?>
	</form>

	<?php if ( $project ) : ?>
		<hr>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Delete this project and all its data?', 'wp-agency-hub' ); ?>');">
			<?php wp_nonce_field( 'wpah_delete_project' ); ?>
			<input type="hidden" name="action" value="wpah_delete_project">
			<input type="hidden" name="project_id" value="<?php echo esc_attr( $project->id ); ?>">
			<?php submit_button( __( 'Delete Project', 'wp-agency-hub' ), 'delete', 'submit', false ); ?>
		</form>
	<?php endif; ?>
</div>
