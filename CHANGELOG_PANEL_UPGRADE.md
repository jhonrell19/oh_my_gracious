# OH MY GRACIOUS v3.0 - Pre-Payment Operational Upgrade

## New in this build

- Replaced strict duration-based scheduling with fixed **capacity-based appointment times**.
- Added default AM/PM time slots requested by the group workflow: 9-11 AM and 1-7 PM.
- Added owner-configurable default capacity per appointment time.
- Added front-desk per-date slot controls: open/closed, capacity override, reason.
- Added automatic Fully Booked detection and server-side capacity enforcement.
- Added front-desk temporary service availability controls with customer-visible reason.
- Service duration is now informational instead of controlling availability.
- Added guest/walk-in front-desk booking without forcing an online customer account.
- Added front-desk rescheduling and No-show handling.
- Preserved multiple-service booking and server-calculated totals.
- Added service categories to the owner catalog.

## Authentication changes

- Google is now the **only external/social login provider**.
- Added official Google Identity Services buttons with Google icon/branding.
- Existing Google provider ID logs into its existing account.
- Verified Google email matches an existing customer instead of creating a duplicate.
- Staff/owner emails are not auto-linked through Google.
- Added DB uniqueness for one provider identity and one Google identity per user/provider.
- Removed Facebook authentication.
- Removed phone/SMS authentication.
- Phone number remains a required contact field for normal registration and booking.

## Payment scope

- Online payment integration was removed from the active workflow for now.
- Current workflow uses Pay at Salon / Cash.
- Front desk records payment after service.
- Online payment should be integrated only after operational testing is accepted.

## Database

Existing installations should run once:

`database/migrations/2026_10_pre_payment_capacity_google_only.sql`

Fresh installations should use:

`database/production_schema.sql`
