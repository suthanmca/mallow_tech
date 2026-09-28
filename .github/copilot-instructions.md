# Metered Billing API

- This is a Laravel 12 API for merchant-scoped usage metering and monthly invoices.
- Keep merchant data scoped through `AuthenticateTenant` middleware and the API key.
- Preserve immutable subscription-segment pricing snapshots and invoice currency.
- Keep usage ingestion idempotent and roll up raw events with queued 1,000-row batches.
- Use `PlanPricing` cache invalidation when merchant plans change.
- Use Laravel conventions for routes, controllers, models, migrations, and feature tests.
- Run the app with `php artisan serve`, tests with `php artisan test`, and formatting checks with `vendor/bin/pint --test`.
- The local environment uses SQLite; keep secrets in `.env` and out of version control.