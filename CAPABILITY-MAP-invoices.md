# Capability Map: Customer Invoices

| Module id | Responsibility | Depends on |
|---|---|---|
| invoice-issuer-settings | Store the seller's legal identity and contact details in admin settings. | — |
| invoice-document | Snapshot billing/order data, assign a unique invoice number, generate and privately store a PDF for every order. | invoice-issuer-settings |
| invoice-delivery | Capture the customer's opt-in and send the generated PDF to the billing email through the existing durable mail outbox. | invoice-document |
| invoice-admin-access | Let authorized administrators preview/download every order's invoice, including older orders. | invoice-document |

Build order: `invoice-issuer-settings` → `invoice-document` → `invoice-delivery` and `invoice-admin-access`.

## Boundary contracts

- Invoice documents use immutable order and billing snapshots; later customer profile edits do not rewrite an issued invoice.
- PDFs live under private storage and are served only through an administrator permission-checked endpoint or attached to an opted-in customer email.
- The storefront opt-in is explicit and independent from ordinary order confirmation email.
- Existing order totals and tax values are authoritative; invoice generation does not invent or recalculate tax.
