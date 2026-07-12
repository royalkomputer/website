# Royal Komputer — Online Marketplace

A single-store e-commerce marketplace for **Royal Komputer**, a computer hardware store in Kediri, East Java, Indonesia.

---

## Architecture Overview

```
┌──────────────────────────────────────────────────────────────────┐
│                     LOCAL PC (Toko Kediri)                        │
│  IPOS ──► sync/ (Task Scheduler, every 1hr) ──► git push        │
└───────────────────────────┬──────────────────────────────────────┘
                            │ git push
                            ▼
┌──────────────────────────────────────────────────────────────────┐
│                     LINUX VPS (Coolify + Docker)                  │
│                                                                   │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐   │
│  │  Frontend    │  │   Backend    │  │   Database (db)      │   │
│  │  Nginx       │  │  Nginx + PHP │  │   PostgreSQL 16      │   │
│  │  Vite SPA    │  │  8.2-FPM     │  │   :5432              │   │
│  └──────┬───────┘  └──────┬───────┘  └──────────┬───────────┘   │
│         │                 │                      │               │
│         └─────────────────┼──────────────────────┘               │
│                           │                                      │
│                  ┌────────┴────────┐                             │
│                  │    Coolify      │                             │
│                  │ (Caddy Proxy)   │                             │
│                  │ Auto HTTPS      │                             │
│                  └─────────────────┘                             │
└──────────────────────────────────────────────────────────────────┘
```

The system has a **hybrid local/cloud architecture**:
1. **IPOS** POS software writes product data to a local PostgreSQL database
2. **sync/** scripts (scheduled via Windows Task Scheduler every hour) pull data and push to git
3. **Linux VPS** runs all three services via Docker Compose, managed by Coolify:
   - **Frontend:** Nginx serving the Vite-built SPA
   - **Backend:** Nginx + PHP 8.2-FPM serving the admin panel and API
   - **Database:** PostgreSQL 16 for all product and config data

---

## Features

### Public Storefront
- Product catalog with search, category/condition filters, and price sorting
- Real-time store open/closed status (operating hours, scheduled closures, manual override)
- Product detail modal with image carousel
- WhatsApp order integration
- Mobile-responsive design
- Fallback to cached data when database is unavailable

### Admin Dashboard
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
| PostgreSQL | Product inventory + config data |
| Vite + Vanilla JS | Frontend SPA |
| Tailwind CSS v4 | UI styling |
| Font Awesome 6 | Icons |
| Nginx | Web server (frontend + backend) |
| PHP-FPM | PHP process manager |
| Docker | Containerization |
| Coolify | Self-hosted PaaS (deployment management) |
| WEBP | Product image format |
| GD Library | Image processing (JPEG/PNG → WEBP) |
| bcrypt | Password hashing |
| JSON (flat files) | Configuration storage |

---

## Monorepo Structure

The project uses a **4-folder monorepo** layout. Each folder maps to a Docker service or local environment.

```
royal-website/              # Git repo root
├── database/               # → DB CONTAINER: schema + migrations
│   ├── schema.sql          #   Full schema DDL
│   ├── init.sql            #   DB init script (for Docker entrypoint)
│   └── migrations/         #   Incremental DB migrations
│
├── frontend/               # → FRONTEND CONTAINER: Vite SPA + Nginx
│   ├── src/                #   JavaScript source (Vite)
│   ├── Dockerfile          #   Multi-stage build (Node → Nginx)
│   ├── nginx.conf          #   Nginx configuration
│   ├── vite.config.js      #   Vite build config
│   ├── package.json        #   Node dependencies
│   ├── logo/               #   Brand assets
│   └── netlify.toml        #   [DEPRECATED — was for Netlify]
│
├── backend/                # → BACKEND CONTAINER: PHP-FPM + Nginx
│   ├── admin.php           #   Admin dashboard
│   ├── login.php           #   Admin login
│   ├── config.php          #   Core config & helpers
│   ├── *.php               #   API endpoints
│   ├── Dockerfile          #   PHP 8.2-FPM + Nginx + Supervisor
│   ├── nginx.conf          #   Nginx configuration
│   ├── supervisord.conf    #   Supervisor config
│   ├── data/               #   JSON config storage
│   ├── uploads/            #   Product photos (persistent volume)
│   └── render.yaml         #   [DEPRECATED — was for Render]
│
├── sync/                   # → LOCAL PC: IPOS data sync agent
│   ├── update_produk.php   #   IPOS sync script
│   ├── config.php          #   DB config (local IPOS)
│   ├── cache_produk.json   #   Generated cache
│   └── git_push.bat        #   Git commit + push
│
├── docker-compose.yml      # Docker Compose for VPS deployment
├── .env.example            # Environment variable template
├── dev.bat                 # Windows: start local PHP servers
├── dev.sh                  # Unix: start local PHP servers
├── AGENTS.md               # AI agent instructions
├── README.md               # This file
└── .gitignore
```

### Service Mapping

| Folder   | Docker Service | Container | Port Internal |
|----------|---------------|-----------|---------------|
| `frontend/` | `frontend` | Nginx + Vite SPA | 80 |
| `backend/` | `backend` | Nginx + PHP 8.2-FPM | 80 |
| `database/` | `db` | PostgreSQL 16 | 5432 |
| `sync/` | *(local only)* | Windows Task Scheduler | — |

---

## VPS + Docker Deployment

### Prerequisites

- **VPS** with Ubuntu 22.04+, minimum 2GB RAM
- **Domain** pointed to VPS IP (for HTTPS)
- **GitHub/GitLab** repository with the project code
- Coolify installed on the VPS

### Architecture Detail

```
Browser ──► https://domain.com
                │
                ▼
          Coolify (Caddy Proxy)
          ──► Auto HTTPS (Let's Encrypt)
          ──► Route ke container frontend:80
                │
                ▼
          Frontend Nginx
          ├── /logo/*         → serve static dari dist/
          ├── /uploads/*      → serve dari volume bersama
          ├── /api_*          → proxy ke backend:80
          ├── /admin*         → proxy ke backend:80
          ├── /login*         → proxy ke backend:80
          ├── /update_*       → proxy ke backend:80
          ├── /api_manage_*   → proxy ke backend:80
          ├── /data/*         → proxy ke backend:80
          ├── /index.html     → SPA (Vite build)
          └── /*              → SPA fallback to index.html
                │
                ▼
          Backend Nginx
          ├── /ping           → 200 OK (health check untuk Coolify)
          ├── /uploads/*      → serve langsung (volume bersama)
          ├── /data/*         → serve langsung (JSON config)
          ├── /logo/*         → serve langsung
          └── *.php           → FastCGI → PHP-FPM (port 9000)
                                    │
                                    ▼
                              PostgreSQL (service: db, port: 5432)
```

### Docker Compose Services

The project uses **3 services** defined in `docker-compose.yml`:

| Service | Image Source | Depends On | Volumes |
|---------|-------------|------------|---------|
| `db` | `postgres:16-alpine` | — | `postgres_data`, `db-init` |
| `backend` | `./backend/Dockerfile` | `db` (health check) | `backend_uploads`, `backend_data` |
| `frontend` | `./frontend/Dockerfile` | `backend` | `backend_uploads` (read-only) |

### Environment Variables

Set these in Coolify dashboard (not in `.env` — they're injected at runtime):

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_PASSWORD` | `royalkomputer2026` | PostgreSQL password |
| `APP_ENV` | `production` | Application environment |
| `GIT_TOKEN` | — | GitHub token (for git backup from backend) |
| `GIT_REPO_URL` | — | Repository URL (for git backup) |

### Data Persistence (Docker Volumes)

| Volume | Mount Point | Contents |
|--------|-------------|----------|
| `postgres_data` | `db:/var/lib/postgresql/data` | Database files |
| `backend_uploads` | `backend:/opt/app/uploads`, `frontend:/usr/share/nginx/html/uploads:ro` | Product photos (WEBP) |
| `backend_data` | `backend:/opt/app/data` | JSON config (admins, schedules, etc.) |

### Health Check Endpoints

| Service | Endpoint | Expected |
|---------|----------|----------|
| `db` | `pg_isready` | exit 0 |
| `backend` | `GET /ping` | HTTP 200 "pong" |
| `frontend` | `GET /` | HTTP 200 (SPA) |

---

## Coolify Setup Guide

### Step 1: Install Coolify on VPS

```bash
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
```

Setelah selesai, akses `http://IP_VPS:8000` untuk membuat akun admin Coolify.

### Step 2: Connect GitHub/GitLab

1. Coolify Dashboard → **Sources** → **GitHub** (or GitLab)
2. Install Coolify GitHub App
3. Grant access to the repository

### Step 3: Create Docker Compose Project

1. Coolify Dashboard → **Projects** → **New Project**
2. Pilih **Application** → **Docker Compose**
3. Pilih repository `royalkomputer/website` → branch `main`
4. Coolify auto-detects `docker-compose.yml` at root

### Step 4: Configure Environment Variables

Coolify Dashboard → **Environment Variables** untuk project:
```
DB_PASSWORD=your-secure-password
APP_ENV=production
```

### Step 5: Set Domain & HTTPS

1. Settings → **Domain** → masukkan `royalkomputer.com`
2. Coolify (via Caddy) auto-provisions **Let's Encrypt SSL**

### Step 6: Deploy

Klik **Deploy**. Coolify akan:
1. Build `frontend/Dockerfile` (Node build → Nginx)
2. Build `backend/Dockerfile` (PHP + Nginx + Supervisor)
3. Pull `postgres:16-alpine`
4. Wait for all health checks to pass
5. Hot-swap via Caddy reverse proxy

---

## Zero-Downtime Deployment Flow

```
1. Git Push ──► 2. Webhook ──► 3. Git Pull
                                   │
                                   ▼
                   4. Ephemeral Build Container
                      (isolasi build, tidak ganggu live)
                                   │
                                   ▼
                   5. New Container (immutable image)
                                   │
                                   ▼
                   6. Health Check (HTTP 200?)
                          │ Yes        │ No
                          ▼            ▼
                  7. Hot Swap      Rollback
                   (Caddy)         (cancel deploy)
                          │
                          ▼
                   8. Seamless Transition
                      (pengguna tidak merasakan downtime)
                          │
                          ▼
                   9. Graceful Teardown
                      (tunggu koneksi lama selesai)
```

Agar graceful teardown berjalan sempurna:
- **Jangan simpan session login di memori kontainer** — gunakan database (session sudah via PHP session files, tidak masalah)
- Data config disimpan di **Docker volumes** (persistent meski container diganti)

---

## Local Development Setup

### Requirements
- PHP 8.0+
- PostgreSQL (local or remote)
- GD Library extension
- Session support
- Node.js 20+ (untuk Vite frontend)

### Quick Start

1. **Clone the repo**
   ```bash
   git clone <repo-url>
   cd royal-website
   ```

2. **Configure database credentials**

   Edit `backend/config.php`:
   ```php
   define('DB_HOST', 'your_host');
   define('DB_PORT', '5432');
   define('DB_NAME', 'royalkomputer');
   define('DB_USER', 'your_user');
   define('DB_PASS', 'your_password');
   ```

3. **Start development servers**

   ```bash
   # Terminal 1: Backend (admin panel + API)
   cd backend && php -S localhost:8081

   # Terminal 2: Frontend (Vite dev server dengan proxy)
   cd frontend && npm run dev
   ```

4. **Access the storefront**

   Vite dev: `http://localhost:5173` (API proxied to backend:8081)

5. **Login to admin**

   Navigate to `http://localhost:8081/login.php`

   Default credentials:
   - Username: `superadmin`
   - Password: `royal2026`

6. **Ensure writable directories**
   - `backend/uploads/` must be writable for photo uploads
   - `backend/data/` must be writable for JSON config changes

### Docker Local Dev (Alternative)

```bash
# Build and run all services locally
docker compose up --build

# Or run specific service
docker compose up -d db backend
```

---

## Running the Local Sync Agent

The `sync/` agent runs on the local Windows PC at the store, pulling product data from IPOS:

### Manual Run
```bash
cd sync
php update_produk.php
```

### Automatic (Windows Task Scheduler)
Create a task that runs every 1 hour:
- **Action 1:** `php C:\path\to\royal-website\sync\update_produk.php --once`
- **Action 2:** `C:\path\to\royal-website\sync\git_push.bat`
- **Start in:** `C:\path\to\royal-website\sync\`

Atau jalankan `sync/setup_scheduler.ps1` sebagai Administrator (sekali saja).

---

## API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `api_produk.php` | GET | None | Returns all products with stock > 0 |
| `api_status.php` | GET | None | Store open/closed status |
| `api_banner.php` | GET | None | Banner playlists |
| `api_schedules.php` | GET | None | Closure schedules |
| `api_hutang.php` | POST | Session | Outstanding debt report |
| `api_manage_photos.php` | POST | Session | Delete or reorder product photos |
| `update_produk.php` | POST | Session | Update product description + photos |
| `update_admin.php` | POST | Session | Admin CRUD, schedules, status |
| `update_jam.php` | POST | Super Admin | Update operating hours |

See [AGENTS.md](AGENTS.md) for detailed API documentation and examples.

---

## License

Proprietary — Royal Komputer Kediri
