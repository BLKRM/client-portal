<?php
/**
 * Project detail. Phase 0 ships the stage tracker and the secure Files panel
 * (chunked encrypted uploads + signed downloads). Timeline, to-dos and
 * feedback tabs arrive in later phases.
 *
 * @package BLKRM\Portal
 *
 * @var array $ctx Context.
 */

use BLKRM\Portal\Data\Orgs;
use BLKRM\Portal\Data\Projects;
use BLKRM\Portal\Portal\Pages;
use BLKRM\Portal\Portal\Router;

defined( 'ABSPATH' ) || exit;

$project = $ctx['project'];
$stages  = Projects::stages();
$types   = Projects::types();
$keys    = array_keys( $stages );
$idx     = (int) array_search( $project->stage, $keys, true );
$org     = Orgs::get( (int) $project->org_id );
?>
<a class="back" href="<?php echo esc_url( Router::url( 'projects' ) ); ?>"><?php echo Pages::icon( 'back', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'All projects', 'blkrm-portal' ); ?></a>

<div class="page-head">
	<div class="stack-sm">
		<span class="eyebrow"><?php echo esc_html( strtoupper( ( $org ? $org->name : '' ) . ' · ' . ( $types[ $project->type ] ?? '' ) ) ); ?></span>
		<h1 class="headline"><?php echo esc_html( strtoupper( $project->name ) ); ?><span class="dot">.</span></h1>
		<p class="meta">
			<?php esc_html_e( 'Due', 'blkrm-portal' ); ?> <strong><?php echo esc_html( Pages::date( $project->due_date ) ); ?></strong>
			<?php if ( $project->start_date ) : ?>
				&nbsp;·&nbsp; <?php esc_html_e( 'Started', 'blkrm-portal' ); ?> <strong><?php echo esc_html( Pages::date( $project->start_date ) ); ?></strong>
			<?php endif; ?>
		</p>
	</div>
</div>

<ol class="tracker" aria-label="<?php esc_attr_e( 'Project stages', 'blkrm-portal' ); ?>">
	<?php foreach ( $keys as $i => $k ) : ?>
		<li class="tracker__s<?php echo $i < $idx ? ' is-done' : ( $i === $idx ? ' is-current' : '' ); ?>"<?php echo $i === $idx ? ' aria-current="step"' : ''; ?>>
			<span class="tracker__bar"></span>
			<span class="tracker__label"><?php echo esc_html( sprintf( '%02d', $i + 1 ) . '  ' . $stages[ $k ] ); ?></span>
		</li>
	<?php endforeach; ?>
</ol>

<?php if ( $project->description ) : ?>
	<section class="panel panel--pad">
		<h2 class="title"><?php esc_html_e( 'The brief', 'blkrm-portal' ); ?><span class="dot">.</span></h2>
		<div class="prose"><?php echo wp_kses_post( wpautop( $project->description ) ); ?></div>
	</section>
<?php endif; ?>

<section class="panel" aria-labelledby="files-title">
	<div class="panel__head">
		<h2 id="files-title" class="title"><?php esc_html_e( 'Files', 'blkrm-portal' ); ?><span class="dot">.</span></h2>
		<span class="muted small"><?php echo Pages::icon( 'lock', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Encrypted · links expire after 15 minutes', 'blkrm-portal' ); ?></span>
	</div>

	<div class="uploader" data-blkrm-uploader data-project-id="<?php echo (int) $project->id; ?>">
		<label class="dropzone">
			<input type="file" multiple class="sr-only" data-blkrm-file-input>
			<?php echo Pages::icon( 'upload', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span class="dropzone__title"><?php esc_html_e( 'Drop files here, or browse', 'blkrm-portal' ); ?></span>
			<span class="muted small">
				<?php
				printf(
					/* translators: %s: max upload size */
					esc_html__( 'Up to %s per file · PDF, AI, PSD, INDD, TIFF, ZIP + more · uploads resume if your connection drops', 'blkrm-portal' ),
					esc_html( size_format( \BLKRM\Portal\Files\UploadService::max_file_bytes() ) )
				);
				?>
			</span>
		</label>
		<ul class="uploads" data-blkrm-upload-list aria-live="polite"></ul>
	</div>

	<?php if ( ! $ctx['files'] ) : ?>
		<p class="empty"><?php esc_html_e( 'No files yet.', 'blkrm-portal' ); ?></p>
	<?php else : ?>
		<ul class="files">
			<?php foreach ( $ctx['files'] as $f ) : ?>
				<?php $ext = strtoupper( (string) pathinfo( $f->name, PATHINFO_EXTENSION ) ); ?>
				<li class="file">
					<span class="file__ext" aria-hidden="true"><?php echo esc_html( substr( $ext, 0, 4 ) ); ?></span>
					<span class="file__info">
						<span class="file__name"><?php echo esc_html( $f->name ); ?></span>
						<span class="muted small">
							<?php echo esc_html( 'v' . (int) $f->version . ' · ' . size_format( (int) $f->size, 1 ) . ' · ' . $f->uploader_name . ' · ' . mysql2date( 'M j, Y', get_date_from_gmt( $f->updated_at ) ) ); ?>
						</span>
					</span>
					<button type="button" class="btn btn--ghost" data-blkrm-download="<?php echo (int) $f->id; ?>">
						<?php echo Pages::icon( 'down', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Download', 'blkrm-portal' ); ?>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
