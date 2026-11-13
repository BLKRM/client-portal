# BLKRM Client Portal (WordPress plugin)

Password-protected client workspace for Black Room Creative. Forked from
[WP Agency Hub](https://github.com/sethshoultes/wp-agency-hub) (GPL-2.0-or-later)
and reworked. See [`../docs/PLAN.md`](../docs/PLAN.md) for the roadmap.

**Status: Phase 0 (foundation).** Secure storage, roles, client organizations,
app shell and login are done. Board / Grid, calendar, to-dos, requests and
feedback come in Phases 1–4.

## What Phase 0 includes

| Area | What it does |
|---|---|
| **App shell** | `/portal/` renders as its own full-screen app: BLKRM sidebar, workspace header, light/dark toggle. It skips the theme entirely, so Elementor can't clash with it. |
| **Login** | `/portal/login/` (BLKRM) and `/portal/login/<client>/` (client logo × BLKRM). Lockout after 5 failed tries for 15 minutes. "Remember me" is the user's choice. |
| **Roles** | `blkrm_client` (portal only, blocked from wp-admin), `blkrm_staff` (manages clients/projects/files without being a WP admin), admin (plus settings). |
| **Client organizations** | Each client is an org with **seats** (default 4), an optional storage limit and an optional logo. Staff add or invite people; new accounts get a set-password email. |
| **Projects** | The six BLKRM stages: Discovery → Scope + Brief → Design → Proofing → Production / Print → Delivered. |
| **Uploads** | Chunked (8 MB), resumable, SHA-256-checked per chunk, any order, retries with backoff. Files up to 2 GB by default. Extension allow-list for print formats; content sniffing blocks HTML/SVG/PHP/executables. |
| **Storage** | Every file is **encrypted at rest** (XChaCha20-Poly1305, per-file keys, chunked format allowing fast byte-range reads) and stored under a **random name** in a private folder, outside the web root when the host allows. |
| **Downloads** | 15-minute signed links bound to the signed-in user, streamed through a permission check, with Range support for large files. |
| **Versions** | Uploading again to the same file creates v2, v3… with history. |
| **Audit log** | Uploads, downloads, deletions and membership changes, viewable in Settings. |
| **Storage self-check** | *BLKRM Portal → Settings + Storage* checks the encryption key, storage location and limits. It also runs a test of whether the storage folder can be fetched from the web; run it on WordPress.com staging. |

## Install

1. Build the zip: `bin/build-zip.sh` creates `build/blkrm-portal-<version>.zip`.
2. In WordPress: *Plugins → Add New → Upload Plugin*, then activate it.
   (WordPress.com: Business plan or higher; try it on a staging site first.)
3. **Recommended:** add the encryption key to `wp-config.php` so a database leak
   alone can't decrypt files. Generate a key:
   ```bash
   php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;'
   ```
   then add:
   ```php
   define( 'BLKRM_PORTAL_ENCRYPTION_KEY', 'base64:...' );
   ```
   Do this **before uploading any files**, and keep a backup of the key. Without
   it, stored files cannot be decrypted. (If files were already uploaded with the
   database key, copy that exact value into the constant; changing it would lock
   those files.)
4. Optional: `define( 'BLKRM_PORTAL_STORAGE_DIR', '/path/outside/webroot' );`
5. Open *BLKRM Portal → Settings + Storage* and click **Run web-access test**.
6. *BLKRM Portal → Clients*: add a client, then add their people.
   *Projects*: add a project. Visit `/portal/`.

### Fonts
The portal loads Proxima Nova from the BLKRM Adobe Fonts kit (`agr6dga`). Make sure the
site's domain is listed in that kit's settings. Until it is, the portal falls back to the system UI font.

## Settings and filters

| Constant / filter | Default | Purpose |
|---|---|---|
| `BLKRM_PORTAL_ENCRYPTION_KEY` | (DB option) | Master key (`base64:` + 32 bytes) |
| `BLKRM_PORTAL_STORAGE_DIR` | auto | Private storage folder |
| `BLKRM_PORTAL_DELETE_DATA` | false | When `true`, uninstall deletes tables and files |
| `blkrm_portal_max_upload_bytes` | 2 GB | Largest file |
| `blkrm_portal_chunk_bytes` | 8 MB | Upload chunk size (auto-reduced to fit `post_max_size`) |
| `blkrm_portal_download_ttl` | 900 s | Download link lifetime |
| `blkrm_portal_allowed_file_types` | see `FileTypes` | Extension → MIME allow-list |
| `blkrm_portal_storage_adapter` | local | Swap in an R2/S3 adapter later |
| `blkrm_portal_font_css` | Adobe kit URL | Font stylesheet ('' to disable) |

## REST API (`/wp-json/blkrm/v1/`)

Cookie auth plus the `wp_rest` nonce. Every route re-checks access to the specific project or file.

- `POST /uploads` · `GET /uploads/<id>` · `PUT /uploads/<id>/chunks/<n>` · `POST /uploads/<id>/complete` · `DELETE /uploads/<id>`
- `GET /projects/<id>/files` · `GET /files/<id>/versions` · `POST /files/<id>/download-link` · `DELETE /files/<id>` (staff)
- `POST /me/preferences` (`theme`)

## Development

```bash
composer install
vendor/bin/phpunit
```

`vendor/bin/phpunit` runs the unit tests: crypto, storage, ranges, tokens and file types.
`tests/smoke/` holds the end-to-end test against a real WordPress site; see its README.

Code layout: `src/` (namespaced `BLKRM\Portal\`, PSR-4, no Composer needed at
runtime), `templates/` (portal + login), `assets/` (CSS/JS, no build step).
