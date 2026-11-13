<?php
/**
 * Project detail template.
 *
 * @package WPAgencyHub
 *
 * @var object $project          Project record.
 * @var array  $milestones       Project milestones.
 * @var array  $recent_files     Recent files.
 * @var array  $recent_messages  Recent messages.
 * @var array  $approvals        Approval requests.
 * @var array  $activity         Activity log.
 * @var object $client           Client record.
 * @var bool   $is_admin         Whether current user is admin.
 */

defined( 'ABSPATH' ) || exit;

use WPAgencyHub\Portal\PortalTemplate;
?>

<div class="wpah-project">
	<div class="wpah-project__header">
		<a href="<?php echo esc_url( PortalTemplate::portal_url() ); ?>" class="wpah-back-link">&larr; <?php esc_html_e( 'Back to Dashboard', 'wp-agency-hub' ); ?></a>
		<h1 class="wpah-page-title"><?php echo esc_html( $project->name ); ?></h1>
		<span class="wpah-badge wpah-badge--lg <?php echo esc_attr( PortalTemplate::status_class( $project->status ) ); ?>">
			<?php echo esc_html( PortalTemplate::status_label( $project->status ) ); ?>
		</span>
	</div>

	<?php if ( ! empty( $project->description ) ) : ?>
		<div class="wpah-project__description">
			<?php echo wp_kses_post( wpautop( $project->description ) ); ?>
		</div>
	<?php endif; ?>

	<div class="wpah-project__meta">
		<?php if ( ! empty( $project->due_date ) ) : ?>
			<div class="wpah-meta-item">
				<span class="wpah-meta-item__label"><?php esc_html_e( 'Due Date', 'wp-agency-hub' ); ?></span>
				<span class="wpah-meta-item__value"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $project->due_date ) ) ); ?></span>
			</div>
		<?php endif; ?>
		<div class="wpah-meta-item">
			<span class="wpah-meta-item__label"><?php esc_html_e( 'Last Updated', 'wp-agency-hub' ); ?></span>
			<span class="wpah-meta-item__value"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $project->updated_at ) ) ); ?></span>
		</div>
	</div>

	<?php if ( ! empty( $milestones ) ) : ?>
		<section class="wpah-section">
			<h2 class="wpah-section-title"><?php esc_html_e( 'Milestones', 'wp-agency-hub' ); ?></h2>
			<div class="wpah-milestones">
				<?php foreach ( $milestones as $milestone ) : ?>
					<div class="wpah-milestone <?php echo 'complete' === $milestone->status ? 'wpah-milestone--complete' : ''; ?>">
						<div class="wpah-milestone__indicator"></div>
						<div class="wpah-milestone__content">
							<span class="wpah-milestone__name"><?php echo esc_html( $milestone->name ); ?></span>
							<span class="wpah-badge wpah-badge--sm <?php echo esc_attr( PortalTemplate::status_class( $milestone->status ) ); ?>">
								<?php echo esc_html( PortalTemplate::status_label( $milestone->status ) ); ?>
							</span>
							<?php if ( ! empty( $milestone->due_date ) ) : ?>
								<span class="wpah-milestone__date"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $milestone->due_date ) ) ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $approvals ) ) : ?>
		<section class="wpah-section">
			<h2 class="wpah-section-title"><?php esc_html_e( 'Approvals', 'wp-agency-hub' ); ?></h2>
			<div class="wpah-card-grid">
				<?php foreach ( $approvals as $approval ) : ?>
					<div class="wpah-card wpah-card--approval">
						<div class="wpah-card__header">
							<span class="wpah-badge <?php echo esc_attr( PortalTemplate::status_class( $approval->status ) ); ?>">
								<?php echo esc_html( PortalTemplate::status_label( $approval->status ) ); ?>
							</span>
						</div>
						<h3 class="wpah-card__title"><?php echo esc_html( $approval->title ); ?></h3>
						<?php if ( 'pending_review' === $approval->status ) : ?>
							<a href="<?php echo esc_url( PortalTemplate::portal_url( 'project/' . $project->id . '/approval/' . $approval->id . '/' ) ); ?>" class="wpah-btn wpah-btn--primary wpah-btn--sm"><?php esc_html_e( 'Review', 'wp-agency-hub' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<div class="wpah-project__actions">
		<a href="<?php echo esc_url( PortalTemplate::portal_url( 'project/' . $project->id . '/files/' ) ); ?>" class="wpah-btn wpah-btn--secondary">
			<?php
			printf(
				/* translators: %d: file count */
				esc_html__( 'Files (%d)', 'wp-agency-hub' ),
				count( $recent_files )
			);
			?>
		</a>
		<a href="<?php echo esc_url( PortalTemplate::portal_url( 'project/' . $project->id . '/messages/' ) ); ?>" class="wpah-btn wpah-btn--secondary">
			<?php
			printf(
				/* translators: %d: message count */
				esc_html__( 'Messages (%d)', 'wp-agency-hub' ),
				count( $recent_messages )
			);
			?>
		</a>
	</div>

	<?php if ( ! empty( $activity ) ) : ?>
		<section class="wpah-section">
			<h2 class="wpah-section-title"><?php esc_html_e( 'Activity', 'wp-agency-hub' ); ?></h2>
			<div class="wpah-activity-feed">
				<?php foreach ( $activity as $entry ) : ?>
					<div class="wpah-activity-item">
						<div class="wpah-activity-item__dot"></div>
						<div class="wpah-activity-item__content">
							<span class="wpah-activity-item__user"><?php echo esc_html( $entry->user_name ); ?></span>
							<span class="wpah-activity-item__action"><?php echo esc_html( str_replace( '_', ' ', $entry->action ) ); ?></span>
							<time class="wpah-activity-item__time"><?php echo esc_html( human_time_diff( strtotime( $entry->created_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'wp-agency-hub' ) ); ?></time>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
</div>
