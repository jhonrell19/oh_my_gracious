# OH MY GRACIOUS — Panel Revision / Production Upgrade Build

This package is an upgraded replacement for the existing OH MY GRACIOUS PHP/MySQL project. It addresses the panel revisions while preserving the existing live database through a one-time migration.

## 1. BACK UP FIRST

1. Copy `C:\xampp\htdocs\oh_my_gracious` to `oh_my_gracious_backup`.
2. In phpMyAdmin, export the current `oh_my_gracious` database.
3. Keep both backups until the upgraded system has been tested.

## 2. REPLACE THE APPLICATION FILES

Replace the old project files with this folder at:

`C:\xampp\htdocs\oh_my_gracious\`

Do **not** delete or overwrite your existing database with the fresh schema if you want to preserve existing users/bookings.

## 3. CREATE `.env`

Copy `.env.example` to `.env`. Local XAMPP defaults are already provided. Never commit or upload a real `.env` containing secrets.

Important production variables include database credentials, `APP_URL`, Google/Meta/Twilio credentials, and PayMongo credentials.

## 4. UPGRADE YOUR EXISTING DATABASE (RUN ONCE)

Open phpMyAdmin → `oh_my_gracious` → SQL and run:

`database/upgrade_existing_database.sql`

Use `database/production_schema.sql` (or `oh_my_gracious.sql`) only for a completely fresh installation.

## 5. VERIFY THE OWNER ROLE

If the owner account is `admin@ohmygracious.com`:

```sql
UPDATE users
SET role='super_admin', account_status='active'
WHERE email='admin@ohmygracious.com';
```

No password is hard-coded in this build. The owner keeps the password already stored in the database.

## 6. LOCAL HEALTH CHECK

Open:

`http://localhost/oh_my_gracious/tools/health-check.php`

It verifies required tables, PHP cURL, and external-provider configuration. The health check returns 404 when `APP_ENV=production`. Remove the `tools` folder before final public deployment.

## 7. ROLE SEPARATION

- `user` → customer dashboard, profile, AR, saved designs, own appointments
- `admin` → receptionist/front desk dashboard, customers, bookings, check-in, appointment status, in-person payment recording
- `super_admin` → owner dashboard, sales/revenue, services/pricing, technicians, admin accounts, business settings, activity logs

All roles use the same `login.html`. Public registration can create only `user` accounts.

## 8. PANEL REVISIONS IMPLEMENTED

### Complete Registration / User Accounts
- detailed registration (name, email, phone, DOB, technician preference, nail preferences, allergy/sensitivity notes)
- password hashing and validation
- account status and verification tokens
- profile editing and password change
- forgot/reset password and resend verification
- connected login-method display

### One Login for All Accounts
- one public login screen
- server-side role detection
- customer / receptionist / owner redirects and authorization

### Multiple Service Types
- database-driven `services` table
- multiple services in one booking
- server-side price/duration calculation (browser price is never trusted)
- owner service/pricing management
- multi-slot duration-aware availability
- unique slot claims to reduce concurrent double bookings

### Payment Method Integration
- Cash at Salon
- PayMongo Hosted Checkout option for online methods enabled on the merchant account (for example GCash, Maya, cards, and QRPh)
- payment records stored separately from appointments
- checkout-session tracking and gateway payment IDs
- verified PayMongo webhook is the only code path that marks an online payment Paid
- return page never trusts the browser to mark a payment paid
- late online payment after cancellation/rejection is flagged `Refund Required` and excluded from normal revenue
- receptionist can mark eligible in-person/manual payments Paid

### Admin Statistics / Features
- today/pending/approved/checked-in/completed counts
- customer directory
- receptionist booking creation
- appointment filtering
- approve / reject / check-in / complete workflow
- payment status visibility

### Owner / Super Admin
- paid revenue today/month/all-time
- revenue by service and payment method
- refund/reconciliation attention
- service/pricing management
- technician management
- receptionist/admin account management
- business booking settings
- audit/activity logs

### UI Upgrade
- responsive navigation, dashboards, cards, forms, tables, alerts and status chips
- separate customer/front desk/owner interfaces
- improved authentication and booking screens

### AR Enhancement
- MediaPipe hand-landmark AR preview
- color/shape/finish/size/calibration controls
- capture preview
- save AR design to customer account
- attach saved design to a booking

**AR limitation:** this remains landmark-based placement rather than commercial-grade nail segmentation. Lighting, hand angle and camera quality can still affect alignment.

## 9. GOOGLE / FACEBOOK / PHONE OTP

The endpoints are included and activate when credentials are present in `.env`. They use PHP cURL directly, so Composer is optional for these integrations.

- Google: set `GOOGLE_CLIENT_ID`
- Facebook: set `FACEBOOK_APP_ID`, `FACEBOOK_APP_SECRET`, and the Graph version
- Twilio Verify: set the Account SID, Auth Token and Verify Service SID

New social/phone registrations are always customers (`user`). The external provider never chooses application roles.

## 10. PAYMONGO SETUP

Set in `.env`:

```env
PAYMONGO_SECRET_KEY=sk_test_...
PAYMONGO_WEBHOOK_SECRET=...
PAYMONGO_PAYMENT_METHODS=card,gcash,paymaya,qrph
```

Configure this **public HTTPS** webhook in PayMongo:

`https://YOUR-DOMAIN/webhooks/paymongo.php`

Subscribe to the Checkout payment-paid event. Localhost cannot receive a public gateway webhook directly; use an HTTPS tunnel for local gateway testing or test after deployment.

The success/return URL is informational only. The verified webhook is the payment source of truth.

## 11. EMAIL

When `APP_ENV=local`, registration/reset can expose a local testing link. In production the code uses PHP mail integration points. Configure reliable SMTP/hosting mail delivery before public launch.

## 12. DEPLOYMENT CHECKLIST

Before going live:

- set `APP_ENV=production` and the real HTTPS `APP_URL`
- use a dedicated database user (not MySQL `root`)
- enable HTTPS
- configure reliable email delivery
- configure only the external login providers you intend to use
- configure PayMongo test mode first, then approved live credentials
- register the public webhook and verify test callbacks
- change/remove all temporary development accounts
- keep regular database backups
- remove `tools/` from the public server
- keep `.env` private and outside source control
- test customer, receptionist, and owner permissions separately

## 13. IMPORTANT PAYMENT OPERATIONS

A PayMongo payment that arrives after its appointment was already cancelled/rejected is **not counted as normal revenue**. The system flags it as `Refund Required` for the owner to reconcile through the merchant/provider process. This build does not pretend that a provider refund occurred unless it actually did.
