<?php
/**
 * Approval detail template.
 *
 * @package WPAgencyHub
 *
 * @var object $project  Project record.
 * @var object $approval Approval record with file info.
 * @var array  $history  Approval history from activity log.
 * @var object $client   Client record.
 * @var bool   $is_admin Whether current user is admin.
 */

defined( 'ABSPATH' ) || exit;

use WPAgencyHub\Portal\PortalTemplate;
?>

<div class="wpah-approval">
	<div class="wpah-approval__header">
		<a href="<?php echo esc_url( PortalTemplate::portal_url( 'project/' . $project->id . '/' ) ); ?>" class="wpah-back-link">&larr; <?php echo esc_html( $project->name ); ?></a>
		<h1 class="wpah-page-title"><?php echo esc_html( $approval->title ); ?></h1>
		<span class="wpah-badge wpah-badge--lg <?php echo esc_attr( PortalTemplate::status_class( $approval->status ) ); ?>">
			<?php echo esc_html( PortalTemplate::status_label( $approval->status ) ); ?>
		</span>
	</div>

	<?php if ( ! empty( $approval->file_name ) ) : ?>
		<div class="wpah-approval__file">
			<div class="wpah-approval__preview">
				<?php if ( $approval->mime_type && strpos( $approval->mime_type, 'image/' ) === 0 ) : ?>
					<?php
					$upload_dir = wp_upload_dir();
					$file_url   = $upload_dir['baseurl'] . '/' . $approval->file_path;
					?>
					<img src="<?php echo esc_url( rest_url( 'wp-agency-hub/v1/files/' . $approval->file_id . '/download' ) ); ?>" alt="<?php echo esc_attr( $approval->file_name ); ?>" class="wpah-approval__image">
				<?php else : ?>
					<div class="wpah-approval__file-info">
						<span class="wpah-file-icon wpah-file-icon--doc"><?php echo esc_html( strtoupper( pathinfo( $approval->file_name, PATHINFO_EXTENSION ) ) ); ?></span>
						<span><?php echo esc_html( $approval->file_name ); ?></span>
					</div>
				<?php endif; ?>
			</div>
			<a href="<?php echo esc_url( rest_url( 'wp-agency-hub/v1/files/' . $approval->file_id . '/download' ) ); ?>" class="wpah-btn wpah-btn--secondary wpah-btn--sm"><?php esc_html_e( 'Download File', 'wp-agency-hub' ); ?></a>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $approval->response_note ) ) : ?>
		<div class="wpah-approval__note">
			<h3><?php esc_html_e( 'Response Note', 'wp-agency-hub' ); ?></h3>
			<?php echo wp_kses_post( wpautop( $approval->response_note ) ); ?>
		</div>
	<?php endif; ?>

	<?php if ( 'pending_review' === $approval->status ) : ?>
		<div class="wpah-approval__actions" id="wpah-approval-actions" data-approval-id="<?php echo esc_attr( $approval->id ); ?>">
			<h3><?php esc_html_e( 'Your Decision', 'wp-agency-hub' ); ?></h3>
			<textarea id="wpah-approval-note" class="wpah-message-form__input" placeholder="<?php esc_attr_e( 'Add a note (optional)...', 'wp-agency-hub' ); ?>" rows="3"></textarea>
			<div class="wpah-approval__buttons">
				<button type="button" class="wpah-btn wpah-btn--primary wpah-btn--lg" id="wpah-approve-btn">
					<?php esc_html_e( 'Approve', 'wp-agency-hub' ); ?>
				</button>
				<button type="button" class="wpah-btn wpah-btn--danger wpah-btn--lg" id="wpah-request-changes-btn">
					<?php esc_html_e( 'Request Changes', 'wp-agency-hub' ); ?>
				</button>
			</div>
		</div>
	<?php elseif ( 'draft' === $approval->status && $is_admin ) : ?>
		<div class="wpah-approval__actions" id="wpah-approval-actions" data-approval-id="<?php echo esc_attr( $approval->id ); ?>">
			<button type="button" class="wpah-btn wpah-btn--primary" id="wpah-submit-review-btn">
				<?php esc_html_e( 'Submit for Client Review', 'wp-agency-hub' ); ?>
			</button>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $history ) ) : ?>
		<section class="wpah-section">
			<h2 class="wpah-section-title"><?php esc_html_e( 'Approval History', 'wp-agency-hub' ); ?></h2>
			<div class="wpah-activity-feed">
				<?php foreach ( $history as $entry ) : ?>
					<div class="wpah-activity-item">
						<div class="wpah-activity-item__dot"></div>
						<div class="wpah-activity-item__content">
							<span class="wpah-activity-item__user"><?php echo esc_html( $entry->user_name ); ?></span>
							<span class="wpah-activity-item__action"><?php echo esc_html( str_replace( '_', ' ', $entry->action ) ); ?></span>
							<time class="wpah-activity-item__time"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at ) ) ); ?></time>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
</div>
