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
