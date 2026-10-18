# Giftcheta architecture

Giftcheta is a modular monolith with a small plain PHP 8.3+ backend. Apache serves only `public/`; all requests enter through `public/index.php` and pass through the application middleware pipeline to a route/controller. Controllers return immutable HTTP responses; API payloads use `data` or `error` envelopes.

Configuration is loaded from environment variables into PHP config files. `Database` creates a PDO MySQL connection with exceptions, associative fetches, and native prepared statements. CLI migrations are sorted SQL files recorded in the `migrations` table. MySQL DDL can partially apply, so a failed migration reports that manual inspection is needed; migrations are not falsely treated as transactional.

Logs go to private `storage/logs`. Health is public and intentionally reveals only `ok`. Status is available only when `APP_ENV=development`. Production should point the domain document root to `public/`; for cPanel accounts that cannot change the root, publish a carefully assembled public directory and place the application source/config/storage above `public_html`, with a reviewed bootstrap path. Never copy the whole repository into `public_html`.

The future storefront remains PHP-rendered. A separately built React/TypeScript admin may place compiled assets under `public/admin`; Node is a build-time tool only. Products, orders, auth, payment, shipping, and admin domain schemas are deliberately deferred.
