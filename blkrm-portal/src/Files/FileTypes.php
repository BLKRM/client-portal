<?php
/**
 * Allowed file types, tuned for a design studio: print production formats
 * (PDF/X, AI, EPS, PSD, INDD/IDML, TIFF), office docs, media and fonts.
 *
 * Checks happen twice: the extension must be on the allow-list, and the
 * file's actual content (sniffed from the first chunk) must not be a
 * dangerous type such as HTML, SVG, PHP or an executable. Files are always
 * downloaded as attachments with nosniff, never rendered inline.
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Files;

defined( 'ABSPATH' ) || exit;

/**
 * File type policy.
 */
final class FileTypes {

	/**
	 * Extension → MIME type served on download.
	 *
	 * @return array<string, string>
	 */
	public static function allowed() {
		$types = array(
			// Print + design.
			'pdf'   => 'application/pdf',
			'ai'    => 'application/postscript',
			'eps'   => 'application/postscript',
			'psd'   => 'image/vnd.adobe.photoshop',
			'psb'   => 'image/vnd.adobe.photoshop',
			'indd'  => 'application/x-indesign',
			'idml'  => 'application/vnd.adobe.indesign-idml-package',
			'indb'  => 'application/octet-stream',
			'tif'   => 'image/tiff',
			'tiff'  => 'image/tiff',
			'sketch' => 'application/octet-stream',
			'fig'   => 'application/octet-stream',
			'xd'    => 'application/octet-stream',
			// Images.
			'jpg'   => 'image/jpeg',
			'jpeg'  => 'image/jpeg',
			'png'   => 'image/png',
			'gif'   => 'image/gif',
			'webp'  => 'image/webp',
			'heic'  => 'image/heic',
			// Office + text.
			'doc'   => 'application/msword',
			'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls'   => 'application/vnd.ms-excel',
			'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'ppt'   => 'application/vnd.ms-powerpoint',
			'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'key'   => 'application/vnd.apple.keynote',
			'pages' => 'application/vnd.apple.pages',
			'rtf'   => 'application/rtf',
			'txt'   => 'text/plain',
			'csv'   => 'text/csv',
			// Archives.
			'zip'   => 'application/zip',
			// Media.
			'mp4'   => 'video/mp4',
			'm4v'   => 'video/x-m4v',
			'mov'   => 'video/quicktime',
			'mp3'   => 'audio/mpeg',
			'wav'   => 'audio/wav',
			'aif'   => 'audio/aiff',
			'aiff'  => 'audio/aiff',
			// Fonts (brand libraries).
			'otf'   => 'font/otf',
			'ttf'   => 'font/ttf',
			'woff'  => 'font/woff',
			'woff2' => 'font/woff2',
		);
		/**
		 * Filter the extension → MIME allow-list.
		 *
		 * @param array<string,string> $types Types.
		 */
		return (array) apply_filters( 'blkrm_portal_allowed_file_types', $types );
	}

	/**
	 * Content types that are never accepted, whatever the extension.
	 *
	 * @return string[]
	 */
	private static function denied_content() {
		return array(
			'text/html',
			'application/xhtml+xml',
			'image/svg+xml',
			'text/x-php',
			'application/x-php',
			'application/x-httpd-php',
			'text/javascript',
			'application/javascript',
			'text/x-shellscript',
			'application/x-sh',
			'application/x-dosexec',
			'application/x-msdownload',
			'application/x-executable',
			'application/x-sharedlib',
			'application/x-mach-binary',
			'application/x-pie-executable',
		);
	}

	/**
	 * Formats whose content must match their extension exactly.
	 *
	 * @return array<string, string[]>
	 */
	private static function strict() {
		return array(
			'pdf'  => array( 'application/pdf' ),
			'jpg'  => array( 'image/jpeg' ),
			'jpeg' => array( 'image/jpeg' ),
			'png'  => array( 'image/png' ),
			'gif'  => array( 'image/gif' ),
			'webp' => array( 'image/webp' ),
			'tif'  => array( 'image/tiff' ),
			'tiff' => array( 'image/tiff' ),
		);
	}

	/**
	 * Validate a filename. Returns the cleaned name and download MIME type.
	 *
	 * @param string $name Original name.
	 * @return array{name:string,ext:string,mime:string}|\WP_Error
	 */
	public static function check_name( $name ) {
		$name = sanitize_file_name( wp_basename( (string) $name ) );
		$ext  = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		$map  = self::allowed();
		if ( '' === $name || '' === $ext || ! isset( $map[ $ext ] ) ) {
			return new \WP_Error( 'blkrm_file_type', __( 'This file type isn\'t allowed. Zip it, or ask BLKRM to add the format.', 'blkrm-portal' ) );
		}
		// Reject names that try to look executable to a misconfigured server.
		if ( preg_match( '/\.(php\d?|phtml|phar|cgi|pl|asp|aspx|jsp|sh|exe|bat|cmd|js|html?|svg)\./i', $name ) ) {
			return new \WP_Error( 'blkrm_file_name', __( 'This file name isn\'t allowed.', 'blkrm-portal' ) );
		}
		return array(
			'name' => $name,
			'ext'  => $ext,
			'mime' => $map[ $ext ],
		);
	}

	/**
	 * Validate the first bytes of a file against its extension.
	 *
	 * @param string $ext  Lowercase extension.
	 * @param string $head First bytes of the file.
	 * @return true|\WP_Error
	 */
	public static function check_content( $ext, $head ) {
		if ( '' === $head ) {
			return true;
		}
		$finfo  = finfo_open( FILEINFO_MIME_TYPE );
		$sniffed = $finfo ? (string) finfo_buffer( $finfo, $head ) : '';
		if ( $finfo ) {
			finfo_close( $finfo );
		}
		if ( in_array( $sniffed, self::denied_content(), true ) ) {
			return new \WP_Error( 'blkrm_file_content', __( 'This file\'s contents aren\'t allowed.', 'blkrm-portal' ) );
		}
		$strict = self::strict();
		if ( isset( $strict[ $ext ] ) && ! in_array( $sniffed, $strict[ $ext ], true ) ) {
			/* translators: %s: file extension */
			return new \WP_Error( 'blkrm_file_mismatch', sprintf( __( 'This file doesn\'t look like a real .%s file.', 'blkrm-portal' ), $ext ) );
		}
		return true;
	}
}
