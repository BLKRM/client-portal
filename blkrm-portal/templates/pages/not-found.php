<?php
/**
 * Not found (also used for projects the user can't see, so their existence isn't revealed).
 *
 * @package BLKRM\Portal
 */

use BLKRM\Portal\Portal\Router;

defined( 'ABSPATH' ) || exit;
?>
<div class="page-head">
	<div class="stack-sm">
		<span class="eyebrow">404</span>
		<h1 class="headline"><?php esc_html_e( 'Not here', 'blkrm-portal' ); ?><span class="dot">.</span></h1>
	</div>
</div>
<p><a class="btn btn--accent" href="<?php echo esc_url( Router::url() ); ?>"><?php esc_html_e( 'Back to home', 'blkrm-portal' ); ?></a></p>
