<?php
/**
 * Email notifications.
 *
 * @package WPAgencyHub\Notifications
 */

namespace WPAgencyHub\Notifications;

use WPAgencyHub\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Sends branded email notifications for messages, approvals, and files.
 */
class EmailNotifier {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
	}

	/**
	 * Set email content type to HTML.
	 *
	 * @return string
	 */
	public function set_html_content_type() {
		return 'text/html';
	}

	/**
	 * Send new message notification.
	 *
	 * @param string $to          Recipient email.
	 * @param object $project     Project record.
	 * @param string $sender_name Sender display name.
	 * @param string $message     Message text.
	 */
	public static function send_new_message_notification( $to, $project, $sender_name, $message ) {
		if ( empty( $to ) ) {
			return;
		}

		$branding = Plugin::get_branding();
		$subject  = sprintf(
			/* translators: 1: sender name, 2: project name */
			__( 'New message from %1$s — %2$s', 'wp-agency-hub' ),
			$sender_name,
			$project->name
		);

		ob_start();
		include WPAH_PLUGIN_DIR . 'templates/emails/new-message.php';
		$body = ob_get_clean();

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Send approval requested notification.
	 *
	 * @param string $to       Recipient email.
	 * @param object $project  Project record.
	 * @param object $approval Approval record.
	 */
	public static function send_approval_requested( $to, $project, $approval ) {
		if ( empty( $to ) ) {
			return;
		}

		$branding = Plugin::get_branding();
		$subject  = sprintf(
			/* translators: 1: approval title, 2: project name */
			__( 'Approval needed: %1$s — %2$s', 'wp-agency-hub' ),
			$approval->title,
			$project->name
		);

		ob_start();
		include WPAH_PLUGIN_DIR . 'templates/emails/approval-requested.php';
		$body = ob_get_clean();

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Send approval completed notification.
	 *
	 * @param string $to       Recipient email.
	 * @param object $project  Project record.
	 * @param object $approval Approval record.
	 * @param string $status   New status (approved or changes_requested).
	 * @param string $note     Client's response note.
	 */
	public static function send_approval_completed( $to, $project, $approval, $status, $note = '' ) {
		if ( empty( $to ) ) {
			return;
		}

		$branding = Plugin::get_branding();

		if ( 'approved' === $status ) {
			$subject = sprintf(
				/* translators: 1: approval title, 2: project name */
				__( 'Approved: %1$s — %2$s', 'wp-agency-hub' ),
				$approval->title,
				$project->name
			);
		} else {
			$subject = sprintf(
				/* translators: 1: approval title, 2: project name */
				__( 'Changes requested: %1$s — %2$s', 'wp-agency-hub' ),
				$approval->title,
				$project->name
			);
		}

		ob_start();
		include WPAH_PLUGIN_DIR . 'templates/emails/approval-completed.php';
		$body = ob_get_clean();

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Wrap email content in branded HTML template.
	 *
	 * @param string $content Inner HTML content.
	 * @return string Full HTML email.
	 */
	public static function wrap_email( $content ) {
		$branding = Plugin::get_branding();
		$color    = esc_attr( $branding->primary_color );
		$logo     = esc_url( $branding->logo_url );
		$name     = esc_html( get_bloginfo( 'name' ) );

		$logo_html = $logo
			? '<img src="' . $logo . '" alt="' . $name . '" style="max-height:48px;max-width:200px;">'
			: '<h2 style="margin:0;color:' . $color . ';">' . $name . '</h2>';

		return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f5f5f7;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f7;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;">
<tr><td style="padding:24px;border-bottom:2px solid ' . $color . ';text-align:center;">
' . $logo_html . '
</td></tr>
<tr><td style="padding:32px 24px;">
' . $content . '
</td></tr>
<tr><td style="padding:16px 24px;background:#f5f5f7;text-align:center;font-size:12px;color:#636366;">
' . $name . '
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
	}
}
