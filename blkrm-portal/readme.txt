=== WP Agency Hub ===
Contributors: smartwebutah
Tags: client portal, agency, project management, white-label, file sharing
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

White-label client portal for agencies. Project management, file sharing, approvals, and messaging — all branded as your own.

== Description ==

WP Agency Hub turns your WordPress site into a professional, white-labeled client portal. Your clients log in to see project progress, approve deliverables, share files, and communicate with you — all under your agency's brand.

**Key Features:**

* **Client Dashboard** — Branded login and dashboard with project overview, activity feed, and pending approvals
* **Project Management** — Track projects with statuses (Discovery, In Progress, Review, Complete) and milestones
* **File Sharing** — Drag-and-drop file uploads organized by project with download management
* **Deliverable Approvals** — Upload deliverables for client review. Clients approve or request changes with full history
* **Per-Project Messaging** — Async message threads with email notifications
* **White-Label Branding** — Upload your logo, pick your colors, customize the portal URL. No WordPress branding visible to clients
* **Email Notifications** — Branded emails for new messages, approval requests, and status changes
* **Client Data Isolation** — Each client only sees their own projects. No cross-client data access

**Self-Hosted:**

All data stays in your WordPress database. No SaaS dependency, no external API calls, no monthly per-client fees. You own everything.

== Installation ==

1. Upload the `wp-agency-hub` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to Agency Hub > Settings to configure your branding
4. Add your first client under Agency Hub > Clients
5. Create a project under Agency Hub > Projects
6. Share the portal URL with your client

== Frequently Asked Questions ==

= Where do clients log in? =

By default, clients access the portal at `yoursite.com/client-portal/`. You can change this URL slug in Settings.

= Can clients see each other's data? =

No. Client data isolation is enforced at every level. Each client can only see their own projects, files, messages, and approvals.

= What file types can be uploaded? =

Images (JPEG, PNG, GIF, WebP, SVG), documents (PDF, Word, Excel, PowerPoint), archives (ZIP), text files, video (MP4, MOV), and audio (MP3, WAV). Maximum 50MB per file.

= Can I customize the portal appearance? =

Yes. Upload your logo, pick primary and secondary colors, and add custom CSS. The entire portal uses CSS custom properties that respond to your brand settings.

= Is this GDPR compliant? =

Yes. All client data is stored in your WordPress database. We do not process, store, or transmit client data to external services. Email notifications are sent through your WordPress mail configuration. No third-party analytics or tracking on the portal.

= What happens to data if I deactivate the plugin? =

All client projects, files, messages, and deliverables remain in your WordPress database. You can reactivate the plugin anytime to restore full access. Deactivating does not delete data.

== Changelog ==

= 1.0.0 =
* Initial release
* Client dashboard with project overview
* Project management with milestones
* File upload and download
* Deliverable approval flow (draft, pending review, approved, changes requested)
* Per-project async messaging
* White-label branding (logo, colors, custom CSS, portal URL)
* Email notifications
* REST API endpoints
* Client data isolation

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP Agency Hub.
