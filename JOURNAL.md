# Dev Journal

## 2026-06-19 — PHPUnit Test Suite & Sync Improvements

### Sync Agent Overhaul
- Rewrote `sync/update_produk.php` with full debug logging
- Each step logs to `sync/sync.log`: PHP version, memory, photo sync details, DB connection, query timing, product processing stats (category distribution, image counts), cache file sizes, peak memory
- Added structured console output with section headers and summary box
- Created `sync/setup_env.ps1` for PHP PATH setup on Windows

### PHP Installation (Dev Machine)
- Installed PHP 8.4.22 via winget
- Enabled `pgsql`, `pdo_pgsql`, `mbstring` extensions
- Added PHP to User PATH
- Installed PCOV 1.0.12 for code coverage

### PHPUnit Test Suite
- Installed PHPUnit 12.5.30 (PHAR)
- Created `tests/` directory with 7 test files:
  - **ConfigTest** (25 tests) — `loadAdmins`, `saveAdmins`, `findAdminByUsername`, `findAdminById`, `generateAdminId`, `loadJamOperasional`, `loadSchedules`, `saveSchedules`, `getCurrentAdmin`, `isSuperAdmin`, `requireLogin`, `getDBConnection`
  - **FrontendConfigTest** (4 tests) — operating hours and schedule roundtrips
  - **CorsTest** (10 tests) — live `handleCORS()` calls + origin matching logic
  - **StoreStatusTest** (16 tests) — manual override, schedule check, hours check, priority order, next opening time
  - **AdminCrudTest** (24 tests) — validation rules, permissions, uniqueness constraints, super admin safeguards
  - **PhotoManagementTest** (17 tests) — safe kode, file naming, realpath security, reorder/delete logic, photo sync
  - **SyncAgentTest** (18 tests) — photo sync, cache writing, JSON structure, logging, directory creation
- **133 tests, 230 assertions — all passing**
- **Coverage results:**
  - `backend/config.php`: **92.37%** (121/131 lines, only top-level boilerplate uncovered)
  - `backend/cors.php`: **96.00%** (48/50 lines, only OPTIONS preflight exit uncovered)
  - 10 uncovered lines in config.php are `session_start()` and `define()` calls (execute before PCOV starts recording)
  - 2 uncovered lines in cors.php are the OPTIONS `exit` (can't be tested)

### Git
- Committed `c5d9a42` — pushed to `origin/main`

## 2026-06-22 — Admin UI Improvements & New Features

### Scroll Preservation
- `backend/admin.php`: `fetchProducts()` now saves `window.scrollY` before re-render and restores it via `requestAnimationFrame` — admin stays at the same scroll position after saving product edits/photo uploads

### Centered Confirmation Modal
- `backend/admin.php`: Replaced all `confirm()` dialog calls with a centered modal (`showConfirmModal()`) featuring backdrop blur, red warning icon, fade-in animation, and backdrop-click-to-close — applied to:
  - Hapus foto produk (`deleteSavedPhoto`)
  - Hapus akun admin (`hapusAdmin`)
  - Hapus jadwal tutup (`deleteSchedule`)

### Closure Schedule Sync to UI
- `backend/api_status.php`: Moved effective close time logic OUTSIDE the response array (was invalid PHP), added `$effective_close` calculation that checks if a closure schedule starts today before normal closing time — adjusts `closeTime` shown in "Buka Sekarang (Tutup ... WIB)"
- `frontend/src/lib/api.js`: Mirror logic in `calculateStatusFromFiles()` fallback

### "Libur" Day for Operating Hours
- `backend/data/jam_operasional.json` + `frontend/jam_operasional.json`: Added `"libur": false` to all days
- `backend/config.php`: Added `libur: false` to defaults, `loadTagline()`/`saveTagline()` helpers
- `backend/update_jam.php`: Accepts `libur_Monday` checkbox — when checked, clears buka/tutup and sets `libur: true`
- `backend/admin.php`: Admin UI — each day row has a "Libur" checkbox that disables time inputs, plus `toggleLibur()` JS function
- `backend/api_status.php`, `frontend/src/lib/api.js`, `frontend/index.php`: Status checks skip libur days; next-open-day search skips libur days; `closeTime` empty when libur
- `frontend/src/components/Footer.js`, `frontend/index.php` footer: Shows "Libur" (red text) instead of time range for closed days

### Editable Tagline
- `backend/data/tagline.json` + `frontend/tagline.json`: New file storing the store tagline text
- `backend/config.php`: `TAGLINE_FILE` constant, `loadTagline()`/`saveTagline()` with frontend sync
- `backend/update_admin.php`: Added `save_tagline` and `get_tagline` actions
- `backend/api_status.php`: Added `"tagline"` field to API response
- `backend/admin.php`: Added tagline textarea + save button in Katalog panel
- `frontend/src/components/StoreStatus.js`: Uses `status.tagline` (falls back to default)
- `frontend/index.php`: Reads `frontend/tagline.json` with fallback, output via `htmlspecialchars()`

### UI Restructure — Katalog Panel
- `backend/admin.php`: Moved "Katalog Produk" heading below Tagline editor; merged heading + filter navbar into one cohesive card with border separator; removed redundant dashboard header counter

### Inline Notification Toast
- `backend/admin.php`: Replaced all `alert()` calls with a fixed notification bar (`showNotification()` / `hideNotification()`) with auto-dismiss after 4 seconds — supports success (green), error (red), and info (blue) types with icons
- Applied to: `deleteSavedPhoto`, `submitForm`, `saveTagline`, `submitSchedule`, `deleteSchedule`, `hapusAdmin`, `submitJam`, `setManualStatus` — consistent UX across all admin actions

### Simplified setManualStatus()
- `backend/admin.php`: Removed references to 4 non-existent DOM elements (`btn-set-manual-panel`, `operational-manual-status`, `operational-notif`, `manual-status` selector fallback); simplified function signature to no parameters; removed redundant UI sync code that manually updated notification badge

### Cache Invalidation for Photo Operations
- `backend/api_manage_photos.php`: Delete action now also removes from `frontend/uploads/` for immediate user-facing update; scans remaining files and rewrites `cache_produk.json` (both backend and frontend) with correct photo URLs after delete — prevents stale image references in storefront
- `backend/api_manage_photos.php`: Reorder action now updates `cache_produk.json` with the new file order — product thumbnails in admin panel and storefront reflect the new order immediately
- `backend/update_produk.php`: After photo upload, copies saved photos to `frontend/uploads/` and rewrites both cache files with correct `?v=filemtime` URLs

### Offline / DB-less Product Updates
- `backend/update_produk.php`: Now gracefully handles DB connection failure — description edits and photo uploads continue to work by writing to `cache_produk.json` when PostgreSQL is unavailable; returns `{"warning": true, ...}` to inform admin that data was saved in offline mode
- Guards all DB operations behind `$db_available` checks; still closes connection via `pg_close($conn)` when DB was reachable

### Frontend Sync for Admin Operations
- `backend/config.php`: `saveSchedules()` now copies to `frontend/jadwal_tutup.json` for immediate user-facing update
- `backend/config.php`: `saveTagline()` now copies to `frontend/tagline.json`
- `backend/update_jam.php`: After saving jam operasional, syncs to `frontend/jam_operasional.json`
- `backend/update_admin.php`: `set_manual_status` action syncs to `frontend/status_toko.txt`
- This eliminates the 1-hour sync agent delay — admin changes reflect on storefront immediately

### api_produk.php Path Fixes
- `backend/api_produk.php`, `frontend/api_produk.php`: Changed `glob("uploads/" ...)` to use absolute paths (`__DIR__ . "/uploads/"`) for consistent file resolution; image URLs now use `basename()` to strip absolute path components

### Test Suite Refactor (PHP 8.5 Compatibility)
- `backend/tests/test_api.php`: Refactored `$http_response_header` parsing to use `http_get_last_response_headers()` (PHP 8.5+ API); added `error_reporting(E_ALL & ~E_DEPRECATED)` to suppress deprecation warnings; output now shows `[7/7]` sections; removed unused `assertContentType()` helper

## 2026-06-23 — Offline-First Architecture & Admin Improvements

### Admin Login Landing Page
- `backend/index.php`: Replaced JSON health check endpoint with a landing page showing logo, "Royal Admin Panel" heading, and a "Login Admin" button linking to `login.php`
- If already logged in, redirects directly to `admin.php`
- Health check on Render still works (returns HTTP 200)

### Offline-First Product API
- `frontend/api_produk.php`, `backend/api_produk.php`: Switched to cache-first strategy — reads `cache_produk.json` as primary data source, only falls back to live DB if cache doesn't exist
- Photos are still refreshed from filesystem on every request (cachebuster `?v=timestamp`)
- System works fully without PostgreSQL connection or `php_pgsql` extension

### Graceful Extension Handling
- `frontend/config.php`, `backend/config.php`: Added `function_exists('pg_connect')` guard in `getDBConnection()` — returns `false` instead of fatal error when the PostgreSQL extension isn't loaded in Apache's PHP module

### Sync Status Tracking
- `sync/update_produk.php`: After successful sync, writes `last_sync.json` to `sync/` and `backend/data/` with timestamp, product count, duration, and photo count
- `backend/admin.php`: Added sync status bar below the dashboard header showing last sync time, product count, duration, and a color-coded badge (green < 2h, yellow 2-6h, red > 6h)

## 2026-06-23 (Session 2) — Web Sync Trigger & Full-Width UI

### Web-Triggered Sync
- `backend/trigger_sync.php`: New POST endpoint that runs `sync/update_produk.php` via `exec()` and returns JSON with full output
- `backend/admin.php`: Added "Sync Now" button in the sync status bar, opens a modal showing real-time terminal output, auto-reloads page on success

### Full-Width Desktop Layout
- `frontend/index.php`, `backend/admin.php`: Removed `max-w-7xl` and `max-w-6xl` constraints from hero, main grid, footer, and admin container — layout now uses `container`'s responsive max (1536px) or fills wider screens

### Flexible Product Grid
- `frontend/index.php`: Replaced fixed breakpoint grid (`xl:grid-cols-3` → `lg:grid-cols-3 xl:grid-cols-5` → CSS Grid `auto-fill` / `minmax(180px, 1fr)`) — adapts to any screen width, showing 5–7 products per row on desktop

## 2026-06-23 (Session 3) — UI Cramping Fix & DB Config Fixes

### Sync Config Fixes
- `sync/config.php`: Fixed IP address from `192.168.8.189` (typo, missing digit) to `192.168.18.189` to match backend config
- `sync/config.php`: Added `date_default_timezone_set('Asia/Jakarta')` so `last_sync.json` timestamps are written in WIB instead of UTC — fixes wrong sync time display in admin panel

### Product Grid & Card Layout Fixes
- `frontend/index.php`: Replaced cramped `minmax(140px, 1fr)` auto-fill grid with responsive `grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4` — wider columns, less cramped layout
- `frontend/index.php`: Added `flex-shrink-0` to WhatsApp buttons on all product cards to prevent the WA icon from being cut off/compressed in narrow cards
- `frontend/index.php`: Added `gap-1.5` to price+WA row for better spacing
- `frontend/index.php`: Added `truncate min-w-0` to price text to prevent price overflow pushing WA button out

## 2026-06-23 (Session 4) — Production Deployment & Image Fixes

### Server URLs for Production
- **Netlify (frontend):** `https://tiny-druid-60182f.netlify.app/`
- **Render (backend):** `https://royal-backend-s3ir.onrender.com/`
- **GitHub:** `https://github.com/royalkomputer/website`

### Product Photo Fix — Absolute Image URLs
- `backend/api_produk.php`: Changed image URL construction from relative paths (`uploads/BRG001_1.webp`) to absolute Render URLs (`https://royal-backend-s3ir.onrender.com/uploads/BRG001_1.webp`) — avoids relying on Netlify's broken wildcard proxy
- Both cache-first path (line 36-48) and DB-fallback path (line 126-130) updated
- 8 products with photos now return correct absolute URLs; API returns 771 products total

### Logo Fix — Exact-Path Redirect
- `frontend/netlify.toml`: Removed broken wildcard redirects (`/uploads/*`, `/logo/*`) — Netlify doesn't support wildcard → external URL with `status = 200`
- `frontend/netlify.toml`: Added exact-path redirect `/logo/logo.webp → https://royal-backend-s3ir.onrender.com/logo/logo.webp` (wildcards work for admin paths with `force = true`)
- Logo removed from `frontend/public/logo/` (no static file to shadow redirect); served entirely from Render via Netlify proxy

### Storefront URL Consistency
- `backend/login.php:84`: "Kembali ke Toko" link changed from hardcoded `royal-komputer.netlify.app` (404) to `getenv('STOREFRONT_URL') ?: 'https://tiny-druid-60182f.netlify.app'`
- `backend/index.php:59`: Fallback URL updated to active Netlify domain
- `backend/cors.php:28`: Added `tiny-druid-60182f.netlify.app` to allowed origins (kept old URL for backward compat)

### Windows Task Scheduler for Auto-Sync
- `sync/sync_and_push.bat`: New wrapper — runs `php update_produk.php --once` then `git_push.bat`
- `sync/setup_scheduler.ps1`: Creates "RoyalKomputer Sync" task running every 1 hour as current user (for Git/SSH access)
- Uses full PHP path `C:\xampp\php\php.exe` (not in SYSTEM PATH)
- Task registered and active (next run: 23:27)

### Key Learnings
- Netlify `_redirects` wildcards + external URL + status 200 = not supported (exact paths only)
- Netlify `netlify.toml` wildcards + external URL + `force = true` = also not supported for `/uploads/*` and `/logo/*`
- Solution: serve images via absolute Render URLs, use exact-path Netlify redirects for fixed paths like `/logo/logo.webp`
- For wildcards that must work: admin paths (`/admin/*`, `/update_*`, `/api_manage_*`) — these are only accessed by admins and `force = true` may work for some
- Netlify deploy preview URLs (`*-*-*--*.netlify.app`) may serve stale builds; use canonical URL (`*.netlify.app`)

## 2026-06-24 — Admin History Logging

### Feature: History Menu in Admin Panel
- Added `admin_history` PostgreSQL table with `migrateConfigTables()` for auto-creation
- Added `logAdminHistory()` function in `config.php` that skips logging for superadmin
- Logged all CRUD operations: product updates, admin management (tambah/edit/hapus), jam operasional, status toko, schedules, heading, tagline, product info, and sync trigger

### Files Modified
- `backend/config.php`: admin_history table, logAdminHistory() helper
- `backend/update_produk.php`: log on product description/photo update
- `backend/update_jam.php`: log on operating hours change
- `backend/trigger_sync.php`: log on product sync
- `backend/update_admin.php`: logging at 10 CRUD success points + `get_history` endpoint with pagination
- `backend/admin.php`: new History nav tab, panel with table UI, loadHistory/refreshHistory JS functions, pagination (50 per page)

### Key Details
- Superadmin actions are excluded from history per user request
- get_history endpoint uses integer-cast limit/offset for SQL injection safety
- All string parameters use pg_escape_string() in logAdminHistory()
- UI labels are in Bahasa Indonesia

## 2026-06-27 — Admin Push Fix & GitHub Auth Overhaul

### Root Cause
PHP/Apache runs as `SYSTEM` user via XAMPP — no cached git credentials, so `git push origin main` from `exec()` silently fails.

### Changes

#### `backend/push_admin.bat` — Rewritten
- Uses full git path (`C:\Program Files\Git\cmd\git.exe`) to avoid PATH issues for SYSTEM
- Reads `GIT_TOKEN` from `backend/.env` for token-based authentication
- Restores original remote URL after push (security: token is never stored in git config)
- Proper exit codes and error messages

#### `backend/config.php` — `backupToGit()` rewrite
- Added `.env` parsing for `GIT_TOKEN` (also reads `ENV_GIT_TOKEN` constant + `getenv()`)
- **3 fallback strategy on Windows:**
  1. Run `push_admin.bat` directly (succeeds if token is in `.env`)
  2. Trigger `RoyalKomputer Admin Push` scheduled task (runs as logged-in user via `schtasks /run`)
  3. `execGitPush()` with token auth (for Render/Linux cloud deployment)
- Added `triggerAdminPushTask()` helper — checks if task exists and runs it
- Added `findGitDir()` — walks up directories to find `.git` root
- Added `execGitPush()` — unified git add/commit/push logic with token auth
- `execGitPush()` now stages `backend/data/`, `backend/uploads/`, AND `frontend/data/`

#### `backend/setup_push_task.bat` — New file
- Creates scheduled task `RoyalKomputer Admin Push` using XML import
- Task runs `push_admin.bat` as the currently logged-in user (S4U logon, no password needed)
- Run once as Administrator to enable push without any token

#### `backend/admin.php` — Push panel updated
- Added instructions for two setup options: token `.env` or `setup_push_task.bat`

## 2026-06-28 — Dark Mode & Banner Reorder Arrows

### Dark Mode Toggle (Frontend)
- `frontend/index.php`: Added `darkMode: 'class'` to Tailwind config, inline theme init script in `<head>` to prevent FOUC, toggle button (sun/moon) in desktop + mobile navbar, `toggleTheme()`/`updateThemeIcon()` functions, safelist with dynamically constructed dark classes
- `frontend/index.html`: Added theme init script in `<head>`
- `frontend/src/main.js`: `toggleTheme()`, `updateThemeIcon()`, dark-mode event binding in `renderApp()`
- `frontend/src/style.css`: Added `@custom-variant dark (&:where(.dark, .dark *));`
- Theme init script runs synchronously in `<head>` before rendering; falls back to `prefers-color-scheme` if no `localStorage` key; persists choice in `localStorage.theme`

### Dark Mode Styling (All Components)
- `body`: `bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200`
- `navbar`: `bg-white dark:bg-astra-950 text-slate-800 dark:text-white`
- `hero`: `from-astra-100 via-white to-astra-50 dark:from-astra-950 dark:via-slate-900 dark:to-astra-900`
- `FilterSidebar.js`: All containers, buttons, labels, borders with `dark:` variants
- `ProductGrid.js`, `ProductCard.js`, `ProductDetailRow.js`: Cards, text, prices, badges with dark variants
- `ProductModal.js`: Modal bg, image area, info panel, close button
- `Footer.js`: All sections with dark variants
- `StoreStatus.js`: Dark gradient header, dark badges
- `index.php`: All dynamic JS functions (`updateCondUI`, `updateSortUI`, `updateCategoryButtons`, `updateViewToggleUI`, `setView`, `initViewToggle`, `openDetailModal`) use `isDark` runtime check for dynamically built class strings
- View toggle buttons: Active uses `bg-astra-700 text-white shadow-sm`; inactive uses `dark:bg-slate-600 dark:text-slate-300` for visibility against `dark:bg-slate-700` parent

### Banner Reorder Arrows (Admin)
- `backend/admin.php`: Added up/down arrow buttons on each playlist card (`movePlaylist()`) — swaps DOM position and sends `reorder_playlists` API call
- `backend/admin.php`: Added left/right arrow buttons on each photo card inside photo modal (`movePlaylistPhoto()`) — swaps indices and sends `reorder_playlist_photos` API call
- Boundary buttons (first/last) are disabled with `opacity-30 cursor-not-allowed`
- Both features coexist with existing drag-and-drop reordering
