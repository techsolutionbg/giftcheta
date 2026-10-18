# Giftcheta

Independent, lightweight e-commerce foundation using plain PHP, PDO, MySQL, Apache, and Docker. The current stage includes a React administration panel for the product catalog and categories, backed by a versioned PHP JSON API. Checkout, orders, and the public storefront are future stages.

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

Open the administration panel at `http://localhost:8080/admin/`; sign in with the administrator created by the command above. The backend health check is `http://localhost:8080/api/v1/health`, and development status is at `/api/v1/status`. phpMyAdmin is available at `http://localhost:8081` and connects to the Compose database service. Log in using `DB_USERNAME` and `DB_PASSWORD` from `.env` (or use `root` and `DB_ROOT_PASSWORD`). Stop with `docker compose down`; `docker compose down -v` also removes the local database volume.

Rebuild the static React panel after changing files under `admin/` with `docker compose --profile build run --rm admin-build`. The generated assets are written to `public/admin/` and are served by Apache; Node is not needed at runtime.

### First administrator

Run `docker compose exec app php bin/create-admin.php`, then provide an email, display name, and a unique password of at least 12 characters when prompted. The password is hashed before storage. Run this command only after applying migrations. Do not commit administrator credentials.

## Configuration and database

Copy `.env.example` to `.env`. The Compose app connects to service `db` using `DB_HOST=db`. For direct hosting, set `DB_HOST` to the database host provided by the host. The PDO factory uses native prepared statements, exceptions, and `utf8mb4`.

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

Continue with customer/cart/order flows and payments/shipping settings. Keep storefront rendering server-side and preserve shared-hosting compatibility.
