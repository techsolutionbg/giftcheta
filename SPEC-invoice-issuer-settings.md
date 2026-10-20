# Spec: Invoice Issuer Settings

## Objective
Allow administrators to maintain the seller identity used on generated invoices: legal name, company identifier, VAT number, address, phone, and email.

## Commands
- Migrations: `docker compose exec -T app php bin/migrate.php`
- PHP syntax: `docker compose exec -T app php -l <file>`
- Admin build: `docker compose run --rm admin-build`

## Project Structure
- Store the singleton settings in a versioned MySQL migration.
- Expose validation and read/write operations through the existing admin Preferences API and Settings UI.
- Keep secrets out of the setting set; invoice issuer data is ordinary business identity data.

## Code Style
Use the existing PHP/PDO prepared-statement API and React admin settings patterns. Validate field lengths and identifiers server-side; return the project's JSON envelope.

## Testing Strategy
Verify settings persistence and permission enforcement with existing PHPUnit conventions; run PHP lint and the admin production build. No test suite will be run unless requested.

## Boundaries
- Always validate and escape issuer values and require the minimum seller identity fields before customer email delivery.
- Ask first before adding payment/tax calculation rules or changing historical order totals.
- Never expose settings write operations without the existing admin authorization and CSRF checks.

## Success Criteria
- An authorized administrator can save and reload all issuer fields.
- Invoice generation reads a consistent settings snapshot.
- Missing required issuer details do not produce an invoice email with incomplete seller information.

## Open Questions
- None; exact seller values will be entered later by the user in admin settings.
