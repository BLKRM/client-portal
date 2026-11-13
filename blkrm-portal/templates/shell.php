<?php
/**
 * App shell: black "room" sidebar + header + page content.
 *
 * @package BLKRM\Portal
 *
 * @var array  $ctx           Page context.
 * @var string $page_template Absolute path of the page template.
 */

use BLKRM\Portal\Portal\Pages;
use BLKRM\Portal\Portal\Router;

defined( 'ABSPATH' ) || exit;

$org_name = $ctx['org'] ? $ctx['org']->name : '';
?>
<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'blkrm-portal' ); ?></a>
<div class="app">
	<nav class="side" aria-label="<?php esc_attr_e( 'Main', 'blkrm-portal' ); ?>">
		<a class="side__brand" href="<?php echo esc_url( Router::url() ); ?>">
			<?php echo Pages::logo( 42 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<span class="side__label"><?php esc_html_e( 'Client', 'blkrm-portal' ); ?><br><?php esc_html_e( 'Portal', 'blkrm-portal' ); ?></span>
		</a>
		<ul class="side__nav">
			<?php foreach ( $ctx['nav'] as $key => $item ) : ?>
				<?php $current = $key === $ctx['route']; ?>
				<li>
					<a class="side__link<?php echo $current ? ' is-current' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $current ? ' aria-current="page"' : ''; ?>>
						<?php echo Pages::icon( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<div class="side__foot">
			<?php if ( ! $ctx['is_staff'] ) : ?>
				<span class="eyebrow eyebrow--dark"><?php esc_html_e( 'Got a new idea?', 'blkrm-portal' ); ?></span>
				<a class="btn btn--outline-accent" href="<?php echo esc_url( Router::url( 'requests' ) ); ?>"><?php esc_html_e( 'Start a request', 'blkrm-portal' ); ?></a>
			<?php else : ?>
				<a class="side__admin" href="<?php echo esc_url( admin_url( 'admin.php?page=blkrm-portal' ) ); ?>"><?php esc_html_e( 'Manage clients + projects', 'blkrm-portal' ); ?></a>
			<?php endif; ?>
			<a class="side__signout" href="<?php echo esc_url( $ctx['logout'] ); ?>"><?php esc_html_e( 'Sign out', 'blkrm-portal' ); ?></a>
		</div>
	</nav>

	<div class="main-col">
		<header class="topbar">
			<div class="workspace">
				<?php if ( $ctx['is_staff'] ) : ?>
					<span class="workspace__name"><?php esc_html_e( 'All clients', 'blkrm-portal' ); ?></span>
					<span class="workspace__meta"><?php esc_html_e( 'BLKRM staff view', 'blkrm-portal' ); ?></span>
				<?php else : ?>
					<span class="workspace__mark" aria-hidden="true"><?php echo esc_html( Pages::initials( $org_name ) ); ?></span>
					<span>
						<span class="workspace__name"><?php echo esc_html( $org_name ); ?></span>
						<span class="workspace__meta"><?php esc_html_e( 'Workspace · powered by BLKRM', 'blkrm-portal' ); ?></span>
					</span>
				<?php endif; ?>
			</div>
			<div class="topbar__actions">
				<button type="button" class="icon-btn" data-blkrm-theme-toggle aria-label="<?php esc_attr_e( 'Switch light / dark mode', 'blkrm-portal' ); ?>">
					<span class="only-light"><?php echo Pages::icon( 'moon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="only-dark"><?php echo Pages::icon( 'sun' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</button>
				<span class="avatar" role="img" aria-label="<?php echo esc_attr( $ctx['user']->display_name ); ?>"><?php echo esc_html( $ctx['initials'] ); ?></span>
			</div>
		</header>

		<main id="main" class="content">
			<?php include $page_template; ?>
		</main>
	</div>
</div>
