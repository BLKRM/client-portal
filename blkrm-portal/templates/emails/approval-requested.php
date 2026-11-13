<?php
/**
 * Approval requested email notification.
 *
 * @package WPAgencyHub
 *
 * @var object $branding Branding settings.
 * @var object $project  Project record.
 * @var object $approval Approval record.
 */

defined( 'ABSPATH' ) || exit;

use WPAgencyHub\Notifications\EmailNotifier;
use WPAgencyHub\Portal\PortalTemplate;

$portal_url = PortalTemplate::portal_url( 'project/' . $project->id . '/approval/' . $approval->id . '/' );

$content = '<h2 style="margin:0 0 16px;font-size:24px;color:#1a1a1a;">'
	. esc_html__( 'Approval Requested', 'wp-agency-hub' ) . '</h2>'
	. '<p style="margin:0 0 8px;font-size:14px;color:#636366;">'
	/* translators: %s: project name */
	. sprintf( esc_html__( 'Project: %s', 'wp-agency-hub' ), esc_html( $project->name ) ) . '</p>'
	. '<p style="margin:0 0 24px;font-size:16px;color:#1a1a1a;">'
	/* translators: %s: deliverable title */
	. sprintf( esc_html__( 'A deliverable requires your approval: %s', 'wp-agency-hub' ), '<strong>' . esc_html( $approval->title ) . '</strong>' ) . '</p>'
	. '<a href="' . esc_url( $portal_url ) . '" style="display:inline-block;padding:10px 24px;background:'
	. esc_attr( $branding->primary_color ) . ';color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">'
	. esc_html__( 'Review Now', 'wp-agency-hub' ) . '</a>';

echo EmailNotifier::wrap_email( $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
