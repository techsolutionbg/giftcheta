# Spec: Administrator Invoice Access

## Objective
Allow an authorized administrator to preview or download the invoice PDF from every order detail, regardless of whether the customer requested email delivery.

## Commands
- Migrations: `docker compose exec -T app php bin/migrate.php`
- PHP syntax: `docker compose exec -T app php -l <file>`
- Admin build: `docker compose run --rm admin-build`

## Project Structure
- Add an admin API route guarded by existing order-view permission and admin session/CSRF conventions.
- Add preview/download actions to the order detail UI and invoice metadata to the order detail API.
- Lazily create missing documents for historical orders before serving them.

## Code Style
Follow existing OrdersApi guards and React order detail components. Return `application/pdf` with safe inline/attachment disposition and private cache headers.

## Testing Strategy
Verify unauthorized access is rejected and authorized access streams only the invoice for the requested order; lint PHP and build admin. No tests will be run unless requested.

## Boundaries
- Always verify order-view permission and invoice/order association before file access.
- Ask first before granting invoice download to customers.
- Never accept a file path or invoice ID from the client as the authorization boundary.

## Success Criteria
- Every order detail has a working administrator preview/download action.
- Historical orders receive a document on first admin request.
- Direct unauthenticated or cross-order file access is rejected.

## Open Questions
- None.
