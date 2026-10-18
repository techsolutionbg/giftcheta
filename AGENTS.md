# Giftcheta project rules

- Keep the application a lightweight modular monolith in plain OOP PHP; do not add a full-stack commerce framework.
- Keep public storefront rendering server-side. React/TypeScript is reserved for a future admin build and must not be required in production.
- Preserve cPanel/shared-hosting compatibility: Apache and PHP-FPM/mod_rewrite, PDO MySQL, no required Node process, Redis, or workers.
- Put only `public/` in the document root. Never expose `.env`, Composer files, source, logs, backups, or private storage.
- Use PDO prepared statements, explicit validation, JSON API envelopes, safe error messages, and `DECIMAL` for money.
- Add versioned SQL migrations; MySQL DDL is not assumed transactional. Inspect partial failures before retrying.
- Do not introduce product/order/admin business logic until requested. Keep dependencies small and justified.
- Document commands that actually work and do not claim checks passed unless run.
