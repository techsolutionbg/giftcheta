# Implementation Plan: Customer Invoices

## Overview
Generate a private, uniquely numbered PDF invoice for every order, email it only when the customer opts in, and make it viewable by an authorized administrator on every order.

## Architecture Decisions
- Use the approved capability map in `CAPABILITY-MAP-invoices.md` and per-module specs.
- Keep issuer settings, invoice snapshots, and invoice files private; store PDF paths only as generated server-side references.
- Reuse the existing PDO/MySQL, admin permissions, order email outbox, SMTP mailer, customer billing profiles, and order snapshots.
- Do not calculate or invent tax; use the order's persisted tax and monetary totals.
- Generate invoices for historical orders lazily when an administrator opens/requests one.
- Require explicit opt-in at checkout for customer email; attach the same stored PDF to the outbox message.

## Task List

### Phase 1: Issuer and document foundations
- [x] Task 1: Add issuer settings schema and admin read/write UI/API.
- [x] Task 2: Add invoice sequence/snapshot schema and Bulgarian-capable PDF generation with private storage.
- [ ] Checkpoint: Lint and verify private storage, unique numbering, and issuer data loading.

### Phase 2: Order checkout and delivery
- [x] Task 3: Add explicit invoice request and billing snapshot to checkout/order creation.
- [x] Task 4: Add durable opt-in invoice email with PDF attachment and retry behavior.
- [ ] Checkpoint: Verify opt-in/out, PDF attachment, and stable retry behavior.

### Phase 3: Admin access and historical orders
- [x] Task 5: Add permission-checked PDF preview/download API and order detail UI.
- [x] Task 6: Backfill historical invoices lazily and verify all order detail states.
- [ ] Checkpoint: Verify access control, older order preview, and admin build.

## Risks and Mitigations
| Risk | Impact | Mitigation |
|---|---|---|
| Missing seller identity fields | High | Keep editable admin settings; block customer delivery until required issuer data is complete. |
| Cyrillic PDF rendering | High | Use a renderer with bundled Unicode fonts and verify Bulgarian output. |
| Duplicate invoice numbering under concurrency | High | Allocate number under a database transaction/unique constraint. |
| Private invoice exposure | High | Serve only through admin authorization; no public path and safe server-generated references. |
| Email retry duplicates | Medium | Keep unique order/event key and reuse the stored PDF. |
| No tax policy configured | High | Preserve order `tax_total`; do not invent a rate or recalculate historical order amounts. |

## Open Questions
- None blocking. Seller legal details will be configured later in the administration.
