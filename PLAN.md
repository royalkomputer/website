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
| `frontend/` — Vite scaffolded | ✅ Complete (placeholder only) |
| `frontend/` — Components built (JS) | ⬜ Not started |
| `backend/` — All admin files migrated | ✅ Complete |
| `backend/` — config with data/ paths + env vars | ✅ Complete |
| `backend/` — render.yaml blueprint | ✅ Complete |
| `backend/` — health check endpoint | ✅ Complete |
| `backend/` — CORS helper | ⬜ Not started |
| `backend/` — New API endpoints (status, schedules) | ⬜ Not started |
| `sync/` — Headless sync agent | ✅ Complete |
| `sync/` — git_push.bat | ✅ Complete |
| `frontend/netlify.toml` — Deploy config | ✅ Complete (PHP mode) |
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
│   ├── main.js             # Placeholder: renders "Memuat..."
│   └── style.css           # Tailwind imports + astra color theme
├── config.php              # DB config (for PHP fallback)
├── api_produk.php          # Product API (for PHP fallback)
├── cache_produk.json       # Product cache (written by sync agent)
├── jam_operasional.json    # Operating hours (for PHP fallback)
├── jadwal_tutup.json       # Closure schedules
├── status_toko.txt         # Manual store status
├── dist/                   # Vite build output (gitignored)
│   └── index.html
├── uploads/                # Product photos (synced from backend)
├── logo/                   # Brand assets
└── node_modules/           # npm dependencies (gitignored)
```

### Vite Scaffold Status

| Item | Status | Details |
|------|--------|---------|
| `package.json` | ✅ Done | vite, tailwindcss, @tailwindcss/vite |
| `vite.config.js` | ✅ Done | Basic Vite + Tailwind config |
| `index.html` | ✅ Done | Font Awesome, Google Fonts, favicon |
| `src/style.css` | ✅ Done | `@import "tailwindcss"` + astra palette |
| `src/main.js` | ⚡ Placeholder | Just renders "Memuat..." |
| Components | ⬜ Not started | Navbar, ProductGrid, Modal, etc. |
| `vite.config.js` proxy | ⬜ Not started | Need proxy to backend PHP |
| `netlify.toml` Vite mode | ⬜ Not started | Currently in PHP mode |

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
[ ] 1. Add proxy to vite.config.js: /api/* → http://localhost:8081
[ ] 2. Create Navbar component (logo, search, social icons)
[ ] 3. Create StoreStatus component (fetch status)
[ ] 4. Create FilterSidebar component (category, condition, sort)
[ ] 5. Create ProductGrid + ProductCard components
[ ] 6. Create ProductModal component (carousel)
[ ] 7. Create Footer component (hours, social, copyright)
[ ] 8. Create lib/api.js — fetch wrapper for produk + status + schedules
[ ] 9. Create lib/format.js — IDR currency formatter
[ ] 10. Wire up main.js with all components
[ ] 11. Update netlify.toml: command="npm run build", publish="dist"
[ ] 12. Test: npm run dev (Vite) + php -S localhost:8081 (backend)
[ ] 13. Build: npm run build → verify dist/
[ ] 14. Deploy to Netlify
```

### Fallback Strategy

The PHP `index.php` at `frontend/` continues to work as a Netlify static PHP site until Vite is ready. Switch Netlify from PHP mode to Vite mode by updating `netlify.toml`:

```toml
# Before (PHP mode): works now
[build]
  publish = "."

# After (Vite mode): when ready
[build]
  command = "npm run build"
  publish = "dist"
```

---

## 5. backend/ — Admin Panel + API (PHP)

### Purpose
Admin dashboard, login, product management, operating hours, closure schedules. Deployed to **Render** as a PHP web service. Serves as the API layer for the Vite frontend.

### Current File Inventory

| File | Status | Auth | Description |
|------|--------|------|-------------|
| `backend/admin.php` | ✅ Migrated | Session | Admin dashboard HTML+JS |
| `backend/login.php` | ✅ Migrated | None | Login form + authentication |
| `backend/logout.php` | ✅ Migrated | None | Session destroy + redirect |
| `backend/config.php` | ✅ Rewritten | Varies | DB + JSON helpers, env var support |
| `backend/index.php` | ✅ Created | None | Health check endpoint (JSON) |
| `backend/update_produk.php` | ✅ Migrated | Session | Product description edit + photo upload |
| `backend/update_admin.php` | ✅ Migrated | Session | Admin CRUD + schedules + status |
| `backend/update_jam.php` | ✅ Migrated | Super Admin | Operating hours configuration |
| `backend/api_produk.php` | ✅ Migrated (+fix) | Public | Product JSON API (cache path fixed) |
| `backend/api_manage_photos.php` | ✅ Migrated | Session | Photo delete + reorder |
| `backend/render.yaml` | ✅ Created | — | Render blueprint config |
| `backend/data/admins.json` | ✅ Migrated | — | Admin accounts (bcrypt hashed) |
| `backend/data/jam_operasional.json` | ✅ Migrated | — | Per-day operating hours |
| `backend/data/jadwal_tutup.json` | ✅ Migrated | — | Closure schedules |
| `backend/data/status_toko.txt` | ✅ Migrated | — | Manual store status override |
| `backend/data/cache_produk.json` | ✅ Migrated | — | Product cache |
| `backend/uploads/` | ✅ Migrated | — | Product photos (WEBP) |
| `backend/logo/` | ✅ Migrated | — | Brand assets (logo.webp) |

### Key Decisions in `config.php`

| Feature | Implementation |
|---------|---------------|
| Cloud DB | `getenv('PGHOST') ?: '192.168.18.189'` — works locally with no env vars |
| Data paths | All constants point to `__DIR__ . '/data/'` subdirectory |
| Persistent storage | Render disk mounts at `backend/data/` for JSON file persistence |
| Session | `session_start()` at top of config.php for all pages |

### Completed

```
[x] Create backend/api_status.php — Store status endpoint
    - Returns: { isOpen, isTemporarilyClosed, hours, upcomingSchedule, nextOpenDay, nextOpenTime, closeTime, timestamp }
    - Same logic as the inline PHP in frontend/index.php
    - Public (no auth) — consumed by Vite frontend
    - CORS: Allow-Origin: *, handles OPTIONS preflight

[x] Add Vite proxy rules for JSON/text files
    - /jam_operasional.json → localhost:8081
    - /jadwal_tutup.json → localhost:8081
    - /status_toko.txt → localhost:8081
```

### Backlog (Still Needed)

```
[ ] 1. Create backend/cors.php — CORS helper
       - Allow localhost:5173 (Vite dev) + Netlify domain (prod)
       - Include in: api_produk.php, api_manage_photos.php,
         update_produk.php, update_admin.php, update_jam.php

[ ] 2. Add CORS headers to existing API files
       - api_produk.php already has Access-Control-Allow-Origin: *
       - api_manage_photos.php needs it
       - update_produk.php (for admin panel, maybe not needed)
       - update_admin.php (for admin panel, maybe not needed)
       - update_jam.php (for admin panel, maybe not needed)

[ ] 3. Create backend/api_schedules.php — Schedules endpoint
       - Returns: array of closure schedules
       - Public (no auth) — consumed by Vite frontend

[ ] 4. Refactor update_produk.php — strip sync logic
       - Keep: photo upload, description edit (session-protected)
       - (Note: sync logic was already extracted to sync/update_produk.php,
         but the old code may still linger in this file — verify)

[ ] 5. Fix login.php "Kembali ke Toko" link
       - Currently links to index.php (doesn't exist in backend/)
       - Should link to Netlify URL or ../frontend/index.php
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

### 8.2 CORS Strategy

| Environment | Frontend URL | Backend Allow-Origin |
|-------------|-------------|---------------------|
| **Local dev** | `http://localhost:5173` (Vite) | Set by `cors.php` helper |
| **Local dev** | `http://localhost:8080` (PHP fallback) | Same origin, no CORS needed |
| **Production** | `https://royal-komputer.netlify.app` | Set by `cors.php` helper |

The `cors.php` helper (to be created) will:
```php
header("Access-Control-Allow-Origin: https://royal-komputer.netlify.app");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
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

### frontend/ — Vite Components (Highest Priority)
```
[ ] Add proxy to vite.config.js (localhost:5173 → localhost:8081)
[ ] Create Navbar component
[ ] Create StoreStatus component
[ ] Create FilterSidebar component
[ ] Create ProductGrid component
[ ] Create ProductCard component
[ ] Create ProductModal component (carousel)
[ ] Create Footer component
[ ] Create lib/api.js — fetch wrapper
[ ] Create lib/format.js — IDR formatter
[ ] Wire up main.js
[ ] Update netlify.toml for Vite build
[ ] Test locally: npm run dev + php -S localhost:8081
[ ] Build: npm run build
[ ] Deploy to Netlify
```

### backend/ — API & CORS (Second Priority)
```
[ ] Create backend/cors.php helper
[ ] Create backend/api_status.php endpoint
[ ] Create backend/api_schedules.php endpoint
[ ] Add CORS to api_produk.php (already has * — update for prod)
[ ] Add CORS to api_manage_photos.php
[ ] Fix login.php "Kembali ke Toko" link
[ ] Verify update_produk.php has no leftover sync code
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
[ ] Deploy frontend to Netlify (PHP mode first)
[ ] Set up Neon with schema
[ ] Verify health check endpoint
[ ] Verify admin login on Render
[ ] Verify storefront on Netlify
[ ] Verify API proxy from Netlify → Render
[ ] Set up cron-job.org ping for Render keep-alive
```

### Cross-Cutting
```
[ ] Configure CORS for production domains
[ ] Test end-to-end: sync → git push → Netlify/Render auto-deploy
[ ] Verify Windows paths in sync scripts
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
| `backend/` | 11 PHP + 5 JSON + 1 YAML | Render | PHP + JSON |
| `sync/` | 3 (2 PHP + 1 BAT) | Local PC | PHP + Batch |
