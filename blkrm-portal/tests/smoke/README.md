# End-to-end smoke test

`e2e.php` drives a running WordPress site over HTTP (34 checks):

- login and the generic error message
- project isolation between clients, and staff access
- chunked upload: out of order, checksum rejection, resume status, double-complete guard
- download: byte-exact full file, Range across a chunk boundary, suffix Range
- link binding: other user, signed out, tampered link
- new versions
- type checks (HTML-as-PDF, `.php`)
- cross-client upload, missing nonce
- logout, and the logout CSRF guard

## Setup used for Phase 0

1. Install WordPress (any recent version; SQLite via the official `sqlite-database-integration` drop-in works).
2. Activate the plugin.
3. Seed the data:
   - org A with client user `maya` / `pass-maya`
   - org B with client user `sam` / `pass-sam`
   - staff user `alex` / `pass-alex`
   - one project in each org
4. Set `post_max_size` to at least 9 MB (8 MB chunks plus overhead).

```bash
php tests/smoke/e2e.php http://127.0.0.1:8899 <project_in_org_A> <project_in_org_B>
```

Run it against staging, never production: it creates files and test sessions.
