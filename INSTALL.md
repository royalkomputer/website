# Panduan Instalasi — Royal Komputer

Panduan lengkap menjalankan web app secara lokal (development) dan deploy ke VPS (production).

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Local Development (Docker)](#2-local-development-docker)
3. [Local Development (Manual)](#3-local-development-manual)
4. [VPS Production Deployment](#4-vps-production-deployment)
5. [Sync Agent Setup (Local PC)](#5-sync-agent-setup-local-pc)
6. [Troubleshooting](#6-troubleshooting)

---

## 1. Prerequisites

### Untuk Docker (Recommended)

| Software | Version | Install |
|----------|---------|---------|
| Docker | 24+ | `curl -fsSL https://get.docker.com \| sh` |
| Docker Compose | v2+ | Sudah termasuk di Docker |

### Untuk Manual (Tanpa Docker)

| Software | Version | Install |
|----------|---------|---------|
| PHP | 8.1+ | `sudo apt install php-cli php-pgsql php-gd php-mbstring php-curl` |
| Node.js | 20+ | `curl -fsSL https://deb.nodesource.com/setup_20.x \| sudo -E bash - && sudo apt install nodejs` |
| PostgreSQL | 16 | `sudo apt install postgresql` |

---

## 2. Local Development (Docker)

### Clone & Jalankan

```bash
# Clone repository
git clone <repo-url>
cd website

# Copy environment file
cp .env.example .env

# Build & start semua services
docker compose up -d --build
```

### Status Services

| Service | URL | Internal Port |
|---------|-----|---------------|
| Frontend (Store) | `http://localhost:80` | 80 |
| Backend (Admin) | `http://localhost/login.php` | 80 (via frontend proxy) |
| PostgreSQL | `localhost:5432` (via Docker network) | 5432 |

### Login Admin

```
URL:      http://localhost/login.php
Username: superadmin
Password: royal2026
```

### Perintah Berguna

```bash
# Lihat status container
docker compose ps

# Lihat logs (semua service)
docker compose logs -f

# Lihat logs satu service
docker compose logs -f backend

# Stop semua service
docker compose down

# Stop + hapus data (fresh start)
docker compose down -v

# Rebuild satu service
docker compose up -d --build backend

# Akses shell container backend
docker compose exec backend bash

# Akses database
docker compose exec db psql -U royal_owner -d royalkomputer
```

### Data Persistence

| Volume | Isi | Location |
|--------|-----|----------|
| `postgres_data` | Database PostgreSQL | `db:/var/lib/postgresql/data` |
| `backend_uploads` | Foto produk (WEBP) | `backend:/opt/app/uploads` + `frontend:/usr/share/nginx/html/uploads:ro` |
| `backend_data` | JSON config (admins, schedules, dll) | `backend:/opt/app/data` |

---

## 3. Local Development (Manual)

Tanpa Docker, cocok untuk development cepat tanpa build container.

### Backend (PHP Built-in Server)

```bash
cd backend
php -S localhost:8081
```

Akses:
- Admin: `http://localhost:8081/login.php`
- API: `http://localhost:8081/api_produk.php`

### Frontend (Vite Dev Server)

```bash
cd frontend
npm install
npm run dev
```

Akses: `http://localhost:5173`

Vite akan proxy API calls ke backend (`localhost:8081`).

### Frontend (PHP Fallback)

```bash
cd frontend
php -S localhost:8080
```

Akses: `http://localhost:8080`

### Database Config

Edit `backend/config.php` bagian database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'royalkomputer');
define('DB_USER', 'royal_owner');
define('DB_PASS', 'your_password');
```

Atau set environment variables:

```bash
export DB_HOST=localhost
export DB_PORT=5432
export DB_NAME=royalkomputer
export DB_USER=royal_owner
export DB_PASS=your_password
```

---

## 4. VPS Production Deployment

### Arsitektur

```
Browser ──► VPS (Ubuntu 22.04+)
                │
                ▼
          Coolify (Caddy Reverse Proxy)
          ──► Auto HTTPS (Let's Encrypt)
                │
        ┌───────┼───────┐
        ▼       ▼       ▼
   Frontend  Backend  PostgreSQL
   (Nginx)  (PHP-FPM) (Port 5432)
    :80      :80      (internal)
```

### Step 1: Persiapan VPS

```bash
# Buat VPS (DigitalOcean, Hetzner, dll)
# Minimum: Ubuntu 22.04+, 2GB RAM

# SSH ke VPS
ssh root@IP_VPS
```

### Step 2: Install Coolify

```bash
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
```

Setelah selesai, akses: `http://IP_VPS:8000` untuk buat akun admin Coolify.

### Step 3: Connect Git Repository

1. Coolify Dashboard → **Sources** → **GitHub/GitLab**
2. Install Coolify GitHub App
3. Grant access ke repository `royalkomputer/website`

### Step 4: Buat Project

1. Coolify Dashboard → **Projects** → **New Project**
2. Pilih **Application** → **Docker Compose**
3. Pilih repository `royalkomputer/website` → branch `main`
4. Coolify auto-detect `docker-compose.yml`

### Step 5: Environment Variables

Di Coolify Dashboard → **Environment Variables**:

```
DB_PASSWORD=your-secure-password
APP_ENV=production
```

Opsional (untuk git backup dari backend):

```
GIT_TOKEN=github_pat_xxxxxxxxxxxx
GIT_REPO_URL=https://github.com/royalkomputer/website.git
```

### Step 6: Domain & HTTPS

1. Settings → **Domain** → masukkan domain (contoh: `royalkomputer.com`)
2. Coolify (via Caddy) auto-provisions **Let's Encrypt SSL**

### Step 7: Deploy

Klik **Deploy** di Coolify. Proses:
1. Build `frontend/Dockerfile` (Node → Nginx)
2. Build `backend/Dockerfile` (PHP-FPM + Nginx)
3. Pull `postgres:16-alpine`
4. Health check semua service
5. Hot swap via Caddy

### Step 8: Verifikasi

```bash
# Cek health check
curl https://domain.com/ping
# Expected: pong

# Cek storefront
curl -s -o /dev/null -w "%{http_code}" https://domain.com
# Expected: 200

# Cek API
curl https://domain.com/api_produk.php | head -c 200
# Expected: {"data":[...],...}
```

### Login Admin (Production)

```
URL:      https://domain.com/login.php
Username: superadmin
Password: royal2026 (ganti setelah login pertama!)
```

---

## 5. Sync Agent Setup (Local PC)

Sync agent berjalan di PC toko (Windows) via Task Scheduler. Setiap 1 jam, mengambil data dari IPOS dan push ke git.

### Prerequisites

- PHP 8.x terinstall di Windows
- Git terinstall dengan credential caching
- Akses ke database IPOS PostgreSQL (`192.168.18.189:5444`)

### Install PHP di Windows

```powershell
# Via winget
winget install PHP.PHP

# Enable extensions (edit php.ini)
; extension=pgsql
; extension=mbstring
```

### Setup Task Scheduler

Jalankan sebagai **Administrator**:

```powershell
powershell -ExecutionPolicy Bypass -File sync\setup_scheduler.ps1`
```

Ini akan membuat task "RoyalKomputer Sync" yang berjalan setiap 1 jam.

### Manual Run

```bash
cd sync
php update_produk.php --once
```

### File yang Di-generate

| File | Lokasi | Deskripsi |
|------|--------|-----------|
| `cache_produk.json` | `sync/` + `frontend/` | Cache data produk |
| `last_sync.json` | `sync/` + `backend/data/` | Status sync terakhir |
| `sync.log` | `sync/` | Log sync |

### Sync Flow

```
IPOS PostgreSQL (192.168.18.189:5444)
    │
    ▼
sync/update_produk.php
    │
    ├── 1. Query produk dengan stok > 0
    ├── 2. Generate cache_produk.json
    ├── 3. Generate cache_*.json (aset, hutang, penghasilan)
    ├── 4. Write last_sync.json
    └── 5. Log ke sync.log
         │
         ▼
    sync/git_push.bat
    (git add → commit → push)
         │
         ▼
    Git Repository (GitHub)
         │
         ▼
    VPS (Coolify auto-deploy)
```

---

## 6. Troubleshooting

### Docker

| Masalah | Solusi |
|---------|--------|
| `docker compose up` gagal build | Cek RAM (min 2GB). Build Node.js butuh memory |
| Port 80 sudah dipakai | `sudo lsof -i :80` lalu hentikan process |
| Database tidak connect | Pastikan container `db` healthy: `docker compose ps` |
| Login "username atau password salah" | Reset hash: `docker compose exec db psql -U royal_owner -d royalkomputer -c "UPDATE admins SET password_hash = '\$2y\$10\$Q6I5JUyogQq8uJtOu/BrH.HhtKUL7l/b/UonmVcQexE9dNtl7bUhq' WHERE username = 'superadmin';"` |

### Frontend

| Masalah | Solusi |
|---------|--------|
| Produk tidak muncul | Buka browser console (F12), cek error `ERR_NAME_NOT_RESOLVED` |
| Gambar tidak load | Cek uploads volume: `docker compose exec backend ls /opt/app/uploads/` |
| API error 500 | Cek backend logs: `docker compose logs backend` |

### Backend

| Masalah | Solusi |
|---------|--------|
| `pg_connect()` fatal error | Pastikan extension `php-pgsql` terinstall |
| Foto tidak ter-upload | Cek permissions: `docker compose exec backend chmod -R 755 /opt/app/uploads` |
| Config tidak tersimpan | Cek `backend/data/` writable: `docker compose exec backend ls -la /opt/app/data/` |

### Sync Agent

| Masalah | Solusi |
|---------|--------|
| `Could not connect to database` | Pastikan IPOS PostgreSQL running dan accessible dari jaringan lokal |
| Git push gagal | Pastikan credential caching: `git config --global credential.helper manager` |
| Task Scheduler tidak jalan | Buka Task Scheduler → cari "RoyalKomputer Sync" → cek status |
