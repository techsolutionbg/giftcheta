# Spec: Opt-In Invoice Email

## Objective
Let a customer explicitly request an invoice by email at checkout; attach that order's PDF to the message. When not requested, do not send the invoice to the customer.

## Commands
- Migrations: `docker compose exec -T app php bin/migrate.php`
- PHP syntax: `docker compose exec -T app php -l <file>`
- Admin build: `docker compose run --rm admin-build`

## Project Structure
- Add an explicit checkout opt-in and snapshot billing data onto the order/invoice.
- Extend the existing durable order email outbox to support a private PDF attachment and retries.
- Preserve ordinary order-confirmation email behavior separately.

## Code Style
Follow the existing outbox retry/locking implementation and Symfony Mailer usage. Validate billing input and use prepared statements; do not trust a posted customer ID.

## Testing Strategy
Verify opted-in/out behavior, snapshot validation, and attachment presence using existing isolated-database mail tests; lint PHP. No tests will be run unless requested.

## Boundaries
- Always require explicit opt-in; absence or false means administrator-only access.
- Ask first before sending invoices for old orders retroactively.
- Never send a customer's invoice to an address other than the order's validated customer email.

## Success Criteria
- Opted-in orders queue one invoice email with the generated PDF attached.
- Non-opted-in orders generate and retain the PDF but queue no invoice email.
- Failed mail attempts remain retryable without regenerating a different invoice.

## Open Questions
- None; opt-in will be a separate, unchecked checkout choice.
