# OH MY GRACIOUS — Half 1 Upgrade

This build is the first half of the finalization work before real payment integration.

## What changed

- Front Desk / Receptionist can no longer create bookings.
- Customer booking remains the only booking-creation flow.
- Real salon services/prices from the supplied salon menu photos were added.
- Owner can edit category, service name, pricing type, price, range maximum, optional estimated duration, and active/inactive status.
- Per-nail services support quantity 1–10 and calculate server-side.
- Range-priced services display a starting total and are flagged as estimated for later payment handling.
- Booking items now store quantity and line total.
- Booking, history, receipt, and revenue views understand quantities.
- Deprecated Facebook and phone/SMS authentication endpoints are disabled. Phone number remains contact information.

## Existing database upgrade

Back up the database first. Then in phpMyAdmin select `oh_my_gracious` and run exactly once:

`database/migrations/2026_10_real_services_frontdesk_lockdown.sql`

Do not import `production_schema.sql` over an existing database.

## Fresh install

A new installation can use:

`database/production_schema.sql`

## Quick checks after migration

1. Open `tools/health-check.php`.
2. Owner > Services should show the real catalog and editable pricing controls.
3. Front Desk navigation should have Dashboard, Appointments, Availability, Customers, Logout — no Create Booking.
4. Manually opening `admin-create-booking.php` should redirect back to Front Desk with a disabled message.
5. Customer booking should support search/category filters and per-nail quantities.
6. A per-nail service such as French Tip x4 should store quantity 4 and calculate 4 × unit price.
7. Range-priced services should show `From` in the customer total.

## Price-source note

The seeded values come from the salon menu photos supplied for the project. Where the photos showed a dedicated detailed menu and a conflicting generic/starting-price menu, the detailed variant prices were preferred. Every seeded price is owner-editable and any service can be deactivated.

Waxing services were not seeded because the supplied consent-form photo listed waxing service names but did not show their prices. Do not invent those prices; add them from Owner > Services once the salon confirms them.
