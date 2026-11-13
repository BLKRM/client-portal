<?php
/**
 * Database schema (dbDelta format: two spaces before PRIMARY KEY, one field per line).
 *
 * @package BLKRM\Portal
 */

namespace BLKRM\Portal\Install;

defined( 'ABSPATH' ) || exit;

/**
 * Table names and CREATE statements.
 */
final class Schema {

	/**
	 * Full table name.
	 *
	 * @param string $name Short name, e.g. 'orgs'.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'blkrm_' . $name;
	}

	/**
	 * Every table this plugin owns (for uninstall).
	 *
	 * @return string[]
	 */
	public static function tables() {
		return array_map(
			array( __CLASS__, 'table' ),
			array( 'orgs', 'org_members', 'projects', 'milestones', 'files', 'file_versions', 'uploads', 'audit_log' )
		);
	}

	/**
	 * Version 1 schema: organizations, projects, files, uploads, audit log.
	 *
	 * @return string
	 */
	public static function v1() {
		global $wpdb;
		$c = $wpdb->get_charset_collate();

		$orgs        = self::table( 'orgs' );
		$members     = self::table( 'org_members' );
		$projects    = self::table( 'projects' );
		$milestones  = self::table( 'milestones' );
		$files       = self::table( 'files' );
		$versions    = self::table( 'file_versions' );
		$uploads     = self::table( 'uploads' );
		$audit       = self::table( 'audit_log' );

		return "CREATE TABLE {$orgs} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL DEFAULT '',
  slug varchar(100) NOT NULL DEFAULT '',
  logo_attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
  accent_color varchar(7) NOT NULL DEFAULT '',
  seat_limit smallint(5) unsigned NOT NULL DEFAULT 4,
  quota_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'active',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug)
) {$c};
CREATE TABLE {$members} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  org_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  org_role varchar(20) NOT NULL DEFAULT 'member',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_id (user_id),
  KEY org_id (org_id)
) {$c};
CREATE TABLE {$projects} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  org_id bigint(20) unsigned NOT NULL,
  name varchar(255) NOT NULL DEFAULT '',
  type varchar(40) NOT NULL DEFAULT '',
  stage varchar(30) NOT NULL DEFAULT 'discovery',
  description longtext NOT NULL,
  start_date date DEFAULT NULL,
  due_date date DEFAULT NULL,
  lead_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  sort_order int(11) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY org_id (org_id),
  KEY stage (stage)
) {$c};
CREATE TABLE {$milestones} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  project_id bigint(20) unsigned NOT NULL,
  name varchar(255) NOT NULL DEFAULT '',
  kind varchar(20) NOT NULL DEFAULT 'milestone',
  due_date date DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'upcoming',
  sort_order int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY project_id (project_id),
  KEY due_date (due_date)
) {$c};
CREATE TABLE {$files} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  org_id bigint(20) unsigned NOT NULL,
  project_id bigint(20) unsigned NOT NULL DEFAULT 0,
  folder varchar(190) NOT NULL DEFAULT '',
  name varchar(255) NOT NULL DEFAULT '',
  current_version_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  deleted_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY org_id (org_id),
  KEY project_id (project_id)
) {$c};
CREATE TABLE {$versions} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  file_id bigint(20) unsigned NOT NULL,
  version int(11) unsigned NOT NULL DEFAULT 1,
  storage_key varchar(64) NOT NULL DEFAULT '',
  size bigint(20) unsigned NOT NULL DEFAULT 0,
  stored_size bigint(20) unsigned NOT NULL DEFAULT 0,
  mime varchar(127) NOT NULL DEFAULT '',
  note varchar(255) NOT NULL DEFAULT '',
  uploaded_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY file_id (file_id)
) {$c};
CREATE TABLE {$uploads} (
  id char(32) NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  org_id bigint(20) unsigned NOT NULL,
  project_id bigint(20) unsigned NOT NULL,
  file_id bigint(20) unsigned NOT NULL DEFAULT 0,
  folder varchar(190) NOT NULL DEFAULT '',
  name varchar(255) NOT NULL DEFAULT '',
  size bigint(20) unsigned NOT NULL DEFAULT 0,
  mime varchar(127) NOT NULL DEFAULT '',
  chunk_size int(11) unsigned NOT NULL DEFAULT 0,
  total_chunks int(11) unsigned NOT NULL DEFAULT 0,
  header_hex varchar(128) NOT NULL DEFAULT '',
  storage_key varchar(64) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'open',
  created_at datetime NOT NULL,
  expires_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id),
  KEY expires_at (expires_at)
) {$c};
CREATE TABLE {$audit} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  org_id bigint(20) unsigned NOT NULL DEFAULT 0,
  project_id bigint(20) unsigned NOT NULL DEFAULT 0,
  action varchar(60) NOT NULL DEFAULT '',
  object_type varchar(30) NOT NULL DEFAULT '',
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  ip varchar(45) NOT NULL DEFAULT '',
  details longtext NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY org_id (org_id),
  KEY project_id (project_id),
  KEY created_at (created_at)
) {$c};";
	}
}
