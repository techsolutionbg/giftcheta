# Implementation Plan: Administrative Orders Module

## Overview

Build the Giftcheta order administration module at `/admin/orders` using the existing PHP/PDO API, React admin components, MySQL migrations, product inventory ledger, and Mailpit SMTP integration. No storefront, cart, checkout, external payment, or carrier integration is in scope.

## Architecture Decisions

- Preserve `product_stock_movements`; order deductions and cancellation restorations append ledger movements in the same transaction as order state changes.
- Store contact, address, product, variant, customization, and price snapshots on order records so catalog/customer edits do not rewrite order history.
- Calculate money on the server using decimal string/integer-cent arithmetic; do not trust client totals.
- Use a transactional email outbox with unique order/event keys and synchronous delivery attempts, so SMTP failure cannot roll back an order and pending messages can be retried without a worker.
- Enforce order-specific permissions, CSRF, validated status transitions, locking, and idempotency on the backend.

## Task List

### Phase 1: Data and API foundations
- [x] Task 1: Add order schema, indexes, snapshots, outbox, and granular permissions.
- [x] Task 2: Add order listing/detail APIs, search/filters/pagination, and history reads.
- [x] Task 3: Add order creation with guest/customer snapshots, server price calculation, inventory locking/decrement, ledger movements, and idempotency.

### Checkpoint: Core order creation
- [x] Schema and API lint clean.
- [x] Creation, stock rollback, and idempotency verified against isolated test database.

### Phase 2: Lifecycle, payments, and mail
- [x] Task 4: Add validated status/payment/cancel actions and exact-once stock restoration.
- [x] Task 5: Add transactional email outbox templates, dispatch, and retry behavior via normal admin requests.
- [x] Task 6: Add integration tests for access, lifecycle, inventory, pricing, and mail failure.

### Phase 3: Admin experience
- [x] Task 7: Add paginated `/admin/orders` table with filters, search, empty/loading states, and permitted bulk operations.
- [x] Task 8: Add `/admin/orders/:id` detail view and controlled status/payment/note actions.
- [x] Task 9: Add create-order flow with customer or guest, address snapshot, products, variants, and customizations.
- [x] Task 10: Update navigation/docs and run the project checks/build.

### Checkpoint: Complete
- [x] Existing modules remain unaffected (19 isolated-database tests pass).
- [x] API and UI flows are connected to MySQL.
- [x] Tests, PHP lint, and admin production build succeed.

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Concurrent orders oversell tracked stock | High | Lock product/variant rows in stable ID order, revalidate quantity in transaction, and fail atomically. |
| Catalog edits/delete invalidate order history | High | Snapshot labels/prices/options; nullable product references with `ON DELETE SET NULL`; never cascade order history. |
| Floating-point rounding | High | Validate decimal strings and calculate integer cents/decimal strings on backend. |
| SMTP outage during order save | Medium | Commit order and outbox first; send after commit; retain failure and retry later. |
| Email delivery ambiguity after SMTP accepts but connection drops | Medium | Unique outbox event keys and bounded retry metadata; document SMTP's at-least-once delivery edge. |
| No configured tax or shipping policy exists | Medium | Inspect settings; use explicit zero tax and an admin-entered shipping snapshot until a real setting exists; never invent a tax rate. |

## Open Questions

- None blocking; implementation will use 0% tax absent configuration, and require the admin to specify a shipping charge (defaulting to `0.00`) as explicitly captured order data.
