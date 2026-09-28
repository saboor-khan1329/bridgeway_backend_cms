# Bridgeway Digital CMS — Backend Engine

[![Laravel Version](https://img.shields.io/badge/Laravel-10.x-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php)](https://php.net)
[![PostgreSQL](https://img.shields.io/badge/Database-PostgreSQL-336791?style=flat-square&logo=postgresql)](https://www.postgresql.org/)
[![Redis Caching](https://img.shields.io/badge/Cache-Redis-DC382D?style=flat-square&logo=redis)](https://redis.io/)
[![PHPUnit Tests](https://img.shields.io/badge/Tests-205%20Passing-success?style=flat-square&logo=phpunit)](https://phpunit.de/)

The headless backend and administration engine powering the **Bridgeway Digital** multi-service platform. Built with Laravel 10, PostgreSQL, and Redis, it delivers structured content APIs to the Next.js 14 frontend, provides a streamlined editorial administration panel, manages dynamic SEO schemas, powers runtime file/asset workflows, and securely captures inquiries and leads with an outbox mail queue.

---

## Table of Contents

- [System Architecture](#system-architecture)
- [Core Features & Modules](#core-features--modules)
  - [1. Service Management (Amazon & Development)](#1-service-management-amazon--development)
  - [2. Runtime File Manager](#2-runtime-file-manager)
  - [3. SEO Configurator & Schema Engine](#3-seo-configurator--schema-engine)
  - [4. Content Block & Section Engine](#4-content-block--section-engine)
  - [5. Inquiries & Durable Mail Outbox](#5-inquiries--durable-mail-outbox)
  - [6. Excel Bulk Import / Export](#6-excel-bulk-import--export)
- [Content Ownership & Headless API Contracts](#content-ownership--headless-api-contracts)
- [Caching & On-Demand Revalidation Flow](#caching--on-demand-revalidation-flow)
- [Directory Structure](#directory-structure)
- [Environment Configuration](#environment-configuration)
- [Local Setup & Installation](#local-setup--installation)
- [Database Migrations & Seeders](#database-migrations--seeders)
- [Testing & Quality Assurance](#testing--quality-assurance)
- [Production Deployment](#production-deployment)

---

## System Architecture

```
                       ┌──────────────────────────────────────────────────┐
                       │           Next.js 14 Frontend App Router         │
                       │           (Server Components & SSR / ISR)        │
                       └───────────▲──────────────────────────┬───────────┘
                                   │                          │
                 HTTP Fetch (Cached)                          │ POST /api/forms
                 with Tagged Keys                             │ (Inquiry Proxy)
                                   │                          │
                       ┌───────────┴──────────────────────────▼───────────┐
                       │          Bridgeway Digital CMS (Laravel 10)       │
                       ├──────────────────────────────────────────────────┤
                       │  • Admin Panel (Blade / LTE Custom Shell)        │
                       │  • RESTful API Engine (/api/frontend/*)           │
                       │  • SEO Configurator & JSON-LD Generator          │
                       │  • File Manager & Public Asset Storage           │
                       │  • Mail Outbox & Spam Protection Engine          │
                       └───────────┬──────────────┬───────────────┬───────┘
                                   │              │               │
                                   ▼              ▼               ▼
                       ┌───────────────┐  ┌───────────────┐ ┌───────────────┐
                       │  PostgreSQL   │  │  Redis Cache  │ │ Next.js Hook  │
                       │  (Persistence)│  │ (API Caching) │ │ (Revalidate)  │
                       └───────────────┘  └───────────────┘ └───────────────┘
```

---

## Core Features & Modules

### 1. Service Management (Amazon & Development)
The administration panel provides dedicated, cleanly separated management for all commercial offerings:
- **Amazon Services**: Dedicated tab managing specialized Amazon operations:
  - *Amazon PPC Management Services*
  - *Amazon SEO Services*
  - *Amazon Product Listing Services*
  - *Amazon Product Research Services*
  - *Amazon Product Photography Services*
  - *Full Amazon Marketing Services*
- **Development Services**: Dedicated tab managing web & mobile engineering services:
  - *AngularJS Web Development*
  - *Magento Web Development*
  - *White Label Web Development*
  - *Ecommerce Web Development*
  - *Laravel Web Development*
  - *Python Web Development*
  - *Shopify Web Development*
  - *ReactJS Web Development*
  - *PHP Web Development*

Each service features custom section configurations matching its respective frontend layout, with full validation and repeater controls.

### 2. Runtime File Manager
A full-featured media management system integrated directly into admin forms:
- **Instant Upload & Selection**: Browse existing assets or upload on the fly without entering manual paths.
- **Detailed Metadata**: Configurable fields for Image Preview, Alt Text, Title, Caption, Width, and Height.
- **File Validation**: Strict file type and MIME-type restrictions with automatic thumbnail previews and responsive dimensions.
- **Form Components**: Reusable Blade image selectors with live preview cards and modal picker integration.

### 3. SEO Configurator & Schema Engine
Centralized, enterprise-grade SEO management across the entire digital footprint:
- **Page-Level SEO**: Custom Meta Titles, Meta Descriptions, Keywords, Canonical URLs, and Robots flags (`noindex`, `nofollow`).
- **Social Graph**: Tailored OpenGraph (`og:title`, `og:description`, `og:image`, `og:url`) and Twitter Card attributes.
- **JSON-LD Structured Data**: Dynamic generation of Schema.org markup including:
  - `Organization` & `WebSite`
  - `Service` & `BreadcrumbList`
  - `Article` (for blog posts)
  - `FAQPage` (dynamic accordion FAQs)
- **Automated Sitemap Generation**: Scheduled and on-demand CLI generator (`php artisan sitemap:generate --force`) writing compliant XML sitemaps.

### 4. Content Block & Section Engine
- Structured JSON document storage (`SectionDocument`) for modular components: Hero sections, Sub-service cards, Comparison tables, Pricing packages, Goal points, Why-trust badges, Real-world tickers, and Accordions.
- Strict schema validation ensuring every field matches its frontend presenter requirements without risking client-side breakage.

### 5. Inquiries & Durable Mail Outbox
- Multi-step form capture for Free Quotes, Contact, and Onboarding inquiries.
- Multi-layer spam defenses: Honeypot trap (`company`), submission rate limiting, link count thresholds, and configurable CAPTCHA support.
- **Durable Mail Outbox**: Submissions are stored reliably in the database; notifications are queued with exponential retries and dead-letter safety, preventing lost leads.

### 6. Excel Bulk Import / Export
- Built-in spreadsheets module enabling bulk data export/import for services, metadata, and pages using Laravel Excel.
- Validates relationships and foreign keys with clear diagnostic error logging.

---

## Content Ownership & Headless API Contracts

| Content Type | Ownership & Source | Frontend Route Handling |
| :--- | :--- | :--- |
| **Static Pages** (Home, About, Portfolio, Privacy, Terms) | Frontend routes with CMS-driven global sections (forms, blog cards) | Explicit routes in Next.js (`src/app/*`) |
| **Amazon Services** | CMS Service Documents & Typed Sections | Next.js dedicated pages (`src/app/amazon-*`) |
| **Development Services** | CMS Service Documents & Dynamic Sections | Next.js dynamic routing (`src/app/[slug]`) |
| **Blogs & Articles** | CMS Blog records, categories, author profiles, and markdown/HTML | Next.js `/blogs` and `/blogs/[slug]` |
| **Navigation & Global Shell** | CMS Menus, Site Settings, Header, Footer, and Floating Form | `GET /api/frontend/site` shared across layout |

### Key API Endpoints

All public frontend read endpoints return structured envelopes: `{ "success": true, "data": ... }`.

- `GET /api/frontend/site`: Global header, menus, footer, contact channels, and site settings.
- `GET /api/frontend/pages/{slug}`: Structured section document and SEO payload for content pages.
- `GET /api/frontend/root-pages/{slug}`: Resolves root-level URLs and service aliases.
- `GET /api/frontend/service-pages/{slug}`: Complete data document for Development services.
- `GET /api/frontend/blogs`: Paginated blog list with category filtering.
- `GET /api/frontend/blogs/{slug}`: Single blog post article details and SEO schema.
- `POST /api/forms`: Unified submission handler for quotes, contact inquiries, and onboarding with spam checks and file attachments.

---

## Caching & On-Demand Revalidation Flow

To achieve sub-millisecond API response times and instant content propagation:

1. **Redis Caching Layer**:
   - CMS read requests are cached in Redis with environment-isolated prefixes (`FRONTEND_API_CACHE_PREFIX`).
   - Default cache TTL is 300 seconds, customizable per entity type.

2. **Automated Cache Invalidation & Webhook Trigger**:
   - Whenever an editor saves or publishes changes in the Admin Panel, model observers fire cache invalidation events.
   - The backend clears matching Redis keys and immediately sends an HTTP `POST` request to the Next.js revalidation endpoint:
     ```
     POST http://localhost:3000/api/revalidate
     Headers:
       Authorization: Bearer <FRONTEND_REVALIDATE_SECRET>
     Body:
       { "tag": "cms", "slug": "laravel-development-company" }
     ```
   - Next.js instantly purges its cached Static Route / Tagged Data (`revalidateTag`), ensuring visitors see the latest updates instantly without a site rebuild.

---

## Directory Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/         # Admin Panel controllers (Services, SEO, Files, Inquiries)
│   │   │   └── Api/           # Headless Frontend API controllers
│   │   └── Middleware/        # Authentication, CORS, and Rate Limiting
│   ├── Models/                # Eloquent models (Service, ContentPage, Blog, Inquiry, SeoMeta)
│   ├── Observers/             # Model observers triggering cache invalidation & webhooks
│   ├── Services/              # Business logic (SEO, File Manager, Excel, Revalidation)
│   └── Support/               # Helpers, SectionDocument parser, and Cleaners
├── config/                    # Application and package configurations
├── database/
│   ├── factories/             # Model factories for testing
│   ├── migrations/            # Database schema migrations
│   └── seeders/               # Content seeders and live website imports
├── public/                    # Web root (index.php, storage symlink, static assets)
├── resources/
│   └── views/
│       ├── admin/             # Admin LTE Blade templates (Services, SEO, Files)
│       └── components/        # Blade UI components (Inputs, Selectors, Breadcrumbs)
├── routes/
│   ├── api.php                # Headless frontend API routes
│   └── web.php                # Admin panel and authentication routes
├── storage/                   # Logs, framework cache, and uploaded media files
└── tests/
    ├── Feature/               # API, Admin CRUD, and integration tests
    └── Unit/                  # Schema, SEO, and parser unit tests
```

---

## Environment Configuration

Copy `.env.example` to `.env` and configure your local settings:

```bash
cp .env.example .env
```

### Essential Settings

| Variable | Description | Default / Example |
| :--- | :--- | :--- |
| `APP_NAME` | Name of the CMS application | `"Bridgeway Digital CMS"` |
| `APP_URL` | Base URL of the backend application | `http://127.0.0.1:8000` |
| `FRONTEND_URL` | Base URL of the Next.js frontend | `http://localhost:3000` |
| `WEBSITE_URL` | Public production URL (for canonical SEO tags) | `https://bridgewaydigital.com` |
| `DB_CONNECTION` | Database driver | `pgsql` |
| `DB_HOST` / `DB_PORT` | PostgreSQL host and port | `127.0.0.1` / `5432` |
| `DB_DATABASE` | Database name | `bridgeway` |
| `DB_USERNAME` / `DB_PASSWORD` | PostgreSQL credentials | `postgres` / `your_password` |
| `REDIS_CLIENT` | Redis client adapter | `predis` |
| `FRONTEND_API_CACHE_STORE` | Cache store for frontend API reads | `redis` |
| `FRONTEND_REVALIDATE_URL` | Webhook URL for Next.js ISR revalidation | `http://localhost:3000/api/revalidate` |
| `FRONTEND_REVALIDATE_SECRET` | Secret token shared with Next.js | Generate secure random string |
| `ADMIN_SEED_EMAIL` | Default administrator account email | `admin@bridgewaydigital.test` |
| `ADMIN_SEED_PASSWORD` | Default administrator account password | `SetSecurePassword123!` |

---

## Local Setup & Installation

### Prerequisites
- **PHP 8.2** or higher with `pgsql`, `pdo_pgsql`, `mbstring`, `openssl`, `curl`, `gd`, `zip` extensions
- **Composer 2.x**
- **PostgreSQL 14+**
- **Redis Server** (optional for local file cache, recommended for production)
- **Node.js 18+** & **npm**

### Step-by-Step Installation

1. **Install Dependencies**:
   ```bash
   composer install
   ```
   > *Windows Note:* If posix/pcntl extensions warn during install, run:
   > ```bash
   > composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
   > ```

2. **Generate Application Key**:
   ```bash
   php artisan key:generate
   ```

3. **Run Migrations & Seed Content**:
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

4. **Create Storage Symlink**:
   ```bash
   php artisan storage:link
   ```

5. **Build Admin Assets**:
   ```bash
   npm install
   npm run build
   ```

6. **Serve the Application**:
   ```bash
   php artisan serve --host=127.0.0.1 --port=8000
   ```

7. **Access the Admin Panel**:
   - URL: [http://127.0.0.1:8000/admin](http://127.0.0.1:8000/admin)
   - Login with the credentials defined in `ADMIN_SEED_EMAIL` and `ADMIN_SEED_PASSWORD`.

---

## Database Migrations & Seeders

- **Migrations**: Defines strict relations, unique indexes, cascading foreign keys, and JSONB fields for sections and schemas.
- **Seeders**:
  - `WebsiteContentSeeder`: Initializes 12 routable CMS pages, six global documents, nine Development services, and 16 blogs.
  - All 16 target Amazon and Web Development services are completely populated with structured content, pricing cards, sub-services, and FAQs.
- To re-run seeders safely without destroying custom data:
  ```bash
  php artisan db:seed
  ```
  *(Never run `migrate:fresh` in production environments where editorial content must be retained).*

---

## Testing & Quality Assurance

The backend includes a comprehensive PHPUnit test suite validating API contracts, CRUD actions, SEO generation, cache invalidation, and file management:

```bash
php artisan test
```

- **In-Memory SQLite**: Unit and feature tests execute using an isolated SQLite database and array cache without modifying your local database.
- **Passing Suite**: **205 tests**, 0 failures.

---

## Production Deployment

1. **Environment Setup**:
   - Configure production `.env` with production PostgreSQL, Redis, and SMTP mailer.
   - Set `APP_ENV=production` and `APP_DEBUG=false`.
2. **Optimize Laravel**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
3. **Queue & Outbox Worker**:
   - Run a supervisor daemon for the outbox queue:
     ```bash
     php artisan queue:work --queue=mail-outbox,default --tries=3
     ```
4. **Scheduled Tasks**:
   - Add Laravel's scheduler to system crontab:
     ```bash
     * * * * * cd /path-to-backend && php artisan schedule:run >> /dev/null 2>&1
     ```
5. **XML Sitemap Generation**:
   - Force regeneration via cron or deployment script:
     ```bash
     php artisan sitemap:generate --force
     ```
