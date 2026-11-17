# ARCHIspace — Accounts, Admin and Sub-admin workspaces

PHP registration/login module integrated with the existing ARCHIspace designer, blueprint uploads and hazard endpoint. Uses PHPMailer over authenticated TLS SMTP and PDO SQLite. Includes Figma-based Admin and Sub-admin dashboards, project submissions, reviewer assignments, feedback and audit history. No real email has been sent during development.

## Run locally

Requirements: PHP 8.2+, Composer 2, PDO SQLite (SQLite 3.35+), mbstring and OpenSSL. An SMTP provider with a verified sender is required for verification emails.

1. From this folder, run `composer install --no-dev`.
2. Copy `config.example.php` to `config.php`; enter the SMTP host, port, username, app password and sender. Use port 587 with `tls` or 465 with `ssl` as your provider requires. Do not disable TLS certificate validation.
3. Run `php bin/init.php` to create the private account database.
4. Run `php -S localhost:8000 -t public`.
5. Open `http://localhost:8000/auth.php`, register, open the email link, click **Verify my email**, then sign in.

Do not put secrets in the browser, JavaScript, Git or the chat. The package intentionally excludes configured credentials and Composer vendor files; Composer installs the real PHPMailer dependency.

## Deployment

- Point the web server document root at **public/** only. Never serve the package root; config, database, tests and dependencies must remain outside it.
- Set `environment` to `production` and `base_url` to the fixed HTTPS application URL (including any subdirectory). Links never use the incoming Host header. Configure HTTPS redirects at the web server or trusted reverse proxy.
- Grant only the PHP service account write access to storage. Back up `storage/auth.sqlite` and `storage/blueprints` privately. Use persistent disk; this SQLite edition is intended for a single server, not multiple replicas or network filesystems.
- Run `php bin/cleanup.php` daily to remove expired tokens and rate-limit records.
- Exclude verification and invitation URL query strings from access logs and analytics so tokens are not retained in logs.
- Configure provider SPF/DKIM/DMARC and verify real email delivery before release. Run `composer audit`; commit the generated composer.lock after resolving dependencies in your deployment environment.
- Rate limits use REMOTE_ADDR. If behind a proxy, configure trusted address forwarding at the web server; the application deliberately ignores arbitrary forwarded headers.

## Included behavior

- Registration validates names, email and matching passwords. Passwords use PHP password_hash/verify/rehash; minimum 15 characters and maximum 72 bytes to avoid bcrypt truncation.
- Email identities are case-insensitive; plus tags and dots are preserved. Unique constraints protect concurrent duplicate registration. Existing registrations never change the stored password.
- Verification uses a 256-bit random token, SHA-256 storage, a one-hour expiry, atomic single-use redemption, and invalidation of sibling tokens after verification.
- Opening a verification link only prepares a confirmation screen; a CSRF-protected POST consumes it. This avoids activation by ordinary email link scanners. Verification does not automatically sign the visitor in.
- Registration and resend return neutral account-existence messages. SMTP failures remain visible as a service problem and leave a pending account eligible for resend.
- Account and IP limits apply to login, registration and resend; verification is also IP-limited. CSRF protection, prepared queries, escaped output, HttpOnly/SameSite cookies, production Secure cookies, session rotation and POST-only logout are included.
- Sessions expire after 30 minutes idle or 8 hours total. Only verified users may access the designer, blueprint endpoint and hazard endpoint.
- **My designs:** Save stores the working design on the server (`public/designs.php`, `app/Designs.php`). Every save is a new version; the newest 20 versions per design are kept, up to 50 designs and 64 MB per account. Saves carry the version they started from, so a save from an older tab is refused instead of overwriting newer work. Open lists the account's designs with Versions, Rename and Delete. Only the owner can reach a design.
- Blueprint ownership uses a stable account identifier. Browser project storage is namespaced by account; existing anonymous projects are not automatically assigned to a new user. Export the old project and import it after login if migration is needed.

## Data and scope limitations

Working plans are kept in browser localStorage and, when the user presses Save, in **My designs** on the server; submitted review snapshots and blueprint images are also stored privately on the server. Browser namespacing prevents accidental crossover but is not encrypted storage: anyone controlling the same browser profile can inspect local plans. Submission is explicit; editing a plan does not update a submitted snapshot. Saving to My designs is manual (Save or Ctrl+S), not automatic sync. There is no password recovery, MFA or social login. Email verification is not password reset.

Admins see all submissions. Sub-admins see only assigned submissions and their own designs. Only the author sees an unpublished feedback draft; owners see published feedback. Feedback appears in **My submissions**, not in email. Invitations use PHPMailer and grant no staff privileges. The preview shows room footprints and dimensions; submitted PNG blueprints and the full JSON snapshot are also accessible through permission-checked downloads. Downloaded blueprint references remain tied to the original owner's account; reviewers should use the attachment links in the review page.
The original designer and hazard screening retain their existing limitations and professional-assessment disclaimer. Files under `hazard-data` remain local preprocessed data, not a live NOAH API.

## Figma UI implementation

Design reference: https://www.figma.com/design/Ao9mjf06DPqJCLDQgfjPwz/Untitled?node-id=0-1

- Login and registration follow frames `15:13348` and `15:13462`: green planning panel, reference blueprint layout, off-white form area, Inter and IBM Plex Mono, original downloaded Figma SVG assets and compact form controls.
- Resend and verification screens reuse the same account layout.
- The existing designer adopts the palette, navigation, icon assets, panel styling and typography from Spatial design studio `3:13034`. Its 2D/3D scene remains live project content; the mockup's static house illustration is not used as a substitute for the editor.
- New planning-step shortcuts open existing blueprint upload, site, environment and readiness tools. Terrain, river, landscape and furniture shortcuts route to existing controls. Quick guide and account privacy/help dialogs work locally.
- All fonts and Figma SVGs are stored in public/assets; no temporary Figma URLs are used at runtime.

Behavior follows the implemented module: resend verification replaces the mockup's unimplemented password-reset link; persistent Remember me, tutorial and automatic cloud-share features are not represented as working features. The existing 15-character password rule is retained. There is no fabricated Terms agreement. Public signup creates a standard account. A local privacy/help explanation describes actual storage behavior.

Admin frame `15:13933` and Sub-admin frame `15:14136` are implemented with their navigation, palette, typography, queue, user management, assignment panel, review notice, plan preview and feedback composer. Counts and project details come from the database. Empty states replace mock data. Responsive layout and security controls adapt the mockup to working forms. AI-review and general project dashboard frames are outside this update.

## Updating an existing installation

Back up your project folder, database and blueprint storage privately. Replace `app/`, `bin/`, `public/` and the documentation from this package; preserve `config.php`, `storage/`, `hazard-data/` and `vendor/`. Run:

```bash
php bin/migrate.php
```

This adds roles, review tables and the My designs tables without deleting accounts. Existing users become standard Users. Old sessions must sign in again. Hard-refresh after replacing assets. Do not downgrade the code without restoring the matching database backup.

## First Admin and Sub-admin access

1. Register and verify the intended Admin's email through the normal login page.
2. From the project root on the server, run:

```bash
php bin/promote-admin.php your-verified-email@example.com
```

3. Sign in again at `/auth.php`; the account opens `/admin.php` automatically.
4. Have each reviewer register and verify their email. In **Users & roles → Edit**, select **Sub admin**, enter your current Admin password, and save. The reviewer signs in again and is routed to `/subadmin.php`.
5. Owners select **Review → Submit for review** in the studio, or upload an exported JSON file at **My submissions**. An Admin then assigns the submission to a reviewer.

No default Admin credentials exist. The promotion command only bootstraps the first Admin; further grants require an existing Admin and password reauthentication. See **ADMIN_SETUP.md** for permissions, operations and deployment details.

## Validation

Run `php tests/auth.php` for registration, hashing, verification gating, duplicate email handling, hashed tokens, malformed/expired/replayed token rejection, login, resend, rate limiting and email-failure recovery. Tests use an in-memory database and a fake mail callback, so no emails leave the machine.

Before release, also exercise HTTP flows: CSRF rejection, session ID rotation, expired sessions, POST-only logout, unauthenticated API rejection, cross-account blueprint denial, two-user browser project separation, and actual SMTP delivery. Check desktop/mobile views and keyboard navigation.

Validation uses PHP 8.5.12 through WebAssembly because native PHP and Composer are unavailable here. Run `php tests/auth.php`, `php tests/admin.php` and `php tests/designs.php` with your native PHP installation. For HTTP checks, run `npm install` then `npm test` inside `tests/` (Node.js 24+). The harness uses isolated temporary databases and sends no email. See **VALIDATION.md** for exact results and remaining checks.

## References

- PHPMailer installation and SMTP configuration: https://github.com/PHPMailer/PHPMailer
- OWASP Authentication Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
- OWASP Email Validation and Verification: https://cheatsheetseries.owasp.org/cheatsheets/Email_Validation_and_Verification_Cheat_Sheet.html

- OWASP Authorization Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html
