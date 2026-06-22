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
