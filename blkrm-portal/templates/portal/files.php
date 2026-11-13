<?php
/**
 * File manager template.
 *
 * @package WPAgencyHub
 *
 * @var object $project  Project record.
 * @var array  $files    File records.
 * @var object $client   Client record.
 * @var bool   $is_admin Whether current user is admin.
 */

defined( 'ABSPATH' ) || exit;

use WPAgencyHub\Portal\PortalTemplate;
?>

<div class="wpah-files">
	<div class="wpah-files__header">
		<a href="<?php echo esc_url( PortalTemplate::portal_url( 'project/' . $project->id . '/' ) ); ?>" class="wpah-back-link">&larr; <?php echo esc_html( $project->name ); ?></a>
		<h1 class="wpah-page-title"><?php esc_html_e( 'Files', 'wp-agency-hub' ); ?></h1>
	</div>

	<div class="wpah-file-upload" id="wpah-file-upload">
		<div class="wpah-file-upload__dropzone" id="wpah-dropzone">
			<p class="wpah-file-upload__text"><?php esc_html_e( 'Drag and drop files here, or click to browse', 'wp-agency-hub' ); ?></p>
			<p class="wpah-file-upload__hint">
				<?php
				printf(
					/* translators: %s: maximum file size */
					esc_html__( 'Maximum file size: %s', 'wp-agency-hub' ),
					esc_html( size_format( WPAH_MAX_FILE_SIZE ) )
				);
				?>
			</p>
			<input type="file" id="wpah-file-input" class="wpah-file-upload__input" data-project-id="<?php echo esc_attr( $project->id ); ?>">
		</div>
		<div class="wpah-file-upload__progress" id="wpah-upload-progress" style="display:none;">
			<div class="wpah-progress-bar">
				<div class="wpah-progress-bar__fill" id="wpah-progress-fill"></div>
			</div>
			<p class="wpah-file-upload__status" id="wpah-upload-status"></p>
		</div>
	</div>

	<?php if ( empty( $files ) ) : ?>
		<p class="wpah-empty"><?php esc_html_e( 'No files uploaded yet.', 'wp-agency-hub' ); ?></p>
	<?php else : ?>
		<div class="wpah-file-list">
			<?php foreach ( $files as $file ) : ?>
				<div class="wpah-file-item">
					<div class="wpah-file-item__icon">
						<?php
						$icon = 'doc';
						if ( strpos( $file->mime_type, 'image/' ) === 0 ) {
							$icon = 'img';
						} elseif ( 'application/pdf' === $file->mime_type ) {
							$icon = 'pdf';
						} elseif ( strpos( $file->mime_type, 'video/' ) === 0 ) {
							$icon = 'vid';
						}
						?>
						<span class="wpah-file-icon wpah-file-icon--<?php echo esc_attr( $icon ); ?>"><?php echo esc_html( strtoupper( $icon ) ); ?></span>
					</div>
					<div class="wpah-file-item__info">
						<span class="wpah-file-item__name"><?php echo esc_html( $file->file_name ); ?></span>
						<span class="wpah-file-item__meta">
							<?php echo esc_html( size_format( $file->file_size ) ); ?>
							&middot;
							<?php echo esc_html( $file->uploader_name ); ?>
							&middot;
							<?php echo esc_html( human_time_diff( strtotime( $file->created_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'wp-agency-hub' ) ); ?>
						</span>
					</div>
					<div class="wpah-file-item__actions">
						<a href="<?php echo esc_url( rest_url( 'wp-agency-hub/v1/files/' . $file->id . '/download' ) ); ?>" class="wpah-btn wpah-btn--sm wpah-btn--secondary"><?php esc_html_e( 'Download', 'wp-agency-hub' ); ?></a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
