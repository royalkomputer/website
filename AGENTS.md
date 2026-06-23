# Instructions for AI Coding Agents

## Project Overview

Vanilla PHP e-commerce marketplace for **Royal Komputer Kediri**, a computer hardware store in Kediri, East Java, Indonesia.

The system has a **hybrid local/cloud architecture**: a PC at the store runs `sync/` scripts to pull product data from the **IPOS** point-of-sale software and push changes to git. Cloud services (**Netlify** frontend, **Render** backend, **Neon** database) auto-deploy from the git repo.

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
│  │   Netlify    │    │   Render     │    │   Neon                    │  │
│  │  (Frontend)  │    │  (Backend)   │    │   (Cloud PostgreSQL)      │  │
│  │  Storefront  │    │  Admin/PHP   │    │   Serverless DB           │  │
│  │  Static/CDN  │    │  API layer   │    │   Mirrors local DB schema │  │
│  └──────────────┘    └──────────────┘    └───────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
```

### Data Flow

1. **IPOS** writes product inventory to local PostgreSQL (`192.168.18.189:5444`, DB: `i4_ROYAL`)
2. **Windows Task Scheduler** runs `php sync/update_produk.php` every 1 hour
3. **sync agent** queries the local DB for products with `stock > 0`, generates `cache_produk.json`, commits & pushes to git via `sync/git_push.bat`
4. **Netlify** auto-deploys the storefront from the git repo
5. **Render** auto-deploys the PHP backend/admin panel from the git repo
6. **Neon** serves as the cloud PostgreSQL database for deployed services

### Cloud Service Roles

| Service  | Role      | URL                                          |
|----------|-----------|----------------------------------------------|
| Netlify  | Frontend  | `https://tiny-druid-60182f.netlify.app`      |
| Render   | Backend   | `https://royal-backend-s3ir.onrender.com`    |
| Neon     | Database  | `postgresql://...@ep-dawn-shape-ao7h4edr.jp-tokyo-1.aws.neon.tech/royalkomputer?sslmode=require` |

### Netlify Redirect Limitations

Netlify **does not support** wildcard/redirect with `status = 200` to an external URL. The following approaches were tested and **failed**:
- `_redirects`: `/uploads/* https://render.com/uploads/:splat 200`
- `netlify.toml`: `from = "/uploads/*"`, `to = "...", status = 200`

**What works:**
- **Exact-path redirects** with `status = 200` to external URLs (e.g., `/logo/logo.webp`)
- **Wildcard redirects with `force = true`** — only works for internal admin paths that hit Render directly (e.g., `/admin/*`, `/update_*`)
- **Absolute image URLs** from the backend API (e.g., `https://royal-backend-s3ir.onrender.com/uploads/BRG001_1.webp`)

**Current strategy:**
- Logo: served via exact-path redirect `/logo/logo.webp → Render URL`
- Product photos: API returns absolute Render URLs (not relative paths)
- Admin paths: wildcard redirects to Render with `force = true`

---

## Project File Map

The project uses a 4-folder monorepo structure at the root level. Each folder maps to a deployment target.

| Folder | Deploys To | Purpose |
|--------|------------|---------|
| `database/` | **Neon** | PostgreSQL schema + migrations |
| `frontend/` | **Netlify** | Public storefront + product API |
| `backend/` | **Render** | Admin dashboard + API layer |
| `sync/` | **Local PC** | IPOS sync agent (Task Scheduler) |

### `database/` — Neon

| File | Purpose |
|------|---------|
| `schema.sql` | Full PostgreSQL schema DDL |
| `migrations/001_initial.sql` | Incremental schema migrations |

### `frontend/` — Netlify

| File | Type | Auth |
|------|------|------|
| `index.php` | Public storefront (HTML+PHP) | No |
| `api_produk.php` | Product JSON API | No (public) |
| `cache_produk.json` | Product cache fallback | — |
| `uploads/` | Product photos (WEBP) | — |
| `logo/` | Brand assets | — |
| `netlify.toml` | Netlify build configuration | — |

### `backend/` — Render

| File | Type | Auth |
|------|------|------|
| `admin.php` | Admin dashboard (HTML+PHP) | Session |
| `login.php` | Login page (HTML+PHP) | No |
| `logout.php` | Logout (PHP) | No |
| `config.php` | Core config & helpers | Varies |
| `update_produk.php` | Product update + photo upload API | Session |
| `update_admin.php` | Admin CRUD + schedules + status API | Session |
| `update_jam.php` | Operating hours API | Super Admin |
| `api_manage_photos.php` | Photo delete/reorder API | Session |
| `data/admins.json` | Admin accounts (bcrypt) | — |
| `data/jam_operasional.json` | Per-day operating hours | — |
| `data/jadwal_tutup.json` | Closure schedules | — |
| `data/status_toko.txt` | Manual store status override | — |
| `data/cache_produk.json` | Product cache (written by admin) | — |
| `uploads/` | Admin photo upload target | — |
| `render.yaml` | Render blueprint config | — |

### `sync/` — Local PC

| File | Purpose |
|------|---------|
| `update_produk.php` | IPOS data sync script |
| `config.php` | DB config (local IPOS PostgreSQL) |
| `cache_produk.json` | Generated product cache |
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
    "image": "https://royal-backend-s3ir.onrender.com/uploads/BRG001_1.webp?v=1234567890",
    "images": ["https://royal-backend-s3ir.onrender.com/uploads/BRG001_1.webp?v=...", "https://royal-backend-s3ir.onrender.com/uploads/BRG001_2.webp?v=..."]
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

### 4. `update_jam.php` — Operating Hours

**Method:** POST  
**Auth:** Super admin only  
**Parameters:** `buka_Monday`, `tutup_Monday`, `buka_Tuesday`, `tutup_Tuesday`, ... (HH:MM format)

### 5. `api_manage_photos.php` — Photo Management

**Method:** POST  
**Auth:** Session (admin logged in)

**Actions:**

| Action | Parameters | Description |
|--------|------------|-------------|
| `delete` | `id`, `file` (URL) | Delete a single photo file |
| `reorder` | `id`, `files` (JSON array of URLs) | Renumber all photos in order |

**Security:** Validates file path via `realpath()` to prevent directory traversal. Only files starting with the product's `safe_kode` prefix in `uploads/` can be deleted.

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

#### `jam_operasional.json`
```json
{
  "Monday":    { "buka": "09:00", "tutup": "21:00", "indo": "Senin" },
  "Tuesday":   { "buka": "09:00", "tutup": "21:00", "indo": "Selasa" },
  ...
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
]
```

### Photo Storage
- Format: WEBP only
- Naming: `{safe_kode}_{index}.webp` (index starts at 1)
  - `safe_kode` = `preg_replace('/[^A-Za-z0-9]/', '_', $kodeitem)`
  - Example: `BRG_001` → `BRG_001_1.webp`, `BRG_001_2.webp`
- Legacy single-photo format: `{safe_kode}.webp` (supported, shown first)
- Location: `uploads/` directory on Render (not served via Netlify proxy)
- Image URLs in API response: **absolute Render URLs** (e.g., `https://royal-backend-s3ir.onrender.com/uploads/BRG001_1.webp?v=...`)
- Netlify wildcard proxy to Render does not work — images must use absolute Render URLs
- Cache busting: `?v=filemtime` appended to URLs

---

## Authentication & Authorization

### Login Flow
1. User submits username + password via `login.php` POST form
2. Script calls `findAdminByUsername()` which reads `admins.json`
3. Verifies password with `password_verify()` against bcrypt hash
4. On success: sets session variables (`admin_logged_in`, `admin_id`, `admin_username`, `admin_role`)
5. Redirects to `admin.php`
6. `config.php` starts session via `session_start()` for all pages

### Session Guards
```php
// Require any logged-in admin
requireLogin();  // Redirects to login.php if not authenticated

// Check for super admin role
isSuperAdmin()  // Returns bool, checks current admin's role === 'super_admin'
```

### Role Hierarchy
| Role | Permissions |
|------|------------|
| `super_admin` | Full access: product management, operating hours, closure schedules, admin CRUD, manual status override, profile edit |
| `admin` | Product management, closure schedules (view/add/edit), manual status override, own profile edit. **Cannot:** manage other admins, change operating hours |

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
3. Operating hours check (jam_operasional.json, current day & time)
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
    → initDatabaseConnection()
        → fetch('api_produk.php')
            → processAndRenderData()
                → generateCategoryFilterOptions()
                → applyFiltersAndSort()
                    → renderProductGrid()
```

### Filter Logic
- **Category:** Extracted from `product.category`, unique values rendered as buttons
- **Search:** Case-insensitive match on `product.name`
- **Condition:** Products with "2ND" in name → "Bekas"; everything else → "Baru"
- **Sort:** Default (DB order), price ascending, price descending

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
| Use `isSuperAdmin()` guard for sensitive operations | Admin management, operating hours |
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

### Modifying operating hours
```http
POST update_jam.php
buka_Monday: 08:00
tutup_Monday: 22:00
```
Requires super admin role. HH:MM format, English day names.

### Checking store status
- Manual override: read `status_toko.txt`
- Operating hours: read `jam_operasional.json`
- Schedules: read `jadwal_tutup.json`

### Running the sync agent manually
```bash
cd sync
php update_produk.php
```

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
