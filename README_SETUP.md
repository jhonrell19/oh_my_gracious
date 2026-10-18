> Updated build: follow START_HERE_HALF2.md first. The older instructions below describe the previous build and are superseded where they conflict.

# OH MY GRACIOUS Salon Management System

## Current release

This is the **pre-payment operational release**. It focuses on making customer, receptionist/front-desk, and owner workflows complete before adding an online payment gateway.

### Authentication

The system has one login page for all roles. Email/password and Google Identity Services are supported. Facebook and phone/SMS authentication have been removed. Phone number remains a normal customer contact field.

Google account handling follows these rules:

- An existing Google provider ID logs into the account already linked to it.
- A verified Google email that already belongs to a **customer** links Google to that same customer instead of creating a duplicate.
- A Google email that belongs to an `admin` or `super_admin` is **not** auto-linked. Staff/owner must use normal staff credentials.
- New Google users are always created with role `user`.
- Database uniqueness prevents the same Google identity from being linked twice.

Configure Google with `GOOGLE_CLIENT_ID` in `.env`. Never commit `.env`.

## Roles

### Customer (`user`)

- register/login
- Google register/login
- manage profile/contact number/preferences
- book one or multiple currently available services
- choose a fixed appointment time with remaining capacity
- cancel eligible unpaid future bookings
- view booking history and receipts
- use AR nail try-on and saved designs

### Front Desk (`admin`)

- daily dashboard and appointment queue
- create appointments for existing customers
- manage customer-created appointments; front-desk booking creation is disabled
- approve/reject/cancel/reschedule
- check in / complete / mark no-show
- record in-salon payment as Paid
- search customer accounts
- temporarily enable/disable services with a reason
- close/reopen a date/time and override its capacity

Front desk does not have owner revenue, staff-admin, or critical business settings access.

### Owner (`super_admin`)

- business dashboard and revenue summaries
- manage service catalog, categories, prices and estimated durations
- manage technicians
- manage front-desk administrator accounts
- configure fixed booking times and default capacity
- review audit logs

Owner is intentionally separated from daily appointment operations.

## Capacity-based scheduling

This release uses fixed booking times rather than strict duration-based blocking:

- AM: 9:00, 10:00, 11:00
- PM: 1:00, 2:00, 3:00, 4:00, 5:00, 6:00, 7:00

Each time has a configurable capacity. The default is 5 customers per time. The booking transaction locks the slot inventory row before incrementing the reservation count, preventing two simultaneous requests from exceeding the configured capacity.

Estimated service duration remains stored and displayed as informational data only.

## Existing database upgrade

Back up first, then run once:

`database/migrations/2026_10_pre_payment_capacity_google_only.sql`

Do not re-run old migrations that have already been applied.

## Fresh database

Import:

`database/production_schema.sql`

Then create `.env` from `.env.example`.

## Local URLs

- Home: `http://localhost/oh_my_gracious/`
- Login: `http://localhost/oh_my_gracious/login.html`
- Health check: `http://localhost/oh_my_gracious/tools/health-check.php`
- Local owner creator: `http://localhost/oh_my_gracious/tools/create-owner.php`

`tools/create-owner.php` and `tools/health-check.php` are local/development helpers and should not be exposed in production.

## Payments

Online payment is deliberately deferred. Current payment workflow is Pay at Salon / Cash with front-desk verification. The system should pass operational user acceptance testing before a real payment provider is added.

## Production checklist before public deployment

- `APP_ENV=production`
- real HTTPS domain in `APP_URL`
- dedicated database account with a strong password (not MySQL root)
- real Google Web Client ID and production authorized origin
- real email delivery/SMTP or transactional email provider for password-account verification/reset
- web server rules preventing `.env` access and directory listing
- remove or block local setup tools
- database backup strategy
- final role/access tests
- final concurrent capacity test
- final AR/camera permission test on target browsers/devices
