# Validation results

Checked on 8 November 2026 using PHP 8.5.12 WebAssembly, real PDO SQLite and PHP password hashing. No actual verification or invitation emails were sent.

## Automated checks: 114 passed

| Suite | Checks | Coverage |
| --- | ---: | --- |
| `tests/auth.php` | 21 | Registration, hashing, verification gating, duplicates, hashed/single-use/expired tokens, login, resend, limits, simulated mail failure |
| `tests/http-smoke.mjs` | 16 | Login rendering, cookie attributes, CSP, CSRF, protected endpoints, verification confirmation, session rotation, account project key, logout |
| `tests/admin.php` | 34 | Repeatable legacy migration, role/ownership/assignment boundaries, draft privacy, publication, stale writes, suspension, session-version changes, invitation email binding/expiry/replay, quotas and invalid blueprint references, audit content |
| `tests/admin-http.mjs` | 38 | Separate account sessions, role redirects, dashboard restrictions, CSRF/POST enforcement, forged privilege fields, submissions, cross-owner downloads and images, assigned access, password reauthentication, escaped feedback, duplicate publication, revoked sessions, invitations |
| `tests/bootstrap-check.mjs` | 5 | Database initialization, refusal of unverified first Admin, verified promotion, repeat-bootstrap refusal, session-version increment and audit event |

Additional checks:

- PHP syntax checks for application, CLI and test files.
- JavaScript syntax checks for the portal, studio helpers and designer inline scripts.
- Thirteen new Figma Admin/Sub-admin SVG assets parse successfully and retain their original root dimensions. Local fonts and existing account/studio assets remain bundled.
- Figma contexts and screenshots were retrieved for Admin `15:13933` and Sub-admin `15:14136`. Static HTML-to-PDF inspection checked their broad layout hierarchy using synthetic test accounts. The renderer does not execute canvas JavaScript and has form/flex layout differences; this is not browser visual validation.

One issue caught by the tests was SQLite parameter typing in the private-draft query. An explicit integer cast fixed it; draft-author visibility, owner exclusion and publication tests then passed.

## Reproduce

With a native PHP installation:

```bash
php tests/auth.php
php tests/admin.php
node tests/bootstrap-check.mjs
cd tests
npm install
npm test
```

The HTTP harness requires Node.js 24+ and the pinned PHP WebAssembly packages. CLI bootstrap tests use `php` on PATH, or an executable supplied through `PHP_EXECUTABLE`. All test databases and accounts are isolated fixtures. The intentional SMTP-failure tests do not deliver email.

## Still required before deployment

- Native PHP 8.2+ server operation, Composer installation/audit, PHPMailer delivery through your provider, and production HTTPS/proxy configuration.
- Browser desktop/mobile/keyboard checks, dialog focus, dropdown placement, canvas drawing, file-picker upload and studio submission interaction. Local live browser preview was unavailable in this environment.
- End-to-end multipart upload through the native web server. The HTTP tests exercise valid stored PNG attachments, access control and snapshot submission; they do not replace a browser upload test.
- Idle/absolute session timeouts, concurrent load, filesystem permissions, consistent backups, retention and recovery procedures.

This is implementation testing, not an independent production security audit. No public deployment was performed.
