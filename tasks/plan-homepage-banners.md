# Implementation plan: Homepage banners

## Goal

Deliver an administrator-managed banner carousel on the public homepage, following the approved capability map and module specifications.

## Design decisions

- Keep the banner administration and public carousel as separate modules sharing a single schema.
- Use a new `banners` table and a new `banners.read` / `banners.write` permission migration. Grant them to the existing `administrator` role without assuming role ID 1.
- Use a dedicated `BannerRepository` and `BannerApi` under the admin module; register APIs in `public/index.php` and follow existing JSON/auth/CSRF conventions.
- Store validated banner image uploads in `public/uploads/banners/` with generated filenames. Validate MIME and dimensions using the same mechanisms as existing catalog uploads. Constrain file deletion to that directory.
- Add an admin banners page with list, create/edit dialog, active state, order, and delete; expose through the existing admin router/navigation and permissions.
- Query active banners in sort-order/ID order through the public storefront repository and pass them to the homepage view.
- Render a server-side carousel with six-second rotation, manual arrows and dots, pause on hover/focus, no autoplay for reduced-motion users, and a static-hero fallback when there are no active banners. A single slide has no controls.
- Preserve all pre-existing working-tree modifications; touch only files required by this feature.

## Ordered tasks

1. Inspect existing catalog upload/auth/repository patterns; add the banner schema, permission migration, repository, and safe image file handling.
2. Add authenticated banner JSON endpoints for list, create, update, ordering/activation, image upload/replacement, and deletion; register routes and enforce permissions.
3. Build the admin banner-management page using shared admin components, add its navigation and route, and connect API mutations with existing toast behavior.
4. Add active-banner lookup to the storefront data layer and controller; render banners in the homepage view with the current static hero as the empty-state fallback.
5. Add responsive carousel styling and progressive-enhancement JavaScript for manual controls, six-second rotation, hover/focus pause, and reduced-motion support.
6. Update README/architecture documentation with banner administration and upload behavior.
7. Verify changed PHP syntax and the admin production build; inspect the final diff and, when the local app is available, manually review admin CRUD and storefront carousel behavior.

## Risks and mitigations

- MySQL DDL can partially apply: inspect the schema after a failed migration before retrying, per project policy.
- Upload names/paths are untrusted: generate filenames, validate actual MIME, and delete only files resolved under the managed banner directory.
- Links may be unsafe: accept only same-origin relative paths or HTTPS URLs; escape values in server-rendered HTML.
- Existing admin permissions may differ by role: add explicit permissions and grant them by role slug for the project's existing administrator role.
- The repo has unrelated uncommitted changes: preserve them and review the final diff by path.

## Explicit non-goals

Customer portal, login, checkout, payment, unrelated storefront edits, deployment, and adding/running automated tests.
