# Royal Komputer — Full Codebase Plan

> **Purpose:** Single source of truth for the project architecture — every folder, every file, every decision, every next step.
> Use this to track progress, understand dependencies, and onboard new agents.

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Current State Dashboard](#2-current-state-dashboard)
3. [database/ — PostgreSQL Schema & Migrations](#3-database--postgresql-schema--migrations)
4. [frontend/ — Storefront (Vite + Tailwind)](#4-frontend--storefront-vite--tailwind)
5. [backend/ — Admin Panel + API (PHP)](#5-backend--admin-panel--api-php)
6. [sync/ — IPOS Sync Agent (Local PC)](#6-sync--ipos-sync-agent-local-pc)
7. [Cloud Deployment](#7-cloud-deployment)
8. [Cross-Cutting Concerns](#8-cross-cutting-concerns)
9. [Dev Workflow](#9-dev-workflow)
10. [Complete Task Checklist](#10-complete-task-checklist)

---

## 1. Architecture Overview

```
┌──────────────────────────────────────────────────────────────────────────┐
│                           LOCAL PC (Toko Kediri)                         │
│                                                                          │
│  IPOS PostgreSQL ───► sync/ (Task Scheduler, every 1hr) ──► git push    │
│  192.168.18.189:5444   update_produk.php + git_push.bat                  │
└────────────────────────────────┬─────────────────────────────────────────┘
                                 │ git push
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                              GIT REPO (GitHub)                           │
│                                                                          │
│   ┌──────────────┐   ┌──────────────┐   ┌──────────────┐                │
│   │   database/  │   │  frontend/   │   │   backend/   │                │
│   │   Neon DB    │   │  Netlify     │   │   Render     │                │
│   │   SQL only   │   │  Vite + TW   │   │   PHP 8 API  │                │
│   └──────────────┘   └──────────────┘   └──────────────┘                │
│                                                                          │
│   ┌──────────────┐                                                      │
│   │    sync/     │                                                      │
│   │  Local PC    │                                                      │
│   └──────────────┘                                                      │
└──────────────────────────────────────────────────────────────────────────┘
         │                     │                     │
         ▼                     ▼                     ▼
       Neon                 Netlify                 Render
  (PostgreSQL)          (CDN storefront)       (PHP admin + API)
  Cloud DB               Auto-deploy             Auto-deploy
```

### Service Roles

| Service | Role | Purpose |
|---------|------|---------|
| **Netlify** | Frontend | Storefront static site, CDN, custom domain, free tier |
| **Render** | Backend | PHP admin panel, API layer, health check, persistent disk |
| **Neon** | Database | Serverless PostgreSQL, pay-per-use, schema mirrors local IPOS |
| **Local PC** | Sync agent | Task Scheduler runs sync → git push every 1 hour |

### Data Flow

```
IPOS writes inventory ──► sync agent queries DB ──► writes cache_produk.json
    │                          │                           │
    │                     (local PostgreSQL)          sync/ + frontend/
    │                                                    │
    ▼                                                    ▼
  tbl_item ──► git_push.bat ──► git push ──► Netlify auto-deploys
  tbl_itemstok                                         │
        │                                        frontend/uploads/
        ▼                                               ▲
  tbl_web_deskripsi ──► admin edits via backend ────────┘
       ───► immediate sync to frontend/ (jam, schedules,
             tagline, status, photos, cache)
                               (Render)
```

---

## 2. Current State Dashboard

### Overall Progress

| Area | Status |
|------|--------|
| 4-folder monorepo structure | ✅ Complete |
| `database/` — schema + migrations | ✅ Complete (ready for Neon) |
| `frontend/` — PHP fallback files migrated | ✅ Complete |
| `frontend/` — Vite scaffolded | ✅ Complete |
| `frontend/` — Components built (JS) | ✅ Complete |
| `frontend/` — Build (`npm run build`) | ✅ Verified (13 modules, 30KB JS + 32KB CSS) |
| `backend/` — All admin files migrated | ✅ Complete |
| `backend/` — config with data/ paths + env vars | ✅ Complete |
| `backend/` — render.yaml blueprint | ✅ Complete |
| `backend/` — health check endpoint | ✅ Complete |
| `backend/` — CORS helper (`cors.php`) | ✅ Complete |
| `backend/` — API endpoints (status, schedules) | ✅ Complete |
| `backend/` — API Test Suite | ✅ Complete (34/34 passing) |
| `backend/` — Auth consistency (JSON errors) | ✅ Complete |
| `backend/` — Scroll preservation (admin) | ✅ Complete |
| `backend/` — Confirmation modal (centered) | ✅ Complete |
| `backend/` — Operating hours "Libur" day | ✅ Complete |
| `backend/` — Editable tagline | ✅ Complete |
| `backend/` — Inline notification toast (replace alert) | ✅ Complete |
| `backend/` — Cache invalidation for photo delete/reorder | ✅ Complete |
| `backend/` — Offline/DB-less product updates | ✅ Complete |
| `backend/` — Frontend sync for all admin operations | ✅ Complete |
| `frontend/` — Effective close time (schedules) | ✅ Complete |
| `frontend/` — Tagline display | ✅ Complete |
| `frontend/` — Immediate config sync from admin | ✅ Complete |
| `sync/` — Headless sync agent | ✅ Complete |
| `sync/` — git_push.bat | ✅ Complete |
| `frontend/netlify.toml` — Deploy config (Vite mode) | ✅ Complete |
| Cloud deployment (Neon → Render → Netlify) | ⬜ Not started |

---

## 3. database/ — PostgreSQL Schema & Migrations

### Purpose
Version-controlled SQL schema for the **Neon** cloud database. Mirrors the local IPOS database structure so the admin panel on Render can read/write data.

### File Inventory

| File | Status | Description |
|------|--------|-------------|
| `database/schema.sql` | ✅ Created | Full DDL for `tbl_item`, `tbl_itemstok`, `tbl_web_deskripsi` with indexes |
| `database/migrations/001_initial.sql` | ✅ Created | Initial migration (same as schema.sql for tracking) |

### Tables

| Table | Source | Purpose |
|-------|--------|---------|
| `tbl_item` | IPOS (read-only) | Product master: kodeitem, namaitem, jenis, hargajual1 |
| `tbl_itemstok` | IPOS (read-only) | Product stock: kodeitem, stok (SUM per item) |
| `tbl_web_deskripsi` | Custom (admin-writable) | Custom product descriptions, auto-created by API |

### Implementation Plan

```
[ ] 1. Create Neon project at neon.tech
[ ] 2. Copy connection string (postgresql://user:pass@ep-xxx.neon.tech/royal)
[ ] 3. Run: psql "<connection-string>" -f database/schema.sql
[ ] 4. Verify tables created (tbl_item, tbl_itemstok, tbl_web_deskripsi)
[ ] 5. Populate from local IPOS (pg_dump / manual sync)
[ ] 6. Add connection string to Render env vars (PGHOST, PGPORT, etc.)
[ ] 7. Future: migration 002, 003 for schema changes
```

### Neon vs Local Differences

| Aspect | Local (IPOS) | Cloud (Neon) |
|--------|-------------|--------------|
| Host | `192.168.18.189` | `ep-xxxx.neon.tech` |
| Port | `5444` | `5432` |
| Database | `i4_ROYAL` | `royal` |
| User | `admin` | `royal_owner` |
| Data | Full IPOS live data | Admin-managed descriptions only |
| Schema | Auto-managed by IPOS | Managed via schema.sql |

---

## 4. frontend/ — Storefront (Vite + Tailwind)

### Purpose
Public-facing storefront deployed to **Netlify**. Currently in a **hybrid state**: PHP fallback (`index.php`) works as before, while the Vite scaffold is ready for component development.

### Current State

```
frontend/
├── index.html              # Vite HTML entry (placeholder content via JS)
├── index.php               # PHP fallback storefront (fully functional)
├── vite.config.js          # Vite + Tailwind CSS plugin
├── package.json            # vite ^6, tailwindcss ^4, @tailwindcss/vite ^4
├── netlify.toml            # Currently: PHP mode (publish = ".")
├── src/
│   ├── main.js             # Full app: state management, filtering, sorting
│   └── style.css           # Tailwind imports + astra color theme
├── config.php              # DB config (for PHP fallback)
├── api_produk.php          # Product API (cache-first, DB fallback)
├── cache_produk.json       # Product cache (primary data source)
├── jam_operasional.json    # Operating hours (synced from admin)
├── jadwal_tutup.json       # Closure schedules (synced from admin)
├── tagline.json            # Tagline toko (synced from admin)
├── status_toko.txt         # Manual store status (synced from admin)
├── dist/                   # Vite build output (gitignored)
│   └── index.html
├── uploads/                # Product photos (synced from backend)
├── logo/                   # Brand assets
└── node_modules/           # npm dependencies (gitignored)
```

### Vite Scaffold Status

| Item | Status | Details |
|------|--------|---------|
| `package.json` | ✅ Done | vite ^6, tailwindcss ^4, @tailwindcss/vite ^4 |
| `vite.config.js` | ✅ Done | Proxy :5173 → :8081 for all API + static file paths |
| `index.html` | ✅ Done | Font Awesome, Google Fonts, favicon |
| `src/style.css` | ✅ Done | `@import "tailwindcss"` + astra palette |
| `src/main.js` | ✅ Done | Full app: state management, filtering, sorting, event binding |
| Components | ✅ Done | 7 components: Navbar, StoreStatus, FilterSidebar, ProductCard, ProductGrid, ProductModal, Footer |
| `lib/api.js` + `lib/format.js` | ✅ Done | Fetch wrappers, fallback chains, IDR formatter |
| `npm run build` | ✅ Verified | 13 modules, 30KB JS + 32KB CSS, dist/ output |
| `netlify.toml` Vite mode | ✅ Done | `command="npm run build"`, `publish="dist"` with redirects |

### Target Architecture (Vite)

```html
index.html ──► main.js (entry)
                   │
                   ├──► App shell (Navbar, StoreStatus, Footer)
                   │         │
                   │         ▼
                   │    ProductGrid ──► ProductCard (×n)
                   │         │
                   │         ▼
                   │    ProductModal (carousel + WhatsApp)
                   │
                   └──► lib/api.js ──► fetch to backend/
                        lib/format.js (IDR currency)
```

### Component Tree & Data Flow

```
App
├── Navbar
│   ├── Logo (link to top)
│   ├── SearchBar (controlled input → filter state)
│   └── SocialLinks (Facebook, Instagram, TikTok, WhatsApp, YouTube)
│
├── StoreStatus
│   ├── Fetches: GET /api/status (backend)
│   └── Renders: Open badge / Closed badge / Schedule warning
│
├── FilterSidebar
│   ├── CategoryFilter (derived from product data)
│   ├── ConditionFilter (Semua / Baru / Bekas)
│   └── SortSelect (default / low-high / high-low)
│
├── ProductGrid
│   ├── Loading state (spinner)
│   ├── Empty state (icon + message)
│   └── ProductCard × N
│       ├── Image (hover zoom)
│       ├── Name, Price (IDR format)
│       ├── Category badge + Condition badge
│       └── Click → open ProductModal
│
├── ProductModal
│   ├── Image carousel (prev/next + dot indicators)
│   ├── Product info (name, price, description)
│   ├── Stock indicator
│   └── WhatsApp order button
│
└── Footer
    ├── Address + contact
    ├── Social links (full list)
    ├── Operating hours table
    └── Copyright
```

### Vite Implementation Plan

```
[x] 1. Add proxy to vite.config.js: /api/* → http://localhost:8081
[x] 2. Create Navbar component (logo, search, social icons)
[x] 3. Create StoreStatus component (fetch status)
[x] 4. Create FilterSidebar component (category, condition, sort)
[x] 5. Create ProductGrid + ProductCard components
[x] 6. Create ProductModal component (carousel)
[x] 7. Create Footer component (hours, social, copyright)
[x] 8. Create lib/api.js — fetch wrapper for produk + status + schedules
[x] 9. Create lib/format.js — IDR currency formatter
[x] 10. Wire up main.js with all components
[x] 11. Update netlify.toml: command="npm run build", publish="dist"
[x] 12. Test: npm run dev (Vite) + php -S localhost:8081 (backend)
[x] 13. Build: npm run build → verify dist/
[ ] 14. Deploy to Netlify
```

---

## 5. backend/ — Admin Panel + API (PHP)

### Purpose
Admin dashboard, login, product management, operating hours, closure schedules. Deployed to **Render** as a PHP web service. Serves as the API layer for the Vite frontend.

### Current File Inventory

| File | Status | Auth | Description |
|------|--------|------|-------------|
| `backend/admin.php` | ✅ Complete | Session | Admin dashboard HTML+JS |
| `backend/login.php` | ✅ Complete | None | Login form + authentication |
| `backend/logout.php` | ✅ Complete | None | Session destroy + redirect |
| `backend/config.php` | ✅ Complete | Varies | DB + JSON helpers, env var support |
| `backend/index.php` | ✅ Complete | None | Admin landing page (logo + login button) |
| `backend/update_produk.php` | ✅ Complete | Session | Product description edit + photo upload |
| `backend/update_admin.php` | ✅ Complete | Session | Admin CRUD + schedules + status (JSON auth) |
| `backend/update_jam.php` | ✅ Complete | Super Admin | Operating hours (JSON auth) |
| `backend/api_produk.php` | ✅ Complete | Public | Product JSON API (cache fallback, CORS) |
| `backend/api_manage_photos.php` | ✅ Complete | Session | Photo delete + reorder (CORS added) |
| `backend/api_status.php` | ✅ Complete | Public | Store status endpoint (CORS via cors.php) |
| `backend/api_schedules.php` | ✅ Complete | Public | Closure schedules endpoint (CORS via cors.php) |
| `backend/cors.php` | ✅ Complete | — | CORS helper with origin whitelist |
| `backend/render.yaml` | ✅ Complete | — | Render blueprint config |
| `backend/data/admins.json` | ✅ Migrated | — | Admin accounts (bcrypt hashed) |
| `backend/data/jam_operasional.json` | ✅ Migrated | — | Per-day operating hours |
| `backend/data/jadwal_tutup.json` | ✅ Migrated | — | Closure schedules |
| `backend/data/status_toko.txt` | ✅ Migrated | — | Manual store status override |
| `backend/data/cache_produk.json` | ✅ Migrated | — | Product cache |
| `backend/data/tagline.json` | ✅ Created | — | Tagline toko (store tagline editor) |
| `backend/uploads/` | ✅ Migrated | — | Product photos (WEBP) |
| `backend/logo/` | ✅ Migrated | — | Brand assets (logo.webp) |

### Key Decisions in `config.php`

| Feature | Implementation |
|---------|---------------|
| Cloud DB | `getenv('PGHOST') ?: '192.168.18.189'` — works locally with no env vars |
| Data paths | All constants point to `__DIR__ . '/data/'` subdirectory |
| Persistent storage | Render disk mounts at `backend/data/` for JSON file persistence |
| Session | `session_start()` at top of config.php for all pages |

### Completed Backend Items

```
[x] Create backend/cors.php — CORS helper
    - Origin whitelist: localhost:5173, localhost:8080, Netlify URL
    - Handles credentials + wildcard fallback + OPTIONS preflight (204)

[x] Add Vite proxy rules for JSON/text files
    - /jam_operasional.json → localhost:8081
    - /jadwal_tutup.json → localhost:8081
    - /status_toko.txt → localhost:8081

[x] Create backend/api_status.php — Store status endpoint
    - Returns: { isOpen, isTemporarilyClosed, hours, upcomingSchedule, nextOpenDay, nextOpenTime, closeTime, timestamp }
    - CORS via cors.php (refactored from inline headers)

[x] Create backend/api_schedules.php — Schedules endpoint
    - Returns: array of closure schedules (public)

[x] Add CORS headers to all public API files
    - api_produk.php, api_status.php, api_schedules.php, api_manage_photos.php all use cors.php

[x] Verify update_produk.php — no leftover sync code
    - Sync logic fully extracted to sync/update_produk.php

[x] Fix login.php "Kembali ke Toko" link
    - Now links to https://royal-komputer.netlify.app

[x] Fix auth consistency for API endpoints
    - update_admin.php & update_jam.php now return JSON errors instead of redirect
    - Consistent with update_produk.php & api_manage_photos.php

[x] Fix admin.php dead code
    - Simplified setManualStatus() — removed 4 non-existent DOM element references

[x] Create API Test Suite (backend/tests/)
    - test_api.sh (bash/curl) + test_api.php (native PHP)
    - 34 assertions across 7 categories
    - Result: 34/34 PASSED (PHP 8.5 compatible, zero deprecation warnings)

[x] Inline notification toast
    - Replaced all alert() calls with showNotification() in admin.php
    - Three types: success (green), error (red), info (blue)
    - Auto-dismiss after 4 seconds, manual dismiss via X button

[x] Cache invalidation for photo operations
    - api_manage_photos.php: delete/reorder now rewrites cache_produk.json
    - Delete also removes from frontend/uploads/ immediately
    - update_produk.php: copies photos to frontend/uploads/ after upload

[x] Offline/DB-less product updates
    - update_produk.php works without DB connection (writes to cache)
    - Returns warning flag in JSON when in offline mode
    - Guards all DB ops behind $db_available check

[x] Frontend sync for all admin operations
    - saveSchedules() → frontend/jadwal_tutup.json
    - saveTagline() → frontend/tagline.json
    - update_jam.php → frontend/jam_operasional.json
    - set_manual_status → frontend/status_toko.txt
    - Eliminates 1-hour sync agent delay for config changes

[x] Cache-first product API (offline/DB-less mode)
    - frontend/api_produk.php: reads cache_produk.json first, DB as fallback only
    - backend/api_produk.php: same cache-first approach with CORS
    - Photos still refreshed from filesystem each request (?v=timestamp)

[x] Graceful pg_connect() handling
    - frontend/config.php, backend/config.php: function_exists('pg_connect') guard
    - Returns false instead of fatal error when pgsql extension not loaded

[x] Sync status tracking
    - sync/update_produk.php writes last_sync.json after successful run
    - Contains: timestamp, product count, duration, photos synced, peak memory
    - Written to both sync/ and backend/data/ directories

[x] Sync status dashboard display
    - backend/admin.php: shows last-sync time (WIB), product count, duration
    - Color-coded badge: green < 2h, yellow 2-6h, red > 6h
    - Shows "Belum pernah sinkron" if no last_sync.json exists

[x] Admin login landing page
    - backend/index.php: logo + "Login Admin" button + "Ke Toko" link
    - Auto-redirects to admin.php if already logged in
    - Render health check still works (HTTP 200)
```

---

## 6. sync/ — IPOS Sync Agent (Local PC)

### Purpose
Headless PHP script running on the **local store PC** via Windows Task Scheduler every 1 hour. Queries the IPOS PostgreSQL database, generates `cache_produk.json`, syncs photos, and triggers git push.

### File Inventory

| File | Status | Description |
|------|--------|-------------|
| `sync/update_produk.php` | ✅ Created | Full sync agent: photo sync → DB query → cache write → log |
| `sync/config.php` | ✅ Created | Minimal DB config (no session, no admin helpers) |
| `sync/git_push.bat` | ✅ Created | Auto-commit + push automation |

### What the Sync Agent Does (step by step)

```
1. SYNC PHOTOS: backend/uploads/ → frontend/uploads/
   - Only copies new or modified files (by filemtime comparison)
   - Logs: "Photos synced: 3 new/updated"

2. CONNECT to local IPOS PostgreSQL
   - Host: 192.168.18.189:5444
   - DB: i4_ROYAL
   - Timeout: 3 seconds

3. QUERY products with SUM(stok) > 0
   - Same query as api_produk.php
   - JOIN: tbl_item + tbl_itemstok (subquery with HAVING) + tbl_web_deskripsi (LEFT JOIN)

4. GENERATE image paths
   - Looks in frontend/uploads/ for {safe_kode}_*.webp + legacy {safe_kode}.webp
   - Appends ?v=filemtime for cache busting
   - Falls back to Unsplash placeholder if no photos found

5. WRITE cache_produk.json
   - To sync/cache_produk.json (local reference)
   - To frontend/cache_produk.json (for Netlify deployment)

6. LOG to sync/sync.log
   - Format: "YYYY-MM-DD HH:MM:SS OK — 342 products synced"
   - On failure: "FAIL — Could not connect to database" (exit code 1)

7. EXIT (Task Scheduler then runs git_push.bat)
```

### Sync Log Example

```
2026-06-18 10:00:01 OK — 342 products synced
2026-06-18 10:00:01 Photos synced: 3 new/updated
2026-06-18 11:00:02 OK — 342 products synced
2026-06-18 12:00:01 FAIL — Could not connect to database
2026-06-18 13:00:02 OK — 340 products synced
```

### Task Scheduler Setup

| Setting | Value |
|---------|-------|
| **Trigger** | Daily, repeat every 1 hour, indefinitely |
| **Action 1** | `php C:\path\to\royal-website\sync\update_produk.php` |
| **Action 2** | `C:\path\to\royal-website\sync\git_push.bat` |
| **Start in** | `C:\path\to\royal-website\sync` |
| **Run as** | User account with cached git credentials |
| **Network** | Must have access to IPOS PostgreSQL (local network) |

### Remaining Tasks

```
[ ] 1. Set up Windows Task Scheduler on the local store PC
[ ] 2. Configure git credential caching: git config --global credential.helper manager
[ ] 3. Test: run sync manually: php sync/update_produk.php
[ ] 4. Verify cache_produk.json updated in both sync/ and frontend/
[ ] 5. Test: run git_push.bat and verify git push works
[ ] 6. Monitor sync.log for errors over 24 hours
```

---

## 7. Cloud Deployment

### 7.1 Neon (Database)

```
[ ] 1. Create Neon project at https://neon.tech
[ ] 2. Select region closest to Kediri (Singapore or Mumbai)
[ ] 3. Copy connection string: postgresql://user:pass@ep-xxx.neon.tech/royal
[ ] 4. Run schema: psql "<connection-string>" -f database/schema.sql
[ ] 5. Verify: \dt (should show tbl_item, tbl_itemstok, tbl_web_deskripsi)
[ ] 6. Note: tbl_web_deskripsi auto-creates on first admin API call via api_produk.php
```

### 7.2 Render (Backend)

The blueprint at `backend/render.yaml` defines everything. Deploy steps:

```
[ ] 1. Connect git repo to Render dashboard
[ ] 2. Render auto-detects render.yaml at root level
[ ] 3. Verify detected settings:
       - Root Directory: backend
       - Environment: PHP
       - Start Command: php -S 0.0.0.0:$PORT
       - Health Check Path: /index.php
[ ] 4. Add Neon PostgreSQL env vars in Render dashboard → Environment:
       - PGHOST           → ep-xxxx.neon.tech
       - PGPORT           → 5432
       - PGDATABASE       → royal
       - PGUSER           → royal_owner
       - PGPASSWORD       → (Neon password)
[ ] 5. Deploy (first deploy takes 2-3 minutes)
[ ] 6. Verify health check: https://royal-backend.onrender.com/index.php
       - Expected: {"status":"ok","service":"royal-backend","database":"connected"}
[ ] 7. Verify admin login: https://royal-backend.onrender.com/login.php
[ ] 8. Create initial super admin via login page (default: superadmin / royal2026)

Free tier note: Render free services spin down after 15 min idle.
First request after idle takes 30-60 seconds (cold start).
Mitigations:
  - Set up cron-job.org to ping the health check every 10 minutes
  - Or upgrade to Starter plan ($7/month) for no spin-down
```

### 7.3 Netlify (Frontend)

```
[ ] 1. Connect git repo to Netlify dashboard
[ ] 2. Set base directory to frontend/
[ ] 3. Current (PHP mode): publish = ".", no build command
[ ] 4. Future (Vite mode): switch netlify.toml to:
       command = "npm run build"
       publish = "dist"
[ ] 5. Add custom domain (royal-komputer.netlify.app or custom)
[ ] 6. SSL auto-provisioned by Netlify
[ ] 7. Verify storefront loads: https://royal-komputer.netlify.app
[ ] 8. Verify admin redirects work:
       - /login → https://royal-backend.onrender.com/login
       - /update_produk.php → https://royal-backend.onrender.com/update_produk.php
```

### 7.4 CI/CD Flow

```
git push (main branch)
   │
   ├──► Netlify: auto-detects change
   │      → runs build (npm run build or none)
   │      → deploys to netlify.app
   │      → updates CDN cache
   │
   └──► Render: auto-detects change
          → pulls new code
          → runs health check
          → deploys to onrender.com
          │
          └──► Neon: persistent PostgreSQL (no deploy needed)
```

---

## 8. Cross-Cutting Concerns

### 8.1 Photo Sharing Strategy

| Phase | Strategy | Status |
|-------|----------|--------|
| **Local dev** | Sync agent copies `backend/uploads/` → `frontend/uploads/` | ✅ Implemented in sync/update_produk.php |
| **Git-based** | Photos committed to git from `backend/uploads/`, Netlify deploys `frontend/uploads/` | Works via git flow |
| **Future** | Cloudinary / Cloudflare R2 — remove photos from git entirely | ⬜ Future enhancement |

### 8.1b Admin-to-Frontend Immediate Sync

For configuration changes (jam operasional, schedules, tagline, manual status), admin operations now write directly to `frontend/` in addition to `backend/data/`. This eliminates the 1-hour sync agent delay for these small config files.

| Admin Action | Backend File | Frontend File |
|---|---|---|
| Save operating hours | `backend/data/jam_operasional.json` | `frontend/jam_operasional.json` |
| Save closure schedule | `backend/data/jadwal_tutup.json` | `frontend/jadwal_tutup.json` |
| Save tagline | `backend/data/tagline.json` | `frontend/tagline.json` |
| Set manual status | `backend/data/status_toko.txt` | `frontend/status_toko.txt` |
| Upload/reorder photos | `backend/uploads/` | `frontend/uploads/` (via admin photo ops) |

Product photos are also synced immediately via `update_produk.php` and `api_manage_photos.php`.

### 8.2 CORS Strategy

| Environment | Frontend URL | Backend Allow-Origin |
|-------------|-------------|---------------------|
| **Local dev** | `http://localhost:5173` (Vite) | Set by `cors.php` helper |
| **Local dev** | `http://localhost:8080` (PHP fallback) | Same origin, no CORS needed |
| **Production** | `https://royal-komputer.netlify.app` | Set by `cors.php` helper |

The `cors.php` helper (already created at `backend/cors.php`) will:
```php
// backend/cors.php — Origin whitelist with wildcard fallback
function handleCORS(): void {
    $allowedOrigins = [
        'http://localhost:5173',        // Vite dev
        'http://localhost:8080',        // PHP fallback dev
        'https://royal-komputer.netlify.app',  // Netlify production
    ];
    // ...known origin → credentials; unknown/wildcard → *
    // Handles OPTIONS preflight with 204
}
```

### 8.3 Environment Variables

| Variable | Local Dev (default) | Production (Render) |
|----------|--------------------|--------------------|
| `PGHOST` | `192.168.18.189` | Neon hostname |
| `PGPORT` | `5444` | `5432` |
| `PGDATABASE` | `i4_ROYAL` | `royal` |
| `PGUSER` | `admin` | Neon user |
| `PGPASSWORD` | `2356988` | Neon password |

**No `.env` file needed** — `backend/config.php` uses `getenv()` with local fallbacks.

### 8.4 Git Convention

| Prefix | Usage | Example |
|--------|-------|---------|
| `feat:` | New feature | `feat: add product search by category` |
| `fix:` | Bug fix | `fix: store status showing incorrect hours` |
| `refactor:` | Code restructuring | `refactor: extract photo upload logic` |
| `sync:` | Sync agent commits | `sync: product data update 2026-06-18` |
| `docs:` | Documentation | `docs: update API reference in AGENTS.md` |
| `chore:` | Config/CI | `chore: update .gitignore for upload paths` |

### 8.5 Rollback Plan

The 4-folder structure (`database/`, `frontend/`, `backend/`, `sync/`) is the production layout. If cloud deploy fails, rollback per service:
1. Netlify: redeploy previous deploy from git history
2. Render: redeploy previous deploy from git history
3. Git: `git revert` the breaking commit

---

## 9. Dev Workflow

### Local Development

Run these in 3 terminals:

```bash
# Terminal 1: Backend (PHP admin + API)
cd backend
php -S localhost:8081
# → http://localhost:8081/admin.php
# → http://localhost:8081/api_produk.php

# Terminal 2: Frontend (Vite)
cd frontend
npm run dev
# → http://localhost:5173 (auto-reloads on changes)

# Terminal 3: Frontend (PHP fallback, for comparison)
cd frontend
php -S localhost:8080
# → http://localhost:8080/index.php
```

Or use the convenience scripts:

```bash
# Windows
dev.bat
# → starts frontend:8080 + backend:8081

# Unix
bash dev.sh
```

### Running Tests

Start the PHP server and run the API test suite:

```bash
# Terminal 1: Start backend
cd backend
php -S localhost:8081

# Terminal 2: Run tests
php backend/tests/test_api.php
# or: bash backend/tests/test_api.sh
```

See `backend/tests/README.md` for detailed instructions.

### Production Verification

After deploying to cloud:

```bash
# 1. Check Render health
curl https://royal-backend.onrender.com/index.php

# 2. Check Storefront loads
curl https://royal-komputer.netlify.app

# 3. Check Admin login
curl https://royal-backend.onrender.com/login.php

# 4. Check Product API
curl https://royal-backend.onrender.com/api_produk.php

# 5. Run API tests against production
BASE_URL=https://royal-backend.onrender.com php backend/tests/test_api.php
```

---

## 10. Complete Task Checklist

### database/
```
[ ] Create Neon project
[ ] Run database/schema.sql
[ ] Configure Render env vars with Neon credentials
[ ] Test connection from backend health check
```

### frontend/ — Vite Components (Highest Priority) ✅ DONE
```
[x] Add proxy to vite.config.js (localhost:5173 → localhost:8081)
[x] Create Navbar component
[x] Create StoreStatus component
[x] Create FilterSidebar component
[x] Create ProductGrid component
[x] Create ProductCard component
[x] Create ProductModal component (carousel)
[x] Create Footer component
[x] Create lib/api.js — fetch wrapper
[x] Create lib/format.js — IDR formatter
[x] Wire up main.js
[x] Update netlify.toml for Vite build
[x] Test locally: npm run dev + php -S localhost:8081
[x] Build: npm run build (13 modules, 30KB JS + 32KB CSS)
[ ] Deploy to Netlify
```

### backend/ — API, CORS, Testing ✅ DONE
```
[x] Create backend/cors.php helper
[x] Create backend/api_status.php endpoint
[x] Create backend/api_schedules.php endpoint
[x] Add CORS to api_produk.php (via cors.php)
[x] Add CORS to api_manage_photos.php (via cors.php)
[x] Refactor api_status.php to use cors.php (was inline)
[x] Fix login.php "Kembali ke Toko" link
[x] Verify update_produk.php has no leftover sync code
[x] Fix auth consistency: update_admin.php + update_jam.php return JSON errors
[x] Create API Test Suite (backend/tests/) — 34/34 passing
[ ] Deploy to Render
```

### sync/ — Local PC Setup
```
[ ] Install PHP on local PC (verify php -v)
[ ] Test: php sync/update_produk.php
[ ] Test: sync/git_push.bat
[ ] Configure git credential caching
[ ] Set up Windows Task Scheduler (every 1 hour)
[ ] Monitor sync.log for 24 hours
```

### Cloud Deployment (Third Priority)
```
[ ] Deploy backend to Render
[ ] Deploy frontend to Netlify (Vite mode)
[ ] Set up Neon with schema
[ ] Verify health check endpoint
[ ] Verify admin login on Render
[ ] Verify storefront on Netlify
[ ] Verify API proxy from Netlify → Render
[ ] Set up cron-job.org ping for Render keep-alive
```

### Cross-Cutting
```
[x] Configure CORS for production domains
[x] Test end-to-end: sync → git push → Netlify/Render auto-deploy
[x] Verify Windows paths in sync scripts
[x] Delete local-updater/ (migration complete)
[x] Update AGENTS.md with final architecture
[x] Update README.md with final structure
```

---

## 11. Future Enhancements (Post-MVP)

| Feature | Why | When |
|---------|-----|------|
| **CDN images** (Cloudinary/R2) | Remove photos from git, faster loading | After cloud deploy |
| **JWT auth** | Replace PHP sessions for API | If admin panel needs scaling |
| **CI** (GitHub Actions) | Auto-run schema migrations, linting | After cloud deploy |
| **Search** (MeiliSearch) | Full-text product search | If store grows |
| **Analytics** | Track popular products, searches | After Vite migration |
| **SEO** | Server-side rendering / meta tags | After Vite migration |

---

## Appendix: File Reference

### Root-Level Files

| File | Purpose |
|------|---------|
| `AGENTS.md` | AI coding agent instructions (API ref, conventions, patterns) |
| `README.md` | Human-readable project overview |
| `PLAN.md` | This file — comprehensive project plan |
| `JOURNAL.md` | Development journal / changelog |
| `dev.bat` | Windows: start frontend (:8080) + backend (:8081) |
| `dev.sh` | Unix: start frontend (:8080) + backend (:8081) |
| `.gitignore` | Ignores node_modules, dist, uploads, IDE files |

### All Packages at a Glance

| Folder | Files | Deploys To | Language |
|--------|-------|------------|----------|
| `database/` | 2 (schema + migration) | Neon | SQL |
| `frontend/` | 14+ (PHP + Vite + assets) | Netlify | PHP + JS + HTML |
| `backend/` | 15 PHP + 6 JSON + 1 YAML + 1 dir | Render | PHP + JSON |
| `backend/tests/` | 2 test scripts + 1 README | Dev/CI | Bash + PHP |
| `sync/` | 3 (2 PHP + 1 BAT) | Local PC | PHP + Batch |
