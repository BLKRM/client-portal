# WP Agency Hub

White-label client portal for WordPress agencies. Project management, file sharing, approvals, and messaging -- all branded as your own.

WP Agency Hub gives agencies a client-facing portal that lives on their own WordPress site. Clients log in to a branded portal (your logo, your colors) where they can view project progress, exchange messages, upload and download files, and approve deliverables. Admins manage everything from the WordPress dashboard.

## Features

- **Client portal** -- dedicated frontend portal with custom URL slug, login page, and dashboard
- **White-label branding** -- custom logo, primary/secondary colors, custom CSS, branded login page
- **Project management** -- create projects with milestones, statuses, due dates, and activity logs
- **File sharing** -- secure file uploads (50MB max) with versioning, served through access-controlled endpoints
- **Messaging** -- per-project message threads with email notifications for both parties
- **Approval workflow** -- submit deliverables for client review with a state machine (draft > pending_review > approved/changes_requested)
- **Data isolation** -- clients only see their own projects, files, and messages
- **Custom role** -- `ah_client` role with minimal WordPress capabilities
- **Email notifications** -- automatic emails for new messages, approval requests, and approval responses
- **Activity logging** -- full audit trail per project
- **Admin dashboard** -- overview of all clients, projects, and pending approvals

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Composer (for autoloading)

## Installation

1. Upload the `wp-agency-hub` directory to `wp-content/plugins/`.
2. Run `composer install` inside the plugin directory.
3. Activate the plugin through the WordPress admin.
4. Go to **Agency Hub > Settings** to configure branding and portal options.

## Setup

### 1. Configure Branding

Navigate to **Agency Hub > Settings** and set:

- **Logo** -- upload your agency logo (displayed on portal login and header)
- **Primary Color** -- used for buttons, links, and accent elements (default: `#007AFF`)
- **Secondary Color** -- used for secondary accents (default: `#5856D6`)
- **Custom CSS** -- optional CSS injected into all portal pages
- **Portal Slug** -- the URL path for the client portal (default: `client-portal`)

The branding is injected as CSS custom properties (`--ah-primary`, `--ah-secondary`, etc.) so the portal automatically adapts to your colors. Hover and active states are computed by darkening the primary color.

### 2. Create Clients

Go to **Agency Hub > Clients** and click **Add Client**:

1. Enter company name and contact email.
2. A WordPress user is created with the `ah_client` role.
3. The client receives login credentials for the portal.

The `ah_client` role has only the `read` capability -- clients cannot access wp-admin.

### 3. Create Projects

Go to **Agency Hub > Projects** and click **Add Project**:

1. Select the client.
2. Enter project name, description, status, and due date.
3. Add milestones with names, due dates, and sort order.

Project statuses: `discovery`, `in_progress`, `review`, `complete`, `on_hold`.

## Portal Routing

The client portal uses WordPress rewrite rules to create a clean URL structure. All portal pages are rendered by the plugin's template engine, not by your theme.

| URL | Page |
|-----|------|
| `/{portal-slug}/login` | Branded login page |
| `/{portal-slug}/` | Client dashboard (project list) |
| `/{portal-slug}/project/{id}` | Project detail with milestones |
| `/{portal-slug}/project/{id}/files` | File list with upload |
| `/{portal-slug}/project/{id}/messages` | Message thread |
| `/{portal-slug}/project/{id}/approval/{id}` | Approval detail with approve/request changes |
| `/{portal-slug}/logout` | Logout and redirect to login |

The default portal slug is `client-portal`. Change it in **Agency Hub > Settings**.

After changing the slug, go to **Settings > Permalinks** and click **Save** to flush rewrite rules (or deactivate/reactivate the plugin).

## Client Experience

1. Client visits `yoursite.com/client-portal/login`.
2. Sees your branded login page (your logo, your colors).
3. Logs in with their credentials.
4. Lands on the dashboard showing only their projects.
5. Clicks into a project to see milestones, files, messages, and pending approvals.
6. Can upload files, send messages, and approve/request changes on deliverables.
7. Receives email notifications when the agency sends a message or requests approval.

Admins can also access the portal for preview purposes.

## File Sharing

- Files are uploaded to `wp-content/uploads/ah-files/` with `.htaccess` protection (deny direct access).
- Downloads are served through the REST API with access verification -- clients can only download files from their own projects.
- Maximum file size: 50MB (configurable via `WPAH_MAX_FILE_SIZE` constant).
- Files include version tracking and uploader attribution.

## Approval Flow

The approval system uses a state machine:

```
draft --> pending_review --> approved
                        --> changes_requested --> pending_review (resubmit)
```

1. **Agency** creates an approval request (optionally attached to a file) and submits it for review.
2. **Client** receives an email notification and views the approval in the portal.
3. **Client** clicks **Approve** or **Request Changes** with an optional note.
4. **Agency** receives an email notification with the client's response.

Only agency staff (admins) can create approvals and submit them for review. Only clients can approve or request changes (admins can also do this on behalf of clients for flexibility).

## White-Label Customization

The branding system injects CSS custom properties into every portal page:

```css
:root {
  --ah-primary: #007AFF;
  --ah-primary-hover: #0062CC;     /* auto-computed, 20% darker */
  --ah-primary-active: #004FA3;    /* auto-computed, 35% darker */
  --ah-secondary: #5856D6;
  --ah-logo: url("...");
  --ah-text-primary: #1A1A1A;
  --ah-text-secondary: #636366;
  --ah-surface-primary: #FFFFFF;
  --ah-surface-secondary: #F5F5F7;
  --ah-border: #E5E5E7;
  --ah-danger: #FF3B30;
  --ah-success: #34C759;
}
```

The `LoginCustomizer` also applies branding to the portal login page. Custom CSS entered in settings is injected after the default styles, giving you full override control.

## REST API

All endpoints are under the `wp-agency-hub/v1` namespace.

**Authentication:** All endpoints require a logged-in user who is either an admin (`manage_options`) or has the `ah_client` role. Client endpoints enforce data isolation -- clients only see resources belonging to their projects.

### Projects

```
GET    /wp-json/wp-agency-hub/v1/projects              # List (filtered for clients)
POST   /wp-json/wp-agency-hub/v1/projects              # Create (admin only)
GET    /wp-json/wp-agency-hub/v1/projects/{id}          # Get with milestones
PUT    /wp-json/wp-agency-hub/v1/projects/{id}          # Update (admin only)
DELETE /wp-json/wp-agency-hub/v1/projects/{id}          # Delete (admin only, cascades)
```

**Create project body:**
```json
{
  "client_id": 3,
  "name": "Website Redesign",
  "description": "Full redesign of corporate website",
  "status": "discovery",
  "due_date": "2026-06-15"
}
```

### Files

```
GET    /wp-json/wp-agency-hub/v1/projects/{id}/files    # List files
POST   /wp-json/wp-agency-hub/v1/projects/{id}/files    # Upload (multipart)
GET    /wp-json/wp-agency-hub/v1/files/{id}              # File details
GET    /wp-json/wp-agency-hub/v1/files/{id}/download     # Download file
DELETE /wp-json/wp-agency-hub/v1/files/{id}              # Delete (admin only)
```

Upload uses `multipart/form-data` with a `file` field.

### Messages

```
GET    /wp-json/wp-agency-hub/v1/projects/{id}/messages  # List messages (chronological)
POST   /wp-json/wp-agency-hub/v1/projects/{id}/messages  # Send message
```

**Send message body:**
```json
{
  "message": "The homepage mockup is ready for your review.",
  "attachment_id": 12
}
```

When a client sends a message, the admin email is notified. When an admin sends a message, the client email is notified.

### Approvals

```
GET    /wp-json/wp-agency-hub/v1/projects/{id}/approvals       # List approvals
POST   /wp-json/wp-agency-hub/v1/projects/{id}/approvals       # Create (admin only)
GET    /wp-json/wp-agency-hub/v1/approvals/{id}                 # Get approval details
POST   /wp-json/wp-agency-hub/v1/approvals/{id}/transition      # Transition state
```

**Create approval body:**
```json
{
  "title": "Homepage Design v2",
  "file_id": 12
}
```

**Transition body:**
```json
{
  "status": "approved",
  "note": "Looks great, ship it!"
}
```

Valid transitions: `draft` > `pending_review` > `approved` | `changes_requested`.

## Security

### Data Isolation

Every REST endpoint and portal page enforces client data isolation:

1. The `RestController` base class resolves the current client from the logged-in user.
2. `verify_project_access()` checks that the requested project belongs to the client.
3. Admins bypass isolation and see all resources.
4. Clients with no matching `ah_clients` record are denied access entirely.

### File Security

- Upload directory (`wp-content/uploads/ah-files/`) is protected with `.htaccess` deny-all rules.
- File downloads are served through the REST API, which verifies project access before streaming.
- An `index.php` file prevents directory listing.

### Portal Access

- All portal pages (except login) require authentication.
- Non-clients and non-admins receive a permission denied error.
- The login page uses WordPress nonce verification and `wp_signon()`.

## Development

### Project Structure

```
wp-agency-hub/
  src/
    API/
      ApprovalEndpoints.php     # Approval CRUD + state transitions
      FileEndpoints.php         # File upload, download, list, delete
      MessageEndpoints.php      # Message send and list
      ProjectEndpoints.php      # Project CRUD
      RestController.php        # Base class: auth, data isolation, response helpers
    Admin/
      ClientManager.php         # Client CRUD admin page
      Dashboard.php             # Admin dashboard overview
      ProjectManager.php        # Project CRUD admin page
      SettingsManager.php       # Branding and portal settings
    Notifications/
      EmailNotifier.php         # Email notifications for messages and approvals
    Portal/
      ApprovalFlow.php          # Approval state machine + portal render
      ClientDashboard.php       # Portal dashboard (project list)
      FileManager.php           # Portal file view + secure storage/serving
      MessageThread.php         # Portal message view
      PortalRouter.php          # Rewrite rules + request routing
      PortalTemplate.php        # Portal page template wrapper
      ProjectView.php           # Portal project detail view
    WhiteLabel/
      BrandingManager.php       # CSS custom property injection
      LoginCustomizer.php       # Branded login page styling
    Plugin.php                  # Singleton bootstrap, DB tables, role registration
  assets/
    css/admin.css               # Admin styles
    css/portal.css              # Portal styles
    js/admin.js                 # Admin JavaScript
    js/portal.js                # Portal JavaScript
  templates/
    admin/                      # Admin page templates
    portal/                     # Portal page templates (login, dashboard, etc.)
  languages/                    # Translation files
```

### Autoloading

PSR-4 via Composer. Namespace `WPAgencyHub\` maps to `src/`.

### Database Tables

Eight tables created on activation:

| Table | Purpose |
|-------|---------|
| `ah_clients` | Client records (user_id, company_name, contact_email) |
| `ah_projects` | Projects (client_id, name, status, description, due_date) |
| `ah_milestones` | Project milestones (name, status, due_date, sort_order) |
| `ah_files` | Uploaded files (project_id, file_name, file_path, mime_type, version) |
| `ah_approvals` | Approval requests (project_id, file_id, title, status, response_note) |
| `ah_messages` | Messages (project_id, sender_id, message, attachment_id, read_at) |
| `ah_activity_log` | Audit trail (project_id, user_id, action, details) |
| `ah_branding` | Branding settings (logo_url, colors, custom_css, portal_slug) |

### Custom Role

The `ah_client` role is created on activation with a single capability: `read`. This prevents clients from accessing wp-admin while allowing them to authenticate for the portal.

### Coding Standards

```bash
composer install
./vendor/bin/phpcs --standard=phpcs.xml src/
```

## Changelog

### 1.0.0
- Initial release
- Client portal with branded login, dashboard, project detail, files, messages, and approvals
- White-label branding with CSS custom properties
- Full REST API for projects, files, messages, and approvals
- Data isolation per client
- Email notifications
- Activity logging
- `ah_client` custom role

## License

GPL-2.0-or-later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
