<?php
/**
 * All files across visible projects. Phase 4 adds folders, the Brand
 * Library, previews and version history here.
 *
 * @package BLKRM\Portal
 *
 * @var array $ctx Context.
 */

use BLKRM\Portal\Portal\Pages;
use BLKRM\Portal\Portal\Router;

defined( 'ABSPATH' ) || exit;

$total = 0;
foreach ( $ctx['files'] as $f ) {
	$total += (int) $f->size;
}
?>
<div class="page-head">
	<div class="stack-sm">
		<span class="eyebrow"><?php echo esc_html( sprintf( /* translators: 1: count, 2: size */ __( '%1$d FILES · %2$s', 'blkrm-portal' ), count( $ctx['files'] ), size_format( $total, 1 ) ) ); ?></span>
		<h1 class="headline"><?php esc_html_e( 'Files + assets', 'blkrm-portal' ); ?><span class="dot">.</span></h1>
	</div>
</div>

<section class="panel">
	<?php if ( ! $ctx['files'] ) : ?>
		<p class="empty"><?php esc_html_e( 'No files yet. Open a project to upload.', 'blkrm-portal' ); ?></p>
	<?php else : ?>
		<ul class="files">
			<?php foreach ( $ctx['files'] as $f ) : ?>
				<?php $ext = strtoupper( (string) pathinfo( $f->name, PATHINFO_EXTENSION ) ); ?>
				<li class="file">
					<span class="file__ext" aria-hidden="true"><?php echo esc_html( substr( $ext, 0, 4 ) ); ?></span>
					<span class="file__info">
						<span class="file__name"><?php echo esc_html( $f->name ); ?></span>
						<span class="muted small">
							<a class="link" href="<?php echo esc_url( Router::url( 'project/' . (int) $f->project_id ) ); ?>"><?php echo esc_html( (string) $f->project_name ); ?></a>
							<?php echo esc_html( ' · v' . (int) $f->version . ' · ' . size_format( (int) $f->size, 1 ) . ' · ' . mysql2date( 'M j, Y', get_date_from_gmt( $f->updated_at ) ) ); ?>
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
