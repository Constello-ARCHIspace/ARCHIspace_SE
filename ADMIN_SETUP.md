# Admin and Sub-admin setup

## Installation

Use PHP 8.2+, PDO SQLite (SQLite 3.35+), mbstring, OpenSSL, Composer and a verified SMTP sender. Follow README.md to install PHPMailer and configure `config.php`. Serve **public/** as the document root. Keep database, credentials, dependencies, scripts and blueprint storage outside the public root.

New installation:

```bash
composer install --no-dev
# Copy config.example.php to config.php and configure it first.
php bin/init.php
php -S localhost:8000 -t public
```

Existing installation: take a private backup, replace the source folders described in README.md, preserve configuration/storage, and run `php bin/migrate.php`. The migration is repeatable and preserves existing accounts. It does not create an Admin or send mail.

For existing accounts, the migration uses the first and last words of the saved name as the first and last names. Any words between them become the middle name. A single-word name stays in the first-name field. New registrations accept compound names in each field and require both a first and a last name.

## Create the first Admin

Register your account at `/auth.php?view=register`, verify the emailed link, then run from the project root:

```bash
php bin/promote-admin.php your-verified-email@example.com
```

Use your actual verified address. The command runs only from the server console and refuses to operate if an Admin already exists. It never creates credentials or skips email verification. Log out and sign in again: `/auth.php` redirects Admins to `/admin.php` and Sub-admins to `/subadmin.php`.

In **Users & roles → Edit**, an Admin can promote a verified account to Sub admin or Admin, or suspend an account. Enter the acting Admin's current password. Accounts cannot remove their own Admin access; the last active verified Admin is protected. Existing sessions are revoked after role/status changes. Staff suspension or removal also unassigns their reviews.

## Permissions

| Capability | User | Sub-admin | Admin |
| --- | --- | --- | --- |
| Use studio and submit own designs | Yes | Yes | Yes |
| Read own published feedback | Yes | Yes | Yes |
| Review other owners' submissions | No | Assigned only | All |
| View submitted blueprint attachments | Own only | Own or assigned | All submitted |
| Save a private review draft / publish feedback | No | Assigned only | All |
| Assign or reassign a reviewer | No | No | Yes; current password required |
| Manage roles and suspend accounts | No | No | Yes; current password required |
| Invite standard users by email | No | No | Yes; current password required |
| View security/activity history | No | No | Yes |

Every page, mutation, download and submitted image checks server-side access. A Sub-admin cannot expand access by changing a URL or posting another project ID. Only a draft's author can read it. Published feedback remains available to the owner after reassignment.

## Review workflow

1. Owner chooses **Review → Submit for review** in the studio. Blueprint images are saved to the account, then a snapshot is submitted. Alternatively, use **My submissions** to upload an exported ARCHIspace JSON file.
2. Admin opens the queue, selects a project and assigns an active verified staff reviewer.
3. Sub-admin opens the assigned project, examines the room preview and uploaded blueprint images, and downloads the full plan if needed.
4. Reviewer enters the location, priority and recommended action. **Save draft** is private; **Send feedback to owner** publishes to the owner's **My submissions** page and marks the review awaiting owner or completed.
5. A revised design is submitted as a new snapshot. Existing snapshots stay unchanged. This edition does not group revisions automatically.

Feedback is human screening guidance, not structural certification. The existing professional-assessment disclaimer remains visible. No hazard assessment or AI recommendation is fabricated by the admin dashboards.

## Invitations and mail

Invitations use the same configured PHPMailer SMTP sender as verification. They expire after 48 hours, are bound to the verified recipient email and can be accepted once. Tokens are stored as hashes; opening a link does not consume it. Public registration and invitation acceptance always leave the account's role unchanged (new accounts default to User). Staff permissions must be granted separately by an Admin.

SMTP delivery failure removes the new invitation token and shows an error. Feedback publication is an in-app notification, not an outgoing email. No real messages were sent during development.

## Security and operations

- HTTPS is mandatory with production configuration. Secure/HttpOnly/SameSite cookies, session rotation, 30-minute idle and 8-hour absolute expiry are enabled.
- Current role, status and session version are checked against the database on each protected request. Authorization is rechecked inside write transactions.
- All writes require POST and CSRF protection. Sensitive Admin actions additionally verify the Admin's password. Reauthentication is limited to 5 attempts/actions per account per 15 minutes and 20 per IP; staff writes are limited to 80 per 15 minutes. Invitations are limited to 5 per hour.
- Assignment and feedback writes use a version check: a stale page returns a conflict rather than overwriting another review. SQLite write transactions serialize mutations.
- Submission limits: 10 attempts/hour, 4 MB/snapshot, 500 rooms, 20 blueprint references, 100 submissions and 64 MB of JSON per account. Account PNG quota remains 100 MB. Configure `post_max_size` and `upload_max_filesize` to support the intended uploads (for example 20M and 12M); enforce server request limits as well.
- Only the submitting owner's existing blueprint references are accepted; remote URLs, embedded image data and arbitrary filesystem paths are rejected. MIME/PNG validation remains in the blueprint upload endpoint.
- Audit history records staff/access/assignment/invitation/submission/feedback events without passwords, tokens or feedback bodies. The UI shows the latest 100 events; establish a private backup and retention policy. This is an application log, not a tamper-proof external audit service.
- Run `php bin/cleanup.php` daily. Exclude verification and invitation query strings from web-server/access analytics logs. Back up the SQLite database and image directory with consistent snapshots.
- Deploy on a single server with private persistent local disk. Do not put SQLite on a network filesystem or share it across replicas. Password reset, MFA, deletion/retention management screens and live collaboration are outside this module.
- Run `composer audit`, verify actual SMTP delivery, and test your native PHP/HTTPS deployment before public release. See VALIDATION.md.
