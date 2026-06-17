# Development Journal

## v3.0 — Monorepo Migration + Vite Frontend

### Architecture Overhaul

Migrated from a monolithic `local-updater/` directory to a 4-folder monorepo:

| Folder | Deploys To | Purpose |
|--------|------------|---------|
| `database/` | Neon | PostgreSQL schema + migrations |
| `frontend/` | Netlify | Vite storefront + PHP fallback |
| `backend/` | Render | Admin panel + API layer |
| `sync/` | Local PC | IPOS sync agent (Task Scheduler) |

### Phase 1 — Vite Frontend (Complete)

- Scaffolded Vite 6 + Tailwind CSS v4 in `frontend/`
- Built 7 vanilla JS components: Navbar, StoreStatus, FilterSidebar, ProductCard, ProductGrid, ProductModal, Footer
- Created `lib/api.js` with fetch wrappers + fallback chains, `lib/format.js` with IDR formatter + helpers
- Wired up `main.js` with app state, data loading, filtering, sorting, event binding
- Added Vite dev proxy (`:5173` → `:8081`) for API calls + static files
- Updated `netlify.toml` for Vite build mode (`npm run build`, publish `dist/`)
- Added explicit Netlify redirects for all 7 data/API paths before SPA fallback

### Phase 2 — Backend Admin Migration (Complete)

- Migrated all admin PHP files from `local-updater/` to `backend/`
- Rewrote `backend/config.php` — all paths point to `data/` subdirectory, `getenv()` fallbacks for Neon
- Created `backend/api_status.php` — public store status endpoint (open/closed/schedules)
- Created `backend/index.php` — health check endpoint
- Created `backend/render.yaml` — Render blueprint with Neon env vars + persistent disk
- Added Vite proxy rules for JSON/text files used by JS fallback

### Phase 3 — Sync Agent (Complete)

- Created `sync/config.php` — minimal DB config (no session, no admin helpers)
- Created `sync/update_produk.php` — headless sync agent: photo sync → DB query → cache write → log
- Created `sync/git_push.bat` — auto-commit + push
- Syncs photos from `backend/uploads/` → `frontend/uploads/`
- Writes `cache_produk.json` to `sync/`, `frontend/`, and `backend/data/`

### Phase 4 — Neon Database Schema (Ready)

- `database/schema.sql` with DDL for `tbl_item`, `tbl_itemstok`, `tbl_web_deskripsi`
- `database/migrations/001_initial.sql` — migration tracking placeholder

### Production Bug Fixes

During review, identified and fixed a critical production issue:

1. **Empty DB fallback** — On Render, `api_produk.php` connects to Neon (valid DB) but `tbl_item`/`tbl_itemstok` have no IPOS data. Fixed: both `backend/api_produk.php` and `frontend/api_produk.php` now fall back to `cache_produk.json` when query returns 0 results.

2. **Cache coverage** — Sync agent now writes cache to all 3 locations: `sync/`, `frontend/`, and `backend/data/` (was missing `backend/data/`).

### Cleanup

- Deleted `local-updater/` — migration complete, no legacy directory remains
- Updated all docs (AGENTS.md, README.md, PLAN.md) to remove stale references

### Key Technical Decisions

- **No JavaScript framework** — vanilla JS with Vite bundler, no React/Vue overhead
- **API fallback chain** — live API → `cache_produk.json` → graceful degradation
- **Store status** calculated server-side via `api_status.php`, with client-side JSON file fallback
- **Event delegation** on product grid for performance
- **CORS** handled per-endpoint with `Access-Control-Allow-Origin: *` + OPTIONS preflight
- **4-folder monorepo** — each folder is self-contained, maps 1:1 to deployment target

### Next Steps

- Deploy backend to Render (connect git repo, add Neon env vars)
- Deploy frontend to Netlify (connect git repo, set base directory to `frontend/`)
- Set up Windows Task Scheduler on local store PC for sync agent
- Configure git credential caching on local PC

---

## v2.2 — Previous

- Product catalog with search, category filter, condition filter, price sort
- Real-time store status (operating hours, manual override, scheduled closures)
- Admin dashboard with catalog management, photo upload/reorder/delete
- Multi-role admin system (super admin, admin)
- Operating hours configuration (per-day)
- Temporary closure scheduling
- Photo auto-conversion to WEBP on upload
- PostgreSQL product database with JSON cache fallback
- Mobile-responsive UI with Tailwind CSS
- WhatsApp order integration
- Session-based authentication with bcrypt password hashing
- Image carousel in product detail modal
- Indonesian language UI, WIB timezone, IDR currency

### Notable technical decisions
- Flat PHP architecture (no framework) for simplicity
- File-based JSON storage for config, database for products
- WEBP-only image format for consistent performance
- Session auth with role-based access control

## v1.0 — Initial Release

- Basic product listing page
- Admin login and simple product management
- Core PostgreSQL integration
