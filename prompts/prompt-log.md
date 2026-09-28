# AI Prompt Log

This is a text transcript of the user-provided prompts that shaped the implementation. It is not a substitute for the requested screenshots. Capture screenshots of these actual messages in the Copilot chat/IDE panel and save the original images in this directory before submission. Do not include credential-maintenance messages or screenshots containing passwords/API keys.

## Project Setup

> could you please create a fresh laravel project

> Continue with #new workspace setup

## Product Brief

> let start the develop the application
>
> Brief: Subscription Billing & Usage-Metering System Design and build a small backend that meters customer usage against a subscription plan and produces billing, for a multi-tenant SaaS-style scenario.
>
> Scope
> - Merchants (tenants), each defining one or more Plans (name, base price, billing cycle, included usage units, overage rate per unit).
> - Customers subscribe to a merchant's plan.
> - Usage Events are recorded per customer per day (e.g. API calls) — assume high write volume.
> - At cycle end, the system generates an invoice: base price + overage beyond the included allowance, with proration if the subscription started mid-cycle.
>
> Functional Requirements
> 1. Design a normalized, indexed schema. Document explicitly how you'd expect it to hold up with 50L+ usage-event rows and note any denormalization/partitioning you'd consider.
> 2. POST /usage endpoint to record a usage event. It should be safe to call at high throughput and idempotent (a retried request must not double-count).
> 3. A queued, chunked job that aggregates a customer's daily usage and, at cycle end, generates the invoice with correct proration and overage math.
> 4. Cache plan/pricing lookups (Redis or array cache is fine for the exercise) and document your invalidation strategy.
> 5. GET /merchants/{id}/dashboard returning: top 5 customers by usage this month, projected overage revenue for the current cycle, and customers whose usage dropped >50% month-over-month (churn risk).
> 6. Basic rate-limiting on the usage endpoint.
> 7. Tests for the aggregation and billing calculation, including proration and overage edge cases.
> 8. Handle a customer upgrading or downgrading their plan mid-cycle: usage recorded before the change must be billed at the original plan's rate, and usage after the change at the new plan's rate, with proration reflecting both segments correctly.

## Portal Request

> I need a dashboard and login UI

## Review Criteria

> they Looking For bellow cases
> The schema and indexing choices, and whether the reasoning scale is sounds good.
> - Whether the queueing/caching choices are deliberate (chunking, idempotency, cache invalidation) rather than incidental.
> - Code architecture: separation of concerns, use of services/actions/DTOs as appropriate, not everything crammed into controllers.
> - A README that reads like something you'd actually hand a team — architecture summary, trade-offs made under time pressure, what you'd do differently with more time.

## Capacity Discussion

> is there anyother option to test the data in 5-million row capacity like clickhouse such things

## Demo Data Request

> I need some data then only then I can able to share to client

## Screenshot Evidence To Add

- Capture the project brief message as displayed in the original Copilot chat.
- Capture the review-criteria message as displayed in the original Copilot chat.
- Capture any follow-up prompts you want to disclose, ensuring no passwords, API keys, or other secrets appear.
- Keep screenshots unedited except for redacting secrets, and use neutral numbered names such as `01-project-brief.png` and `02-review-criteria.png`.
