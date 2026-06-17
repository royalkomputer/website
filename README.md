# Royal Komputer — Online Marketplace

A single-store e-commerce marketplace for **Royal Komputer**, a computer hardware store in Kediri, East Java, Indonesia.

---

## Architecture Overview

```
┌────────────────────────────────────────────────────────────────────┐
│                        LOCAL PC (Toko Kediri)                      │
│                                                                    │
│  IPOS ──► local-updater (Task Scheduler, every 1hr) ──► git push │
└─────────────────────────────────┬──────────────────────────────────┘
                                  │
                                  ▼
┌────────────────────────────────────────────────────────────────────┐
│              ┌──────────┐  ┌──────────┐  ┌───────────────────┐   │
│              │ Netlify  │  │  Render  │  │      Neon         │   │
│              │ Frontend │  │ Backend  │  │ Cloud PostgreSQL  │   │
│              └──────────┘  └──────────┘  └───────────────────┘   │
│                          Auto-deploy from git                     │
└────────────────────────────────────────────────────────────────────┘
```

The system has a **hybrid local/cloud architecture**:
1. **IPOS** POS software writes product data to a local PostgreSQL database
2. **local-updater** scripts (scheduled via Windows Task Scheduler every hour) pull data and push to git
3. **Netlify** (frontend), **Render** (backend), and **Neon** (database) deploy from the git repo

---

## Features

### Public Storefront (`index.php`)
- Product catalog with search, category/condition filters, and price sorting
- Real-time store open/closed status (operating hours, scheduled closures, manual override)
- Product detail modal with image carousel
- WhatsApp order integration
- Mobile-responsive design
- Fallback to cached data when database is unavailable

### Admin Dashboard (`admin.php`)
- Product catalog management (descriptions, photo upload/reorder/delete, WEBP auto-conversion)
- Operating hours configuration (per-day, super admin only)
- Temporary closure scheduling
- Manual store status override
- Multi-role admin management (super admin + regular admin)
- Profile editing (self-service)

---

## Tech Stack

| Technology | Usage |
|------------|-------|
| PHP 8.x | Backend application logic |
| PostgreSQL (`tbl_item`, `tbl_itemstok`, `tbl_web_deskripsi`) | Product inventory database |
| Tailwind CSS (CDN) | UI styling |
| Font Awesome 6 | Icons |
| JavaScript Vanilla ES6+ | Client-side interactivity |
| WEBP | Product image format |
| GD Library | Image processing (JPEG/PNG → WEBP) |
| bcrypt (`password_hash`/`password_verify`) | Password hashing |
| JSON (flat files) | Configuration storage |

---

## Monorepo Structure

The project uses a **4-folder monorepo** layout at the root level. Each folder maps directly to a deployment target or environment.

```
royal-website/              # Git repo root
├── database/               # → NEON: PostgreSQL schema + migrations
│   ├── schema.sql          #   Full schema DDL
│   └── migrations/         #   Incremental DB migrations
│       └── 001_initial.sql
│
├── frontend/               # → NETLIFY: public storefront
│   ├── index.php           #   Public storefront
│   ├── api_produk.php      #   Public product API
│   ├── cache_produk.json   #   Product cache fallback
│   ├── uploads/            #   Product photos (WEBP)
│   ├── logo/               #   Brand assets
│   └── netlify.toml        #   Netlify config
│
├── backend/                # → RENDER: admin panel + API
│   ├── admin.php           #   Admin dashboard
│   ├── login.php           #   Admin login
│   ├── logout.php          #   Logout
│   ├── config.php          #   Core config & helpers
│   ├── update_produk.php   #   Product update + photo upload API
│   ├── update_admin.php    #   Admin CRUD + schedules + status
│   ├── update_jam.php      #   Operating hours API
│   ├── api_manage_photos.php #   Photo delete/reorder
│   ├── data/               #   JSON config storage
│   │   ├── admins.json
│   │   ├── jam_operasional.json
│   │   ├── jadwal_tutup.json
│   │   ├── status_toko.txt
│   │   └── cache_produk.json
│   ├── uploads/            #   Admin photo upload target
│   └── render.yaml         #   Render config
│
├── sync/                   # → LOCAL PC: IPOS data sync agent
│   ├── update_produk.php   #   IPOS sync script
│   ├── config.php          #   DB config (local IPOS)
│   ├── cache_produk.json   #   Generated cache
│   └── git_push.bat        #   Git commit + push
│
├── dev.bat                 # Windows: start local PHP servers
├── dev.sh                  # Unix: start local PHP servers
├── AGENTS.md               # AI agent instructions
├── README.md               # This file
├── JOURNAL.md              # Development journal
├── .gitignore
└── LICENSE
```

### Deployment Mapping

| Folder   | Deploys To | Service |
|----------|------------|---------|
| `database/` | **Neon** | Cloud PostgreSQL — run schema.sql on first deploy |
| `frontend/` | **Netlify** | Public storefront — set base directory to `frontend/` |
| `backend/`  | **Render** | Admin backend — set root directory to `backend/` |
| `sync/`     | **Local PC** | IPOS sync agent — Windows Task Scheduler runs scripts here |

### Migration Note

Code currently lives in `local-updater/`. The 4-folder layout above is the **target structure**. Files will be migrated one folder at a time as the codebase is separated.

---

## Cloud Deployment

This project deploys via git to three services:

### Netlify (Frontend)
- Hosts the storefront
- Domain/CDN layer
- Auto-deploys from git `main` branch

### Render (Backend)
- Hosts the PHP admin panel and API
- PHP runtime environment
- Auto-deploys from git

### Neon (Database)
- Serverless PostgreSQL
- Schema mirrors local database structure:
  - `tbl_item` — product data
  - `tbl_itemstok` — stock data
  - `tbl_web_deskripsi` — custom descriptions (auto-created if missing)

---

## Local Development Setup

### Requirements
- PHP 8.0+
- PostgreSQL (local or remote)
- GD Library extension
- Session support

### Quick Start

1. **Clone the repo**
   ```bash
   git clone <repo-url>
   cd royal-website
   ```

2. **Configure database credentials**
   
   Edit `local-updater/config.php`:
   ```php
   define('DB_HOST', 'your_host');
   define('DB_PORT', '5444');
   define('DB_NAME', 'i4_ROYAL');
   define('DB_USER', 'your_user');
   define('DB_PASS', 'your_password');
   ```

3. **Deploy to a PHP web server**
   
   Serve the `local-updater/` directory with Apache/Nginx/PHP built-in server:
   ```bash
   cd local-updater
   php -S localhost:8000
   ```

4. **Access the storefront**
   
   Open `http://localhost:8000/index.php` in a browser.

5. **Login to admin**
   
   Navigate to `http://localhost:8000/login.php`
   
   Default credentials:
   - Username: `superadmin`
   - Password: `royal2026`

6. **Ensure writable directories**
   - `uploads/` must be writable by the web server for photo uploads
   - All JSON files must be writable for config changes

---

## Running the Local Sync Agent

The `local-updater` syncs product data from the local IPOS database:

### Manual Run
```bash
cd local-updater
php update_produk.php
```

### Automatic (Windows Task Scheduler)
Create a task that runs every 1 hour:
- Program: `php`
- Arguments: `C:\path\to\local-updater\update_produk.php`
- Start in: `C:\path\to\local-updater\`

---

## API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `api_produk.php` | GET | None | Returns all products with stock > 0 |
| `update_produk.php` | POST | Session | Update product description + photos |
| `update_admin.php` | POST | Session | Admin CRUD, schedules, status (multi-action) |
| `update_jam.php` | POST | Super Admin | Update operating hours |
| `api_manage_photos.php` | POST | Session | Delete or reorder product photos |

See [AGENTS.md](AGENTS.md) for detailed API documentation and examples.

---

## License

Proprietary — Royal Komputer Kediri
