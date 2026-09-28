# Bridgeway Digital SEO Configurator

The SEO configurator stores sitewide and per-template metadata in `site_settings`.
Dynamic pages, service pages and blogs resolve those settings through:

- `App\Support\SeoDefaults`
- `App\Support\FrontendSeoPresenter`
- `App\Support\SchemaTemplates`

Public URLs should come from `WEBSITE_URL`; local API traffic should use
`FRONTEND_URL` and `API_BASE_URL`. Do not hardcode domains in SEO templates.

Default schema values describe Bridgeway Digital as a digital growth agency and
use the CMS-managed site name, social profile settings, and `/images/logo.svg`.
Editors can override page-level title, description, canonical, Open Graph,
Twitter and JSON-LD values in the admin SEO fields.

After changing SEO defaults or content, clear backend caches and revalidate the
frontend:

```bash
php artisan optimize:clear
php artisan sitemap:generate --force
```
