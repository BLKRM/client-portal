<?php
/**
 * Portal header partial.
 *
 * @package WPAgencyHub
 *
 * @var object $branding   Branding settings.
 * @var string $page_title Current page title.
 * @var object $user       Current WP user.
 */

defined( 'ABSPATH' ) || exit;

$portal_base = \WPAgencyHub\Portal\PortalTemplate::portal_url();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $page_title ? $page_title . ' — ' . get_bloginfo( 'name' ) : get_bloginfo( 'name' ) ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="wpah-portal">
	<header class="wpah-header">
		<div class="wpah-header__inner">
			<a href="<?php echo esc_url( $portal_base ); ?>" class="wpah-header__logo">
				<?php if ( ! empty( $branding->logo_url ) ) : ?>
					<img src="<?php echo esc_url( $branding->logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<?php else : ?>
					<span class="wpah-header__site-name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
				<?php endif; ?>
			</a>

			<nav class="wpah-header__nav">
				<a href="<?php echo esc_url( $portal_base ); ?>" class="wpah-nav-link"><?php esc_html_e( 'Dashboard', 'wp-agency-hub' ); ?></a>
				<div class="wpah-header__user">
					<span class="wpah-header__user-name"><?php echo esc_html( $user->display_name ); ?></span>
					<a href="<?php echo esc_url( \WPAgencyHub\Portal\PortalTemplate::portal_url( 'logout/' ) ); ?>" class="wpah-nav-link wpah-nav-link--logout"><?php esc_html_e( 'Log Out', 'wp-agency-hub' ); ?></a>
				</div>
			</nav>
		</div>
	</header>

	<main class="wpah-main">
		<div class="wpah-container">
