<?php
/**
 * Projects list. Phase 1 replaces this with the Board / Grid views.
 *
 * @package BLKRM\Portal
 *
 * @var array $ctx Context.
 */

use BLKRM\Portal\Data\Projects;
use BLKRM\Portal\Portal\Pages;
use BLKRM\Portal\Portal\Router;

defined( 'ABSPATH' ) || exit;

$stages = Projects::stages();
$types  = Projects::types();
$keys   = array_keys( $stages );
?>
<div class="page-head">
	<div class="stack-sm">
		<span class="eyebrow"><?php echo esc_html( $ctx['is_staff'] ? __( 'ALL CLIENTS', 'blkrm-portal' ) : strtoupper( $ctx['org']->name ) ); ?></span>
		<h1 class="headline"><?php esc_html_e( 'Projects', 'blkrm-portal' ); ?><span class="dot">.</span></h1>
	</div>
</div>

<div class="table-wrap">
	<table class="table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Project', 'blkrm-portal' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Stage', 'blkrm-portal' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Type', 'blkrm-portal' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Due', 'blkrm-portal' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $ctx['projects'] ) : ?>
				<tr><td colspan="4" class="empty"><?php esc_html_e( 'No projects yet.', 'blkrm-portal' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $ctx['projects'] as $p ) : ?>
				<?php $idx = (int) array_search( $p->stage, $keys, true ); ?>
				<tr>
					<td>
						<a class="list__title" href="<?php echo esc_url( Router::url( 'project/' . (int) $p->id ) ); ?>"><?php echo esc_html( $p->name ); ?></a>
						<?php if ( $ctx['is_staff'] ) : ?><div class="muted small"><?php echo esc_html( $p->org_name ); ?></div><?php endif; ?>
					</td>
					<td>
						<span class="steps" aria-hidden="true">
							<?php foreach ( $keys as $i => $k ) : ?>
								<span class="steps__s<?php echo $i < $idx ? ' is-done' : ( $i === $idx ? ' is-current' : '' ); ?>"></span>
							<?php endforeach; ?>
						</span>
						<span class="small strong"><?php echo esc_html( $stages[ $p->stage ] ?? $p->stage ); ?></span>
					</td>
					<td class="muted"><?php echo esc_html( $types[ $p->type ] ?? '' ); ?></td>
					<td class="strong"><?php echo esc_html( Pages::date( $p->due_date ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
