# OH MY GRACIOUS — Pre-Payment Acceptance Test Checklist

Use this checklist before enabling any online payment gateway.

## 1. Installation and database
- [ ] Back up the current database before migration.
- [ ] Existing installation: run `database/migrations/2026_10_pre_payment_capacity_google_only.sql` once.
- [ ] Fresh installation: import `database/production_schema.sql` instead.
- [ ] Create `.env` from `.env.example` and keep `.env` out of Git.
- [ ] `tools/health-check.php` shows all required tables/schema checks as green.

## 2. Customer account
- [ ] Email/password registration rejects duplicate email/phone.
- [ ] Email verification activates the account.
- [ ] Login/logout works.
- [ ] Forgot/reset password works.
- [ ] Profile changes persist.
- [ ] A customer cannot access receptionist or owner pages.

## 3. Google customer login/register
- [ ] The Google button renders using Google Identity Services and shows the Google icon.
- [ ] A new Google account creates exactly one customer (`role=user`).
- [ ] Reusing the same Google account logs into the same customer instead of creating a duplicate.
- [ ] A verified Google email matching an existing customer links to that customer instead of creating another user row.
- [ ] A Google email belonging to an admin/super-admin is rejected; staff use password login.
- [ ] A Google-created customer without a contact number is required to complete their profile before booking.
- [ ] Facebook login/register is absent.
- [ ] Phone/SMS login/register is absent; phone remains a contact field.

## 4. Booking and capacity
- [ ] Customer can select one or multiple available services.
- [ ] Unavailable services are visible but disabled and show the reason.
- [ ] Fixed booking times are shown: 9, 10, 11 AM and 1–7 PM.
- [ ] Closed/past/full slots cannot be selected.
- [ ] Remaining capacity is displayed.
- [ ] The server calculates service totals from the database.
- [ ] Two requests cannot exceed the capacity of the same date/time.
- [ ] Cancelling/rejecting/no-show releases capacity.
- [ ] Rescheduling releases the old slot and reserves the new slot transactionally.

## 5. Receptionist / Front Desk
- [ ] Receptionist can access daily appointments but not owner pages.
- [ ] Receptionist can create a booking for an existing customer.
- [ ] Receptionist can create a guest/walk-in booking with name and phone.
- [ ] Receptionist can approve/reject/reschedule/check in/complete/cancel/no-show.
- [ ] Receptionist can mark an eligible Pay-at-Salon booking Paid.
- [ ] Receptionist can disable/re-enable a service with a reason.
- [ ] Receptionist can close/reopen a date/time and safely change its capacity.
- [ ] Capacity cannot be reduced below current active reservations.

## 6. Owner / Super Admin
- [ ] Owner cannot enter receptionist-only daily booking pages.
- [ ] Owner can manage services/categories/prices/estimated durations.
- [ ] Owner can manage technicians.
- [ ] Owner can create/suspend/reactivate receptionist accounts.
- [ ] Owner can configure default slot capacity and activate/deactivate fixed booking times.
- [ ] Owner can view business/revenue summaries and audit logs.

## 7. AR and saved designs
- [ ] Camera permission and AR nail try-on work in the target browser.
- [ ] Customer can save/delete a design.
- [ ] Customer can attach an owned saved design to a booking.

## 8. Security and release checks
- [ ] `.env` is not tracked by Git.
- [ ] No hard-coded staff passwords/API secrets exist in committed files.
- [ ] CSRF protection is active on modifying requests.
- [ ] Role checks are enforced server-side.
- [ ] Login rate limiting works.
- [ ] `APP_ENV=production` hides local helper pages before public deployment.
- [ ] HTTPS, dedicated DB user, real email delivery and backups are configured before deployment.

Only after this checklist passes should online payment integration begin.
