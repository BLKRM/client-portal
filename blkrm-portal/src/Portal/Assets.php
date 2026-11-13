<?php
/**
 * Prints the portal's own HTML document head/foot. The portal deliberately
 * does NOT call wp_head()/wp_footer(), so theme, Elementor and other plugin
 * styles never leak in; only BLKRM assets load.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Portal;

use BLKRM\Portal\Files\UploadService;

defined( 'ABSPATH' ) || exit;

/**
 * Document + asset printer.
 */
final class Assets {

	/**
	 * Adobe Fonts kit carrying Proxima Nova (BLKRM brand face). The portal's
	 * domain must be allowed in the kit's settings. Filter to '' to disable.
	 *
	 * @return string
	 */
	public static function font_css_url() {
		return (string) apply_filters( 'blkrm_portal_font_css', 'https://use.typekit.net/agr6dga.css' );
	}

	/**
	 * Open the document.
	 *
	 * @param string $title      Page title.
	 * @param string $theme      light|dark|system.
	 * @param string $body_class Body class.
	 */
	public static function print_document_start( $title, $theme, $body_class = '' ) {
		$css     = BLKRM_PORTAL_URL . 'assets/css/portal.css?ver=' . rawurlencode( BLKRM_PORTAL_VERSION );
		$font    = self::font_css_url();
		$favicon = BLKRM_PORTAL_URL . 'assets/img/favicon.svg';
		?><!doctype html>
<html <?php language_attributes(); ?> data-theme="<?php echo esc_attr( $theme ); ?>">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title . ' · BLKRM Portal' ); ?></title>
<link rel="icon" href="<?php echo esc_url( $favicon ); ?>" type="image/svg+xml">
<?php if ( $font ) : ?>
<link rel="stylesheet" href="<?php echo esc_url( $font ); ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?php echo esc_url( $css ); ?>">
<script>
(function(){var t=document.documentElement.getAttribute('data-theme');try{var s=localStorage.getItem('blkrm-theme');if(s==='light'||s==='dark'){t=s;}}catch(e){}if(t==='system'){t=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.setAttribute('data-theme',t);})();
</script>
</head>
<body class="<?php echo esc_attr( $body_class ); ?>">
		<?php
	}

	/**
	 * Close the document, adding the portal script and its config.
	 *
	 * @param bool $with_app_js Whether to load portal.js.
	 */
	public static function print_document_end( $with_app_js = false ) {
		if ( $with_app_js ) {
			$config = array(
				'restUrl'   => esc_url_raw( rest_url( 'blkrm/v1/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'chunkSize' => UploadService::chunk_bytes(),
				'maxBytes'  => UploadService::max_file_bytes(),
				'i18n'      => array(
					'uploading' => __( 'Uploading', 'blkrm-portal' ),
					'paused'    => __( 'Paused. Resume picks up where it left off.', 'blkrm-portal' ),
					'done'      => __( 'Uploaded', 'blkrm-portal' ),
					'failed'    => __( 'Upload failed', 'blkrm-portal' ),
					'pause'     => __( 'Pause', 'blkrm-portal' ),
					'resume'    => __( 'Resume', 'blkrm-portal' ),
					'cancel'    => __( 'Cancel', 'blkrm-portal' ),
					'tooLarge'  => __( 'This file is larger than the upload limit.', 'blkrm-portal' ),
				),
			);
			?>
<script>window.BLKRM_PORTAL = <?php echo wp_json_encode( $config ); ?>;</script>
<script src="<?php echo esc_url( BLKRM_PORTAL_URL . 'assets/js/portal.js?ver=' . rawurlencode( BLKRM_PORTAL_VERSION ) ); ?>" defer></script>
			<?php
		}
		echo "</body>\n</html>\n";
	}
}
