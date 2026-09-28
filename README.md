# Bridgeway Digital CMS

The existing Laravel CMS supplies Amazon and Development services, blogs, forms, navigation and
shared content to ../frontend. See [CONTENT_ARCHITECTURE.md](CONTENT_ARCHITECTURE.md) for ownership,
API contracts, SEO and caching.

## Local setup

Copy .env.example to .env and configure local PostgreSQL, Redis and an admin account.
Start PostgreSQL and Redis before the application. Keep credentials out of version control.

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
npm install
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

On Windows, install with composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix.
Horizon requires Unix worker extensions; use the ordinary queue worker locally. Use Laragon's PHP
terminal if PHP is not on PATH.

Admin login: http://127.0.0.1:8000/login. Set ADMIN_SEED_EMAIL, ADMIN_SEED_NAME and ADMIN_SEED_PASSWORD.
Password resets on seed reruns are disabled unless ADMIN_SEED_RESET_PASSWORD=true. Production
requires an explicit password.

The frontend uses http://localhost:3000 and server-only API_BASE_URL=http://127.0.0.1:8000/api.
WEBSITE_URL controls public canonical URLs separately from the local admin/API address.

Local inquiry notifications use the log transport and are recorded in `storage/logs`. For real
delivery, set `NOTIFICATION_MAILER=notification-smtp`, configure the `NOTIFICATION_MAIL_*` SMTP
values shown in `.env.example`, use a verified `MAIL_FROM_ADDRESS`, and set
`ADMIN_INQUIRY_RECIPIENTS` to one or more comma-separated addresses. The durable mail outbox
records delivery status and retries failures; when
`MAIL_OUTBOX_QUEUE_ENABLED=true`, keep `php artisan queue:work --queue=mail-outbox,default` and the
Laravel scheduler running.

## Cache and publishing

Use FRONTEND_API_CACHE_STORE=redis and distinct REDIS_PREFIX, CACHE_PREFIX and
FRONTEND_API_CACHE_PREFIX per environment. Read responses are cached; submissions are not.
After committed edits, the backend expires API data and notifies the Next.js revalidation webhook.

Set FRONTEND_REVALIDATE_URL=http://localhost:3000/api/revalidate locally and match
FRONTEND_REVALIDATE_SECRET with frontend CMS_REVALIDATION_SECRET. Run php artisan config:clear
after environment changes.

## Verification and deployment

```bash
php artisan test
php artisan sitemap:generate --force
```

Tests use in-memory SQLite and an array cache, not the local website database.
Run npm test and npm run build in ../frontend. Builds need the backend for CMS prerendering.

For production: provision PostgreSQL/Redis, configure production URLs/secrets/mail, install
dependencies, back up existing data, run php artisan migrate --force, build both apps and cache
Laravel configuration/routes/views. Seed only to initialize missing content. Run a queue worker
and schedule sitemap generation as needed. Back up the database and uploaded media.
Never run migrate:fresh against content that must be retained.
