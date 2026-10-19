# Capability map: Homepage banners

## Approved modules

1. **Banner administration** — authenticated CRUD, image upload, sort order, active state, validation, persistence, and permissions.
2. **Public homepage carousel** — server-rendered active banners, accessible navigation, and six-second automatic rotation.

## Dependency

The public carousel consumes only banners saved and activated through the administration module. Both capabilities share one banner schema and media storage convention.

## Explicit boundaries

- No customer portal, checkout, payment, or unrelated storefront work.
- Keep the public page server-rendered and the application a plain PHP OOP modular monolith.
- No required Node process or background worker in production.
