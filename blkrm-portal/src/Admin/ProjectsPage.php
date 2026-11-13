<?php
/**
 * Projects screen: create and edit projects (client, stage, type, dates,
 * brief). Phase 1 adds the Board / Grid views in the portal for staff.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Admin;

use BLKRM\Portal\Audit\AuditLog;
use BLKRM\Portal\Auth\Roles;
use BLKRM\Portal\Data\Orgs;
use BLKRM\Portal\Data\Projects;
use BLKRM\Portal\Portal\Router;

defined( 'ABSPATH' ) || exit;

/**
 * Projects admin.
 */
final class ProjectsPage {

	const PAGE = 'blkrm-portal-projects';

	/**
	 * Form handlers.
	 */
	public static function hooks() {
		add_action( 'admin_post_blkrm_save_project', array( __CLASS__, 'save' ) );
	}

	/**
	 * Render.
	 */
	public static function render() {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			return;
		}
		$id      = isset( $_GET['project'] ) ? absint( $_GET['project'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$new     = isset( $_GET['new'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$project = $id ? Projects::get( $id ) : null;
		echo '<div class="wrap blkrm-admin">';
		AdminMenu::notice();
		if ( $project || $new ) {
			self::form( $project );
		} else {
			self::table();
		}
		echo '</div>';
	}

	/**
	 * Project list.
	 */
	private static function table() {
		$stages = Projects::stages();
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Projects', 'blkrm-portal' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&new=1' ) ); ?>"><?php esc_html_e( 'Add project', 'blkrm-portal' ); ?></a>
		<hr class="wp-header-end">
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Project', 'blkrm-portal' ); ?></th><th><?php esc_html_e( 'Client', 'blkrm-portal' ); ?></th><th><?php esc_html_e( 'Stage', 'blkrm-portal' ); ?></th><th><?php esc_html_e( 'Due', 'blkrm-portal' ); ?></th><th></th></tr></thead>
			<tbody>
			<?php $all = Projects::list_for( null ); ?>
			<?php if ( ! $all ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No projects yet.', 'blkrm-portal' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $all as $p ) : ?>
				<tr>
					<td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&project=' . (int) $p->id ) ); ?>"><?php echo esc_html( $p->name ); ?></a></strong></td>
					<td><?php echo esc_html( $p->org_name ); ?></td>
					<td><?php echo esc_html( $stages[ $p->stage ] ?? $p->stage ); ?></td>
					<td><?php echo esc_html( $p->due_date ? $p->due_date : '—' ); ?></td>
					<td><a href="<?php echo esc_url( Router::url( 'project/' . (int) $p->id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View in portal', 'blkrm-portal' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Create / edit form.
	 *
	 * @param object|null $p Project.
	 */
	private static function form( $p ) {
		$orgs = Orgs::all();
		$v    = static function ( $field, $default = '' ) use ( $p ) {
			return $p && isset( $p->$field ) && null !== $p->$field ? $p->$field : $default;
		};
		?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>">&larr; <?php esc_html_e( 'All projects', 'blkrm-portal' ); ?></a></p>
		<h1><?php echo $p ? esc_html( $p->name ) : esc_html__( 'Add project', 'blkrm-portal' ); ?></h1>
		<?php if ( ! $orgs ) : ?>
			<p><?php esc_html_e( 'Add a client first.', 'blkrm-portal' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=blkrm-portal' ) ); ?>"><?php esc_html_e( 'Clients', 'blkrm-portal' ); ?></a></p>
			<?php
			return;
		endif;
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="blkrm-form">
			<input type="hidden" name="action" value="blkrm_save_project">
			<input type="hidden" name="project_id" value="<?php echo $p ? (int) $p->id : 0; ?>">
			<?php wp_nonce_field( 'blkrm_save_project' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="blkrm-p-name"><?php esc_html_e( 'Name', 'blkrm-portal' ); ?></label></th><td><input id="blkrm-p-name" name="name" type="text" class="regular-text" required value="<?php echo esc_attr( $v( 'name' ) ); ?>"></td></tr>
				<tr><th scope="row"><label for="blkrm-p-org"><?php esc_html_e( 'Client', 'blkrm-portal' ); ?></label></th><td>
					<select id="blkrm-p-org" name="org_id" required>
						<?php foreach ( $orgs as $o ) : ?>
							<option value="<?php echo (int) $o->id; ?>" <?php selected( (int) $v( 'org_id', 0 ), (int) $o->id ); ?>><?php echo esc_html( $o->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td></tr>
				<tr><th scope="row"><label for="blkrm-p-stage"><?php esc_html_e( 'Stage', 'blkrm-portal' ); ?></label></th><td>
					<select id="blkrm-p-stage" name="stage">
						<?php foreach ( Projects::stages() as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( 'stage', 'discovery' ), $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td></tr>
				<tr><th scope="row"><label for="blkrm-p-type"><?php esc_html_e( 'Type', 'blkrm-portal' ); ?></label></th><td>
					<select id="blkrm-p-type" name="type">
						<?php foreach ( Projects::types() as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( 'type', 'print' ), $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td></tr>
				<tr><th scope="row"><label for="blkrm-p-start"><?php esc_html_e( 'Start date', 'blkrm-portal' ); ?></label></th><td><input id="blkrm-p-start" name="start_date" type="date" value="<?php echo esc_attr( $v( 'start_date' ) ); ?>"></td></tr>
				<tr><th scope="row"><label for="blkrm-p-due"><?php esc_html_e( 'Due date', 'blkrm-portal' ); ?></label></th><td><input id="blkrm-p-due" name="due_date" type="date" value="<?php echo esc_attr( $v( 'due_date' ) ); ?>"></td></tr>
				<tr><th scope="row"><label for="blkrm-p-desc"><?php esc_html_e( 'Brief', 'blkrm-portal' ); ?></label></th><td><textarea id="blkrm-p-desc" name="description" rows="6" class="large-text"><?php echo esc_textarea( $v( 'description' ) ); ?></textarea></td></tr>
			</table>
			<?php submit_button( $p ? __( 'Save project', 'blkrm-portal' ) : __( 'Add project', 'blkrm-portal' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Save.
	 */
	public static function save() {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Not allowed.', 'blkrm-portal' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'blkrm_save_project' );
		$id   = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		$data = array(
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'org_id'      => isset( $_POST['org_id'] ) ? absint( $_POST['org_id'] ) : 0,
			'stage'       => isset( $_POST['stage'] ) ? sanitize_key( wp_unslash( $_POST['stage'] ) ) : 'discovery',
			'type'        => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'unsure',
			'start_date'  => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '',
			'due_date'    => isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '',
		);
		$result = Projects::save( $data, $id ? $id : null );
		if ( is_wp_error( $result ) ) {
			AdminMenu::back( self::PAGE, $id ? array( 'project' => $id ) : array( 'new' => 1 ), $result->get_error_message(), 'error' );
		}
		AuditLog::record( $id ? 'project_updated' : 'project_created', array( 'org_id' => $data['org_id'], 'project_id' => $result, 'object_type' => 'project', 'object_id' => $result ) );
		AdminMenu::back( self::PAGE, array( 'project' => $result ), __( 'Project saved.', 'blkrm-portal' ) );
	}
}
