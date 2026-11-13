<?php
/**
 * New message email notification.
 *
 * @package WPAgencyHub
 *
 * @var object $branding    Branding settings.
 * @var object $project     Project record.
 * @var string $sender_name Sender display name.
 * @var string $message     Message text.
 */

defined( 'ABSPATH' ) || exit;

use WPAgencyHub\Notifications\EmailNotifier;
use WPAgencyHub\Portal\PortalTemplate;

$portal_url = PortalTemplate::portal_url( 'project/' . $project->id . '/messages/' );

$content = '<h2 style="margin:0 0 16px;font-size:24px;color:#1a1a1a;">'
	. esc_html__( 'New Message', 'wp-agency-hub' ) . '</h2>'
	. '<p style="margin:0 0 8px;font-size:14px;color:#636366;">'
	/* translators: %s: project name */
	. sprintf( esc_html__( 'Project: %s', 'wp-agency-hub' ), esc_html( $project->name ) ) . '</p>'
	. '<p style="margin:0 0 16px;font-size:14px;color:#636366;">'
	/* translators: %s: sender name */
	. sprintf( esc_html__( 'From: %s', 'wp-agency-hub' ), esc_html( $sender_name ) ) . '</p>'
	. '<div style="padding:16px;background:#f5f5f7;border-radius:6px;margin:0 0 24px;">'
	. '<p style="margin:0;font-size:16px;color:#1a1a1a;">' . wp_kses_post( nl2br( $message ) ) . '</p>'
	. '</div>'
	. '<a href="' . esc_url( $portal_url ) . '" style="display:inline-block;padding:10px 24px;background:'
	. esc_attr( $branding->primary_color ) . ';color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">'
	. esc_html__( 'View Messages', 'wp-agency-hub' ) . '</a>';

echo EmailNotifier::wrap_email( $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML email built with proper escaping.
