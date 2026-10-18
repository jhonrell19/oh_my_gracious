# OH MY GRACIOUS — Half 2 installation and deployment

This is a deployment candidate, not a confirmation that your public website is live.
The AR renderer/model are unchanged. Customer, receptionist and owner roles remain separate.
Receptionists still cannot create bookings.

## What changed

- Customers submit a booking, then choose Pay Online from Booking History for fixed-price bookings.
- Checkout collects the FULL saved booking amount, including per-nail quantities. No deposit percentage or extra customer fees are introduced.
- Pay at Salon remains available by default. Estimated/range-priced bookings stay at Pay at Salon because a final-price editing workflow has not yet been built.
- After a checkout request begins, that booking becomes online-payment only. Receptionists cannot manually mark it paid, preventing cash and online double collection.
- PayMongo hosted checkout uses the real v2 API. Test keys process test payments; live keys process real money.
- Only a correctly signed, matching PayMongo paid webhook marks the booking Paid. Returning to a success URL cannot do this.
- Webhook validation checks mode, session, PHP currency and exact centavos. A payment is applied once, with a transaction and durable event/payment records.
- Stored checkout requests reuse the same random idempotency key and request body. Existing session URLs are reused, not replaced every 20 minutes.
- Google sign-in uses Google's PHP client for ID-token verification. Passwords are still required for staff. Automatic email linking requires Google-hosted email; existing non-Google-hosted accounts use password login.
- Production verification/reset emails use authenticated SMTP through PHPMailer.
- Protected pages recheck current account status and role.
- Production configuration, sensitive-file protection, owner refund-review list and a command-line preflight are included.
- Composer dependencies are bundled and locked; no Composer installation is needed merely to copy this package onto a compatible host.

## 1. Back up before replacing files

1. Stop using the site while upgrading.
2. Copy `C:\xampp\htdocs\oh_my_gracious` to a backup folder outside `htdocs`.
3. Open `http://localhost/phpmyadmin/`.
4. Select `oh_my_gracious`, click Export, select Quick / SQL, and download the backup.
5. Keep your existing `.env` and `uploads` folder. This ZIP excludes your secrets and old database dump.

## 2. Install locally

1. Extract this ZIP.
2. Open the extracted `grac` folder.
3. Copy its CONTENTS into `C:\xampp\htdocs\oh_my_gracious`, allowing replacement. Do not create `oh_my_gracious\grac`.
4. Keep your current `.env`. Add the payment/SMTP settings from `.env.example` without replacing your database or Google values.
5. In phpMyAdmin, select your EXISTING `oh_my_gracious` database.
6. Click Import and choose `database\migrations\2026_10_half2_payments.sql` from the project. Click Go.
7. This migration adds two tables and can be rerun. Do NOT import `production_schema.sql` into an existing database. It is for a new empty database only.
8. Start Apache/MySQL and open `http://localhost/oh_my_gracious/tools/health-check.php`.
9. Confirm both payment tables appear as present. Existing Half 1 service/quantity columns must also pass.
10. Open Booking History and press Ctrl+F5. Online payment is hidden until enabled/configured.

Local settings initially:

```ini
APP_ENV=local
APP_URL=http://localhost/oh_my_gracious
PAYMONGO_ENABLED=false
PAYMONGO_SECRET_KEY=
PAYMONGO_WEBHOOK_SECRET=
PAYMONGO_PAYMENT_METHODS=gcash,paymaya,qrph,card
SMTP_HOST=
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=
SMTP_PASSWORD=
```

Do not paste secret keys, SMTP passwords or your `.env` into chat.

## 3. Hosting requirements

Use a host that runs PHP and MySQL/MariaDB; this project cannot run as a static-only website.
Required: PHP 8.2+ (8.3 recommended), mysqli/mysqlnd, cURL, OpenSSL, mbstring, ctype, sessions, outbound HTTPS, authenticated SMTP, HTTPS certificate, and writable `uploads/designs`.
Apache must allow the supplied `.htaccess` files (including Options, authorization and headers). For Nginx, the host must implement equivalent deny rules; `.htaccess` is ignored there.
Confirm the host accepts the approximately 47 MB ONNX model file and serves it intact.

## 4. Upload and configure the host

1. Create a database and a dedicated database user in the host control panel. Grant access only to this application's database.
2. Export your upgraded LOCAL database and import it into the hosting database. This keeps the real services, owner and data. Remove CREATE DATABASE / USE lines from your export if the host requires its assigned database name.
3. Upload project contents to the intended web directory, including `vendor`, `models` and hidden protection files.
4. Do not upload your local `.env`. Create the host `.env` using `.env.example`.
5. Set `APP_ENV=production` and `APP_URL=https://YOUR-DOMAIN` (include the subfolder if installed in one; no trailing slash).
6. Set the host's DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASSWORD.
7. Configure SMTP_HOST, SMTP_PORT, SMTP_ENCRYPTION, SMTP_USERNAME, SMTP_PASSWORD and MAIL_FROM using your mail provider. Use `tls` with port 587 or `ssl` with 465 as your provider specifies. MAIL_FROM must be an authorized sender.
8. Ensure `uploads/designs` is writable by PHP. Do not use world-writable permissions by default.
9. Configure the hosting panel to redirect HTTP to HTTPS. Production cookies require HTTPS.
10. Remove `tools` from the public upload after preflight, and do not upload `tests`, old SQL dumps or backup ZIPs. Database files should also be kept outside the public web directory where practical.
11. Change the owner's previous test password through Account before public use.
12. In Google Cloud, add the exact production HTTPS origin to the web client's Authorized JavaScript Origins. Keep the same client ID in the host `.env`.

If your host provides a terminal, run from the project directory:

```sh
php tools/deployment-check.php
```

This checks configuration and dependencies without sending emails or making charges. It does not prove external delivery.
If you need to rebuild dependencies later, run `composer install --no-dev --prefer-dist --optimize-autoloader` from this directory using the supplied lock file.

## 5. Enable PayMongo in TEST mode first

1. Open your PayMongo dashboard and obtain the TEST secret key through the Developers settings.
2. Register an HTTPS webhook URL pointing to `https://YOUR-DOMAIN/webhooks/paymongo.php` (include your subfolder if applicable).
3. Subscribe to `checkout_session.payment.paid`.
4. Copy that webhook's signing secret to PAYMONGO_WEBHOOK_SECRET. It is different from the API secret key.
5. Put your test API secret in PAYMONGO_SECRET_KEY and set PAYMONGO_ENABLED=true.
6. List only payment methods enabled for your PayMongo account in PAYMONGO_PAYMENT_METHODS.
7. Register/login as a customer, add a phone number and book fixed-price services. Include a per-nail service with quantity greater than one.
8. In Booking History choose Pay Online. Verify every quantity and the total on PayMongo's page.
9. Complete a provider-supported TEST payment. Check the webhook delivery in PayMongo.
10. Booking History must become Paid and display Receipt. Appointment approval remains a separate receptionist action.
11. Repeat with a cancelled/failed attempt. It must NOT become Paid. Reopen the SAME booking's checkout to retry; do not create another booking just to retry payment.
12. Replay the paid webhook in the provider dashboard. It must not create duplicate payment effects or audit entries.

A localhost URL cannot receive public provider webhooks. Use your HTTPS staging/hosting URL for these tests. Test transactions are not real-money payment acceptance.

## 6. Final tests and live switch

- Normal registration: email arrives; verification link works; password login works.
- Forgot password and resend verification: emails arrive and links work.
- Google: new customer, repeat login, existing Gmail customer, staff email rejection, suspended customer rejection.
- Customer: multiple services and quantities, unavailable services, full slot, closed slot, cancellation/rescheduling, profile, history, saved designs, AR camera on HTTPS.
- Receptionist: approve/reject/check-in/complete/no-show, cash Mark Paid, online Mark Paid blocked, no Create Booking, service/slot availability.
- Owner: services/prices, staff accounts, technicians, capacity, revenue, logs. Direct role-restricted URLs must reject the wrong role.
- Public requests to `.env`, `.env.example`, `composer.json`, database SQL, vendor files and tools must return denied/not found. Verify this on the actual host.
- Confirm AR uses the unchanged model and renderer on both desktop and your demo phone.
- Keep a backup of files and database before enabling live payments. Never overwrite a database containing live transactions with an older test copy.

After test mode passes, use a LIVE-enabled merchant account and live API key, register/configure its LIVE webhook signing secret, and verify account payment methods. Resolve all pending test checkouts before switching; use a clean production database or a controlled migration that preserves real customer data. Do not mix outstanding test sessions with live mode. Arrange an authorized small real payment and confirm the live webhook/receipt before opening public bookings.

## Payment exceptions and current limits

- Leaving checkout does not cancel the appointment. The salon's existing Pending booking/capacity policy remains in effect; no new automatic timeout is imposed.
- A paid webhook after cancellation/rejection/no-show records Refund Required without reopening the appointment. The owner sees it under Revenue Reports. Arrange refunds in PayMongo; automated refund API and refund-status synchronization are not implemented.
- An ambiguous checkout creation request can be retried with the same key for up to 23 hours. After that it requires owner/provider reconciliation to avoid duplicate payment sessions. Never delete its request row or reset its gateway just to retry.
- A provider-expired session is not automatically replaced. Reconcile/expire the old session in PayMongo before a developer-assisted reset. Normal failed payment attempts can reuse the active session.
- Estimated/range-price bookings remain pay-at-salon. Their final-price adjustment workflow remains outstanding; do not mark an estimate as a final collected amount unless it is actually correct.
- Reports show gross paid amounts, not net deposits after provider fees. System receipts are payment records, not a claim of tax-invoice compliance.
- Back up daily; verify restoration, error logs, SMTP delivery and webhook failures with your host. Do not log full tokens or payment payloads.

## Sources checked during implementation

- https://docs.paymongo.com/docs/payment-channels-hosted-checkout
- https://docs.paymongo.com/docs/payment-channels-hosted-checkout-quick-start
- https://docs.paymongo.com/docs/developer-tools-webhook-setup-management
- https://docs.paymongo.com/reference/idempotent-requests
- https://developers.google.com/identity/gsi/web/guides/verify-google-id-token
- https://github.com/PHPMailer/PHPMailer
