# Meterwise Subscription Billing

A Laravel 12 service for merchant-scoped plans, customer subscriptions, usage metering, daily rollups, and prorated invoices. It includes a small merchant portal for sign-in and billing metrics. Payment collection is intentionally out of scope.

## Local Setup

Requirements: PHP 8.2+, Composer, and Node.js/npm.

```powershell
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
```

The local environment uses SQLite, the database queue, and the database cache. Run each process in a separate terminal:

```powershell
php artisan serve
php artisan queue:work --queue=billing,usage,default --tries=3
php artisan schedule:work
```

To populate the existing `admin@test.com` merchant with a client-demo dataset, run:

```powershell
php artisan db:seed --class=Database\Seeders\DemoDataSeeder --force
```

This repeatable seeder creates three plans, seven fictional customers, current/prior-month daily usage, completed prior-cycle invoices, and daily rollups. All sample customer email addresses use the reserved `.example` domain.

Open `/login` or create a merchant account at `/register`. Merchant registration shows its generated API key once. The seeder intentionally does not create a globally-owned demo plan; merchants create plans through their own authenticated API.

## API Surface

All API paths are prefixed with `/api`.

| Endpoint | Purpose |
| --- | --- |
| `POST /merchants` | Register a merchant and return an API key once |
| `GET /plans` | List the authenticated merchant's plans |
| `POST /plans`, `PUT /plans/{id}` | Create/update a merchant-owned plan |
| `POST /customers` | Create a merchant-owned customer |
| `POST /customers/{id}/subscriptions` | Subscribe a customer to one of the merchant's plans |
| `PATCH /customers/{id}/subscriptions/plan` | Change plan at the current time, preserving prior pricing |
| `POST /usage` | Record an idempotent customer usage event |
| `GET /merchants/{id}/dashboard` | Return top usage, projected overage by currency, and usage-drop signals |

Send `X-API-Key` on all API requests except merchant registration. `/usage` is limited to 6,000 requests per minute per merchant. A retry must reuse its idempotency key and the same event. Reusing a key for a different event returns `409`; a new event for a closed cycle is rejected.

Prices and invoice values are integer minor units. Supported billing cycles are calendar monthly, quarterly, and yearly. The first cycle's base price and included allowance are prorated by active seconds. Changing a plan closes the old rate segment and opens a new one; each segment gets a separate invoice line. Plan changes cannot change billing cycle or currency mid-cycle.

## Architecture

HTTP controllers validate request shape, enforce merchant-scoped resource lookup, and translate domain results into HTTP responses. Transactional workflows live in actions:

- `RecordUsageEvent` resolves the active rate segment, checks the subscription window, enforces tenant-scoped idempotency, and returns a typed result.
- `CustomerSubscriptionManager` creates subscriptions and changes plans while keeping immutable segment snapshots.
- `BillingCalculator` is a pure proration/overage calculation; `InvoiceGenerator` coordinates cycle closure and invoice persistence.
- `UsageAggregator` performs restartable daily rollups; queued jobs and the scheduler control chunking and cycle-end processing.
- `PlanPricing` owns merchant-specific cache keys and invalidation; `MerchantDashboard` owns the dashboard queries and projection rules.

This keeps persistence workflows out of the controller without introducing DTOs for simple CRUD payloads. The `UsageEventResult` DTO is used where the caller needs both the event and whether it was newly created.

## Data Model And Indexes

The schema is normalized around merchants (`tenants`), their `plans`, their `customers`, one current `customer_subscriptions` row per customer, immutable `subscription_segments`, raw `usage_events`, derived `daily_usage`, `invoices`, and `invoice_lines`. Foreign keys enforce ownership and lifecycle relationships. Repeated merchant/customer/segment IDs in `daily_usage` are intentional query denormalization; price, allowance, and currency are snapshotted on each segment so later plan edits cannot rewrite history.

| Index / constraint | Query or invariant it supports |
| --- | --- |
| `plans (tenant_id, name)` unique | Merchant-scoped plan listing/name uniqueness |
| `customers (tenant_id, external_id)` unique; `(tenant_id, name)` | External identity idempotency and merchant customer lookup |
| `customer_subscriptions (tenant_id, active, next_billing_at)` | Scheduler's due-cycle scan |
| `subscription_segments (customer_subscription_id, starts_at, ends_at)` | Effective plan-segment lookup for an event timestamp |
| `usage_events (tenant_id, idempotency_key)` unique | Prevent duplicate metering within a merchant |
| `usage_events (tenant_id, customer_id, occurred_at)` | Customer time-window and cycle investigations |
| `usage_events (subscription_segment_id, occurred_at)` | Segment-level billing/audit reads |
| `usage_events (aggregated_at, tenant_id, id)` | Find pending events in merchant-scoped chunks |
| `daily_usage (tenant_id, customer_id, subscription_segment_id, usage_date)` unique | Idempotent daily rollup key |
| `daily_usage (tenant_id, usage_date, customer_id)` | Month-to-date ranking and churn queries |
| `invoices (customer_id, period_start)` unique; `invoice_lines (invoice_id, subscription_segment_id)` unique | One invoice per customer-cycle and one line per pricing segment |

At 50 lakh (5 million) events, ingestion writes one indexed raw row; dashboard and invoice reads use daily aggregates rather than scanning the full ledger. This is a reasoned schema target, not a measured capacity claim. SQLite is for development only. Before production, run the same workload on PostgreSQL, inspect `EXPLAIN (ANALYZE, BUFFERS)`, and measure ingest throughput, p95/p99 latency, index size, lock waits, and dashboard/invoice query time at realistic tenant skew.

Partitioning is deferred until those measurements justify its operational cost. Monthly PostgreSQL range partitions on `usage_events.occurred_at` are a candidate for retention and index maintenance. PostgreSQL cannot enforce a global `(tenant_id, idempotency_key)` unique constraint across time partitions unless the partition key participates in the constraint. Before partitioning, move idempotency keys to a compact unpartitioned registry or explicitly scope their uniqueness to a time bucket. Keep `daily_usage` unpartitioned until its own measured size warrants partitioning.

## Queue And Cache Semantics

Usage ingestion does not calculate invoices or update dashboard totals synchronously. It validates and inserts the raw event; the database unique constraint is the final idempotency guard. Per-merchant `AggregateUsageEvents` jobs are unique and process at most 1,000 ordered events per transaction. Each batch groups events by merchant, customer, rate segment, and event day, upserts daily units, and marks the source rows aggregated in the same transaction. Retries therefore do not double-count. Separate merchants can be processed by separate workers; writes for the same customer serialize briefly against plan changes and cycle finalization to prevent a rate-boundary race.

The scheduler queues pending merchant rollups every minute and dispatches invoices for due cycles. Invoice generation drains any remaining usage before reading daily totals, creates a unique customer-cycle invoice with one line per rate segment, and advances the subscription window in a transaction. Queue jobs are at-least-once; database uniqueness and transactional state changes provide idempotent outcomes. Dashboard values are eventually consistent and can lag by one aggregation interval.

`PlanPricing` caches each merchant's plan list for five minutes. Plan create/update explicitly forgets that merchant's cache key. The TTL bounds staleness if data is changed outside the API; existing subscriptions are unaffected because their pricing segments are immutable snapshots. The local database cache exercises the same Laravel API; Redis can be configured for shared production cache and rate-limit storage.

## Trade-offs And Next Steps

The exercise favors a compact relational source of truth and a derived daily aggregate over introducing ClickHouse before measuring need. PostgreSQL is the first production benchmark target because current idempotency, invoice uniqueness, and transaction boundaries depend on relational constraints. ClickHouse could later serve analytics/dashboard workloads through an outbox or queue-fed projection, but it should not own invoice state or strict idempotency.

The subscription row lock makes plan changes and cycle closure correct, but serializes simultaneous event inserts for a single high-volume customer. With more time, I would benchmark that contention on PostgreSQL and consider an append-first ingestion design with a narrowly scoped cycle-close barrier, plus an outbox for analytics delivery. I would also add multi-worker race tests, query-plan regression checks, a realistic skewed 5-million-event load test, and explicit retention/late-arrival policy before claiming production capacity.

## Assumptions

- Each customer has at most one subscription row; plans are owned by exactly one merchant.
- Billing windows follow calendar month, quarter, or year boundaries. The initial and changed plan segments are prorated by active seconds in the current window, and included units are prorated by the same fraction.
- A plan switch takes effect at the request's current timestamp. The cycle and currency cannot change mid-cycle; a new currency or cycle requires a new subscription at a cycle boundary.
- Usage timestamps must land inside the current billing window and an active pricing segment. New events for a closed cycle are rejected; there is no late-arrival grace period or invoice adjustment workflow.
- Idempotency keys are unique per merchant for the lifetime of the ledger. Clients must reuse the same key and payload when retrying.
- Churn risk compares equal-length month-to-date windows, and the dashboard uses rolled-up data, so it is eventually consistent.
- The 6,000-request/minute limit is a simple per-merchant guardrail, not a throughput guarantee. Production limits should be tuned from measured traffic and shared rate-limit storage.
- Demo customers and their `.example` addresses are fictitious. The demo seeder targets the local merchant whose email is `admin@test.com`.

## Submission Checklist

- **Private repository:** the source tree is ready, but Git is not installed in this workspace and there is no `.git` directory. Initialize and push it to a private repository from an authenticated Git environment, then grant the reviewers access.
- **Prompt evidence:** the prompt transcript is in [prompts/prompt-log.md](prompts/prompt-log.md). The requested screenshots must be captured from the actual Copilot chat/IDE panel and added under `prompts/`; no prompt screenshots are fabricated or included by this workspace.
- **Narrated recording:** record a 5–10 minute walkthrough in your screen-recording tool. Suggested timing: 1 minute sign-in and dashboard; 2 minutes API usage and invoice examples; 2 minutes schema/indexes and queue/cache boundaries; 1–2 minutes tests, scale assumptions, and trade-offs. Narrate the decisions and clearly state that the 5-million-row capacity has not been benchmarked.

## Verification

```powershell
php artisan test
vendor/bin/pint --test
npm run build
```
