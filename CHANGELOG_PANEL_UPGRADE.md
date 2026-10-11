# Panel Revision Upgrade Summary

## Major upgrades in this build
- Complete customer registration/profile information
- One login for customer, receptionist, and owner roles
- Role-separated dashboards and server-side authorization
- Database-managed services/prices/durations
- Multiple services in one appointment
- Duration-aware multi-slot scheduling and unique slot claims
- Cash at Salon + PayMongo Hosted Checkout integration
- Verified payment-webhook processing and financial reconciliation alerts
- Receptionist statistics, customer directory, front-desk booking, check-in and completion workflow
- Owner revenue reports, service management, technician management, staff admin management and system settings
- My Account, password change, forgot/reset password, email verification/resend
- Google, Facebook, and Twilio Verify integration endpoints
- Phone OTP can log in an existing account or start a new verified customer registration
- Saved AR designs and booking attachment
- Improved AR calibration/capture workflow
- Printable paid receipts
- CSRF protection, login rate limiting, audit logs, secure sessions, environment configuration
- Responsive UI redesign

## Removed / cleaned
- separate public Admin Login page
- public admin registration
- hard-coded admin credentials
- hard-coded service prices as the server source of truth
- obsolete manual GCash/Maya account-number configuration
- owner access to receptionist daily booking operations
- browser-controlled online payment confirmation
