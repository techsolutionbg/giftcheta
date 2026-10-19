# Giftcheta

Independent, lightweight e-commerce application using plain PHP, PDO, MySQL, Apache, and Docker. The PHP-rendered public storefront reads the same product and category catalog used by the React administration panel. Admin catalog, customer contacts and addresses, password recovery for existing customer accounts, and order management are backed by a versioned PHP JSON API.

## Requirements

- Docker Engine/Desktop with Compose (recommended local setup).
- For direct use: PHP 8.3+ with PDO MySQL and Composer 2.
- MySQL 8 or compatible MariaDB.

## Start locally

```sh
cp .env.example .env
# Change DB_PASSWORD and DB_ROOT_PASSWORD in .env for your local setup.
docker compose up --build -d
docker compose exec app php bin/migrate.php
docker compose exec app php bin/create-admin.php
```

Open the administration panel at `http://localhost:8080/admin/`; sign in with the administrator created by the command above. The backend health check is `http://localhost:8080/api/v1/health`, and development status is at `/api/v1/status`. phpMyAdmin is available at `http://localhost:8081` and connects to the Compose database service. Log in using `DB_USERNAME` and `DB_PASSWORD` from `.env` (or use `root` and `DB_ROOT_PASSWORD`). Mailpit captures local test email at `http://localhost:8025`; the app's SMTP settings point to `mailpit:1025` inside Compose. Messages stay in Mailpit and are not delivered to real recipients. Its UI and SMTP ports are bound to localhost only. Stop with `docker compose down`; `docker compose down -v` also removes the local database volume.

### Customer password recovery

In a customer's edit dialog, use **Изпрати линк за възстановяване** for an active customer account that already has a password. The customer receives a one-time link that expires after 60 minutes. The reset page accepts a new password of 12–72 characters, stores only a password hash, and invalidates all outstanding reset links. The reset request is limited to three links per customer in 15 minutes. The public reset page does not provide customer registration, login, or a customer portal. In local development, inspect the captured message in Mailpit. Set `APP_URL` to the trusted public origin in deployment; password reset URLs require HTTPS in production.

### Orders administration

The admin order module supports manual order entry for guests or existing customers, saved or manually entered shipping addresses, product variants and personalization, inventory deduction, order and payment status history, cancellation with one-time stock restoration, internal notes, and customer email notifications through the retryable outbox. Prices are recalculated by the server and saved as order snapshots. Shipping cost is entered per order because there is no configured shipping-rate service yet; tax and discounts remain zero until their policies are defined.

### Public storefront

The server-rendered store uses the existing admin catalog at `/`, `/category/{slug}`, `/product/{slug}`, and `/search`. Active products and categories are exposed with their images, variant pricing, stock state, and personalization descriptions. Category and search pages support price filters, stock filters, sorting, and pagination. Category/product descriptions pass through an HTML allowlist. SEO metadata and schema markup are rendered per page; `/sitemap.xml` and `/robots.txt` are generated dynamically, and catalog slug changes keep a 301 redirect. The public `/cart` supports guest sessions, product variants, text/choice/checkbox/image personalization, quantity changes, item removal, and per-item customization updates. Prices and stock are rechecked by the server; adding to cart does not reserve stock. Checkout, payment, customer login, and cart-to-customer merging are not implemented.

Cart customization images are validated and stored under `storage/private/cart-customizations`, outside the public document root. The cart file endpoint checks the current guest session before serving them. Run `php bin/cleanup-cart-files.php` periodically from a protected CLI scheduler to remove expired carts and old unclaimed uploads; no background worker is required.

### Homepage banners

Administrators with banner permissions can create, edit, publish, hide, reorder, and delete homepage banners at `/admin/banners`. Each banner has a title, description, button label and destination, image, optional alternative text, order, and active state. Images must be JPEG, PNG, or WebP up to 10 MB and are stored with generated names under `public/uploads/banners/`. Button destinations accept same-site paths or HTTPS URLs. Active banners appear in the server-rendered homepage carousel, rotate every six seconds, and include manual controls. Autoplay pauses during pointer/keyboard interaction and is disabled when reduced motion is requested. If there are no active banners, the original static hero remains visible.

For automatic browser reloads while editing the admin, start the optional Vite development server with `docker compose --profile dev up -d --build admin-dev` and open `http://localhost:5173/admin/`. It watches additions, edits, and removals under `admin/src/`, `app/`, `config/`, `bin/`, and `database/`, as well as `public/index.php`, and proxies API requests to the PHP app. The regular `http://localhost:8080/admin/` address serves the static build and does not watch source files; rebuild it after changes with `docker compose --profile build run --rm admin-build`. Node is not needed in production.

### First administrator

Run `docker compose exec app php bin/create-admin.php`, then provide an email, display name, and a unique password of at least 12 characters when prompted. The password is hashed before storage. Run this command only after applying migrations. Do not commit administrator credentials.

## Configuration and database

Copy `.env.example` to `.env`. The Compose app connects to service `db` using `DB_HOST=db`. Local email configuration uses `MAIL_HOST=mailpit` and `MAIL_PORT=1025`; the corresponding PHP config is available as `mail.*`. For direct hosting, set `DB_HOST` to the database host provided by the host and configure mail settings for that environment. The PDO factory uses native prepared statements, exceptions, and `utf8mb4`.

Place ordered `*.sql` migration files in `database/migrations`; run `php bin/migrate.php`. Applied names and batches are recorded in `migrations`. MySQL DDL is not transactional: inspect the schema after a failed migration before retrying.

## Tests and checks

```sh
docker compose exec app ./vendor/bin/phpunit
docker compose exec app composer lint
```

Integration tests use the dedicated database `giftcheta_test`; create it and grant the app database user access before running PHPUnit. They never target the development database. Keep test fixtures in that database only.

`docker compose --profile build run --rm admin-build npm audit` currently reports advisories in the existing Tailwind CSS 3 and React Router 6 dependency trees. npm only offers fixes via Tailwind CSS 4 and React Router 7 major upgrades; the current build remains on the compatible majors pending a reviewed migration.

## cPanel preparation

Use PHP 8.3+, PDO MySQL, Apache `mod_rewrite`, and set the domain document root to `public/` where supported. Upload production Composer dependencies and compiled admin assets only when that future app exists. Keep `.env`, config, migrations, logs, and private storage outside the public document root. Deployment details and a safe fallback layout must be adapted to the actual hosting account before publication. No production deployment is performed by this stage.

## Structure

- `app/Core`: config, router, database, app, migration runner, logger.
- `app/Http`: request/response, middleware, controllers.
- `config`: environment-backed app and database config.
- `database/migrations`: versioned SQL.
- `public`: sole web root and rewrite entry point.
- `docker`: Apache/PHP image configuration.
- `admin`: React/TypeScript source, built into static assets under `public/admin/`.

## Next stages

Define payment, tax, discount, and shipping-rate policies before automating those parts of order entry. Keep storefront rendering server-side and preserve shared-hosting compatibility.
