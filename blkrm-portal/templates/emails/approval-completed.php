<?php
/**
 * Approval completed email notification.
 *
 * @package WPAgencyHub
 *
 * @var object $branding Branding settings.
 * @var object $project  Project record.
 * @var object $approval Approval record.
 * @var string $status   New status.
 * @var string $note     Client response note.
 */

defined( 'ABSPATH' ) || exit;

use WPAgencyHub\Notifications\EmailNotifier;

$is_approved  = 'approved' === $status;
$status_color = $is_approved ? '#34C759' : '#FF3B30';
$status_label = $is_approved
	? __( 'Approved', 'wp-agency-hub' )
	: __( 'Changes Requested', 'wp-agency-hub' );

$content = '<h2 style="margin:0 0 16px;font-size:24px;color:#1a1a1a;">'
	. esc_html( $status_label ) . '</h2>'
	. '<p style="margin:0 0 8px;font-size:14px;color:#636366;">'
	/* translators: %s: project name */
	. sprintf( esc_html__( 'Project: %s', 'wp-agency-hub' ), esc_html( $project->name ) ) . '</p>'
	. '<p style="margin:0 0 16px;font-size:16px;color:#1a1a1a;">'
	. '<strong>' . esc_html( $approval->title ) . '</strong>'
	. ' <span style="display:inline-block;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600;color:#fff;background:'
	. esc_attr( $status_color ) . ';">' . esc_html( $status_label ) . '</span>'
	. '</p>';

if ( ! empty( $note ) ) {
	$content .= '<div style="padding:16px;background:#f5f5f7;border-radius:6px;margin:0 0 24px;">'
		. '<p style="margin:0 0 4px;font-size:12px;font-weight:500;color:#636366;">' . esc_html__( 'Client Note:', 'wp-agency-hub' ) . '</p>'
		. '<p style="margin:0;font-size:16px;color:#1a1a1a;">' . wp_kses_post( nl2br( $note ) ) . '</p>'
		. '</div>';
}

echo EmailNotifier::wrap_email( $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
