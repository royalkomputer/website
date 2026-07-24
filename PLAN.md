# Royal Komputer — Project Plan

> **Arsitektur saat ini:** Hybrid — sync langsung via rsync ke VPS (non-Docker).
> **Referensi utama:** [AGENTS.md](AGENTS.md) untuk API, konvensi kode, dan pola arsitektur.

### Changelog
| Tanggal | Perubahan |
|---------|-----------|
| 2026-07-24 | **Direct Sync VPS** — Ganti flow sync dari git push ke rsync langsung ke VPS (103.93.133.60). Tambah fitur Aset, Hutang, Penghasilan di dashboard admin. Data finansial tidak masuk GitHub public (`.gitignore`). Foto tetap di VPS, tidak sync dari lokal. |
| 2026-07-19 | Hapus menu Serial Number, Penghasilan, Hutang, Aset dari admin dashboard. Hapus endpoint API `api_aset.php`, `api_hutang.php`, `api_penghasilan.php`. Hapus sync data aset/hutang/penghasilan dari sync agent. |
| 2026-07-19 | Hapus fitur jam buka toko (operating hours). Hapus `loadJamOperasional()` dari `frontend/config.php`. Hapus logic jam operasional, badge tutup/buka, schedule warning, dan footer "JAM BUKA TOKO" dari `frontend/index.php`. Hapus `StoreStatus.js` (dead code). Sederhanakan `api_status.php`. Hapus `fetchStoreStatus()` dari `api.js`. |
| 2026-07-19 | Refactor UI `frontend/index.php` — minimal & modern: navbar lebih ramping (border-bottom, social links satu warna), hero header solid tanpa gradient, sidebar bg-slate-800/60, product card hover subtle (border aksen), grid 5 kolom di xl, info bar & empty state lebih clean, footer compact. |
| 2026-07-19 | Tambah banner carousel (promo) di halaman awal + dual-state layout: awal banner full-width, sidebar hidden; setelah cari/filter sidebar muncul, banner hide. Banner dari `api_banner.php`. |
| 2026-07-19 | Tambah label kategori di pojok kanan atas gambar grid card (flat, subtle). |
| 2026-07-19 | Fix: `api_banner.php` tidak ditemukan di frontend — buat wrapper `frontend/api_banner.php` yang baca langsung dari `backend/data/banners.json`. Sebelumnya fetch gagal 404, seluruh Promise.all reject → empty state muncul salah. |
| 2026-07-19 | Batasi render produk maksimal 40 per halaman. `renderProductGrid()` slice `filteredProducts` ke 40 item pertama, counter ubah jadi format "Menampilkan 40 dari 766 produk". |
| 2026-07-19 | Tambah pagination "Muat Lainnya": variable `displayLimit`, tombol di bawah grid increment +40, reset ke 40 saat filter/search berganti. |
| 2026-07-19 | Tambah floating button "Kembali ke Atas": muncul setelah scroll >400px, smooth scroll, `bg-astra-700` di pojok kanan bawah. |
| 2026-07-19 | Hapus 10 file tidak terpakai: savepoint.md, server.pid, dev.sh, dev.bat, admin.php.bak, test_*.txt, push_admin.bat, setup_push_task.bat, api_schedules.php, sf_sync.bat. Perbarui dokumentasi dan vite.config.js. |
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
┌───────────────────────────────────────────────────────────────────────┐
│                      LOCAL PC (Windows — Toko Kediri)                 │
│                                                                       │
│  IPOS PostgreSQL (192.168.18.189:5444/i4_ROYAL)                      │
│         │                                                              │
│         ▼                                                              │
│  sync/update_produk.php (Task Scheduler tiap jam)                     │
│         │                                                              │
│    ┌────┴────┬────────────┬──────────────┬──────────────┐             │
│    │         │            │              │              │             │
│    ▼         ▼            ▼              ▼              ▼             │
│ cache_  cache_aset  cache_hutang  cache_         backend/            │
│ produk  .json      .json        penghasilan     uploads/             │
│ .json                         .json            (foto — VPS saja)     │
│ (public) (─ SENSITIVE ─)                           │                 │
│    │         │            │              │         ── tidak di rsync  │
│    └────┬────┴────────────┴──────────────┴──────────┘                 │
│         │                                                              │
│         ▼                                                              │
│  rsync -az --rsync-path="sudo rsync" -e "ssh -i royalserver.pem"     │
│  (hanya 5 file JSON ke backend/data/)                                 │
│         │                                                              │
│         ▼                                                              │
└───────────────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌───────────────────────────────────────────────────────────────────────┐
│                    VPS (royaladmin@103.93.133.60)                      │
│                                                                       │
│  /var/www/royalkomputer/                                              │
│  ├── frontend/          → royalkomputer.com (Nginx + PHP-FPM)        │
│  │   ├── index.php        (storefront)                                │
│  │   ├── api_produk.php   (product API)                               │
│  │   └── uploads/         → symlink → ../backend/uploads/             │
│  │                                                                     │
│  ├── backend/           → admin.royalkomputer.com (Nginx + PHP-FPM)  │
│  │   ├── admin.php        (dashboard admin)                           │
│  │   ├── api_aset.php     (aset API — BARU)                           │
│  │   ├── api_hutang.php   (hutang API — BARU)                         │
│  │   ├── api_penghasilan.php (penghasilan API — BARU)                 │
│  │   └── data/            (diblock nginx — deny all)                  │
│  │       ├── cache_produk.json                                        │
│  │       ├── cache_aset.json        ← rsync dari lokal                │
│  │       ├── cache_hutang.json      ← rsync dari lokal                │
│  │       ├── cache_penghasilan.json ← rsync dari lokal                │
│  │       ├── waktu_sync.json        ← rsync dari lokal                │
│  │       └── ... (admins, kategori, dll — diubah via admin panel)     │
│  │                                                                     │
│  └── sync/              → tidak dipakai di VPS                        │
│                                                                       │
│  Nginx: royalkomputer.com + admin.royalkomputer.com (SSL via certbot) │
│  PHP 8.2-FPM, PostgreSQL 16 (cloud DB untuk auth + config)           │
└───────────────────────────────────────────────────────────────────────┘
```

### Aliran Data Sync (setiap jam)

| Langkah | Deskripsi |
|---------|-----------|
| 1 | Task Scheduler jalankan `sync_to_vps.bat` |
| 2 | `php update_produk.php --once` |
| 3 | Sync foto lokal: `backend/uploads/` → `frontend/uploads/` (local dev) |
| 4 | Query produk dari IPOS → `cache_produk.json` |
| 5 | Query aset dari IPOS → `cache_aset.json` |
| 6 | Query hutang dari IPOS → `cache_hutang.json` |
| 7 | Query penghasilan dari IPOS → `cache_penghasilan.json` |
| 8 | Tulis `waktu_sync.json` |
| 9 | rsync 5 file JSON ke VPS `backend/data/` via SSH |
| 10 | VPS Nginx + PHP serve data dari file tersebut |

### Catatan penting

- **Foto tidak di rsync** — foto diupload via admin panel langsung ke VPS, tidak perlu sync dari lokal
- **GitHub hanya untuk kode** — push manual saat ada perubahan PHP/konfigurasi
- **File finansial di .gitignore** — tidak masuk public repo
- **rsync pakai sudo** karena file di VPS milik `www-data`, user `royaladmin` punya sudo
- **Data file** (`admins.json`, `kategori.json`, dll) tetap diubah via admin panel di VPS — tidak sync dari lokal

## Service Mapping

| Folder | Server | Port | Purpose |
|--------|--------|------|---------|
| `frontend/` | royalkomputer.com | 443 | Storefront (Nginx + PHP-FPM) |
| `backend/` | admin.royalkomputer.com | 443 | Admin dashboard + API (Nginx + PHP-FPM) |
| `database/` | localhost | 5432 | PostgreSQL 16 (cloud) |
| `sync/` | — | — | Windows Task Scheduler (local PC) |

## API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `api_produk.php` | GET | Public | Product catalog |
| `api_status.php` | GET | Public | Store open/closed status |
| `api_banner.php` | GET | Public | Banner playlists |
| `api_aset.php` | GET | Session | Aset report (BARU) |
| `api_hutang.php` | GET | Session | Hutang report (BARU) |
| `api_penghasilan.php` | GET | Session | Penghasilan report (BARU) |
| `api_manage_photos.php` | POST | Session | Photo delete/reorder |
| `api_data.php` | GET | Public | JSON file proxy |
| `update_produk.php` | POST | Session | Product description + photo upload |
| `update_admin.php` | POST | Session | Admin CRUD + schedules + status |
| `update_banner.php` | POST | Session | Banner management |

## Features

### Storefront (Public)
- [x] Product catalog with search, category/condition filters, price sort
- [x] Store open/closed status (schedules, manual override)
- [x] Product detail modal with image carousel
- [x] WhatsApp order integration
- [x] Mobile-responsive, dark mode
- [x] Pagination (40 products/page)
- [x] Fallback to cache when DB unavailable

### Admin Dashboard
- [x] Product management (description, photo upload/reorder/delete, WEBP auto-conversion)
- [x] Temporary closure scheduling
- [x] Manual store status override
- [x] Multi-role admin (super_admin + admin)
- [x] Profile editing (self-service)
- [x] Banner management (playlist-based carousel)
- [x] Sync status dashboard
- [x] **Aset report** (nilai modal, nilai jual per item)
- [x] **Hutang report** (hutang ke supplier)
- [x] **Penghasilan report** (penjualan bulan berjalan)

### Sync Agent
- [x] IPOS PostgreSQL → cache_produk.json + cache_aset/hutang/penghasilan.json
- [x] Photo sync (backend → frontend uploads — local dev)
- [x] Rsync ke VPS via SSH (langsung, tanpa GitHub)
- [x] Windows Task Scheduler integration
- [x] Sync status tracking (last_sync.json, waktu_sync.json)
- [x] File finansial di .gitignore (tidak bocor ke public repo)

### Infrastructure
- [x] Nginx + PHP 8.2-FPM (VPS)
- [x] SSL via Let's Encrypt (certbot)
- [x] PostgreSQL 16 (cloud)
- [ ] Coolify — tidak dipakai (deploy manual via git)

## Data Flow

### Admin → Storefront (Immediate via VPS API)
| Admin Action | Writes To |
|---|---|
| Edit product | `cache_produk.json`, `backend/data/` |
| Upload photo | `backend/uploads/` |
| Save schedule | `jadwal_tutup.json` |
| Set manual status | `status_toko.txt` |
| Save tagline | `tagline.json` |

### Sync Agent → VPS (Every 1 Hour, Direct rsync)
```
IPOS DB → sync agent → 5 cache JSON → rsync via SSH → VPS backend/data/
```

## Deployment Flow (Kode)
```
git push → VPS git pull → reload PHP-FPM
```
Dilakukan manual saat ada perubahan kode. Tidak otomatis.

## Testing

```bash
# Jalankan sync agent manual (dry-run, tanpa rsync)
cd sync
php update_produk.php --once

# Cek hasil cache
ls -la sync/cache_*.json
ls -la backend/data/cache_*.json

# Test rsync ke VPS
rsync -az --rsync-path="sudo rsync" -e "ssh -i key.pem -p 22" sync/cache_produk.json royaladmin@103.93.133.60:/var/www/royalkomputer/backend/data/
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