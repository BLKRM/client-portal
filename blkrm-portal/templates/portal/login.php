<?php
/**
 * Branded login page template.
 *
 * @package WPAgencyHub
 *
 * @var object $branding Branding settings.
 * @var string $error    Login error message.
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html__( 'Login', 'wp-agency-hub' ) . ' — ' . esc_html( get_bloginfo( 'name' ) ); ?></title>
	<?php wp_head(); ?>
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body {
			font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
			background: var(--ah-surface-secondary, #f5f5f7);
			color: var(--ah-text-primary, #1a1a1a);
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.wpah-login {
			width: 100%;
			max-width: 400px;
			padding: 24px;
		}
		.wpah-login__logo {
			text-align: center;
			margin-bottom: 32px;
		}
		.wpah-login__logo img {
			max-height: 64px;
			max-width: 240px;
		}
		.wpah-login__logo-text {
			font-size: 32px;
			font-weight: 600;
			color: var(--ah-primary, #007AFF);
		}
		.wpah-login__card {
			background: #fff;
			border: 1px solid var(--ah-border, #e5e5e7);
			border-radius: 8px;
			padding: 32px 24px;
			box-shadow: 0 4px 12px rgba(0,0,0,0.08);
		}
		.wpah-login__title {
			font-size: 24px;
			font-weight: 600;
			margin-bottom: 24px;
			text-align: center;
		}
		.wpah-login__error {
			background: rgba(255, 59, 48, 0.1);
			color: #FF3B30;
			padding: 10px 16px;
			border-radius: 6px;
			font-size: 14px;
			margin-bottom: 16px;
		}
		.wpah-login__field {
			margin-bottom: 16px;
		}
		.wpah-login__label {
			display: block;
			font-size: 14px;
			font-weight: 500;
			margin-bottom: 4px;
		}
		.wpah-login__input {
			width: 100%;
			padding: 8px 12px;
			border: 1px solid var(--ah-border, #e5e5e7);
			border-radius: 6px;
			font-size: 16px;
			font-family: inherit;
			transition: border-color 200ms ease, box-shadow 200ms ease;
		}
		.wpah-login__input:focus {
			outline: none;
			border-color: var(--ah-primary, #007AFF);
			box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
		}
		.wpah-login__submit {
			width: 100%;
			padding: 10px 16px;
			background: var(--ah-primary, #007AFF);
			color: #fff;
			border: none;
			border-radius: 6px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			transition: background 200ms ease, transform 200ms ease;
			margin-top: 8px;
		}
		.wpah-login__submit:hover {
			background: var(--ah-primary-hover, #0051D5);
			transform: scale(1.02);
		}
		.wpah-login__submit:active {
			background: var(--ah-primary-active, #003A99);
			transform: scale(1);
		}
	</style>
</head>
<body>
	<div class="wpah-login">
		<div class="wpah-login__logo">
			<?php if ( ! empty( $branding->logo_url ) ) : ?>
				<img src="<?php echo esc_url( $branding->logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php else : ?>
				<span class="wpah-login__logo-text"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<?php endif; ?>
		</div>

		<div class="wpah-login__card">
			<h1 class="wpah-login__title"><?php esc_html_e( 'Client Login', 'wp-agency-hub' ); ?></h1>

			<?php if ( ! empty( $error ) ) : ?>
				<div class="wpah-login__error"><?php echo esc_html( $error ); ?></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'wpah_portal_login' ); ?>

				<div class="wpah-login__field">
					<label class="wpah-login__label" for="wpah-username"><?php esc_html_e( 'Username or Email', 'wp-agency-hub' ); ?></label>
					<input type="text" id="wpah-username" name="username" class="wpah-login__input" required autocomplete="username">
				</div>

				<div class="wpah-login__field">
					<label class="wpah-login__label" for="wpah-password"><?php esc_html_e( 'Password', 'wp-agency-hub' ); ?></label>
					<input type="password" id="wpah-password" name="password" class="wpah-login__input" required autocomplete="current-password">
				</div>

				<button type="submit" class="wpah-login__submit"><?php esc_html_e( 'Sign In', 'wp-agency-hub' ); ?></button>
			</form>
		</div>
	</div>

	<?php wp_footer(); ?>
</body>
</html>
