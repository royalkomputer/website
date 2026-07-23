# Instructions for AI Coding Agents

## Project Overview

Vanilla PHP e-commerce marketplace for **Royal Komputer Kediri**, a computer hardware store in Kediri, East Java, Indonesia.

The system has a **hybrid local/cloud architecture**: a PC at the store runs `sync/` scripts to pull product data from the **IPOS** point-of-sale software and push changes to git. Cloud services (frontend, backend, database) auto-deploy from the git repo.

**Language:** PHP 8.x, vanilla JavaScript (ES6+), Tailwind CSS via CDN, Font Awesome icons.
**No frameworks, no build tools, no package manager.** Flat-file monolithic architecture.

---

## Table of Contents
1. [Deployment Architecture](#deployment-architecture)
2. [Project File Map](#project-file-map)
3. [API Reference](#api-reference)
4. [Data Model](#data-model)
5. [Authentication & Authorization](#authentication--authorization)
6. [Store Status Algorithm](#store-status-algorithm)
7. [Photo Management Flow](#photo-management-flow)
8. [Frontend Architecture](#frontend-architecture)
9. [Code Conventions](#code-conventions)
10. [Security Rules](#security-rules)
11. [Common Tasks Quick Reference](#common-tasks-quick-reference)

---

## Deployment Architecture

```
┌──────────────────────────────────────────────────────────────────────────┐
│                          LOCAL PC (Toko Kediri)                          │
│                                                                          │
│  IPOS Software ──────► sync/ (PHP) ──────► git commit & push            │
│  (PostgreSQL DB)        runs every 1 hour via Windows Task Scheduler    │
└───────────────────────────┬──────────────────────────────────────────────┘
                            │ git push
                            ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                            CLOUD (via git repo)                         │
│                                                                          │
│  ┌──────────────┐    ┌──────────────┐    ┌───────────────────────────┐  │
│  │   Frontend   │    │   Backend    │    │   Database                │  │
│  │  (Storefront)│    │  (Admin/PHP) │    │   (Cloud PostgreSQL)      │  │
│  │  Static/CDN  │    │  API layer   │    │   Serverless DB           │  │
│  └──────────────┘    └──────────────┘    └───────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
```

### Data Flow

1. **IPOS** writes product inventory to local PostgreSQL (`192.168.18.189:5444`, DB: `i4_ROYAL`)
2. **Windows Task Scheduler** runs `php sync/update_produk.php` every 1 hour
3. **sync agent** queries the local DB for products with `stock > 0`, generates `cache_produk.json`, commits & pushes to git via `sync/git_push.bat`
4. **Frontend** auto-deploys from the git repo
5. **Backend** auto-deploys from the git repo
6. **Database** serves as the cloud PostgreSQL for deployed services

---

## Project File Map

The project uses a 4-folder monorepo structure at the root level. Each folder maps to a deployment target.

| Folder | Purpose |
|--------|---------|
| `database/` | PostgreSQL schema + migrations |
| `frontend/` | Public storefront + product API |
| `backend/` | Admin dashboard + API layer |
| `sync/` | IPOS sync agent (Task Scheduler) |
| Root | | |
| `deploy.sh` | VPS setup script (manual install) |
| `INSTALL.md` | Full installation guide (Docker + manual) |
| `.env.example` | Environment variable template |

### `database/`

| File | Purpose |
|------|---------|
| `schema.sql` | Full PostgreSQL schema DDL |
| `migrations/001_initial.sql` | Incremental schema migrations |

### `frontend/`

| File | Type | Auth |
|------|------|------|
| `index.php` | Public storefront (HTML+PHP) | No |
| `api_produk.php` | Product JSON API | No (public) |
| `api_banner.php` | Banner JSON API (proxies `backend/data/banners.json`) | No (public) |
| `cache_produk.json` | Product cache fallback | — |
| `uploads/` | Symlink to `backend/uploads/` — product + banner photos | — |
| `logo/` | Brand assets | — |

### `backend/`

| File | Type | Auth |
|------|------|------|
| `admin.php` | Admin dashboard (HTML+PHP) | Session |
| `login.php` | Login page (HTML+PHP) | No |
| `logout.php` | Logout (PHP) | No |
| `config.php` | Core config & helpers | Varies |
| `update_produk.php` | Product update + photo upload API | Session |
| `update_admin.php` | Admin CRUD + schedules + status API | Session |
| `api_manage_photos.php` | Photo delete/reorder API | Session |
| `api_hutang.php` | Outstanding debt report API | Session |
| `data/admins.json` | Admin accounts (bcrypt) | — |
| `data/status_toko.txt` | Manual store status override | — |
| `data/cache_produk.json` | Product cache (written by admin) | — |
| `uploads/` | Admin photo upload target | — |

### `sync/` — Local PC

| File | Purpose |
|------|---------|
| `update_produk.php` | IPOS data sync script |
| `config.php` | DB config (local IPOS PostgreSQL) |
| `cache_produk.json` | Generated product cache |
| `cache_aset.json` | Generated asset data cache |
| `cache_hutang.json` | Generated debt data cache |
| `cache_penghasilan.json` | Generated revenue data cache |
| `git_push.bat` | Git commit + push automation |

## API Reference

### 1. `api_produk.php` — Get Products (Public)

**Method:** GET  
**Auth:** None (public)  
**Response:** JSON array of product objects

```json
[
  {
    "id": "BRG001",
    "name": "AMD Ryzen 5 5600",
    "category": "Processor",
    "price": 1850000,
    "stock": 12,
    "description": "Spesifikasi lengkap...",
    "image": "/uploads/BRG001_1.webp?v=1234567890",
    "images": ["/uploads/BRG001_1.webp?v=...", "/uploads/BRG001_2.webp?v=..."]
  }
]
```

**Behavior:**
- Queries `tbl_item` JOIN `tbl_itemstok` WHERE `SUM(stok) > 0`
- LEFT JOINs `tbl_web_deskripsi` for custom descriptions
- Auto-creates `tbl_web_deskripsi` table if missing
- Writes `cache_produk.json` as fallback cache
- Falls back to cache file if DB connection fails
- Assigns `'Lainnya'` category for empty categories
- Falls back to Unsplash default image when no photos exist
- Appends `?v=filemtime` to image URLs for cache busting

### 2. `update_produk.php` — Update Product (Admin)

**Method:** POST  
**Auth:** Session (admin logged in)  
**Content-Type:** `multipart/form-data`

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | Yes | Product kodeitem |
| `description` | string | No | Custom product description |
| `new_files[]` | file[] | No | New photo files to upload |
| `image_order` | JSON string | No | Array of image tokens in desired order |

**Behavior:**
- Updates or inserts description into `tbl_web_deskripsi`
- Converts uploaded photos to WEBP (JPEG, PNG, WEBP input supported)
- Saves as `{safe_kode}_{index}.webp` (index starts at 1)
- Processes `image_order` to reorder existing + new photos
- Deletes photos not included in the new order

**Image order token format:**
- Existing images: the full URL path (e.g., `uploads/BRG001_1.webp?v=...`)
- New images: `new_0`, `new_1`, etc. (auto-assigned sequentially)

### 3. `update_admin.php` — Admin CRUD + Schedules + Status

**Method:** POST  
**Auth:** Session (admin logged in, with role checks per action)

**Actions:**

| Action | Auth Required | Parameters | Description |
|--------|--------------|------------|-------------|
| `tambah_admin` | Super admin | `username`, `password`, `nama`, `role` | Create new admin |
| `edit_admin` | Self or super admin | `target_id`, `username`, `nama`, `password`, `role` | Edit admin (regular admins can only edit themselves, can't change role) |
| `hapus_admin` | Super admin | `target_id` | Delete admin (can't delete self, must keep ≥1 super admin) |
| `get_admins` | Super admin | — | List all admins (password_hash stripped) |
| `get_schedules` | Session | — | List closure schedules |
| `add_schedule` | Session | `start_date`, `start_time`, `end_date`, `end_time`, `note` | Add closure schedule |
| `edit_schedule` | Session | `id`, `start_date`, `start_time`, `end_date`, `end_time`, `note` | Edit closure schedule |
| `delete_schedule` | Session | `id` | Delete closure schedule |
| `set_manual_status` | Session | `status` (`buka` or `tutup`) | Override store status manually |

**Validation rules:**
- Username: min 3 chars, must be unique
- Password: min 6 chars
- At least 1 super admin must exist at all times
- Schedule dates must be valid, start ≤ end

      "keterangan": "",
      "status": "terlambat",
      "hari_terlambat": 5
    }
  ],
  "grand_total_faktur": 5000000,
  "grand_total_sisa": 4000000,
  "total": 1
}
```

**Data source:** `tbl_imhd` (purchase transactions with `jmlkredit > 0` and outstanding balance > 0). Supplier names from `tbl_supel` (LEFT JOIN on `kodesupel = kode`). Currently `tbl_byrhutanghd`/`tbl_byrhutangdt` are unused (all payments are zero).

**Notes:**
- RKI (Retur Kongsi) transactions are **excluded** from all queries because returns to suppliers are not debts.
- The filter dropdown and table badges only show BL (Pembelian) and KI (Kongsi).

---

## Data Model

### PostgreSQL Tables (from IPOS + custom)

#### `tbl_item` (IPOS — read only)
| Column | Type | Description |
|--------|------|-------------|
| `kodeitem` | VARCHAR(50) PK | Product ID |
| `namaitem` | VARCHAR(255) | Product name |
| `jenis` | VARCHAR(100) | Category |
| `hargajual1` | NUMERIC | Selling price (cast to float in API) |

#### `tbl_itemstok` (IPOS — read only)
| Column | Type | Description |
|--------|------|-------------|
| `kodeitem` | VARCHAR(50) | FK to tbl_item |
| `stok` | NUMERIC | Stock quantity |

Only items with `SUM(stok) > 0` are exposed to the storefront.

#### `tbl_web_deskripsi` (Custom — auto-created if missing)
| Column | Type | Description |
|--------|------|-------------|
| `kodeitem` | VARCHAR(50) PK | FK to tbl_item |
| `deskripsi` | TEXT | Custom product description |

### JSON Files (local config)

#### `admins.json`
```json
{
  "admins": [
    {
      "id": "1",
      "username": "superadmin",
      "password_hash": "$2y$10$...",
      "role": "super_admin",
      "nama": "Super Admin",
      "created_at": "2026-01-01"
    }
  ]
}
```

}
```

#### `jadwal_tutup.json`
```json
[
  {
    "id": "s_abc123",
    "start": "2026-06-20 08:00",
    "end": "2026-06-22 20:00",
    "note": "Libur Lebaran",
    "created_at": "2026-06-18 10:00"
  }
### Role Hierarchy
| Role | Permissions |
|------|------------|
| `super_admin` | Full access: product management, closure schedules, admin CRUD, manual status override, profile edit |
| `admin` | Product management, closure schedules (view/add/edit), manual status override, own profile edit. **Cannot:** manage other admins, change |

### Default Credentials
- Username: `superadmin`
- Password: `royal2026`

---

## Store Status Algorithm

The storefront in `index.php` evaluates status in this priority order:

```
1. Manual override (status_toko.txt = 'tutup')
   ↓ If not manually closed:
2. Active closure schedule (jadwal_tutup.json, current time within any range)
   ↓ If no active schedule:
3. Schedule and manual status check
```

**Implementation logic (in `index.php`):**
```php
// 1. Manual override
$tutup_sementara = (file_get_contents('status_toko.txt') === 'tutup');

// 2. Schedule check
$now_dt = date('Y-m-d H:i');
foreach ($schedules as $s) {
    if ($now_dt >= $s['start'] && $now_dt <= $s['end']) {
        $tutup_sementara = true; break;
    }
}

// 3. Operating hours check
$is_open = ($jam_sekarang >= $hari_ini['buka'] && $jam_sekarang <= $hari_ini['tutup']);
```

**Display logic:**
- **Open:** Green badge showing closing time
- **Closed (off-hours):** Grey badge showing next opening day/time
- **Temporarily closed:** Red badge "Toko Tutup Sementara"
- **Upcoming schedule:** Yellow info bar showing planned closure dates

---

## Photo Management Flow

Photos are stored in `backend/uploads/`. `frontend/uploads/` is a **symlink** to `backend/uploads/`, so all images are accessible from the frontend without separate sync.

```
User selects photos ──► update_produk.php
                           │
                           ▼
                    Convert to WEBP via GD
                    (JPEG, PNG, WEBP supported)
                           │
                           ▼
                    Save as {safe_kode}_{index}.webp
                    (index = position in image_order)
                           │
                           ▼
                    Delete photos not in new order
                    (removes reordered-out files)
```

**Note:** There is no separate FRONTEND SYNC step — the symlink `frontend/uploads -> ../backend/uploads` eliminates the need to copy files. Cache JSON files are updated directly by `update_produk.php`.

### Reorder Flow
1. Client maintains `currentEditImages[]` array (mixed existing + new items)
2. On reorder: existing items trigger `api_manage_photos.php?action=reorder`
3. Server renames files to temp names → then to final `_1`, `_2`, `_3` etc.

### Delete Flow
1. `api_manage_photos.php?action=delete` with file URL
2. Server verifies file is in `uploads/` and belongs to the product kode
3. Security: `realpath()` check prevents path traversal

---

## Frontend Architecture

The storefront (`index.php`) is a single-page application with client-side filtering and sorting.

### JavaScript State
```javascript
let allProducts = [];        // All products from API
let filteredProducts = [];   // After filtering/sorting
let activeFilters = {
    category: 'Semua',
    search: '',
    sortBy: 'default',       // 'default' | 'low-high' | 'high-low'
    condition: 'Semua'       // 'Semua' | 'Baru' | 'Bekas'
};
```

### Data Flow
```
DOMContentLoaded
    → initPage()
        → Promise.all([fetch('api_produk.php'), fetch('api_banner.php')])
            → generateCategoryFilterOptions()
            → initViewToggle()
            → renderBanners()
```

### Layout Modes
The page has two layout states:
- **Initial:** Banner carousel full-width, sidebar hidden, products hidden, search prompt visible
- **Active:** Banner slides up & hides, sidebar shows, product grid rendered

Triggered by first search/filter action. `resetFilters()` returns to initial mode.

### Filter Logic
- **Category:** Extracted from `product.category`, unique values rendered as buttons
- **Search:** Case-insensitive match on `product.name`
- **Condition:** Products with "2ND" in name → "Bekas"; everything else → "Baru"
- **Sort:** Default (DB order), price ascending, price descending

### Pagination
- Products are rendered in pages of 40 via `displayLimit` variable
- **"Muat Lainnya"** button increments `displayLimit += 40` and re-renders
- `displayLimit` resets to 40 when filters/search change
- Counter shows "Menampilkan X dari Y produk"

### Back to Top
- Floating button (`#back-to-top`) appears after scrolling >400px
- Smooth scroll to top on click, `bg-astra-700` style

### Product Detail Modal
- Opens via `openDetailModal(id)` which finds product in `allProducts[]`
- Image carousel with prev/next navigation and dot indicators
- WhatsApp order button with pre-filled message template

### Admin Dashboard (`admin.php`) State
```javascript
let allProducts = [];
let filteredProducts = [];
let adminFilters = { search: '', photoStatus: 'all', sortBy: 'name-asc', condition: 'all' };
```

Photo management uses a drag-free reorder UI with left/right arrow buttons on each thumbnail.
Photos are managed in the client-side `currentEditImages[]` array with mixed `{type: 'existing', src}` and `{type: 'new', file, tempId}` items.

---

## Category Management

### Data Storage (`backend/data/kategori.json`)

Maps raw IPOS `jenis` values to user-friendly names with hierarchy and visibility control.

```json
{
  "kategori": [
    {
      "id": "MONITOR",
      "nama": "Monitor",
      "parent": null,
      "urut": 1,
      "visible": true
    },
    {
      "id": "MOUSE",
      "nama": "Mouse",
      "parent": "PERIPHERAL",
      "urut": 2,
      "visible": true
    },
    {
      "id": "PERIPHERAL",
      "nama": "Peripheral",
      "parent": null,
      "urut": 5,
      "visible": true,
      "is_group": true
    }
  ]
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Raw `jenis` value from IPOS (for matching) |
| `nama` | string | User-friendly display name (Indonesian) |
| `parent` | string\|null | Parent category ID (null = root) |
| `urut` | number | Sort order |
| `visible` | bool | Show/hide from marketplace |
| `is_group` | bool | Virtual parent (no products directly assigned) |

**Auto-detection:** Categories from IPOS not yet in `kategori.json` are detected when admin opens the panel and shown with default `visible: false`.

### Product Visibility (`backend/data/produk_tersembunyi.json`)

Array of product IDs hidden from marketplace:
```json
["BRG001", "VNRXEV_1"]
```

### Public API (`frontend/api_kategori.php`)

Returns visible category hierarchy (excluding `is_group` parents as standalone items).

### Frontend Filter Tree

Categories with children render as expandable groups:
```
[Semua]
[▼ Peripheral]
  ├─ Mouse
  ├─ Keyboard
  └─ Mouse Keyboard
[Monitor]
[▼ Penyimpanan]
  ├─ HDD
  ├─ SSD SATA
  └─ M.2 NVMe
```

- Clicking parent shows products from ALL its children
- Categories with `visible: false` are excluded entirely
- Products in hidden categories are filtered from API response

### Admin Panel

Tab "Kategori" in admin dashboard with:
- Table of all categories (auto-detected + configured)
- Inline edit: display name, parent dropdown, visibility toggle, sort order
- New categories highlighted in yellow

---

## Promo Management

### Data Storage (`backend/data/produk_promo.json`)

```json
{
  "BRG001": { "harga_coret": 2500000, "label": "Diskon Akhir Tahun" },
  "VNRXEV_1": { "harga_coret": 500000, "label": "Flash Sale" }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `harga_coret` | number | Original price (displayed with strikethrough) |
| `label` | string | Optional promo badge text |

**Rules:**
- Only visible when `harga_coret > harga_jual` (actual price from IPOS)
- `harga_coret` does NOT replace the actual selling price
- Admin can set/unset via "Kelola" modal or tab "Promo"

### Frontend Display
```
[ DISKON 20% ]          ← label badge
~~Rp2.500.000~~         ← coret (strikethrough)
Rp1.850.000             ← harga jual (IPOS, unchanged)
```

---

## Sync Timestamp

Sync agent writes `backend/data/waktu_sync.json` on every run:
```json
{
  "terakhir": "2026-07-23 09:30:02",
  "timezone": "Asia/Jakarta",
  "produk": 744,
  "produk_tanpa_foto": 667,
  "total_foto": 135
}
```

Displayed in admin dashboard as an info bar: "Sync terakhir: 23 Juli 2026 09:30 WIB | 744 produk | 135 foto"

---

## Dashboard Statistics (Admin)

Cards computed from product data:
| Card | Data Source |
|------|-------------|
| Total Produk | `allProducts.length` |
| Kategori Aktif | Unique visible categories |
| Tanpa Foto | Products with Unsplash fallback |
| Total Stok | Sum of all `stock` |
| Total Nilai Jual | Sum of `price * stock` |
| Total Modal | Sum of `harga_pokok * stock` (read-only from IPOS) |

---

## Code Conventions

- **PHP files:** Lowercase with underscores (`update_produk.php`)
- **Directory structure:** 4 root folders — `database/`, `frontend/`, `backend/`, `sync/`
- **JS variables:** camelCase
- **HTML:** Tailwind utility classes with custom `astra` color palette (blue/navy/navy-950)
- **Images:** WEBP only, naming `{safe_kode}_{index}.webp`
- **API responses:** Always JSON with `Content-Type: application/json`
- **API response format:** `{ "success": bool, "message": string }` for mutations; array of objects for data fetches
- **Language:** Indonesian UI, `Asia/Jakarta` timezone, IDR currency formatting
- **Error handling:** `E_ERROR | E_PARSE` error reporting on API files to prevent HTML corruption of JSON

---

## Security Rules

| Rule | Applies To |
|------|-----------|
| Always use `htmlspecialchars()` on user output | All PHP templates |
| Always call `requireLogin()` on admin pages | All admin files |
| Use `isSuperAdmin()` guard for sensitive operations | Admin management, |
| Validate file paths with `realpath()` | Photo delete API |
| Never store plaintext passwords | Always bcrypt via `password_hash()` |

---

## Common Tasks Quick Reference

### Adding a product photo
1. Log into admin panel
2. Click "Kelola" on the product
3. Click photo upload input (accepts JPEG, PNG, WEBP)
4. Auto-converted to WEBP, saved as `{safe_kode}_{index}.webp`

### Adding an admin
```http
POST update_admin.php
action: tambah_admin
username: newadmin
password: sekret123
nama: New Admin
role: admin
```
Requires super admin role.

### Modifying
```http
buka_Monday: 08:00
tutup_Monday: 22:00
```
Requires super admin role. HH:MM format, English day names.

### Checking store status
- Manual override: read `status_toko.txt`
- Schedules: read `jadwal_tutup.json`

### Running the sync agent manually
```bash
cd sync
php update_produk.php
```

### Sync Agent — Admin Data (Aset, Hutang, Penghasilan)

Selain produk, sync agent juga menyinkronkan 3 jenis data admin setiap jam:

#### Data yang disinkronkan:

| Cache File | Admin Menu | Sumber Tabel IPOS |
|------------|------------|-------------------|
| `cache_aset.json` | Aset | `tbl_item`, `tbl_itemstok` (nilai modal, nilai jual per item) |
| `cache_hutang.json` | Hutang | `tbl_imhd`, `tbl_supel` (hutang beredar ke supplier) |
| `cache_penghasilan.json` | Penghasilan | `tbl_ikhd`, `tbl_ikdt`, `tbl_item` (penjualan bulan berjalan) |

#### Cache file locations:
- `sync/cache_*.json` — cache source (sync agent)
- `backend/data/cache_*.json` — digunakan oleh API backend sebagai fallback

#### Cache fallback mechanism:
```php
// Pattern used in API files:
$db = getDB();
if (!$db) {
    // Load from cache file
    $cached = json_decode(file_get_contents('data/cache_aset.json'), true);
    // Use cached data with filtering/sorting support
}
```

Ketika database IPOS tidak tersedia, menu admin akan menggunakan cache yang tersimpan untuk:
- **Aset:** Ringkasan, breakdown kategori, daftar produk dengan filter & sorting
- **Hutang:** Ringkasan, daftar hutang dengan filter & sorting
- **Penghasilan:** Data bulan berjalan (summary, transaksi terbaru)
- **Mutasi Aset:** Tidak tersedia di cache (membutuhkan query DB langsung)

### Configuring Windows Task Scheduler

The sync task is configured using `sync/setup_scheduler.ps1` (run as Administrator once). It creates the **"RoyalKomputer Sync"** task that runs:

**Action:** `C:\xampp\php\php.exe` with arguments `update_produk.php --once`
**Follow-up action:** `sync/git_push.bat`
**Wrapper:** `sync/sync_and_push.bat` (runs both sequentially)
**Schedule:** Every 1 hour
**Run as:** Current user (for Git/SSH credentials)

To install:
```powershell
# Run as Administrator once
powershell -ExecutionPolicy Bypass -File sync\setup_scheduler.ps1
```

### Adding a new feature
1. Identify the appropriate file based on the [Project File Map](#project-file-map)
2. Follow patterns from existing code (same error handling, JSON response format, session checks)
3. For new API endpoints: add `Content-Type: application/json` header, use `pg_query_params()` for DB queries
4. For UI changes: use Tailwind CSS classes with `astra` color palette, Font Awesome icons

### Database connection
```php
$conn = getDBConnection();  // From config.php
// Returns false on failure
pg_query_params($conn, $sql, $params);
```

---

## Git Commit Convention

Every commit must be **small and focused** — one logical change per commit.

| Scenario | Example Message |
|----------|----------------|
| New feature | `feat: add product search by category` |
| Bug fix | `fix: resolve store status showing incorrect hours` |
| Refactor | `refactor: extract photo upload logic to helper` |
| Content update | `sync: product data update 2026-06-18 10:00` |
| Documentation | `docs: update API reference in AGENTS.md` |
| Config | `chore: update .gitignore for upload paths` |

**Rules:**
- Never mix unrelated changes in one commit (e.g., don't combine a bug fix with a new feature)
- Use the prefix format: `type: short description`
- Keep subject line under 72 characters
- Body (optional) explains *why*, not *what* — the diff shows what changed
- Before committing: `git status` → `git diff` → stage *only* intended files

---

## Testing

No automated test suite. Manual testing through browser.
