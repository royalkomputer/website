# Deployment Royal Komputer — VPS (Nginx + PHP + PostgreSQL)

> **Arsitektur:** VPS Ubuntu 22.04 — Nginx + PHP 8.2-FPM + PostgreSQL 16
> **IP VPS:** `103.93.133.60`
> **cPanel (DNS only):** `103.125.180.60` — Biznet Neo
> **Nameserver:** `satu.neodns.id` / `dua.neodns.id`
> **Tgl:** 22 Juli 2026

---

## Arsitektur

```
Domain ─► cPanel DNS ─► A record 103.93.133.60 ─► VPS: Nginx
                       ├─ royalkomputer.com  ─► frontend/ (marketplace)
                       └─ admin.royalkomputer.com ─► backend/ (admin)
```

---

## Perubahan yang Dilakukan pada deploy.sh

### 1. PHP 8.2 PPA — Fix Deteksi Repository
**Masalah:** `apt-cache policy php8.2-fpm` selalu return exit 0 meski package tidak ada di repo default Ubuntu 22.04 (hanya PHP 8.1). Akibatnya PPA ondrej/php tidak pernah ditambahkan dan instalasi PHP 8.2 gagal.

**Perbaikan:** Ganti deteksi dari `apt-cache policy` jadi cek file PPA langsung:
```bash
if [[ ! -f /etc/apt/sources.list.d/ondrej-ubuntu-php-$(lsb_release -sc).list ]]; then
    add-apt-repository -y ppa:ondrej/php
fi
```

### 2. PostgreSQL 16 — Official Repo
**Masalah:** Ubuntu 22.04 hanya menyediakan PostgreSQL 14 di repo default, tapi deploy.sh menginstall `postgresql` tanpa menentukan versi.

**Perbaikan:** Tambah official PostgreSQL repository (`jammy-pgdg`) dan install `postgresql-16` secara eksplisit.

### 3. Deteksi Versi PostgreSQL Dinamis
**Masalah:** Path konfigurasi PostgreSQL hardcoded `/etc/postgresql/16/main/postgresql.conf` — akan error jika versi berbeda.

**Perbaikan:** Deteksi versi otomatis via `pg_config` atau fallback ke `ls /etc/postgresql/`:
```bash
PG_VERSION=$(pg_config --version | grep -oP '\d+' | head -1)
PG_CONF="/etc/postgresql/$PG_VERSION/main/postgresql.conf"
```

### 4. Admin Nginx Config — Index & Try Files
**Masalah:** Konfigurasi admin menggunakan `index index.php` dan `try_files /index.php`, padahal entry point admin adalah `admin.php` (bukan `index.php`). Akibatnya akses ke `admin.royalkomputer.com/` return 404.

**Perbaikan:**
```nginx
index admin.php;
try_files $uri $uri/ /admin.php?$query_string;
```

### 5. Uploads Symlink
**Masalah:** `frontend/uploads` seharusnya symlink ke `backend/uploads` agar foto produk bisa diakses dari frontend, tapi tidak dibuat oleh deploy.sh.

**Perbaikan:** Buat symlink otomatis:
```bash
ln -sf ../backend/uploads "$APP_DIR/frontend/uploads"
```

### 6. .env File Lengkap
**Masalah:** File `.env` hanya berisi `DB_PASSWORD`, tanpa `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`.

**Perbaikan:** Tambah semua variabel yang dibutuhkan:
```ini
DB_HOST=localhost
DB_PORT=5432
DB_NAME=royalkomputer
DB_USER=royal_owner
DB_PASSWORD=...
```

### 7. Schema & Permission Grants
**Masalah:** Setelah import schema via `init.sql`, user `royal_owner` tidak bisa membuat/memanipulasi tabel karena kurang grants di level schema.

**Perbaikan:**
```sql
GRANT ALL ON SCHEMA public TO royal_owner;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO royal_owner;
GRANT ALL ON ALL TABLES IN SCHEMA public TO royal_owner;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO royal_owner;
```

### 8. pg_hba.conf — Koneksi PHP via TCP
**Masalah:** PHP terhubung ke PostgreSQL via `localhost` (TCP), tapi pg_hba.conf mungkin tidak mengizinkan koneksi TCP dengan password.

**Perbaikan:** Update pg_hba.conf untuk mengizinkan koneksi TCP 127.0.0.1/32 dengan autentikasi md5.

### 9. default_server — Akses IP ke Marketplace
**Masalah:** Saat akses via IP publik `103.93.133.60`, Nginx tidak tahu server block mana yang dipakai. Karena tidak ada `default_server`, Nginx memilih server block pertama secara alfabet (`admin.royalkomputer.com`), sehingga yang muncul adalah halaman login admin.

**Perbaikan:** Tambah `default_server` di frontend:
```nginx
listen 80 default_server;
```

### 10. Port 8080 — Akses Admin Sementara (sebelum DNS propagasi)
**Masalah:** Karena DNS belum propagasi, `admin.royalkomputer.com` belum bisa diakses. Admin hanya bisa diakses via server_name, tidak via IP.

**Perbaikan:** Tambah `listen 8080;` di konfigurasi admin:
```nginx
listen 80;
listen 8080;
```
Akses sementara: `http://103.93.133.60:8080/` → Admin Panel

---

## Status DNS & SSL

| Domain | A Record | DNS Status | HTTPS Status |
|--------|----------|------------|--------------|
| `royalkomputer.com` | `103.93.133.60` | ✅ Propagasi OK | ✅ Let's Encrypt (exp: 2026-10-20) |
| `admin.royalkomputer.com` | `103.93.133.60` | ✅ Propagasi OK | ✅ Let's Encrypt (exp: 2026-10-20) |
| Nameserver | `satu.neodns.id` / `dua.neodns.id` | ✅ Terisi di registrar | — |

---

## Bug yang Ditemukan

### Bug 1 — Produk: Foto slide 2+ tidak tampil
**Root cause:** `frontend/uploads` adalah git symlink dengan target `C:/Websites/website/backend/uploads` (path Windows absolut). Di Linux VPS path ini tidak ada → symlink putus → semua request `/uploads/*` return 404.

Cache `cache_produk.json` tetap menyertakan path untuk semua gambar (_1, _2, _3, dst), tapi file fisik hanya bisa diakses lewat symlink yang rusak.

**Fix:** Hapus symlink lama lalu buat yang benar:
```bash
cd /var/www/royalkomputer/frontend
rm uploads
ln -sf ../backend/uploads uploads
```
Deploy.sh juga sudah diperbaiki untuk selalu recreate symlink (tidak skip jika sudah ada).

### Bug 2 — Banner blank
**Root cause:** Sama seperti Bug 1 — banner image di-render sebagai `/uploads/banners/pl_6a5c6e8fd1854_0.webp`, path tersebut melewati symlink yang sama yang rusak. Image 404 → `onerror` menyembunyikan img → container kosong.

**Fix:** Sama seperti Bug 1 — perbaiki symlink.

---

## Riwayat

| Tanggal | Perubahan |
|---------|-----------|
| 22 Jul 2026 | DNS A record `royalkomputer.com` + `admin.royalkomputer.com` → `103.93.133.60` ✅ |
| 22 Jul 2026 | SSL Let's Encrypt terpasang, HTTP → HTTPS redirect aktif ✅ |
| 22 Jul 2026 | `tbl_web_deskripsi` — hapus FK constraint (agar deskripsi bisa disimpan walau produk belum ada di cloud DB) |
| 22 Jul 2026 | Banner multi-playlist: setiap playlist jadi carousel sendiri, tumpuk vertikal |
