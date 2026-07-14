# Savepoint — 14 Juli 2026

## Ringkasan

Memperbaiki masalah loading katalog produk di admin dashboard Docker, dan mengubah konfigurasi Docker volume dari named volume ke bind mount agar perubahan file lokal langsung terefleksi di container.

---

## Perubahan File

### 1. `docker-compose.yml` — Volume bind mount

**Sebelum:** Named volume (`backend_data`, `backend_uploads`)
**Sesudah:** Bind mount (`./backend/data`, `./backend/uploads`)

Perubahan:
- `backend_data:/opt/app/data` → `./backend/data:/opt/app/data`
- `backend_uploads:/opt/app/uploads` → `./backend/uploads:/opt/app/uploads`
- `backend_uploads:/usr/share/nginx/html/uploads:ro` → `./backend/uploads:/usr/share/nginx/html/uploads:ro`
- Named volume `backend_uploads` dan `backend_data` dihapus dari deklarasi volumes (tidak diperlukan lagi)

**Penyebab:** Sebelumnya data di dalam container tidak pernah terupdate karena named volume menyimpan data dari build image (12 Juli), sementara file lokal sudah diupdate (13 Juli). Bind mount memastikan container selalu membaca file terkini dari repo.

---

### 2. `backend/admin.php` — Fix loading katalog produk

**Masalah:** Katalog produk di admin dashboard terus menampilkan loading spinner (tidak pernah selesai loading). Root cause: PHP session locking — `config.php` memanggil `session_start()` yang mengunci file session, menyebabkan request API (`api_produk.php?admin=1`) terblokir.

**Perbaikan:** Fungsi `fetchProducts()` diubah menjadi `async function` dengan 2 strategi:

1. **Strategi utama (cache file langsung):** Load data dari `/data/cache_produk.json` — static file via nginx, tanpa PHP, tanpa session locking
2. **Strategi cadangan (API):** Fallback ke `api_produk.php?admin=1` jika cache gagal
3. **Normalisasi path gambar:** Menambahkan prefix `/` pada path `uploads/...` (sama seperti yang dilakukan `api_produk.php?admin=1`)
4. **Error handling lebih baik:** console.warn untuk debugging + pesan error final

---

### 3. `backend/api_produk.php` — Session write close

Menambahkan `session_write_close()` setelah `require_once 'config.php'` pada awal file untuk melepas kunci session PHP, sehingga request lain (dari admin page) tidak terblokir.

---

### 4. `frontend/config.php` — Fungsi jam operasional

Menambahkan fungsi `loadJamOperasional()` yang membaca file `jam_buka.json` dengan fallback ke default (Senin-Sabtu 08:00-22:00, Minggu libur).

---

### 5. `frontend/package-lock.json` — Dihapus

File package-lock.json dihapus (tidak diperlukan untuk proyek yang tidak menggunakan npm untuk production).

---

### 6. `server.pid` — Update

PID server diperbarui sesuai proses yang berjalan.

---

## Verifikasi

```
✅ cache_produk.json -> HTTP 200, 226KB, 766 produk
✅ admin page -> HTTP 200
✅ login -> HTTP 302 (redirect)
✅ session_write_close -> aktif di api_produk.php
✅ Docker containers -> running (frontend, backend, db)
```

---

## Catatan

- Perubahan `admin.php` dan `api_produk.php` sudah di-copy ke container Docker via `docker cp`
- Untuk rebuild image penuh (setelah git pull di VPS), perlu `docker compose up --build`
- Jika ingin deploy ke VPS, pastikan origin/main sudah terupdate
