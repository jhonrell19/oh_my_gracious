# Phase 3 — install and test before live use

This is a staging candidate, not a claim of a completed security audit or production certification.
Use this guide before START_HERE_HALF2.md. Older documents describe earlier releases.

## What changed

- Owner AND receptionist password sign-in now require authenticator setup/verification before staff access.
- TOTP uses 30-second codes with a one-step clock allowance. Accepted codes cannot be replayed. Ten single-use recovery codes are shown once at enrollment.
- Password confirmation plus a fresh second factor protect payment changes, staff password changes, session revocation and authenticator/recovery-code replacement.
- Staff idle timeout: 30 minutes; absolute session lifetime: 8 hours. Customer limits: 2 hours idle / 24 hours absolute. Expiry is enforced at the next protected request.
- Existing sessions are invalidated by the upgrade. Password changes invalidate sessions; suspending/reactivating staff increments a session version.
- Owner payment page verifies the API key and exact registered webhook, mode, event, enabled status and signing secret using PayMongo's read-only API. It does NOT verify payouts, merchant identity, enabled customer payment methods, fees or settlement.
- Credential edits are encrypted using AES-256-GCM in paymongo.enc outside the web root. APP_KEY stays in your private server configuration. Authenticator secrets are encrypted in the database; recovery codes are stored as hashes.
- A matching, fully reconciled webhook records a separate delivery status. Earlier Phase 2 deliveries do not count as a Phase 3 delivery check.
- Account/IP request limits, CSRF POST enforcement, password-reset token locking, protected image previews, and POST sign-out confirmation are included.
- The AR JavaScript and ONNX model are unchanged.

## 1. Back up and stop public testing

1. Stop ngrok (Ctrl+C), so visitors cannot use the site during replacement.
2. Export your current database with phpMyAdmin. Keep the SQL privately OUTSIDE htdocs and GitHub.
3. Copy the whole current project, including .env and uploads, to a private backup folder outside htdocs.
4. Stop Apache while replacing files. Leave MySQL available for importing the migration; temporarily start Apache for phpMyAdmin if needed, with ngrok stopped.

## 2. Copy files

Extract this ZIP and copy the CONTENTS of GRACE into C:\xampp\htdocs\oh_my_gracious. Do not create another GRACE subfolder inside the website.
Allow replacement of application files. This ZIP excludes your .env and customer preview images; retain your existing files.
The included uploads/designs/.htaccess must be copied too. It blocks direct public access while preview-image.php serves images only to their owner.
Remove old oh_my_gracious.sql, super-admin-test.php and any backup ZIPs from the public project folder. The package cannot delete obsolete files from your computer merely by being copied over them.

## 3. Import ONE additive migration into your existing database

In phpMyAdmin select oh_my_gracious, Import:

`database/migrations/2026_10_phase3_security.sql`

This adds user_security and security_attempts. It can be rerun. It does NOT delete users, services, bookings or payments.
DO NOT import production_schema.sql into your existing database. That schema is for a new empty installation only.

## 4. Create the private encryption setup

Open PowerShell:

```powershell
cd C:\xampp\htdocs\oh_my_gracious
New-Item -ItemType Directory -Path C:\xampp\omg-private -Force
& C:\xampp\php\php.exe tools\generate-app-key.php
```

The final command prints APP_KEY= followed by a newly generated value. Copy that entire line into your existing .env. Do not share or commit it.
Add this other line:

```ini
PRIVATE_STORAGE_DIR=C:/xampp/omg-private
```

Keep your existing APP_URL, database values, PayMongo TEST keys and SMTP/Google settings. Keep APP_ENV=production for your HTTPS/ngrok test setup.
Generate APP_KEY ONCE. Keep a private backup. Changing or losing it makes existing MFA secrets and saved payment credentials unreadable. Restore the same APP_KEY when moving to hosting.
The directory must be outside ALL public document roots, including htdocs, and writable by the PHP process. Do not use 777 permissions on hosting.
If no paymongo.enc exists, your existing .env PayMongo values continue working. After saving in the owner dashboard, the encrypted settings take precedence. Do not delete paymongo.enc as a way of switching accounts.

## 5. Restart and enroll staff

1. Start Apache and MySQL.
2. Start the existing tunnel: `ngrok http http://127.0.0.1:8081`.
3. Open the HTTPS forwarding URL. If changed, update APP_URL, Google origins and PayMongo webhook first.
4. Sign in with your existing owner email/password. Old sessions will ask you to sign in again.
5. You will see Staff Security instead of the dashboard.
6. In an authenticator app, choose Add account manually, enter the displayed setup key, choose Time based, SHA-1 / six digits / 30 seconds if the app asks.
7. Enter your current password and the current code. Save the ten recovery codes privately. They are shown only once.
8. Open the dashboard. Repeat enrollment for each receptionist using their own phone/account.
9. Sign out and sign in again. You must enter a code before accessing protected staff pages.

Keep phone time automatic. A code used for login cannot immediately be reused for changing payment settings; wait for the next code.
If a phone is lost, sign in with password plus an unused recovery code. In Security, use another unused code plus the password to replace the authenticator, then sign in and enroll the new phone.
If both factors/recovery codes are lost, an authorized server administrator must independently verify identity and use the CLI-only recovery tool. It does not reset the password:

```powershell
& C:\xampp\php\php.exe tools\recover-staff-mfa.php --email=YOUR-STAFF-EMAIL
```

Do not create a public recovery bypass. Delete or keep tools outside the public root on hosting; retain a private operator copy for maintenance.

## 6. Verify existing PayMongo TEST settings

Owner dashboard → Payments → Verify current connection.
Enter owner password and a fresh authenticator code. Leave replacement fields blank.
A green connection message verifies API credentials and registered webhook configuration only. It does not prove event delivery or the owner's bank/GCash setup.
Make a new test CARD payment and confirm Paid, receipt, revenue once, and Matching paid webhook received on the Payments page.
Do not scan test QR Ph codes with a real bank/e-wallet app. The provider documents that this can move real money.
Only select payment methods enabled in the merchant account. PayPal is not implemented.
Owner payouts are configured in PayMongo, not by entering a GCash number in this website. The live API key must belong to the intended owner merchant account.

To change credentials, use Verify and save settings after resolving every old online checkout with PayMongo. A failed/cancelled attempt may still be an unresolved payable session. The app deliberately blocks switching while those records exist. Do not delete rows or force sessions to Paid to bypass this. A fresh empty PRODUCTION database with the real service catalog and provisioned owner is an alternative before real customer data exists; preserve the test database separately.
For emergency pause set PAYMONGO_PAUSED=true in .env. This stops new checkout creation but keeps webhooks active for payments already in progress. Restore false only after review.
Replace the signing secret exposed in earlier screenshots through a planned provider configuration update. Keep old pending sessions in mind and test delivery afterwards.

## 7. Required acceptance checks on your XAMPP setup

- Customer password/Google login, logout, booking, cancellation/reschedule, receipt and AR preview save/delete still work.
- Owner and receptionist must enroll and supply MFA. Direct page/backend requests before verification must not succeed.
- Wrong, expired and already-used TOTP codes fail. A recovery code works once and fails on reuse. Store the remaining codes.
- Customer cannot view owner/receptionist pages or another customer's receipt/preview. Receptionist cannot open Payments or owner revenue.
- Suspend a logged-in receptionist; their next protected request must fail. Reactivating must not revive that old session.
- Change staff password; other sessions and the current session must be signed out. MFA remains required after email password reset.
- Verify payment configuration with wrong credentials: existing config must remain unchanged and no raw provider secret/error is shown.
- Test successful card, declined card, repeated webhook, and online-to-cash blocking. Revenue changes exactly once per payment.
- Close and reopen a slot; test capacity with two customers targeting the last place. Test cancellation/rejection followed by a late paid webhook: Refund Required, no reopening.
- Test real SMTP registration/reset delivery. Mail request limits are shared by local proxy IP on ngrok; testing many accounts may trigger the 15-minute limit.
- Confirm direct requests to uploads/designs images, .env, database SQL, vendor, tests and tools are blocked on the actual host. PHP's built-in development server ignores .htaccess: do not use it for public hosting.

## 8. Hosting and operating procedures

Use PHP 8.2+, MySQL/MariaDB, mysqli/mysqlnd, OpenSSL, cURL, mbstring and ctype. Apache/LiteSpeed must honor .htaccess; Nginx needs equivalent rules.
Upload the current code/vendor/models, create a dedicated DB account, configure HTTPS and SMTP, migrate the intended production data, and set the final APP_URL and Google origin.
Move APP_KEY and paymongo.enc securely; set the hosting PRIVATE_STORAGE_DIR outside public_html. Protect server credentials and hosting access with MFA.
Run `php tools/deployment-check.php` privately before removing public tools. The preflight does not test SMTP delivery, provider availability or authorization flows.
Schedule `php tools/security-maintenance.php` daily in the host's task scheduler/cron; it deletes expired security attempts and login history older than 90 days, not bookings or payments.
Configure daily encrypted/private backups of database, uploads, .env/APP_KEY and private payment storage, with an off-server copy. Test a restore in a separate environment before launch. Backups/monitoring are NOT activated by installing this ZIP.
Monitor application error logs and PayMongo failed deliveries. The app does not automatically reconcile provider-expired sessions or process refunds. Handle these via PayMongo and an authorized operator.
Test on the real domain before turning on live payments. Owner activation, enabled methods, payout setup and an authorized small live payment with confirmed settlement remain external launch requirements.

## Rollback

Stop ngrok/traffic. Restore the backed-up application and .env if Phase 3 fails. The additive security tables can remain; do not drop the booking database. Old Phase 2 code will not read dashboard-saved encrypted credentials, so reconcile any credential changes before rollback. Restore database backups only in a maintenance window and only if doing so will not erase newer payments/bookings.

## Validation limits

See VALIDATION_PHASE3.md. This delivery is not a guarantee of breach prevention or an independent penetration test.
