<?php
/**
 * Client dashboard template.
 *
 * @package WPAgencyHub
 *
 * @var array  $projects          Client projects.
 * @var array  $activity          Recent activity entries.
 * @var array  $pending_approvals Pending approval requests.
 * @var int    $unread_messages   Count of unread messages.
 * @var object $client            Client record.
 * @var bool   $is_admin          Whether current user is admin.
 */

defined( 'ABSPATH' ) || exit;

use WPAgencyHub\Portal\PortalTemplate;
?>

<div class="wpah-dashboard">
	<h1 class="wpah-page-title"><?php esc_html_e( 'Dashboard', 'wp-agency-hub' ); ?></h1>

	<?php if ( ! empty( $pending_approvals ) || $unread_messages > 0 ) : ?>
		<div class="wpah-alerts">
			<?php if ( ! empty( $pending_approvals ) ) : ?>
				<div class="wpah-alert wpah-alert--info">
					<?php
					printf(
						/* translators: %d: number of pending approvals */
						esc_html( _n( '%d item needs your approval', '%d items need your approval', count( $pending_approvals ), 'wp-agency-hub' ) ),
						count( $pending_approvals )
					);
					?>
				</div>
			<?php endif; ?>

			<?php if ( $unread_messages > 0 ) : ?>
				<div class="wpah-alert wpah-alert--info">
					<?php
					printf(
						/* translators: %d: number of unread messages */
						esc_html( _n( '%d unread message', '%d unread messages', $unread_messages, 'wp-agency-hub' ) ),
						$unread_messages
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $pending_approvals ) ) : ?>
		<section class="wpah-section">
			<h2 class="wpah-section-title"><?php esc_html_e( 'Pending Approvals', 'wp-agency-hub' ); ?></h2>
			<div class="wpah-card-grid">
				<?php foreach ( $pending_approvals as $approval ) : ?>
					<div class="wpah-card wpah-card--approval">
						<div class="wpah-card__header">
							<span class="wpah-badge status-progress"><?php esc_html_e( 'Pending Review', 'wp-agency-hub' ); ?></span>
						</div>
						<h3 class="wpah-card__title"><?php echo esc_html( $approval->title ); ?></h3>
						<p class="wpah-card__meta"><?php echo esc_html( $approval->project_name ); ?></p>
						<a href="<?php echo esc_url( PortalTemplate::portal_url( 'project/' . $approval->project_id . '/approval/' . $approval->id . '/' ) ); ?>" class="wpah-btn wpah-btn--primary wpah-btn--sm"><?php esc_html_e( 'Review', 'wp-agency-hub' ); ?></a>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="wpah-section">
		<h2 class="wpah-section-title"><?php esc_html_e( 'Projects', 'wp-agency-hub' ); ?></h2>

		<?php if ( empty( $projects ) ) : ?>
			<p class="wpah-empty"><?php esc_html_e( 'No projects yet.', 'wp-agency-hub' ); ?></p>
		<?php else : ?>
			<div class="wpah-card-grid">
				<?php foreach ( $projects as $project ) : ?>
					<a href="<?php echo esc_url( PortalTemplate::portal_url( 'project/' . $project->id . '/' ) ); ?>" class="wpah-card wpah-card--project">
						<div class="wpah-card__header">
							<span class="wpah-badge <?php echo esc_attr( PortalTemplate::status_class( $project->status ) ); ?>">
								<?php echo esc_html( PortalTemplate::status_label( $project->status ) ); ?>
							</span>
						</div>
						<h3 class="wpah-card__title"><?php echo esc_html( $project->name ); ?></h3>
						<?php if ( ! empty( $project->description ) ) : ?>
							<p class="wpah-card__desc"><?php echo esc_html( wp_trim_words( $project->description, 20 ) ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $project->due_date ) ) : ?>
							<p class="wpah-card__meta">
								<?php
								printf(
									/* translators: %s: due date */
									esc_html__( 'Due: %s', 'wp-agency-hub' ),
									esc_html( wp_date( get_option( 'date_format' ), strtotime( $project->due_date ) ) )
								);
								?>
							</p>
						<?php endif; ?>
						<p class="wpah-card__footer">
							<?php
							printf(
								/* translators: %s: relative time */
								esc_html__( 'Updated %s', 'wp-agency-hub' ),
								esc_html( human_time_diff( strtotime( $project->updated_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'wp-agency-hub' ) )
							);
							?>
						</p>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>

	<?php if ( ! empty( $activity ) ) : ?>
		<section class="wpah-section">
			<h2 class="wpah-section-title"><?php esc_html_e( 'Recent Activity', 'wp-agency-hub' ); ?></h2>
			<div class="wpah-activity-feed">
				<?php foreach ( $activity as $entry ) : ?>
					<div class="wpah-activity-item">
						<div class="wpah-activity-item__dot"></div>
						<div class="wpah-activity-item__content">
							<span class="wpah-activity-item__user"><?php echo esc_html( $entry->user_name ); ?></span>
							<span class="wpah-activity-item__action"><?php echo esc_html( str_replace( '_', ' ', $entry->action ) ); ?></span>
							<span class="wpah-activity-item__project"><?php echo esc_html( $entry->project_name ); ?></span>
							<time class="wpah-activity-item__time"><?php echo esc_html( human_time_diff( strtotime( $entry->created_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'wp-agency-hub' ) ); ?></time>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
</div>
