# Initial website content

These fixtures initialize structured CMS records through `WebsiteContentSeeder`.
They contain public authored content extracted from the existing frontend and migrated CMS,
including section order, SEO, images, blog bodies, and taxonomy. The backend and frontend APIs
never read these files at runtime. Editors change the database through the CMS, not these files.

The `pages` directory includes non-routable global documents for floating calls to action
and forms/blog cards embedded in frontend-owned static pages. Static marketing copy is not seeded.
Other fixtures preserve the current CMS slugs, including previously editor-renamed slugs.
Navigation and contact settings are seeded by `NavigationSeeder` and `SiteSettingSeeder`.

Run `php artisan migrate --force` then `php artisan db:seed --force` for a new environment.
Run `php artisan db:seed --class=WebsiteContentSeeder --force` to add missing initial records
without resetting admin credentials. Reruns do not overwrite existing content, drafts, images,
SEO, categories, or intentionally deleted sections. Never use `migrate:fresh` on a live database.

Seeder fixtures are bootstrap content, not a database backup. Back up the live database and
uploaded media to transfer subsequent editor changes between environments.
