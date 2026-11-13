<?php
/**
 * Message thread template.
 *
 * @package WPAgencyHub
 *
 * @var object $project  Project record.
 * @var array  $messages Message records.
 * @var object $client   Client record.
 * @var bool   $is_admin Whether current user is admin.
 */

defined( 'ABSPATH' ) || exit;

use WPAgencyHub\Portal\PortalTemplate;

$current_user_id = get_current_user_id();
?>

<div class="wpah-messages">
	<div class="wpah-messages__header">
		<a href="<?php echo esc_url( PortalTemplate::portal_url( 'project/' . $project->id . '/' ) ); ?>" class="wpah-back-link">&larr; <?php echo esc_html( $project->name ); ?></a>
		<h1 class="wpah-page-title"><?php esc_html_e( 'Messages', 'wp-agency-hub' ); ?></h1>
	</div>

	<div class="wpah-message-thread" id="wpah-message-thread" data-project-id="<?php echo esc_attr( $project->id ); ?>">
		<?php if ( empty( $messages ) ) : ?>
			<p class="wpah-empty"><?php esc_html_e( 'No messages yet. Start the conversation below.', 'wp-agency-hub' ); ?></p>
		<?php else : ?>
			<?php foreach ( $messages as $msg ) : ?>
				<div class="wpah-message <?php echo (int) $msg->sender_id === $current_user_id ? 'wpah-message--own' : 'wpah-message--other'; ?>">
					<div class="wpah-message__header">
						<span class="wpah-message__sender"><?php echo esc_html( $msg->sender_name ); ?></span>
						<time class="wpah-message__time"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $msg->created_at ) ) ); ?></time>
					</div>
					<div class="wpah-message__body">
						<?php echo wp_kses_post( wpautop( $msg->message ) ); ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<form class="wpah-message-form" id="wpah-message-form">
		<?php wp_nonce_field( 'wp_rest', '_wpnonce' ); ?>
		<textarea name="message" class="wpah-message-form__input" placeholder="<?php esc_attr_e( 'Type your message...', 'wp-agency-hub' ); ?>" required rows="3"></textarea>
		<button type="submit" class="wpah-btn wpah-btn--primary"><?php esc_html_e( 'Send Message', 'wp-agency-hub' ); ?></button>
	</form>
</div>
