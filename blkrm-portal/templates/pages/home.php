<?php
/**
 * Home. Phase 0: greeting, stats and active projects. The calendar, to-dos
 * and activity feed arrive in Phase 2.
 *
 * @package BLKRM\Portal
 *
 * @var array $ctx Context.
 */

use BLKRM\Portal\Data\Projects;
use BLKRM\Portal\Portal\Pages;
use BLKRM\Portal\Portal\Router;

defined( 'ABSPATH' ) || exit;

$stages   = Projects::stages();
$active   = array_filter( $ctx['projects'], static function ( $p ) { return 'delivered' !== $p->stage; } );
$first    = strtok( (string) $ctx['user']->display_name, ' ' );
$hour     = (int) current_time( 'G' );
$greeting = $hour < 12 ? __( 'Good morning', 'blkrm-portal' ) : ( $hour < 18 ? __( 'Good afternoon', 'blkrm-portal' ) : __( 'Good evening', 'blkrm-portal' ) );
?>
<div class="page-head">
	<div class="stack-sm">
		<span class="eyebrow"><?php echo esc_html( strtoupper( date_i18n( 'l · F j, Y' ) ) ); ?></span>
		<h1 class="headline"><?php echo esc_html( strtoupper( $greeting . ', ' . $first ) ); ?><span class="dot">.</span></h1>
	</div>
	<div class="stats">
		<div class="stat"><span class="stat__n"><?php echo (int) count( $active ); ?></span><span class="stat__l"><?php esc_html_e( 'Active projects', 'blkrm-portal' ); ?></span></div>
		<div class="stat"><span class="stat__n"><?php echo (int) ( count( $ctx['projects'] ) - count( $active ) ); ?></span><span class="stat__l"><?php esc_html_e( 'Delivered', 'blkrm-portal' ); ?></span></div>
	</div>
</div>

<section class="panel" aria-labelledby="home-projects">
	<div class="panel__head">
		<h2 id="home-projects" class="title"><?php esc_html_e( 'Active projects', 'blkrm-portal' ); ?><span class="dot">.</span></h2>
		<a class="link" href="<?php echo esc_url( Router::url( 'projects' ) ); ?>"><?php esc_html_e( 'All projects →', 'blkrm-portal' ); ?></a>
	</div>
	<?php if ( ! $active ) : ?>
		<p class="empty"><?php esc_html_e( 'No active projects yet.', 'blkrm-portal' ); ?></p>
	<?php else : ?>
		<ul class="list">
			<?php foreach ( $active as $p ) : ?>
				<li class="list__row">
					<a class="list__title" href="<?php echo esc_url( Router::url( 'project/' . (int) $p->id ) ); ?>"><?php echo esc_html( $p->name ); ?></a>
					<span class="muted"><?php echo esc_html( $ctx['is_staff'] ? $p->org_name . ' · ' : '' ); ?><?php echo esc_html( $stages[ $p->stage ] ?? $p->stage ); ?></span>
					<span class="muted"><?php echo esc_html( __( 'Due', 'blkrm-portal' ) . ' ' . Pages::date( $p->due_date ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<p class="muted small"><?php esc_html_e( 'Coming next: your calendar of milestones, to-dos and recent activity.', 'blkrm-portal' ); ?></p>
