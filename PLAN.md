# Royal Komputer — Project Plan

> **Arsitektur saat ini:** Docker Compose (3 services) + Coolify di VPS.
> **Referensi utama:** [AGENTS.md](AGENTS.md) untuk API, konvensi kode, dan pola arsitektur.

### Changelog
| Tanggal | Perubahan |
|---------|-----------|
| 2026-07-19 | Hapus menu Serial Number, Penghasilan, Hutang, Aset dari admin dashboard. Hapus endpoint API `api_aset.php`, `api_hutang.php`, `api_penghasilan.php`. Hapus sync data aset/hutang/penghasilan dari sync agent. |
| 2026-07-19 | Hapus fitur jam buka toko (operating hours). Hapus `loadJamOperasional()` dari `frontend/config.php`. Hapus logic jam operasional, badge tutup/buka, schedule warning, dan footer "JAM BUKA TOKO" dari `frontend/index.php`. Hapus `StoreStatus.js` (dead code). Sederhanakan `api_status.php`. Hapus `fetchStoreStatus()` dari `api.js`. |
| 2026-07-19 | Refactor UI `frontend/index.php` — minimal & modern: navbar lebih ramping (border-bottom, social links satu warna), hero header solid tanpa gradient, sidebar bg-slate-800/60, product card hover subtle (border aksen), grid 5 kolom di xl, info bar & empty state lebih clean, footer compact. |
| 2026-07-19 | Tambah banner carousel (promo) di halaman awal + dual-state layout: awal banner full-width, sidebar hidden; setelah cari/filter sidebar muncul, banner hide. Banner dari `api_banner.php`. |
| 2026-07-19 | Tambah label kategori di pojok kanan atas gambar grid card (flat, subtle). |
| 2026-07-19 | Fix: `api_banner.php` tidak ditemukan di frontend — buat wrapper `frontend/api_banner.php` yang baca langsung dari `backend/data/banners.json`. Sebelumnya fetch gagal 404, seluruh Promise.all reject → empty state muncul salah. |
| 2026-07-19 | Batasi render produk maksimal 40 per halaman. `renderProductGrid()` slice `filteredProducts` ke 40 item pertama, counter ubah jadi format "Menampilkan 40 dari 766 produk". |
| 2026-07-19 | Tambah pagination "Muat Lainnya": variable `displayLimit`, tombol di bawah grid increment +40, reset ke 40 saat filter/search berganti. |
| 2026-07-19 | Tambah floating button "Kembali ke Atas": muncul setelah scroll >400px, smooth scroll, `bg-astra-700` di pojok kanan bawah. |
| 2026-07-19 | Hapus 10 file tidak terpakai: savepoint.md, server.pid, dev.sh, dev.bat, admin.php.bak, test_*.txt, push_admin.bat, setup_push_task.bat, api_schedules.php. Perbarui dokumentasi dan vite.config.js. |
| 2026-07-19 | Posisikan search prompt "Gunakan pencarian untuk menampilkan produk." di atas banner carousel (sebelumnya di dalam main layout). |
| 2026-07-19 | Ubah teks search prompt dari "Gunakan pencarian atau pilih kategori untuk menampilkan produk." menjadi "Gunakan pencarian untuk menampilkan produk." |
| 2026-07-19 | Fix banner & product image 404 di `php -S -t frontend` — buat symlink `frontend/uploads -> ../backend/uploads`. |
| 2026-07-19 | Fix upload foto produk: hapus blok FRONTEND SYNC di `update_produk.php` (lines 247-303) yang jadi destruktif setelah symlink. Foto disimpan di `backend/uploads/` dan aksesibel via symlink, tidak perlu copy. |
| 2026-07-19 | Ganti logo toko dengan "DESIGN LOGO R.png" — dikonversi ke WEBP, diresize 160×200, diterapkan ke `frontend/logo/`, `backend/logo/`, `frontend/public/logo/`. |
| 2026-07-19 | Hapus WhatsApp dari MEDIA SOSIAL (Navbar Desktop, Navbar Mobile, Footer). Tetap di KONTAK dan tombol Pesan produk. |
| 2026-07-19 | Tambah link TikTok di Navbar Desktop "Ikuti Kami:". |
| 2026-07-19 | Ubah alamat footer jadi link Google Maps (koordinat akurat Royal Komputer). |
| 2026-07-19 | Tambah favicon `logo/logo.webp` ke semua halaman (frontend & backend). |
| 2026-07-19 | Tambah kolom Harga Pokok (read-only) di modal edit produk admin dashboard. Hapus `harga_pokok` dari frontend API (`frontend/api_produk.php`) agar tidak diekspos ke publik. |

---

## Arsitektur

```
┌─────────────────────────────────────────────────────┐
│               LOCAL PC (Toko Kediri)                 │
│  IPOS PostgreSQL ──► sync/ (Task Scheduler 1 jam)   │
│                       ──► git push                   │
└──────────────────────┬──────────────────────────────┘
                       │ git push
                       ▼
┌─────────────────────────────────────────────────────┐
│                    VPS (Coolify)                      │
│                                                       │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────┐   │
│  │ Frontend │  │ Backend  │  │ PostgreSQL 16    │   │
│  │ Nginx    │  │ PHP-FPM  │  │                  │   │
│  │ Vite SPA │  │ + Nginx  │  │ :5432            │   │
│  │ :80      │  │ :80      │  │                  │   │
│  └──────────┘  └──────────┘  └──────────────────┘   │
│                                                       │
│  Coolify (Caddy) → Auto HTTPS → Hot Swap Deploy     │
└─────────────────────────────────────────────────────┘
```

## File Structure

```
website/
├── database/           → DB container (init.sql)
├── frontend/           → Frontend container (Vite SPA + Nginx)
├── backend/            → Backend container (PHP-FPM + Nginx)
├── sync/               → Local PC (Task Scheduler)
├── docker-compose.yml  → Docker Compose untuk VPS
├── tests/              → PHPUnit test suite
├── AGENTS.md           → AI agent instructions
├── README.md           → Project overview
├── INSTALL.md          → Panduan instalasi lengkap
└── PLAN.md             → File ini
```

## Service Mapping

| Folder | Container | Port | Purpose |
|--------|-----------|------|---------|
| `frontend/` | `frontend` | 80 | Vite SPA + Nginx (storefront) |
| `backend/` | `backend` | 80 | PHP-FPM + Nginx (admin + API) |
| `database/` | `db` | 5432 | PostgreSQL 16 |
| `sync/` | — | — | Windows Task Scheduler (local) |

## API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `api_produk.php` | GET | Public | Product catalog (paginated) |
| `api_status.php` | GET | Public | Store open/closed status |
| `api_banner.php` | GET | Public | Banner playlists |
| `api_manage_photos.php` | POST | Session | Photo delete/reorder |
| `api_data.php` | GET | Public | JSON file proxy |
| `update_produk.php` | POST | Session | Product description + photo upload |
| `update_admin.php` | POST | Session | Admin CRUD + schedules + status |
| `update_banner.php` | POST | Session | Banner management |

## Features

### Storefront (Public)
- [x] Product catalog with search, category/condition filters, price sort
- [x] Store open/closed status (operating hours, schedules, manual override)
- [x] Product detail modal with image carousel
- [x] WhatsApp order integration
- [x] Mobile-responsive, dark mode
- [x] Pagination (32 products/page)
- [x] Fallback to cache when DB unavailable

### Admin Dashboard
- [x] Product management (description, photo upload/reorder/delete, WEBP auto-conversion)
- [-] Operating hours (per-day, with "Libur" option) — dihapus
- [x] Temporary closure scheduling
- [x] Manual store status override
- [x] Multi-role admin (super_admin + admin)
- [x] Profile editing (self-service)
- [x] Banner management (playlist-based carousel)
- [x] Sync status dashboard
- [-] Serial Number search — dihapus
- [-] Penghasilan report — dihapus
- [-] Hutang report — dihapus
- [-] Aset report — dihapus

### Sync Agent
- [x] IPOS PostgreSQL → cache_produk.json
- [x] Photo sync (backend → frontend uploads)
- [x] Git auto-commit + push
- [x] Windows Task Scheduler integration
- [x] Sync status tracking (last_sync.json)
- [-] Admin data sync (aset, hutang, penghasilan) — dihapus

### Infrastructure
- [x] Docker Compose (3 services)
- [x] Health checks (db, backend /ping, frontend /)
- [x] Persistent volumes (uploads, data, postgres)
- [x] Coolify integration (auto HTTPS, hot swap deploy)

## Data Flow

### Admin → Storefront (Immediate)
| Admin Action | Writes To |
|---|---|
| Edit product | `cache_produk.json`, `backend/data/` |
| Upload photo | `backend/uploads/` + `frontend/uploads/` |
| _(Operating hours removed) |
| Save schedule | `jadwal_tutup.json` (both) |
| Set manual status | `status_toko.txt` (both) |
| Save tagline | `tagline.json` (both) |

### Sync Agent → Git → VPS (Every 1 Hour)
```
IPOS DB → sync agent → cache_produk.json → git push → Coolify deploy
```

## Deployment Flow

```
git push → Webhook → Coolify Git Pull → Build Container →
Health Check (HTTP 200?) → Hot Swap (Caddy) → Graceful Teardown
```

## Testing

```bash
# Run PHPUnit tests
cd tests
php ../vendor/bin/phpunit

# Run without vendor
php phpunit.phar --configuration phpunit.xml
```

## Git Convention

| Prefix | Usage | Example |
|--------|-------|---------|
| `feat:` | New feature | `feat: add product search` |
| `fix:` | Bug fix | `fix: store status hours` |
| `refactor:` | Restructure | `refactor: extract photo logic` |
| `sync:` | Sync agent | `sync: product data update` |
| `docs:` | Documentation | `docs: update API reference` |
| `chore:` | Config/CI | `chore: update .gitignore` |
