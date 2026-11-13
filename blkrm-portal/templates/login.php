<?php
/**
 * Login screen (approved mockup v1): black panel with yellow keys, sign-in
 * form on the right. Client logo appears only when the URL names an org.
 *
 * @package BLKRM\Portal
 *
 * @var object|null $org      Client org for co-branding.
 * @var string      $logo_url Client logo URL.
 * @var string      $action   Form action URL.
 * @var string      $error    Error message.
 * @var string      $username Prefilled username.
 */

use BLKRM\Portal\Portal\Pages;

defined( 'ABSPATH' ) || exit;
?>
<div class="login">
	<section class="login__room" aria-label="BLKRM">
		<svg class="login__keys" aria-hidden="true" viewBox="0 0 1466.8 1408.3" fill="currentColor"><path d="M633.93,659.07l-203.38-173.96-1.6,1.18c-45.77,33.7-100.25,51.51-157.55,51.51-71.17,0-138.07-27.71-188.37-78.03C32.71,409.46,5,342.57,5,271.4s27.71-138.05,78.03-188.37C133.34,32.71,200.24,5,271.4,5s138.05,27.71,188.37,78.03c50.31,50.32,78.02,117.22,78.02,188.37,0,50.78-14.23,100.02-41.14,142.41l-1.16,1.83,212.54,181.8c.15-.22.28-.4.35-.52.81-1.25,1.63-2.47,2.46-3.66l-208.81-178.61c26.67-42.77,40.76-92.25,40.76-143.25,0-72.49-28.23-140.64-79.48-191.91C412.04,28.23,343.88,0,271.4,0S130.75,28.23,79.49,79.49C28.23,130.75,0,198.91,0,271.4s28.23,140.66,79.49,191.91c51.25,51.26,119.41,79.49,191.91,79.49,57.71,0,112.61-17.73,158.91-51.31l200.05,171.11c1.19-1.18,2.37-2.36,3.57-3.53Z"/><path d="M100,271.4c0,94.51,76.89,171.4,171.4,171.4s171.39-76.89,171.39-171.4-76.89-171.4-171.39-171.4-171.4,76.89-171.4,171.4ZM437.79,271.4c0,91.75-74.64,166.4-166.39,166.4s-166.4-74.65-166.4-166.4,74.65-166.4,166.4-166.4,166.39,74.65,166.39,166.4Z"/><path d="M830.42,695.55c-1.11.6-2.2,1.22-3.27,1.89-.42.26-.83.55-1.25.82l624.88,534.5-61.750,72.2-110.5-94.51-164.72,190.8-71.9-62.08,162.79-188.57,1.64-1.9-79.77-68.23-117.58,134.04-71.42-62.64,116.8-133.17-297.88-254.79c-1.23,1.11-2.45,2.22-3.67,3.34-.02.02-.04.04-.06.06l294.51,251.91-116.76,133.12,78.94,69.24,117.62-134.09,72.21,61.76-164.43,190.45,79.48,68.62,164.74-190.82,110.5,94.51,68.25-79.78-627.4-536.66Z"/><path d="M1195.4,100c-94.5,0-171.39,76.89-171.39,171.4s76.89,171.4,171.39,171.4,171.4-76.89,171.4-171.4-76.89-171.4-171.4-171.4ZM1195.4,437.8c-91.75,0-166.39-74.65-166.39-166.4s74.64-166.4,166.39-166.4,166.4,74.65,166.4,166.4-74.65,166.4-166.4,166.4ZM1387.31,79.49C1336.05,28.23,1267.89,0,1195.4,0s-140.64,28.23-191.91,79.49c-51.25,51.27-79.48,119.42-79.48,191.91,0,51,14.09,100.48,40.76,143.25l-231.37,197.91-3.85,3.29-73.07,62.51-3.85,3.29L8.98,1232.21l68.25,79.78,110.5-94.51,164.74,190.82,79.48-68.62-164.43-190.45,72.21-61.76,117.62,134.09,78.94-69.24-116.76-133.12,313.87-268.47,3.85-3.29,73.08-62.5,3.84-3.29,222.32-190.16c46.3,33.58,101.2,51.31,158.91,51.31,72.5,0,140.66-28.23,191.91-79.49,51.26-51.25,79.49-119.41,79.49-191.91s-28.23-140.65-79.49-191.91ZM1383.77,459.77c-50.31,50.32-117.2,78.03-188.37,78.03-57.3,0-111.78-17.81-157.55-51.51l-1.6-1.18-225.93,193.25-3.84,3.28-73.08,62.51-3.85,3.29-317.12,271.25,116.8,133.17-71.42,62.64-117.58-134.04-79.77,68.23,164.43,190.47-71.9,62.08-164.72-190.8-110.5,94.51-61.75-72.2,640.46-547.81,3.85-3.29,73.07-62.51,3.85-3.29,232.42-198.8,1.64-1.41-1.16-1.83c-26.91-42.39-41.14-91.63-41.14-142.41,0-71.15,27.71-138.05,78.02-188.37,50.32-50.32,117.22-78.03,188.37-78.03s138.06,27.71,188.37,78.03c50.32,50.31,78.03,117.21,78.03,188.37s-27.71,138.07-78.03,188.37Z"/></svg>
		<div class="login__brand">
			<?php echo Pages::logo( 52 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span class="eyebrow eyebrow--dark"><?php esc_html_e( 'Client Portal', 'blkrm-portal' ); ?></span>
		</div>
		<div class="login__headline">
			<span class="kicker"><?php esc_html_e( 'Welcome to your', 'blkrm-portal' ); ?></span>
			<h1 class="display"><?php esc_html_e( 'Workspace', 'blkrm-portal' ); ?><span class="dot">.</span></h1>
			<p><?php esc_html_e( 'Projects, files, approvals + feedback, all in one room. Better brands inside.', 'blkrm-portal' ); ?> <strong><?php esc_html_e( 'Come on in.', 'blkrm-portal' ); ?></strong></p>
		</div>
	</section>

	<section class="login__panel" aria-label="<?php esc_attr_e( 'Sign in', 'blkrm-portal' ); ?>">
		<div class="login__bar">
			<button type="button" class="icon-btn" data-blkrm-theme-toggle aria-label="<?php esc_attr_e( 'Switch light / dark mode', 'blkrm-portal' ); ?>">
				<span class="only-light"><?php echo Pages::icon( 'moon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="only-dark"><?php echo Pages::icon( 'sun' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</button>
		</div>
		<div class="login__form-wrap">
			<div class="login__lockup">
				<?php if ( $org ) : ?>
					<?php if ( $logo_url ) : ?>
						<img class="login__client-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $org->name ); ?>">
					<?php else : ?>
						<span class="login__client-mark" role="img" aria-label="<?php echo esc_attr( $org->name ); ?>"><?php echo esc_html( Pages::initials( $org->name ) ); ?></span>
					<?php endif; ?>
					<span class="login__x" aria-hidden="true">×</span>
				<?php endif; ?>
				<span class="login__blkrm"><?php echo Pages::logo( $org ? 56 : 72 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>
			<div class="stack-sm">
				<span class="eyebrow"><?php echo esc_html( $org ? strtoupper( $org->name ) . ' × BLKRM' : __( 'BLKRM CLIENT PORTAL', 'blkrm-portal' ) ); ?></span>
				<h2 class="headline"><?php esc_html_e( 'Sign in', 'blkrm-portal' ); ?><span class="dot">.</span></h2>
			</div>

			<?php if ( $error ) : ?>
				<div class="notice notice--error" role="alert"><?php echo esc_html( $error ); ?></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( $action ); ?>" class="stack">
				<?php wp_nonce_field( 'blkrm_login', '_blkrm_login' ); ?>
				<div class="field">
					<label for="blkrm-log"><?php esc_html_e( 'Email', 'blkrm-portal' ); ?></label>
					<input id="blkrm-log" name="log" type="text" autocomplete="username" required value="<?php echo esc_attr( $username ); ?>">
				</div>
				<div class="field">
					<label for="blkrm-pwd"><?php esc_html_e( 'Password', 'blkrm-portal' ); ?></label>
					<input id="blkrm-pwd" name="pwd" type="password" autocomplete="current-password" required>
				</div>
				<div class="row-between">
					<label class="check"><input type="checkbox" name="rememberme" value="1"> <?php esc_html_e( 'Keep me signed in', 'blkrm-portal' ); ?></label>
					<a class="link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgot password?', 'blkrm-portal' ); ?></a>
				</div>
				<button type="submit" class="btn btn--accent btn--block"><?php esc_html_e( 'Sign in', 'blkrm-portal' ); ?></button>
			</form>

			<div class="login__foot">
				<span><?php echo Pages::icon( 'lock', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Encrypted file storage + private download links', 'blkrm-portal' ); ?></span>
				<span><?php esc_html_e( 'New here? Your BLKRM lead will send your invite.', 'blkrm-portal' ); ?></span>
			</div>
		</div>
	</section>
</div>
<script>
document.addEventListener('click',function(e){var b=e.target.closest('[data-blkrm-theme-toggle]');if(!b)return;var r=document.documentElement,n=r.getAttribute('data-theme')==='dark'?'light':'dark';r.setAttribute('data-theme',n);try{localStorage.setItem('blkrm-theme',n);}catch(x){}});
</script>
