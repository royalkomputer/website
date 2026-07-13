# Savepoint — 13 Juli 2026

## Ringkasan

Menambahkan sinkronisasi data admin (Aset, Hutang, Penghasilan) ke dalam sync agent yang sudah ada, sehingga admin dapat mengakses data tersebut secara normal (termasuk offline) melalui cache fallback.

---

## Perubahan File

### 1. `sync/update_produk.php` — Sync agent diperluas

**Fungsi baru ditambahkan:**

- **`writeDataCacheFile()`** — helper untuk menulis cache file ke `sync/` dan `backend/data/`
- **`runAsetSync($conn)`** — query & cache data aset (summary, breakdown kategori, daftar produk dengan HPP/nilai jual)
- **`runHutangSync($conn)`** — query & cache data hutang (summary, breakdown jenis nota, daftar hutang beredar)
- **`runPenghasilanSync($conn)`** — query & cache data penghasilan bulan berjalan (summary, HPP, transaksi terbaru)

**Perbaikan critical:**
- `pg_close($conn)` dipindahkan dari **sebelum** fungsi admin sync ke **setelah** semua selesai → mencegah error "connection already closed"

**Alur baru `runSync()`:**
1. Sync Foto (backend/uploads → frontend/uploads)
2. Query produk → `cache_produk.json`
3. `runAsetSync()` → `cache_aset.json`
4. `runHutangSync()` → `cache_hutang.json`
5. `runPenghasilanSync()` → `cache_penghasilan.json`
6. `pg_close($conn)`

---

### 2. `backend/api_aset.php` — Cache fallback

- Deteksi DB unavailable → otomatis load `cache_aset.json`
- `getAsetFromCache()` — helper untuk filter, sort, search dari cache
- Mendukung action: `summary`, `get_products` (dengan filter & sorting), `get_categories`
- Mutasi aset (get_mutasi) tetap membutuhkan DB langsung

### 3. `backend/api_hutang.php` — Cache fallback

- Deteksi DB unavailable → otomatis load `cache_hutang.json`
- `getHutangFromCache()` — helper untuk filter & sort dari cache
- Mendukung action: `get_summary`, `get_list` (dengan filter supplier, jenis nota, overdue, sorting)

### 4. `backend/api_penghasilan.php` — Cache fallback

- Deteksi DB unavailable → otomatis load `cache_penghasilan.json`
- Menampilkan data bulan berjalan (default view)
- Action `get_date_range` bisa diambil dari cache (available_date_range)

### 5. `backend/api_data.php` — Whitelist diperluas

3 file cache baru ditambahkan ke whitelist:
- `cache_aset.json`
- `cache_hutang.json`
- `cache_penghasilan.json`

### 6. `AGENTS.md` — Dokumentasi

- File cache baru di tabel `sync/`
- Mekanisme cache fallback dijelaskan
- Tabel mapping: cache file → admin menu → tabel IPOS

---

## File Cache Baru

| File | Konten | Lokasi |
|------|--------|--------|
| `cache_aset.json` | Summary aset, breakdown kategori, daftar produk (HPP, modal, jual) | `sync/`, `backend/data/` |
| `cache_hutang.json` | Summary hutang, breakdown jenis nota, daftar hutang beredar | `sync/`, `backend/data/` |
| `cache_penghasilan.json` | Data penghasilan bulan berjalan, transaksi terbaru | `sync/`, `backend/data/` |

---

## Auto Sync Setiap Jam

Sudah berjalan otomatis melalui infrastructure yang ada:

```
Task Scheduler (setiap 1 jam)
    │
    ▼
sync_and_push.bat
    │
    ├── php update_produk.php --once
    │       ├── Sync Foto
    │       ├── cache_produk.json
    │       ├── cache_aset.json       ← BARU
    │       ├── cache_hutang.json     ← BARU
    │       └── cache_penghasilan.json ← BARU
    │
    └── git_push.bat → commit & push
```

Tidak perlu konfigurasi tambahan — Task Scheduler yang sudah ada langsung mencakup semua jenis data.

---

## Catatan

- Serial Number tidak di-cache karena berbasis search (data terlalu besar & dinamis)
- Mutasi Aset tetap butuh DB langsung (perhitungan date range)
- Penghasilan cache hanya menyimpan data bulan berjalan (default view), data historical tetap butuh DB
