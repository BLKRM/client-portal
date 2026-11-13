<?php
/**
 * Placeholder for sections built in later phases.
 *
 * @package BLKRM\Portal
 *
 * @var array $ctx Context.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="page-head">
	<div class="stack-sm">
		<span class="eyebrow"><?php esc_html_e( 'COMING SOON', 'blkrm-portal' ); ?></span>
		<h1 class="headline"><?php echo esc_html( strtoupper( $ctx['title'] ) ); ?><span class="dot">.</span></h1>
	</div>
</div>
<section class="panel panel--pad">
	<p><?php esc_html_e( 'This section is being built. Until then, reach your BLKRM lead directly with anything you need.', 'blkrm-portal' ); ?></p>
</section>
