# Specification: Banner administration

## 1. Objective

Let an administrator create, edit, activate, deactivate, reorder, and delete homepage banners used by the public homepage carousel.

## 2. Stack

- Plain OOP PHP, PDO/MySQL, versioned SQL migrations.
- Existing React/TypeScript admin source compiled to static files; no runtime Node requirement.
- Store uploaded public banner images below `public/uploads/banners/` after validating the actual file type and size.

## 3. Commands

Project commands documented in `README.md` include `docker compose exec app php bin/migrate.php` for migrations and `docker compose --profile build run --rm admin-build` for the static admin build. Run PHP syntax checks with `docker compose exec -T app php -l <changed-php-file>`.

## 4. Project structure

- Admin screens and API calls live in `admin/src/` following existing catalog/settings patterns.
- API route/controller/repository code belongs in the appropriate `app/Modules/` module and route registry.
- Schema changes use a new ordered migration under `database/migrations/`.
- Uploaded image content is served from `public/uploads/banners/`; application source and private storage remain outside the document root.

## 5. Data and behavior

Each banner has a required title, description, CTA label, CTA URL, and image; optional alternative text; integer sort order; active flag; and created/updated timestamps. Provide an admin list and create/edit form. Allow image replacement and deletion. Validate text lengths, required fields, CTA URL (same-site relative path or HTTPS URL), and image MIME/size (JPEG, PNG, or WebP; maximum 10 MB, consistent with catalog uploads). Use authenticated API endpoints, explicit banner read/write permissions, JSON envelopes, prepared statements, and safe errors. Permission migration must grant the new access to the existing administrator role.

The image is mandatory on save. Alternative text is editable for accessible carousel images. Deleting a banner or replacing its image must clean up only the corresponding managed upload, with path checks that prevent deleting arbitrary files.

## 6. Style and interfaces

Follow the existing admin forms, shared input/button components, permission guards, API helper, and upload behavior. Keep controller validation and persistence in module classes rather than adding business logic to route closures. API responses use the established JSON success/error envelope.

## 7. Verification strategy

- PHP syntax checks for changed PHP files.
- Admin production build using the documented command.
- Manual review of create/edit, active state, ordering, upload validation, permissions, and deletion behavior.
- Do not add or run automated tests unless requested.

## 8. Boundaries and success criteria

An authorized administrator can manage the complete banner fields and ordering; invalid files/URLs are rejected; only authorized requests mutate records; uploads stay within the intended public banner directory; public rendering can query active banners in deterministic order. No client portal or Node runtime is introduced.

## 9. Open questions

None. Carousel interval is six seconds as confirmed by the user.
