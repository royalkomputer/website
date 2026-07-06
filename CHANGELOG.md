# Changelog

## 2026-07-06

### Fixed: Admin UI database connection error
- `backend/api_produk.php`: Added `?admin=1` parameter to return raw cache array (bypasses pagination & DB fallback)
- `backend/admin.php`: Changed JS fetch from `api_produk.php` to `api_produk.php?admin=1`, removed `if(data.error)` check
- **Root cause:** Admin JS called `.filter()` on paginated object `{data:[],total:...}` instead of array, causing `TypeError`

### Removed: Playlist Banner feature
- `backend/admin.php`: Removed banner tab button, panel HTML (~107 baris), and all JS functions (~400 baris)
- `frontend/index.php`: Removed banner CSS (`.banner-hiding`), HTML container (`#banner-playlists`), JS functions (`loadBanners`, `hideBanner`, `escAttr`), and all banner references
- `frontend/api_banner.php`: Deleted
- `frontend/data/banners.json`: Deleted
- `backend/update_banner.php`: Deleted (no longer used)

### Removed: Category count badges from filter buttons
- `frontend/index.php`: Removed category count from filter button labels and associated JS computation

### Changed: Product card colors for light/dark mode
- `frontend/index.php`: Card background changed from `bg-black` to `bg-slate-50 dark:bg-slate-900` (both grid and detail views)
- Adjusted border and text colors for consistency across themes

### Fixed: Remnant banner JS references
- `frontend/index.php`: Removed `hideBanner()` call in `handleCondition()` (line 655) — caused `ReferenceError` on condition filter click
- `frontend/index.php`: Simplified `hideSearchPrompt()` — removed `banner-hiding` class references
- `frontend/index.php`: Removed `banner-hiding` class removal in reset function

### Changed: Git config for local pc
- Local git repo configured to commit as `royalkomputer <royalkomputer@royalkomputer.com>`
