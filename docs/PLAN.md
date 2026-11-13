# BLKRM Client Portal — Plan

A password-protected client hub for **Black Room Creative (BLKRM)**: project management + tracking,
asset management, feedback + revisions, and secure file exchange. It runs as a WordPress plugin
on blkrm.com, and the whole experience is BLKRM-branded: clients work *inside BLKRM's tool*.

_Last updated: 2026-11-13_

---

## 1. Starting point and approach

**Base:** fork [`sethshoultes/wp-agency-hub`](https://github.com/sethshoultes/wp-agency-hub) (GPL-2.0+) into this repo
and rework it. Do not build directly on top of it as-is.

**Why fork instead of starting fresh**

- About half of that plugin is groundwork every portal needs, and it is written cleanly:
  - portal page addresses (rewrite routing)
  - the client role
  - a REST API with permission callbacks
  - checks that keep each client's data separate
  - database setup on activation
  - admin screens with nonce checks
  - email notifications
- It is small (~6k lines). After Phase 0 it is fully ours, and we won't track upstream updates.
- The GPL allows forking and modifying. We keep the license notice and credit.
- The weak parts get rebuilt regardless: data model, file storage and portal UI.

**Problems in the base plugin, all fixed in Phase 0**

| # | Problem | Fix |
|---|---|---|
| 1 | Download links hit the REST API without a nonce, so they fail with 401 | Signed, expiring download URLs served by a dedicated handler |
| 2 | Files sit at predictable public paths under `uploads/`. The only protection is `.htaccess`, which Nginx (incl. WordPress.com) ignores | Private storage layer (see §4) |
| 3 | No staff role: staff must be full WordPress administrators | `blkrm_staff` role with scoped capabilities |
| 4 | One login per client company | Client organizations with multiple users |
| 5 | Upload `version` field is always 1 | Real file versions with revision history |
| 6 | Approvals overwrite notes; admins can approve on a client's behalf without a record | Approval history table, recorded "on behalf of" flag |
| 7 | No login rate limiting or 2FA; "remember me" forced on | Lockout, optional 2FA, normal session handling |
| 8 | Requires `vendor/` from Composer, which isn't committed | Ship a built release zip; add a fallback PSR-4 autoloader |
| 9 | No tests | PHPUnit and REST integration tests for permissions + storage |

---

## 2. Environment

| Item | Value |
|---|---|
| Host | **WordPress.com, Business plan**: custom plugins allowed; Nginx-based hosting, so `.htaccess` has no effect |
| Site builder | Elementor (theme + marketing pages) |
| Storage | 200 GB plan allowance, ~473 MB used. Start self-hosted; move to Cloudflare R2 / S3 if needed |
| Scale | ~10 client orgs at launch, up to 4 users each, plus BLKRM staff |
| File sizes | 20 MB–1 GB typical (large-format print files) |
| Testing | Use a WordPress.com staging site for every release before production |

**Implications**

- **Portal shell.** The portal renders as its own full-screen app shell and bypasses the theme, so
  Elementor can't clash with it. A small shortcode and Elementor widget provide a "Client Login" button for marketing pages.
- **Interactive UI.** Board drag-and-drop, the calendar and the timeline are built with React, using
  WordPress's bundled `wp-element` and `@wordpress/scripts`, talking to the plugin's REST API.
- **Large files.** 1 GB uploads cannot go through a single PHP request. Uploads are **chunked and
  resumable** (~8 MB chunks), so each request stays under host limits.

---

## 3. Branding + experience

- **BLKRM everywhere.** Logo, palette, type and app chrome come from the BLKRM Design System, using its
  digital/web tokens. The UI should be clean, contemporary and square-cornered with the yellow accent used sparingly.
- **Light and dark mode.** A toggle sits in the top-right corner. Initial value follows the OS
  preference; the choice is remembered per user.
- **Client brand on login only.** Each client org gets its own login URL (`/portal/{client-slug}`)
  showing the client's logo beside BLKRM's ("Acme Co. × BLKRM"). If a client has no brand assets, the BLKRM
  login shows. The generic login is at `/portal`.
- **Workspace feel.** After login, the header shows a "{Client} workspace" label inside the BLKRM
  app. Staff get a client switcher.
- **Voice.** Follows the BLKRM content fundamentals: headlines in caps that end with a period, "+" in place of "and",
  plain-spoken copy, no emoji.

---

## 4. File storage + security (self-hosted first)

- **Storage interface.** Code talks to a `StorageAdapter` interface. `LocalPrivateAdapter` ships first;
  `S3CompatibleAdapter` (Cloudflare R2 / S3) comes later and needs no UI changes.
- **Local storage rules**
  - Phase 0 spike: on WordPress.com staging, find a writable directory that is **not web-served**. If one exists, store files there.
  - Regardless of the spike result, files are stored under **random, unguessable names** (no original filename or project ID in the path).
  - **Encryption at rest:** libsodium `secretstream`, streamed in chunks so 1 GB files never load into memory.
    This keeps files safe even if a storage path were ever exposed.
- **Downloads.** Every download goes through a permission-checked handler and a **signed, expiring
  link** (default 15 minutes, single user), streamed with range support for large files.
- **Uploads**
  - Chunked and resumable, with a client-side SHA-256 checksum verified after assembly.
  - Type checked by file contents, against an allow-list that adds print formats (PDF/X, AI, EPS, PSD, INDD/IDML, TIFF).
  - Per-org storage quota.
- **Audit log.** Every upload, download, view, delete and permission change is recorded.
- **Admin storage meter.** Shows usage against the 200 GB plan, with a warning at 75% that is the trigger to
  enable the R2/S3 adapter.
- **Backups.** Large files inflate site backups. Document how files are excluded or included, and
  revisit when moving to R2.

---

## 5. Roles + permissions

| Role | Can |
|---|---|
| **BLKRM Admin** (WP admin) | Everything, including settings, branding, storage and staff |
| **BLKRM Staff** (`blkrm_staff`) | Manage assigned or all orgs and projects; files, to-dos, approvals, requests |
| **Client Owner** | View org projects; manage org users (up to the seat limit); submit requests; approve |
| **Client Member** | View org projects; upload; comment; complete own to-dos; submit requests |

All data access is scoped by **organization**, checked in a single permission service used by every
REST route and portal page.

---

## 6. Features

### 6.1 Projects: Board view + Grid view
- **Toggle** between Board and Grid views; the choice is remembered per user.
- **Board** (Kanban) columns are the BLKRM process:
  1. **Discovery**
  2. **Scope + Brief**: define scope of work and draft the creative brief; *both require client approval
     before the project can move to Design* (enforced as an approval gate)
  3. **Design**
  4. **Proofing**
  5. **Production / Print**
  6. **Delivered**
- **Card contents:** client logo, project title, type, progress bar, next milestone,
  due date, open to-do count, and an approval-needed flag.
- **Permissions:** staff drag cards between columns; clients see the board read-only.
- **Grid:** sortable, filterable table (client, status, type, due date, progress, owner).

### 6.2 Project detail + timeline
- **Tabs:** Overview · Timeline · To-dos · Files · Feedback · Messages · Activity.
- **Timeline:** Gantt-style bars per phase (status columns) with milestone diamonds and a "today" line.

### 6.3 Home dashboard
- **Greeting + summary:** active projects, items awaiting the client, upcoming dates.
- **Calendar:** month view of milestones, phase dates and to-do due dates, color-coded by project.
  - Clicking a date opens a box listing that day's items, each with **Open project** and **View timeline**.
  - Clients see only their org's projects; staff see all, filterable by client.
- **Other panels:** "My to-dos" and a recent activity feed.

### 6.4 To-dos
- **Fields:** title, project, assignee (staff or client user), due date, status, notes.
- **Visible to client** toggle on staff to-dos, **off by default** so internal work never leaks. To-dos
  assigned to a client are always visible to that client.
- **Client view:** a "My to-dos" list, plus BLKRM to-dos marked visible (read-only), so they can see
  what we're working on.
- **Calendar:** due dates appear on the calendar.

### 6.5 Requests (new project intake)
- **Who can submit:** any client user.
- **Workflow:** *New → Reviewing → Accepted / Declined*. Clients see the status of their requests.
- **Accept → Create project:** pre-fills the title, type, overview, deliverables and dates, moves the attached files into
  the project, and starts the project in **Discovery**.

**Form (consolidated from 12 fields to 7 sections, 3 short steps):**

| Step | Field | Replaces | Required |
|---|---|---|---|
| 1. The Project | **Project title** | Title | Yes |
| | **Project type**: Branding + Strategy · Graphic + Print · Digital · Creative Development + Consulting · Not sure | Project type | Yes |
| | **Overview**: "What's the idea or problem, and what would you like BLKRM to do?" | Description + Project assignment | Yes |
| 2. Goals + Deliverables | **Goal + audience**: "What should people do after seeing this?" + optional "Who is it for?" | Goal / intended outcome + Target audience | Goal yes, audience optional |
| | **Deliverables + specs**: repeatable rows: *Deliverable · Size / format · Material · Qty* | Final deliverables + Specs + dimensions | At least 1 row |
| 3. Timing, Budget + Direction | **Timeline**: target date + optional milestone rows (*label · date*) | Target date (incl. milestones) | Target date yes |
| | **Budget**: range select (incl. "Not sure yet") | Budget | No |
| | **Creative direction + files**: style notes, links, and upload of references and working files (chunked uploads) | Creative direction + Reference + Working files | No |

### 6.6 Files + assets
- **Organization:** project folders and an org-level **Brand Library** (logos, guidelines, fonts).
- **Versions:** v1, v2… under one file, with full history and restore.
- **Previews:** images, PDF and video; thumbnails generated for print PDFs/TIFFs where possible.
- **Uploads:** chunked and resumable with a progress UI; drag-and-drop folders.

### 6.7 Feedback, revisions + approvals
- **Pinned comments** on a point of an image or PDF page; threaded, resolvable.
- **Revision rounds** (R1, R2…), each tied to a file version.
- **Approvals:**
  - flow: Draft → In review → Approved / Changes requested
  - full history kept, with "on behalf of" recorded for staff
  - the **Scope + Brief gate** uses this flow.

### 6.8 Messages + notifications
- Project message threads with @mentions and attachments.
- In-app notification bell, plus email (BLKRM-branded templates) with per-user preferences.

### 6.9 Security hardening
- Login rate limiting and lockout; optional TOTP 2FA (required for staff).
- Session timeout setting; "remember me" left to the user.
- Audit log viewer for admins; data export and delete per org (privacy).

---

## 7. Phases

| Phase | Scope | Outcome |
|---|---|---|
| **Mockup** | Clickable mockup of Login, Home (calendar + date box), Projects (Board/Grid), Project detail (Timeline), To-dos, Requests form, Files; light/dark | User edits and approves the look and flow before feature code |
| **0. Foundation** | Fork; fix downloads; storage adapter + encryption + chunked uploads; staff role; orgs + multi-user; schema migrations; app shell + BLKRM theme + light/dark; WP.com staging spike | Secure base on WordPress.com |
| **1. Projects** | Board/Grid toggle, 6-stage pipeline, project detail, milestones, Timeline | Day-to-day project tracking |
| **2. Home + To-dos** | Dashboard, calendar + date box, to-dos with visibility toggle, activity feed | At-a-glance view for both sides |
| **3. Requests** | 3-step intake form, request queue, accept → project | Clients start projects themselves |
| **4. Files + Feedback** | Folders, Brand Library, versions, previews, pinned comments, revision rounds, approval history, Scope + Brief gate | Full asset + revision workflow |
| **5. Hardening + Polish** | Lockout, 2FA, audit viewer, notifications, Elementor widget, R2 adapter (when needed) | Production-ready |

Each phase ships to WordPress.com staging for review before production.

---

## 8. Open items

- [ ] Approve the mockup (layout, branding, light/dark treatment).
- [ ] Confirm the Project type list (draft mirrors BLKRM's four service groups).
- [ ] Confirm budget ranges for the request form.
- [ ] Phase 0 spike result: is a non-web-served writable path available on WordPress.com?
- [ ] Decide the client login URL base (`/portal/{client}` proposed).
