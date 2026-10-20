# Spec: Order Invoice Documents

## Objective
Create one uniquely numbered, durable PDF invoice document for every order and make its contents stable even if customer or product data later changes.

## Commands
- Migrations: `docker compose exec -T app php bin/migrate.php`
- PHP syntax: `docker compose exec -T app php -l <file>`
- Admin build: `docker compose run --rm admin-build`

## Project Structure
- Add a versioned migration for invoice snapshots and sequence/numbering.
- Add an OOP invoice document service and PDF renderer using a small PHP-compatible dependency.
- Store PDFs below `storage/private`; never place them under `public/`.
- Generate documents when new orders are placed; allow an admin to backfill a document for historical orders.

## Code Style
Use PDO prepared statements, integer cents/DECIMAL values from the order, escaped UTF-8 text, deterministic invoice snapshots, and safe file names. Reuse existing order and billing-profile snapshots.

## Testing Strategy
Verify numbering uniqueness, PDF signature/content, private-path safety, and historical data snapshot behavior with focused PHPUnit tests; run PHP lint. No tests will be executed unless requested.

## Boundaries
- Always retain the PDF and invoice snapshot privately, and do not derive customer-supplied paths.
- Ask first before changing order pricing, tax calculations, or invoice data after issuance.
- Never make invoice files publicly addressable or silently mutate an issued document.

## Success Criteria
- Each order has one invoice number and stored PDF.
- A document includes seller, bill-to, order reference/date, items, quantities, prices, discounts, shipping, and the order's recorded tax/total values.
- Every generated document renders Bulgarian text correctly.

## Open Questions
- None; use existing persisted order totals and tax values without inventing a tax rate.
