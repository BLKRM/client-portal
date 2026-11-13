<?php
/**
 * Settings + Storage: portal address, storage self-check (incl. the
 * WordPress.com "is the storage folder web-served?" test) and recent activity.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Admin;

use BLKRM\Portal\Audit\AuditLog;
use BLKRM\Portal\Auth\Roles;
use BLKRM\Portal\Portal\Router;
use BLKRM\Portal\Storage\Diagnostics;

defined( 'ABSPATH' ) || exit;

/**
 * Settings admin.
 */
final class SettingsPage {

	const PAGE = 'blkrm-portal-settings';

	/**
	 * Form handlers.
	 */
	public static function hooks() {
		add_action( 'admin_post_blkrm_save_settings', array( __CLASS__, 'save' ) );
	}

	/**
	 * Render.
	 */
	public static function render() {
		if ( ! current_user_can( Roles::CAP_SETTINGS ) ) {
			return;
		}
		$probe  = isset( $_GET['probe'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'blkrm_probe' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$checks = Diagnostics::run( $probe );
		?>
		<div class="wrap blkrm-admin">
			<?php AdminMenu::notice(); ?>
			<h1><?php esc_html_e( 'BLKRM Portal Settings', 'blkrm-portal' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="blkrm_save_settings">
				<?php wp_nonce_field( 'blkrm_save_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="blkrm-slug"><?php esc_html_e( 'Portal address', 'blkrm-portal' ); ?></label></th>
						<td>
							<code><?php echo esc_html( home_url( '/' ) ); ?></code><input id="blkrm-slug" name="slug" type="text" value="<?php echo esc_attr( Router::slug() ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Must not match an existing page slug.', 'blkrm-portal' ); ?> <a href="<?php echo esc_url( Router::url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open portal', 'blkrm-portal' ); ?></a></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'blkrm-portal' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Storage + security check', 'blkrm-portal' ); ?></h2>
			<table class="widefat striped blkrm-checks">
				<tbody>
				<?php foreach ( $checks as $c ) : ?>
					<tr>
						<td class="blkrm-check blkrm-check--<?php echo esc_attr( $c['status'] ); ?>"><?php echo esc_html( strtoupper( $c['status'] ) ); ?></td>
						<th scope="row"><?php echo esc_html( $c['label'] ); ?></th>
						<td><?php echo esc_html( $c['detail'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . self::PAGE . '&probe=1' ), 'blkrm_probe' ) ); ?>"><?php esc_html_e( 'Run web-access test', 'blkrm-portal' ); ?></a>
				<span class="description"><?php esc_html_e( 'Writes a temporary file into storage and checks whether the public can fetch it. Run this once on WordPress.com staging.', 'blkrm-portal' ); ?></span>
			</p>

			<h2><?php esc_html_e( 'Recent activity', 'blkrm-portal' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'When (UTC)', 'blkrm-portal' ); ?></th><th><?php esc_html_e( 'Who', 'blkrm-portal' ); ?></th><th><?php esc_html_e( 'What', 'blkrm-portal' ); ?></th><th><?php esc_html_e( 'Details', 'blkrm-portal' ); ?></th><th><?php esc_html_e( 'IP', 'blkrm-portal' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( AuditLog::recent( 50 ) as $a ) : ?>
					<tr>
						<td><?php echo esc_html( $a->created_at ); ?></td>
						<td><?php echo esc_html( (string) $a->display_name ); ?></td>
						<td><code><?php echo esc_html( $a->action ); ?></code></td>
						<td><?php echo esc_html( $a->object_type . ' #' . (int) $a->object_id . ' ' . $a->details ); ?></td>
						<td><?php echo esc_html( $a->ip ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Save settings.
	 */
	public static function save() {
		if ( ! current_user_can( Roles::CAP_SETTINGS ) ) {
			wp_die( esc_html__( 'Not allowed.', 'blkrm-portal' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'blkrm_save_settings' );
		$slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		if ( '' === $slug || get_page_by_path( $slug ) ) {
			AdminMenu::back( self::PAGE, array(), __( 'That address is empty or already used by a page.', 'blkrm-portal' ), 'error' );
		}
		update_option( Router::OPTION_SLUG, $slug );
		AdminMenu::back( self::PAGE, array(), __( 'Settings saved.', 'blkrm-portal' ) );
	}
}
