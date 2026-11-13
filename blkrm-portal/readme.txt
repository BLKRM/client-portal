=== BLKRM Client Portal ===
Contributors: blkrm
Tags: client portal, project management, file sharing, approvals
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Password-protected client workspace for Black Room Creative: projects, encrypted file exchange, approvals and requests.

== Description ==

A private, BLKRM-branded portal at /portal/ where clients sign in to follow their projects and exchange files securely.

* Client organizations with seat limits; BLKRM staff role (no admin rights needed)
* Co-branded login per client (/portal/login/client-name/)
* Chunked, resumable uploads up to 2 GB, encrypted at rest
* Signed, short-lived download links with access checks
* File versions and an audit log
* Light and dark mode

Forked from WP Agency Hub by Seth Shoultes (GPL-2.0-or-later).

== Installation ==

1. Upload and activate.
2. Recommended: define BLKRM_PORTAL_ENCRYPTION_KEY in wp-config.php before uploading files (see README.md).
3. BLKRM Portal → Settings + Storage: run the storage check.
4. Add clients, people and projects.

== Changelog ==

= 0.1.1 =
* Site administrators always get portal access (fixes missing admin menu on some hosts).

= 0.1.0 =
* Phase 0 foundation: orgs + seats, staff role, encrypted chunked storage, signed downloads, app shell, login.
