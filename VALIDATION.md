# Validation results

Checked on 7 November 2026.

Passed:
- PHP 8.5.12 WebAssembly syntax checks for all module/application PHP files.
- All 21 checks in tests/auth.php (real PHP password hashing and SQLite; fake mail transport).
- All 16 HTTP checks using PHPRequestHandler: login rendering, cookie attributes, CSP, CSRF rejection, protected designer and endpoints, POST-only logout, verification confirmation, invalid token rejection, verified login, session-cookie rotation, per-account project key and logout access revocation.
- Independent PHP parser checks.
- JavaScript syntax checks for auth.js and the designer's inline scripts.

Not verified:
- Native PHP 8.2+ web-server deployment, Composer installation/audit and live PHPMailer SMTP delivery.
- Production HTTPS/proxy configuration, concurrent load and email deliverability.
- Full browser visual/mobile/keyboard testing. Figma login, register and studio design contexts and screenshots were successfully retrieved from the new file. Local browser previews were blocked by environment restrictions, so live browser geometry and interactions remain unverified.
- Cross-account blueprint upload/download end-to-end and idle/absolute session timeout behavior (implemented, but not exercised by the HTTP smoke suite).

Do not treat this as a production security audit. Configure the sender and run the documented deployment checks before public release.

## Figma UI update checks

- Re-ran the 16 HTTP checks after the UI update: all passed.
- PHP syntax checks passed for the updated entry points and shared story partial.
- JavaScript syntax checks passed for auth.js, studio-ui.js and all designer inline scripts.
- All 14 referenced Figma SVG files are non-empty, parse correctly, and retain their original root dimensions. Fonts are bundled with their licenses.
- Authentication logic and database schema are unchanged. No real SMTP messages were sent.
- Login and registration were inspected through a static HTML-to-PDF layout render with local fonts/assets. This is not a substitute for live browser/mobile testing.
