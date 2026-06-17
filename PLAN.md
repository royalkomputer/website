# Royal Komputer — Project Plan

## Architecture Overview

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ database  │    │ frontend │    │ backend  │    │   sync   │
│  (Neon)   │    │(Netlify) │    │ (Render) │    │ (Local)  │
│  SQL      │◄──►│  Vite +  │◄──►│  PHP 8   │◄──►│  IPOS    │
│  schema   │    │  Tailwind│    │  API     │    │  sync    │
└──────────┘    └──────────┘    └──────────┘    └──────────┘
```

---

## Phase 1: Frontend with Vite (Current Focus)

Migrate the PHP storefront (`local-updater/index.php`) to a modern Vite + Tailwind CSS frontend inside `frontend/`.

### Why Vite?

- **Hot Module Replacement** — instant feedback during development
- **PostCSS + Tailwind CLI** — proper CSS processing instead of CDN
- **ES Module imports** — cleaner JS architecture than inline `<script>` tags
- **Dev server** — `localhost:5173` with proxy to PHP backend
- **Production build** — minified, optimized output for Netlify deploy

### Migration Steps

#### 1. Scaffold Vite Project
```bash
cd frontend
npm create vite@latest . -- --template vanilla
npm install tailwindcss @tailwindcss/vite
```

#### 2. Convert PHP → Static HTML/JS
Replace the PHP server-rendered page (`index.php`) with:
- `index.html` — static HTML skeleton
- `src/main.js` — JavaScript entry point
- `src/style.css` — Tailwind CSS

#### 3. Replace Server-side Logic with Client-side API Calls
| PHP Feature | Vite Replacement |
|------------|-----------------|
| `require_once 'config.php'` | Remove — config lives in backend |
| Store status (jam operasional) | `GET /api/status` endpoint on backend |
| Schedule data | `GET /api/schedules` endpoint on backend |
| Store hours display | Fetch + render in JS |
| Product data | Already uses `api_produk.php` — just change URL to backend |
| Social links | Static HTML — no change needed |

#### 4. Set Up Dev Proxy
```javascript
// vite.config.js
export default {
  server: {
    proxy: {
      '/api': 'http://localhost:8080'  // PHP backend
    }
  }
}
```

#### 5. File Structure (Frontend)
```
frontend/
├── index.html              # Entry point
├── vite.config.js          # Vite config + proxy
├── package.json            # Dependencies
├── netlify.toml            # Deploy config
├── src/
│   ├── main.js             # App entry
│   ├── style.css           # Tailwind imports
│   ├── components/         # UI components
│   │   ├── Navbar.js
│   │   ├── ProductGrid.js
│   │   ├── ProductCard.js
│   │   ├── ProductModal.js
│   │   ├── FilterSidebar.js
│   │   ├── StoreStatus.js
│   │   └── Footer.js
│   ├── lib/
│   │   ├── api.js          # API client
│   │   └── format.js       # IDR formatting helpers
│   └── data/
│       └── social.js       # Social media links data
├── public/
│   ├── uploads/            # Product photos
│   └── logo/               # Brand assets
└── dist/                   # Build output (gitignored)
```

#### 6. Tailwind Config
Replace CDN-based Tailwind with proper npm installation:
- `tailwind.config.js` — custom `astra` color palette
- `postcss.config.js` — PostCSS processing
- `src/style.css` — `@import "tailwindcss"`

#### 7. Netlify Deploy Config
```toml
# frontend/netlify.toml
[build]
  command = "npm run build"
  publish = "dist"

[[redirects]]
  from = "/api/*"
  to = "https://royal-backend.onrender.com/api/:splat"
  status = 200
```

### Dev Workflow
```bash
# Terminal 1: Frontend (Vite)
cd frontend
npm run dev                # http://localhost:5173

# Terminal 2: Backend (PHP)
cd backend
php -S localhost:8080      # http://localhost:8080/api_produk.php

# Or use one-command launcher:
./dev.bat                  # Starts both
```

### Frontend Dependencies
```json
{
  "devDependencies": {
    "vite": "^6",
    "tailwindcss": "^4",
    "@tailwindcss/vite": "^4"
  }
}
```

**No frontend framework** — vanilla JS with Vite + Tailwind CSS. Keeps it lightweight and matches the existing codebase philosophy. Can upgrade to React/Vue/Svelte later if needed.

---

## Phase 2: Backend (PHP) — Migrate to backend/

### Goals
- Move all admin PHP files from `local-updater/` to `backend/`
- Refactor `update_produk.php` — split sync logic (→ `sync/`) from photo upload (stays in backend)
- Add new API endpoints for the Vite frontend (`api_status.php`, `api_schedules.php`)
- Add CORS headers for cross-origin requests from Vite dev server
- Move JSON config files to `backend/data/` and update path constants
- Keep `backend/` self-contained for Render deployment

### File Migration

| Source (local-updater/) | Destination (backend/) | Changes Needed |
|------------------------|-----------------------|---------------|
| `index.php` | → `frontend/` (Vite rebuild) | Full rewrite — not copied to backend |
| `admin.php` | `admin.php` | Update `config.php` path if needed |
| `login.php` | `login.php` | Update `config.php` path |
| `logout.php` | `logout.php` | No changes |
| `config.php` | `config.php` | **Major update** — see below |
| `update_produk.php` | `update_produk.php` | **Strip sync logic** — keep only photo upload + description edit |
| `update_admin.php` | `update_admin.php` | Update file path constants |
| `update_jam.php` | `update_jam.php` | Update file path constants |
| `api_produk.php` | `api_produk.php` | Add CORS, update paths |
| `api_manage_photos.php` | `api_manage_photos.php` | Update paths |
| (new) | `api_status.php` | **New** — store status endpoint |
| (new) | `api_schedules.php` | **New** — public schedules endpoint |
| `admins.json` | `data/admins.json` | Update constant in config.php |
| `jam_operasional.json` | `data/jam_operasional.json` | Update constant in config.php |
| `jadwal_tutup.json` | `data/jadwal_tutup.json` | Update constant in config.php |
| `status_toko.txt` | `data/status_toko.txt` | Update constant in config.php |
| `cache_produk.json` | `data/cache_produk.json` | Update paths in api_produk.php |
| `uploads/` | `uploads/` | No changes |
| `logo/` | → `frontend/public/logo/` | Move to frontend |

### Config.php Refactoring

The current `config.php` mixes local DB credentials with admin helpers. For cloud deployment:

```php
// backend/config.php

// Database — try Neon env vars first, fall back to local IPOS
$db_host = getenv('PGHOST') ?: '192.168.18.189';
$db_port = getenv('PGPORT') ?: '5444';
$db_name = getenv('PGDATABASE') ?: 'i4_ROYAL';
$db_user = getenv('PGUSER') ?: 'admin';
$db_pass = getenv('PGPASSWORD') ?: '2356988';

define('DB_HOST', $db_host);
define('DB_PORT', $db_port);
define('DB_NAME', $db_name);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);

// JSON config — now in data/ subdirectory
define('ADMINS_FILE', __DIR__ . '/data/admins.json');
define('JAM_FILE', __DIR__ . '/data/jam_operasional.json');
define('SCHEDULE_FILE', __DIR__ . '/data/jadwal_tutup.json');
define('STATUS_FILE', __DIR__ . '/data/status_toko.txt');
define('CACHE_FILE', __DIR__ . '/data/cache_produk.json');
```

### CORS Strategy

For local development, the Vite frontend (`localhost:5173`) calls the PHP backend (`localhost:8080`) — this is a cross-origin request.

```php
// Add to the top of all API files (or a shared cors.php helper)
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Production: only allow Netlify domain
// header('Access-Control-Allow-Origin: https://royal-komputer.netlify.app');
```

**Better approach:** Create `backend/cors.php` and include it in all API files.

### New API Endpoints

#### `api_status.php` — Store Public Status
Replaces the inline PHP logic from `index.php` with a JSON endpoint.

```
GET /api_status.php
Response:
{
  "is_open": true,
  "manual_override": false,
  "hours": {
    "buka": "09:00",
    "tutup": "21:00"
  },
  "next_open": {
    "hari": "Senin",
    "jam": "09:00"
  },
  "upcoming_schedule": null
}
```

#### `api_schedules.php` — Active Closure Schedules
```
GET /api_schedules.php
Response: [
  {
    "start": "2026-06-20 08:00",
    "end": "2026-06-22 20:00",
    "note": "Libur Lebaran"
  }
]
```

### Refactoring update_produk.php

The current file does TWO things:
1. **Sync** — connects to local IPOS DB, queries products, writes cache (needed by sync agent)
2. **Admin upload** — handles photo upload, reorder, description edit (needed by admin panel)

**After split:**
- `backend/update_produk.php` — keeps only photo upload + description edit (session-protected)
- `sync/update_produk.php` — keeps only DB query + cache generation (no session, no photo handling)

### Dev Workflow
```bash
# Terminal 1: Frontend (Vite)
cd frontend
npm run dev                # http://localhost:5173

# Terminal 2: Backend (PHP)
cd backend
php -S localhost:8080      # http://localhost:8080/api_produk.php

# Terminal 3: Admin panel (separate port)
cd backend
php -S localhost:8081      # http://localhost:8081/admin.php
```

### Render Deploy Config

```yaml
# backend/render.yaml
services:
  - type: web
    name: royal-backend
    env: php
    rootDir: backend
    buildCommand: ""
    startCommand: php -S 0.0.0.0:10000
    envVars:
      - key: PGHOST
        fromDatabase:
          name: royal-neon-db
          property: host
      - key: PGDATABASE
        fromDatabase:
          name: royal-neon-db
          property: database
      - key: PGUSER
        fromDatabase:
          name: royal-neon-db
          property: user
      - key: PGPASSWORD
        fromDatabase:
          name: royal-neon-db
          property: password
      - key: PGPORT
        value: "5432"

databases:
  - name: royal-neon-db
    databaseName: royal
    plan: starter  # or: free
```

---

## Phase 3: Sync Agent — Extract to sync/

### Goals
- Strip `update_produk.php` down to a headless sync script — no session check, no photo upload, no HTML
- Create a reliable git commit + push pipeline
- Set up Windows Task Scheduler to run it automatically every hour
- Add logging so you can verify syncs are working

### What the Sync Script Does

1. Connects to local IPOS PostgreSQL (`192.168.18.189:5444`)
2. Queries `tbl_item` + `tbl_itemstok` for products with `SUM(stok) > 0`
3. Builds the product array (same query as `api_produk.php`)
4. Writes `cache_produk.json`
5. Commits to git → pushes to remote

### Stripped-Down Script (`sync/update_produk.php`)

```php
<?php
// sync/update_produk.php — Headless sync agent, no session, no photos

error_reporting(E_ERROR | E_PARSE);
require_once 'config.php';

$conn = getDBConnection();
if (!$conn) {
    file_put_contents('sync.log', date('Y-m-d H:i:s') . " DB FAIL\n", FILE_APPEND);
    exit(1);
}

// Same query as api_produk.php
$sql = "SELECT i.kodeitem AS id, i.namaitem AS name, i.jenis AS category,
            i.hargajual1 AS price, COALESCE(s.total_stok, 0) AS stock
        FROM tbl_item i
        INNER JOIN (
            SELECT kodeitem, SUM(stok) as total_stok
            FROM tbl_itemstok GROUP BY kodeitem HAVING SUM(stok) > 0
        ) s ON i.kodeitem = s.kodeitem";

$result = pg_query($conn, $sql);
if (!$result) {
    file_put_contents('sync.log', date('Y-m-d H:i:s') . " QUERY FAIL: " . pg_last_error($conn) . "\n", FILE_APPEND);
    exit(1);
}

$produk = [];
while ($row = pg_fetch_assoc($result)) {
    $row['price'] = (float) $row['price'];
    $row['stock'] = (float) $row['stock'];
    if (empty(trim($row['category']))) $row['category'] = 'Lainnya';
    
    // Image path generation (same as api_produk.php)
    $safe_kode = preg_replace('/[^A-Za-z0-9]/', '_', $row['id']);
    $images = glob(__DIR__ . '/../frontend/uploads/' . $safe_kode . '_*.webp');
    $legacy = __DIR__ . '/../frontend/uploads/' . $safe_kode . '.webp';
    if (file_exists($legacy)) array_unshift($images, $legacy);
    
    if (!empty($images)) {
        foreach ($images as $file) {
            $images_clean[] = 'uploads/' . basename($file) . '?v=' . filemtime($file);
        }
        $row['image'] = $images_clean[0];
        $row['images'] = $images_clean;
    } else {
        $default = 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=500';
        $row['image'] = $default;
        $row['images'] = [$default];
    }
    
    $produk[] = $row;
}

// Write cache to BOTH locations (frontend/ for Netlify, sync/ for local)
file_put_contents(__DIR__ . '/cache_produk.json', json_encode($produk));
file_put_contents(__DIR__ . '/../frontend/cache_produk.json', json_encode($produk));

// Log success
file_put_contents('sync.log', date('Y-m-d H:i:s') . " OK — " . count($produk) . " products synced\n", FILE_APPEND);
pg_close($conn);
```

### Sync Config (`sync/config.php`)

```php
<?php
// sync/config.php — Local IPOS connection only, no session, no JSON helpers

define('DB_HOST', '192.168.18.189');
define('DB_PORT', '5444');
define('DB_NAME', 'i4_ROYAL');
define('DB_USER', 'admin');
define('DB_PASS', '2356988');

function getDBConnection() {
    $conn_string = "host=" . DB_HOST . " port=" . DB_PORT
        . " dbname=" . DB_NAME . " user=" . DB_USER
        . " password=" . DB_PASS . " connect_timeout=3";
    return @pg_connect($conn_string);
}
```

### Git Push Script (`sync/git_push.bat`)

```bat
@echo off
cd /d "%~dp0.."

git add -A

git diff --cached --quiet
if %errorlevel% equ 0 (
    echo No changes to commit.
    exit /b 0
)

git commit -m "sync: product data update %date% %time%"
git push origin main
```

### Task Scheduler Setup

Create a Windows Task that runs **every hour**:

| Setting | Value |
|---------|-------|
| **Action** | Start a program |
| **Program** | `php` |
| **Arguments** | `C:\projects\royal-website\sync\update_produk.php` |
| **Start in** | `C:\projects\royal-website\sync` |
| **Trigger** | Daily, repeat every 1 hour |
| **Run as** | User account (not SYSTEM) — needs git credentials cached |

**Second action** (run after PHP completes):
| **Program** | `C:\projects\royal-website\sync\git_push.bat` |

> **Important:** The user account running Task Scheduler must have git credentials cached (`git config --global credential.helper manager`) or use SSH keys.

### Sync Logging

The sync agent writes to `sync/sync.log`:
```
2026-06-18 10:00:01 OK — 342 products synced
2026-06-18 11:00:02 OK — 342 products synced
2026-06-18 12:00:01 DB FAIL
2026-06-18 13:00:02 OK — 340 products synced
```

---

## Phase 4: Cloud Deployment

### Neon (Database)

#### Setup
1. Create a Neon project at [neon.tech](https://neon.tech)
2. Copy the connection string: `postgresql://user:pass@ep-xxx.us-east-2.aws.neon.tech/royal`
3. Run the schema:
   ```bash
   psql "<neon-connection-string>" -f database/schema.sql
   ```

#### Connection from Render
Neon provides environment variables that Render can inject directly:
- `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`
- Or use a single `DATABASE_URL` connection string

The refactored `backend/config.php` reads these env vars automatically (see Phase 2).

#### Schema Management
- `database/schema.sql` — full schema, run on first deploy
- `database/migrations/001_initial.sql` — tracking file (contains metadata)
- `database/migrations/002_*.sql` — future changes

Neon has a **branching** feature — you can create a branch for development, test changes, then merge to production. This is useful for testing schema changes before they hit production.

#### Schema Differences: Local vs Cloud

| Feature | Local IPOS | Neon |
|---------|-----------|------|
| Tables | `tbl_item`, `tbl_itemstok` (managed by IPOS) | Same tables, or only `tbl_web_deskripsi` |
| Stock data | Real-time from IPOS | Synced via `cache_produk.json` |
| Custom desc | `tbl_web_deskripsi` | `tbl_web_deskripsi` (auto-created) |

**Key insight:** The storefront doesn't need a live DB connection on Netlify — it reads `cache_produk.json`. The backend on Render uses the DB for admin operations. So Neon primarily serves the admin panel, while the storefront works from cached JSON.

### Netlify (Frontend)

#### Setup
1. Connect git repo to Netlify
2. Configure:
   - **Base directory:** `frontend/`
   - **Build command:** `npm run build`
   - **Publish directory:** `frontend/dist`
3. Or use `frontend/netlify.toml`: 

```toml
[build]
  command = "npm run build"
  publish = "dist"

# Proxy API calls to Render backend
[[redirects]]
  from = "/api/*"
  to = "https://royal-backend.onrender.com/api/:splat"
  status = 200
  force = true

# Proxy admin paths to Render
[[redirects]]
  from = "/admin"
  to = "https://royal-backend.onrender.com/admin.php"
  status = 200
  force = true

[[redirects]]
  from = "/login"
  to = "https://royal-backend.onrender.com/login.php"
  status = 200
  force = true

# SPA fallback — all other routes serve index.html
[[redirects]]
  from = "/*"
  to = "/index.html"
  status = 200
```

#### Custom Domain
- Point your domain (e.g., `royalkomputer.com`) to Netlify nameservers
- Netlify auto-provisions SSL certificate
- Or use Netlify subdomain: `royal-komputer.netlify.app`

### Render (Backend)

#### Setup
1. Create a **Web Service** on Render
2. Connect the same git repo
3. Configure:
   - **Name:** `royal-backend`
   - **Root Directory:** `backend/`
   - **Runtime:** `PHP`
   - **Build Command:** *(leave empty — no build step)*
   - **Start Command:** `php -S 0.0.0.0:10000`
   - **Plan:** Starter ($7/month) or Free (spins down after inactivity)

#### Environment Variables
| Variable | Value |
|----------|-------|
| `PGHOST` | Neon host (from Neon dashboard) |
| `PGPORT` | `5432` |
| `PGDATABASE` | `royal` |
| `PGUSER` | Neon user |
| `PGPASSWORD` | Neon password |

#### Health Check
Render will ping the service every 5 minutes. Make sure `backend/` has an `index.php` or a route that returns 200:
```php
<?php
// backend/index.php — Health check responder
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'service' => 'royal-backend']);
```

Or configure Render to ping `/api_produk.php` directly.

#### Free Tier Caveats
- Render Free Web Services **spin down after 15 minutes of inactivity**
- First request after idle takes 30-60 seconds to cold-start
- **Solution:** Use Render's Starter plan ($7/month) or add a [cron-job.org](https://cron-job.org) ping every 10 minutes

### CI/CD Flow

```
Developer pushes to git
        │
        ▼
  ┌─────────────┐    ┌─────────────┐
  │   Netlify   │    │   Render    │
  │ Auto-builds │    │ Auto-builds │
  │ frontend/   │    │ backend/    │
  │ → dist/     │    │ → php -S   │
  └──────┬──────┘    └──────┬──────┘
         │                  │
         ▼                  ▼
  https://royal-        https://royal-
  komputer.netlify.app  backend.onrender.com
         │                  │
         └──────┬───────────┘
                ▼
         Custom domain
         (DNS: Netlify)
```

---

## Phase 5: Cleanup

After all 4 folders are populated and deployments are verified:

1. **Verify frontend on Netlify** — storefront works, product grid loads, status shows correctly
2. **Verify backend on Render** — admin login works, product management works
3. **Verify sync on local PC** — Task Scheduler runs successfully, git push works
4. **Verify Neon** — admin can add descriptions, they persist
5. **Delete `local-updater/`** — all files migrated
6. **Update `.gitignore`** — remove old local-updater entries
7. **Update `AGENTS.md` and `README.md`** — finalize paths

### Rollback Plan

If something breaks during migration:
- **Keep `local-updater/` intact** until everything is verified
- The old PHP storefront (`local-updater/index.php`) still works as a fallback
- Just switch Netlify base directory back to `local-updater/` temporarily

---

## Migration Order

```
Phase 1: Frontend (Vite)        ← YOU ARE HERE
   └── Scaffold Vite + rebuild storefront

Phase 2: Backend (PHP)
   └── Split PHP files, add API endpoints

Phase 3: Sync Agent
   └── Extract sync script, set up Task Scheduler

Phase 4: Cloud Deploy
   └── Neon schema → Render deploy → Netlify deploy

Phase 5: Cleanup
   └── Delete local-updater/, verify all working
```

---

## Configuration Strategy

### Environment Variables (12-Factor App Style)

```
# Local development — uses defaults in config.php
# No .env file needed

# Render (production) — set in Render dashboard
PGHOST=ep-xxx.us-east-2.aws.neon.tech
PGPORT=5432
PGDATABASE=royal
PGUSER=royal_owner
PGPASSWORD=secret123

# Future: config for image storage
# STORAGE=local          # local (uploads/) or s3 or r2
# WHATSAPP_NUMBER=...    # Configurable WhatsApp number
```

The `config.php` uses `getenv()` with fallbacks, so it works locally without any env vars set.

### Path Strategy for Shared Photos

Photos are uploaded via `backend/` (admin panel) but served via `frontend/` (storefront).

**Option A: Symlink** (simplest for local dev)
```bash
# In frontend/
mklink /D uploads ..\backend\uploads
```

**Option B: Copy on sync** (for cloud)
The sync agent writes `cache_produk.json` to `frontend/`, and photos are committed to git from `backend/uploads/`. Netlify deploys everything in `frontend/`.

**Option C: CDN** (future)
Upload photos to Cloudinary/Cloudflare R2, serve from CDN. Removes the need to store photos in git.

---

## Future Enhancements (Post-MVP)

| Feature | Why | Where |
|---------|-----|-------|
| **Phone number in config** | WhatsApp number shouldn't be hardcoded | `backend/config.php` |
| **Cloudinary/R2 for images** | Remove photos from git repo | `backend/upload` → CDN |
| **Admin API auth via JWT** | Replace session-based auth for API calls | `backend/` |
| **Order inquiries via API** | Let customers submit orders through the storefront | `backend/api_order.php` |
| **Multi-branch Neon** | Test schema changes before production | `database/` |
| **GitHub Actions CI** | Auto-deploy only changed packages | Root `.github/` |

---

## Development Principles

1. **No framework lock-in** — vanilla JS with Vite, plain PHP, simple SQL
2. **Incremental migration** — one folder at a time, don't break what works
3. **Backward compatibility** — old `local-updater/` stays until full migration
4. **Docker-free** — simple `php -S` and `npm run dev`
5. **Minimal dependencies** — only Vite + Tailwind CSS for frontend, no Composer for backend
6. **12-Factor config** — env vars for cloud, defaults for local deployment
