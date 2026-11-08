# ARCHIspace — Accounts and email verification

PHP registration/login module integrated with the existing ARCHIspace designer, blueprint uploads and hazard endpoint. Uses PHPMailer over authenticated TLS SMTP and PDO SQLite. No real email has been sent during development.

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
- Exclude verification URL query strings from access logs and analytics so tokens are not retained in logs.
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
- Blueprint ownership uses a stable account identifier. Browser project storage is namespaced by account; existing anonymous projects are not automatically assigned to a new user. Export the old project and import it after login if migration is needed.

## Data and scope limitations

Project plans still use browser localStorage: namespacing prevents accidental account crossover in the interface, but it is **not encrypted storage or server authorization**. Anyone controlling the same browser profile can inspect local data. Accounts and blueprint files are server-side; project plans are not cloud-synced. No password recovery, MFA, social login or administrator console is included in this registration/login module. Do not treat the email verification screen as a password reset flow.

The original designer and hazard screening retain their existing limitations and professional-assessment disclaimer. Files under `hazard-data` remain local preprocessed data, not a live NOAH API.

## Figma UI implementation

Design reference: https://www.figma.com/design/Ao9mjf06DPqJCLDQgfjPwz/Untitled?node-id=0-1

- Login and registration follow frames `15:13348` and `15:13462`: green planning panel, reference blueprint layout, off-white form area, Inter and IBM Plex Mono, original downloaded Figma SVG assets and compact form controls.
- Resend and verification screens reuse the same account layout.
- The existing designer adopts the palette, navigation, icon assets, panel styling and typography from Spatial design studio `3:13034`. Its 2D/3D scene remains live project content; the mockup's static house illustration is not used as a substitute for the editor.
- New planning-step shortcuts open existing blueprint upload, site, environment and readiness tools. Terrain, river, landscape and furniture shortcuts route to existing controls. Quick guide and account privacy/help dialogs work locally.
- All fonts and Figma SVGs are stored in public/assets; no temporary Figma URLs are used at runtime.

Behavior follows the implemented module: resend verification replaces the mockup's unimplemented password-reset link; persistent Remember me, admin/sub-admin roles, tutorial and cloud-share features are not represented as working features. The existing 15-character password rule is retained. There is no fabricated Terms agreement. Public signup creates a standard account. A local privacy/help explanation describes actual storage behavior.

This update covers account screens and the existing editor UI. The separate dashboard, admin, sub-admin and AI-review frames were not implemented as new modules. The editor is an adaptation to working controls, not a pixel-identical copy of the illustrative Figma scene.

## Updating an existing installation

Back up your project folder. Replace `public/auth.php`, `public/index.php`, `public/assets/`, and add `app/auth-story.php` from this package. Keep your existing `config.php`, `storage/`, and `vendor/` directories. No database migration is needed. Hard-refresh the browser after replacing the assets.

## Validation

Run `php tests/auth.php` for registration, hashing, verification gating, duplicate email handling, hashed tokens, malformed/expired/replayed token rejection, login, resend, rate limiting and email-failure recovery. Tests use an in-memory database and a fake mail callback, so no emails leave the machine.

Before release, also exercise HTTP flows: CSRF rejection, session ID rotation, expired sessions, POST-only logout, unauthenticated API rejection, cross-account blueprint denial, two-user browser project separation, and actual SMTP delivery. Check desktop/mobile views and keyboard navigation.

Validation used PHP 8.5.12 through WebAssembly because native PHP and Composer were unavailable. All 21 core checks and 16 HTTP checks passed. See VALIDATION.md for scope and remaining checks. To reproduce the optional HTTP tests, use Node.js 24.18+ and npm 11.16+, run `npm install` inside `tests/`, then `npm test`. The harness uses a temporary isolated database and never sends email. Native server deployment and actual SMTP delivery still need validation.

## References

- PHPMailer installation and SMTP configuration: https://github.com/PHPMailer/PHPMailer
- OWASP Authentication Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
- OWASP Email Validation and Verification: https://cheatsheetseries.owasp.org/cheatsheets/Email_Validation_and_Verification_Cheat_Sheet.html
