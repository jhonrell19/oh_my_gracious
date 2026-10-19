# Validation — Phase 3, 2026-10-19

## Passed in the build environment
- PHP 8.3.6 syntax lint of all 74 first-party PHP files: no failures.
- Existing payment-validation suite: 21 checks passed (signature validation and paid-event parsing).
- New security-validation suite: 15 checks passed (RFC 6238 SHA-1 TOTP vectors, invalid/expired/replayed code rejection, base32, authenticated encryption round trip, wrong-key/context and tamper rejection).
- Fresh production_schema.sql imported using MariaDB 10.11 bootstrap; Phase 3 migration rerun twice successfully.
- Source review: protected owner/receptionist pages and mutation handlers use centralized authorization; MFA is enforced there, not just by hiding navigation.
- Byte-for-byte SHA-256 comparison: js/ar-3d-nails.js and models/nail-seg.onnx unchanged from the supplied ZIP.
- Delivery excludes .env, the old user-data SQL export, customer images, .git and obsolete super-admin-test.php. Vendor dependencies are included.

## Not passed or not executed here
The environment rejected MariaDB's required Unix socket binding, so a normal running database connection for browser integration tests was unavailable. PHP/MySQL end-to-end login, enrollment, recovery-code database consumption, session revocation, UI rendering, and checkout concurrency need verification on XAMPP. Schema bootstrap and pure-function tests do not prove those behaviors.
No PayMongo credentials were supplied or used. Live API verification, real webhook delivery, real payment acceptance, payout destination, account activation, and settlement were NOT tested here.
SMTP delivery, Google login, Apache .htaccess enforcement, mobile AR, hosting TLS, backup restoration and capacity concurrency need the acceptance checks in START_HERE_PHASE3.md.
Existing earlier user tests cover the Phase 2 build; rerun affected workflows after installing Phase 3.
This is a staging candidate, not a production-readiness certification or penetration-test report.

## Repeat locally
From the application folder in PowerShell:

```powershell
& C:\xampp\php\php.exe tests\security-validation.php
& C:\xampp\php\php.exe tests\payment-validation.php
& C:\xampp\php\php.exe tools\deployment-check.php
```
The security unit test sets a temporary process-only test key and never writes .env or the database. The deployment preflight deliberately reports production settings that are still missing (for example a dedicated non-root DB account on local XAMPP).

## Sources used for design
- https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html
- https://docs.paymongo.com/docs/developer-tools-webhook-setup-management
- https://docs.paymongo.com/reference/webhook-resource
- https://docs.paymongo.com/docs/payment-acceptance-testing
