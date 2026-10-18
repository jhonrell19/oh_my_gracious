# Validation — October 17, 2026

Completed in the build environment:

- 65 application/test PHP files pass PHP 8.3 syntax checks.
- 21 payment validation checks pass: signed payload, tampering, stale timestamps, wrong secret, wrong test/live mode, both webhook envelopes, correct successful payment selection, currency/amount/reference rejection, multiple paid attempts, per-nail quantities, stored totals and payment total agreement.
- Fresh production schema imports successfully using MariaDB 10.11 bootstrap mode; Half 2 migration rerun twice without SQL errors.
- Google client and PHPMailer instantiate successfully from the bundled dependencies.
- Composer validates; dependency audit reported no known vulnerability advisories at build time. General package metadata warnings are not runtime errors.
- AR JavaScript and ONNX model are byte-identical to the uploaded working version.
- Both receptionist create-booking blockers are byte-identical to the uploaded version.

Not completed here:

- Real PayMongo API checkout, provider test/live payments, or provider webhook delivery.
- Database-backed PHP/HTTP integration tests and concurrent checkout/webhook races. Local database connectivity was unavailable in this execution environment; the schema import test is not a substitute for those tests.
- Real Google token login, SMTP delivery, full UI/role regression, actual host access-control rules, TLS/HTTPS, or live AR camera testing.
- Public deployment; no hosting destination has been configured.

Run `php tests/payment-validation.php` locally to rerun pure payment validation tests. Follow START_HERE_HALF2.md for acceptance testing. This build is a candidate for testing and deployment, not a certification that the whole application is production-ready.
