<?php
/**
 * Renders portal pages inside the BLKRM app shell (sidebar + header).
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Portal;

use BLKRM\Portal\Auth\Access;
use BLKRM\Portal\Data\Files;
use BLKRM\Portal\Data\Orgs;
use BLKRM\Portal\Data\Projects;
use BLKRM\Portal\Rest\PreferenceEndpoints;

defined( 'ABSPATH' ) || exit;

/**
 * Page controller.
 */
final class Pages {

	/**
	 * Render a route.
	 *
	 * @param string $route Route.
	 * @param string $arg   Route argument.
	 */
	public function render( $route, $arg ) {
		$user_id  = get_current_user_id();
		$user     = wp_get_current_user();
		$is_staff = Access::is_staff( $user_id );
		$org      = $is_staff ? null : Orgs::for_user( $user_id );
		$scope    = $is_staff ? null : (int) $org->id;

		$ctx = array(
			'user'     => $user,
			'is_staff' => $is_staff,
			'org'      => $org,
			'route'    => $route,
			'nav'      => self::nav(),
			'theme'    => PreferenceEndpoints::theme( $user_id ),
			'logout'   => Router::logout_url(),
			'initials' => self::initials( $user->display_name ),
		);

		switch ( $route ) {
			case 'home':
				$ctx['title']    = __( 'Home', 'blkrm-portal' );
				$ctx['projects'] = Projects::list_for( $scope );
				$template        = 'home';
				break;

			case 'projects':
				$ctx['title']    = __( 'Projects', 'blkrm-portal' );
				$ctx['projects'] = Projects::list_for( $scope );
				$template        = 'projects';
				break;

			case 'project':
				$project = Projects::get( (int) $arg );
				if ( ! $project || ! Access::can_view_project( $user_id, (int) $project->id ) ) {
					$this->not_found( $ctx );
					return;
				}
				$ctx['title']   = $project->name;
				$ctx['project'] = $project;
				$ctx['files']   = Files::list_for( array( 'project_id' => (int) $project->id ) );
				$template       = 'project';
				$ctx['route']   = 'projects';
				break;

			case 'files':
				$ctx['title'] = __( 'Files', 'blkrm-portal' );
				$ctx['files'] = Files::list_for( $is_staff ? array() : array( 'org_id' => $scope ) );
				$template     = 'files';
				break;

			case 'todos':
			case 'requests':
				$ctx['title'] = 'todos' === $route ? __( 'To-dos', 'blkrm-portal' ) : __( 'Requests', 'blkrm-portal' );
				$ctx['phase'] = 'todos' === $route ? 2 : 3;
				$template     = 'coming';
				break;

			default:
				$this->not_found( $ctx );
				return;
		}

		$this->output( $template, $ctx );
	}

	/**
	 * Print the shell around a page template.
	 *
	 * @param string $template Page template name.
	 * @param array  $ctx      Context (extracted into the templates' scope).
	 */
	private function output( $template, array $ctx ) {
		Assets::print_document_start( $ctx['title'], $ctx['theme'], 'blkrm-app' );
		$page_template = BLKRM_PORTAL_DIR . 'templates/pages/' . $template . '.php';
		include BLKRM_PORTAL_DIR . 'templates/shell.php';
		Assets::print_document_end( true );
	}

	/**
	 * 404 inside the shell.
	 *
	 * @param array $ctx Context.
	 */
	private function not_found( array $ctx ) {
		status_header( 404 );
		$ctx['title'] = __( 'Not found', 'blkrm-portal' );
		$this->output( 'not-found', $ctx );
	}

	/**
	 * Sidebar navigation.
	 *
	 * @return array<string, array{label:string,url:string,icon:string}>
	 */
	public static function nav() {
		return array(
			'home'     => array( 'label' => __( 'Home', 'blkrm-portal' ), 'url' => Router::url(), 'icon' => 'home' ),
			'projects' => array( 'label' => __( 'Projects', 'blkrm-portal' ), 'url' => Router::url( 'projects' ), 'icon' => 'board' ),
			'todos'    => array( 'label' => __( 'To-dos', 'blkrm-portal' ), 'url' => Router::url( 'todos' ), 'icon' => 'check' ),
			'requests' => array( 'label' => __( 'Requests', 'blkrm-portal' ), 'url' => Router::url( 'requests' ), 'icon' => 'plus' ),
			'files'    => array( 'label' => __( 'Files', 'blkrm-portal' ), 'url' => Router::url( 'files' ), 'icon' => 'folder' ),
		);
	}

	/**
	 * Inline SVG icon (stroke, currentColor).
	 *
	 * @param string $name Icon name.
	 * @param int    $size Pixel size.
	 * @return string Safe SVG markup.
	 */
	public static function icon( $name, $size = 18 ) {
		$paths = array(
			'home'   => '<path d="M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3z"/>',
			'board'  => '<rect x="3" y="4" width="5" height="16"/><rect x="10" y="4" width="5" height="10"/><rect x="17" y="4" width="4" height="13"/>',
			'check'  => '<path d="m4 12 4 4 8-9"/><path d="M14 18h7M17 12h4"/>',
			'plus'   => '<rect x="3" y="3" width="18" height="18"/><path d="M12 8v8M8 12h8"/>',
			'folder' => '<path d="M3 6h7l2 2h9v12H3z"/>',
			'moon'   => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
			'sun'    => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
			'upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>',
			'down'   => '<path d="M12 4v12M7 11l5 5 5-5"/><path d="M4 20h16"/>',
			'lock'   => '<rect x="4" y="11" width="16" height="10"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
			'back'   => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
		);
		$inner = isset( $paths[ $name ] ) ? $paths[ $name ] : '';
		return sprintf(
			'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			(int) $size,
			$inner
		);
	}

	/**
	 * BLKRM condensed mark as inline SVG (fill currentColor).
	 *
	 * @param int $width Width in px.
	 * @return string
	 */
	public static function logo( $width = 42 ) {
		$h = (int) round( $width * 126.88 / 159.4 );
		return '<svg viewBox="0 0 159.4 126.88" width="' . (int) $width . '" height="' . $h . '" fill="currentColor" role="img" aria-label="BLKRM"><path d="M49.38,126.88L0,114.7.65,15.91,48.44,0l.94,126.88ZM40.59,68.13c0-3.03-2.52-5.57-5.55-5.59-3.04-.02-5.59,2.47-5.63,5.51-.04,3.1,2.53,5.7,5.63,5.67,3.03-.02,5.56-2.56,5.56-5.59Z"/><path d="M87.23,15.03h11.81v35.86h18.35V15.07h11.97v18.35l.36.15c4.62-6.17,9.23-12.34,13.88-18.54h14.9c-6.07,7.34-11.98,14.47-18.01,21.76,6.27,8.17,12.52,16.33,18.92,24.67h-14.82c-3.88-5.57-7.89-11.32-12.06-17.29-1.09,1.34-2.09,2.57-3.14,3.87v13.46h-42.15V15.03Z"/><path d="M57.51,61.56v-10.03s8.45.02,11.68,0c2.75-.02,4.51-1.65,4.52-4.11.02-2.54-1.77-4.26-4.53-4.28-3.84-.03-7.68,0-11.63,0v-10.04c3.75,0,7.43.04,11.11-.01,3.09-.04,4.95-2.85,3.79-5.65-.7-1.69-2.06-2.4-3.84-2.4-3.64,0-7.28,0-11.04,0v-10.19s10.31.08,15.05.15c5.99.1,10.92,3.89,12.1,9.18,1.33,5.97-1.07,10.7-6.99,13.51,3.82,1.15,6.34,3.58,7.58,7.21,2.16,6.31-.39,15.7-11.08,16.59-5.47.46-16.71.08-16.71.08Z"/><path d="M107.5,105.75s12.34-23.88,18.44-35.69h3.65v45.82h-2.34v-42.03s-.26-.08-.39-.12c-6.39,12.23-19.43,37.17-19.43,37.17,0,0-19.5-37.02-19.59-37v41.97h-2.47v-45.82h3.68l18.45,35.7Z"/><path d="M78.38,115.89h-3.27c-4.72-6.68-9.49-13.43-14.33-20.27h-3.26c0-.79-.05-2.55-.05-2.55,0,0,3.98.12,5.88-.25,7.25-1.38,10.7-8.61,7.27-15.19-1.98-5.65-11.75-5.21-13.08-5.27v-2.33c4.15-.2,8.14.12,11.74,2.32,3.92,2.4,5.87,7.47,5.02,12.58-.27,8.65-10.05,10.25-10.62,10.4,4.87,6.81,9.64,13.49,14.69,20.56Z"/><path d="M142.85,105.92c2.9,0,5.33,2.45,5.31,5.38-.01,2.92-2.46,5.34-5.37,5.31-2.81-.03-5.24-2.47-5.28-5.29-.04-2.86,2.47-5.39,5.34-5.39Z"/></svg>';
	}

	/**
	 * Initials for an avatar.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	public static function initials( $name ) {
		// Words that start with a letter or digit ("Harbor + Pine" → "HP").
		$parts = preg_split( '/\s+/', trim( (string) $name ) );
		$parts = array_values( array_filter( (array) $parts, static function ( $w ) { return (bool) preg_match( '/^[\p{L}\p{N}]/u', $w ); } ) );
		$out   = '';
		foreach ( array_slice( $parts, 0, 2 ) as $p ) {
			$out .= function_exists( 'mb_substr' ) ? mb_substr( $p, 0, 1 ) : substr( $p, 0, 1 );
		}
		return strtoupper( $out );
	}

	/**
	 * Human date from Y-m-d.
	 *
	 * @param string|null $date Date.
	 * @return string
	 */
	public static function date( $date ) {
		return $date ? date_i18n( 'M j, Y', strtotime( $date ) ) : '—';
	}
}
