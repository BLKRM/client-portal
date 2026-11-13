<?php
/**
 * Clients (organizations) screen: create clients, set seat limits and logo,
 * add or invite their people (up to the seat limit), remove access.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Admin;

use BLKRM\Portal\Audit\AuditLog;
use BLKRM\Portal\Auth\Roles;
use BLKRM\Portal\Data\Orgs;
use BLKRM\Portal\Portal\Router;

defined( 'ABSPATH' ) || exit;

/**
 * Organizations admin.
 */
final class OrgsPage {

	const PAGE = 'blkrm-portal';

	/**
	 * Form handlers.
	 */
	public static function hooks() {
		add_action( 'admin_post_blkrm_save_org', array( __CLASS__, 'save_org' ) );
		add_action( 'admin_post_blkrm_add_member', array( __CLASS__, 'add_member' ) );
		add_action( 'admin_post_blkrm_remove_member', array( __CLASS__, 'remove_member' ) );
	}

	/**
	 * Render list or edit view.
	 */
	public static function render() {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			return;
		}
		$org_id = isset( $_GET['org'] ) ? absint( $_GET['org'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$org    = $org_id ? Orgs::get( $org_id ) : null;
		echo '<div class="wrap blkrm-admin">';
		AdminMenu::notice();
		if ( $org ) {
			self::render_org( $org );
		} else {
			self::render_list();
		}
		echo '</div>';
	}

	/**
	 * List + create form.
	 */
	private static function render_list() {
		$orgs = Orgs::all();
		?>
		<h1><?php esc_html_e( 'Clients', 'blkrm-portal' ); ?></h1>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Client', 'blkrm-portal' ); ?></th>
				<th><?php esc_html_e( 'People', 'blkrm-portal' ); ?></th>
				<th><?php esc_html_e( 'Storage', 'blkrm-portal' ); ?></th>
				<th><?php esc_html_e( 'Login address', 'blkrm-portal' ); ?></th>
				<th><?php esc_html_e( 'Status', 'blkrm-portal' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $orgs ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No clients yet. Add your first one below.', 'blkrm-portal' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $orgs as $o ) : ?>
				<tr>
					<td><strong><a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE, 'org' => (int) $o->id ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $o->name ); ?></a></strong></td>
					<td><?php echo (int) count( Orgs::members( (int) $o->id ) ) . ' / ' . (int) $o->seat_limit; ?></td>
					<td><?php echo esc_html( size_format( Orgs::storage_used( (int) $o->id ), 1 ) ?: '0 B' ); ?></td>
					<td><code><?php echo esc_html( Router::url( 'login/' . $o->slug ) ); ?></code></td>
					<td><?php echo esc_html( ucfirst( $o->status ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Add a client', 'blkrm-portal' ); ?></h2>
		<?php self::org_form( null ); ?>
		<?php
	}

	/**
	 * Org create/edit form.
	 *
	 * @param object|null $org Org.
	 */
	private static function org_form( $org ) {
		$logo_id = $org ? (int) $org->logo_attachment_id : 0;
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="blkrm-form">
			<input type="hidden" name="action" value="blkrm_save_org">
			<input type="hidden" name="org_id" value="<?php echo $org ? (int) $org->id : 0; ?>">
			<?php wp_nonce_field( 'blkrm_save_org' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="blkrm-org-name"><?php esc_html_e( 'Name', 'blkrm-portal' ); ?></label></th>
					<td><input id="blkrm-org-name" name="name" type="text" class="regular-text" required value="<?php echo esc_attr( $org ? $org->name : '' ); ?>"></td>
				</tr>
				<?php if ( ! $org ) : ?>
				<tr>
					<th scope="row"><label for="blkrm-org-slug"><?php esc_html_e( 'Login address', 'blkrm-portal' ); ?></label></th>
					<td>
						<code><?php echo esc_html( Router::url( 'login' ) ); ?></code><input id="blkrm-org-slug" name="slug" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'auto from name', 'blkrm-portal' ); ?>">
						<p class="description"><?php esc_html_e( 'Their co-branded sign-in page. Can\'t be changed later.', 'blkrm-portal' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><label for="blkrm-org-seats"><?php esc_html_e( 'Seats', 'blkrm-portal' ); ?></label></th>
					<td><input id="blkrm-org-seats" name="seat_limit" type="number" min="1" max="50" value="<?php echo $org ? (int) $org->seat_limit : 4; ?>"> <span class="description"><?php esc_html_e( 'People who can sign in for this client.', 'blkrm-portal' ); ?></span></td>
				</tr>
				<tr>
					<th scope="row"><label for="blkrm-org-quota"><?php esc_html_e( 'Storage limit (GB)', 'blkrm-portal' ); ?></label></th>
					<td><input id="blkrm-org-quota" name="quota_gb" type="number" min="0" step="1" value="<?php echo $org ? (int) round( $org->quota_bytes / GB_IN_BYTES ) : 0; ?>"> <span class="description"><?php esc_html_e( '0 = no limit', 'blkrm-portal' ); ?></span></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Logo (login page)', 'blkrm-portal' ); ?></th>
					<td>
						<input type="hidden" name="logo_attachment_id" value="<?php echo (int) $logo_id; ?>" data-blkrm-logo-id>
						<span data-blkrm-logo-preview><?php echo $logo_id ? wp_get_attachment_image( $logo_id, array( 120, 64 ) ) : ''; ?></span>
						<button type="button" class="button" data-blkrm-pick-logo><?php esc_html_e( 'Choose logo', 'blkrm-portal' ); ?></button>
						<button type="button" class="button-link" data-blkrm-clear-logo><?php esc_html_e( 'Remove', 'blkrm-portal' ); ?></button>
						<p class="description"><?php esc_html_e( 'Optional. Without one, the BLKRM login is shown.', 'blkrm-portal' ); ?></p>
					</td>
				</tr>
				<?php if ( $org ) : ?>
				<tr>
					<th scope="row"><label for="blkrm-org-status"><?php esc_html_e( 'Status', 'blkrm-portal' ); ?></label></th>
					<td>
						<select id="blkrm-org-status" name="status">
							<option value="active" <?php selected( $org->status, 'active' ); ?>><?php esc_html_e( 'Active', 'blkrm-portal' ); ?></option>
							<option value="archived" <?php selected( $org->status, 'archived' ); ?>><?php esc_html_e( 'Archived (no sign-in)', 'blkrm-portal' ); ?></option>
						</select>
					</td>
				</tr>
				<?php endif; ?>
			</table>
			<?php submit_button( $org ? __( 'Save client', 'blkrm-portal' ) : __( 'Add client', 'blkrm-portal' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Single org: details + members.
	 *
	 * @param object $org Org.
	 */
	private static function render_org( $org ) {
		$members = Orgs::members( (int) $org->id );
		$full    = count( $members ) >= (int) $org->seat_limit;
		?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>">&larr; <?php esc_html_e( 'All clients', 'blkrm-portal' ); ?></a></p>
		<h1><?php echo esc_html( $org->name ); ?></h1>
		<p><?php esc_html_e( 'Sign-in page:', 'blkrm-portal' ); ?> <a href="<?php echo esc_url( Router::url( 'login/' . $org->slug ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( Router::url( 'login/' . $org->slug ) ); ?></a></p>

		<h2><?php echo esc_html( sprintf( /* translators: 1: members, 2: seats */ __( 'People (%1$d of %2$d seats)', 'blkrm-portal' ), count( $members ), (int) $org->seat_limit ) ); ?></h2>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Name', 'blkrm-portal' ); ?></th><th><?php esc_html_e( 'Email', 'blkrm-portal' ); ?></th><th><?php esc_html_e( 'Role', 'blkrm-portal' ); ?></th><th></th></tr></thead>
			<tbody>
			<?php if ( ! $members ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'Nobody yet.', 'blkrm-portal' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $members as $m ) : ?>
				<tr>
					<td><?php echo esc_html( $m->display_name ); ?></td>
					<td><?php echo esc_html( $m->user_email ); ?></td>
					<td><?php echo esc_html( 'owner' === $m->org_role ? __( 'Owner', 'blkrm-portal' ) : __( 'Member', 'blkrm-portal' ) ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this person\'s portal access?', 'blkrm-portal' ) ); ?>');">
							<input type="hidden" name="action" value="blkrm_remove_member">
							<input type="hidden" name="org_id" value="<?php echo (int) $org->id; ?>">
							<input type="hidden" name="user_id" value="<?php echo (int) $m->user_id; ?>">
							<?php wp_nonce_field( 'blkrm_remove_member' ); ?>
							<button type="submit" class="button-link button-link-delete"><?php esc_html_e( 'Remove access', 'blkrm-portal' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $full ) : ?>
			<p><em><?php esc_html_e( 'All seats are in use. Raise the seat limit below to add someone.', 'blkrm-portal' ); ?></em></p>
		<?php else : ?>
			<h3><?php esc_html_e( 'Add a person', 'blkrm-portal' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="blkrm-form">
				<input type="hidden" name="action" value="blkrm_add_member">
				<input type="hidden" name="org_id" value="<?php echo (int) $org->id; ?>">
				<?php wp_nonce_field( 'blkrm_add_member' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="blkrm-m-email"><?php esc_html_e( 'Email', 'blkrm-portal' ); ?></label></th><td><input id="blkrm-m-email" name="email" type="email" class="regular-text" required></td></tr>
					<tr><th scope="row"><label for="blkrm-m-name"><?php esc_html_e( 'Full name', 'blkrm-portal' ); ?></label></th><td><input id="blkrm-m-name" name="display_name" type="text" class="regular-text"> <span class="description"><?php esc_html_e( 'For new accounts.', 'blkrm-portal' ); ?></span></td></tr>
					<tr><th scope="row"><label for="blkrm-m-role"><?php esc_html_e( 'Role', 'blkrm-portal' ); ?></label></th><td>
						<select id="blkrm-m-role" name="org_role"><option value="member"><?php esc_html_e( 'Member', 'blkrm-portal' ); ?></option><option value="owner"><?php esc_html_e( 'Owner', 'blkrm-portal' ); ?></option></select>
					</td></tr>
				</table>
				<p class="description"><?php esc_html_e( 'If there\'s no account for this email yet, one is created and they get an email to set their password.', 'blkrm-portal' ); ?></p>
				<?php submit_button( __( 'Add person', 'blkrm-portal' ), 'secondary' ); ?>
			</form>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Client settings', 'blkrm-portal' ); ?></h2>
		<?php
		self::org_form( $org );
	}

	/**
	 * Save org.
	 */
	public static function save_org() {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Not allowed.', 'blkrm-portal' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'blkrm_save_org' );
		$org_id = isset( $_POST['org_id'] ) ? absint( $_POST['org_id'] ) : 0;
		$data   = array(
			'name'               => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'slug'               => isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '',
			'seat_limit'         => isset( $_POST['seat_limit'] ) ? absint( $_POST['seat_limit'] ) : 4,
			'quota_bytes'        => isset( $_POST['quota_gb'] ) ? absint( $_POST['quota_gb'] ) * GB_IN_BYTES : 0,
			'logo_attachment_id' => isset( $_POST['logo_attachment_id'] ) ? absint( $_POST['logo_attachment_id'] ) : 0,
		);
		if ( $org_id ) {
			if ( isset( $_POST['status'] ) ) {
				$data['status'] = sanitize_key( wp_unslash( $_POST['status'] ) );
			}
			unset( $data['slug'] );
			Orgs::update( $org_id, $data );
			AuditLog::record( 'org_updated', array( 'org_id' => $org_id, 'object_type' => 'org', 'object_id' => $org_id ) );
			AdminMenu::back( self::PAGE, array( 'org' => $org_id ), __( 'Client saved.', 'blkrm-portal' ) );
		}
		$new = Orgs::create( $data );
		if ( is_wp_error( $new ) ) {
			AdminMenu::back( self::PAGE, array(), $new->get_error_message(), 'error' );
		}
		AuditLog::record( 'org_created', array( 'org_id' => $new, 'object_type' => 'org', 'object_id' => $new ) );
		AdminMenu::back( self::PAGE, array( 'org' => $new ), __( 'Client added. Now add their people.', 'blkrm-portal' ) );
	}

	/**
	 * Add (or invite) a member.
	 */
	public static function add_member() {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Not allowed.', 'blkrm-portal' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'blkrm_add_member' );
		$org_id = isset( $_POST['org_id'] ) ? absint( $_POST['org_id'] ) : 0;
		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$name   = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$role   = isset( $_POST['org_role'] ) && 'owner' === $_POST['org_role'] ? Orgs::ROLE_OWNER : Orgs::ROLE_MEMBER;
		$back   = array( 'org' => $org_id );

		if ( ! is_email( $email ) ) {
			AdminMenu::back( self::PAGE, $back, __( 'Enter a valid email.', 'blkrm-portal' ), 'error' );
		}

		$user    = get_user_by( 'email', $email );
		$created = false;
		if ( ! $user ) {
			$user_id = wp_insert_user(
				array(
					'user_login'   => $email,
					'user_email'   => $email,
					'display_name' => $name ? $name : $email,
					'first_name'   => $name ? strtok( $name, ' ' ) : '',
					'user_pass'    => wp_generate_password( 32, true, true ),
					'role'         => Roles::CLIENT,
				)
			);
			if ( is_wp_error( $user_id ) ) {
				AdminMenu::back( self::PAGE, $back, $user_id->get_error_message(), 'error' );
			}
			$user    = get_userdata( $user_id );
			$created = true;
		}

		$result = Orgs::add_member( $org_id, $user->ID, $role );
		if ( is_wp_error( $result ) ) {
			if ( $created ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $user->ID );
			}
			AdminMenu::back( self::PAGE, $back, $result->get_error_message(), 'error' );
		}
		if ( $created ) {
			wp_new_user_notification( $user->ID, null, 'user' );
		}
		AuditLog::record( 'member_added', array( 'org_id' => $org_id, 'object_type' => 'user', 'object_id' => $user->ID, 'details' => array( 'role' => $role, 'invited' => $created ) ) );
		AdminMenu::back(
			self::PAGE,
			$back,
			$created ? __( 'Person added and invited by email to set a password.', 'blkrm-portal' ) : __( 'Person added.', 'blkrm-portal' )
		);
	}

	/**
	 * Remove a member.
	 */
	public static function remove_member() {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Not allowed.', 'blkrm-portal' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'blkrm_remove_member' );
		$org_id  = isset( $_POST['org_id'] ) ? absint( $_POST['org_id'] ) : 0;
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		Orgs::remove_member( $org_id, $user_id );
		// End their sessions so access stops immediately.
		\WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		AuditLog::record( 'member_removed', array( 'org_id' => $org_id, 'object_type' => 'user', 'object_id' => $user_id ) );
		AdminMenu::back( self::PAGE, array( 'org' => $org_id ), __( 'Access removed.', 'blkrm-portal' ) );
	}
}
