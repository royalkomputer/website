# Changelog

## 2026-06-29

### Fixed
- **Admin push-to-Git**: prioritize PHP `execGitPush()` when `GIT_TOKEN` is set in `.env` (more reliable on XAMPP/Apache than batch file)
- **`execGitPush()` `git add` working directory**: use `findGitDir(__DIR__)` to run from correct git root
- **`execGitPush()` push command**: changed `git push $escaped_branch` → `git push origin $branch` (Windows `escapeshellarg` wraps `main` in quotes, causing "not a git repository" error)
- **`backupPhotosToGit()` token**: now reads `.env` for `GIT_TOKEN` (same pattern as `backupToGit()`)
- **`push_admin.bat`**: simplified (no token logic, uses git config credential.helper)
- **`execGitPush()` git add paths**: sekarang mencakup SEMUA file settings frontend: `cache_produk.json`, `heading.json`, `jadwal_tutup.json`, `jam_operasional.json`, `product_info.json`, `tagline.json`, `status_toko.txt`
- **`backupPhotosToGit()` git add paths**: sekarang juga stage `data/` + `../frontend/*.json/*.txt` selain `uploads/`, sehingga perubahan foto + deskripsi dikomit bersamaan
- **`tambah_admin` crash**: `$id_new` tidak pernah di-assign dari `generateAdminId()`, menyebabkan `TypeError` di `logAdminHistory(string $target_id)`. Admin tersimpan ke JSON tapi script crash sebelum mengirim response sukses. Diperbaiki dengan menyimpan `generateAdminId()` ke `$new_id` dan menggunakannya di history log. (`update_admin.php:33-43`)
- **Deskripsi produk tidak tersimpan di cache**: `update_produk.php` hanya menyimpan deskripsi ke `cache_produk.json` ketika DB tidak tersedia (`if (!$db_available && $desc_provided)`). Karena `api_produk.php` membaca dari cache terlebih dahulu, deskripsi yang disimpan ke DB tidak pernah tampil kembali. Diperbaiki dengan menghapus guard `!$db_available` sehingga cache selalu diperbarui. (`update_produk.php:341-364`)
- **Hari yang disorot di tabel jam buka geser 1 hari**: `Footer.js` `dayNames` array dimulai dari `'Monday'` bukan `'Sunday'`, sementara `Date.getDay()` return 0=Sunday, 1=Monday. Akibatnya hari Senin malah menyorot baris Tuesday. Diperbaiki dengan mengubah urutan array. (`Footer.js:13`)

### Added
- **`backend/.env`**: menyimpan `GIT_TOKEN` untuk autentikasi git via HTTPS
- **Auto-push settings**: `update_jam.php`, `update_admin.php` (status, jadwal, heading, tagline, product info) — semua perubahan settings di admin otomatis dikomit dan dipush ke GitHub

### Changed
- **Dark mode**: switched Tailwind dari `media` (mengikuti OS `prefers-color-scheme`) ke `class` strategy; `class="dark"` ditambahkan ke `<html>` sehingga tema selalu gelap di semua deployment tanpa peduli setelan sistem pengguna
  - Vite app: `@custom-variant dark` di `style.css`, `class="dark"` di `index.html`
  - PHP site: `darkMode: 'class'` di CDN tailwind.config, `class="dark"` di `index.php`
- **AGENTS.md**: diperbarui dengan progress session, key decisions, dan file references

### In Progress
- **Photo upload display bug**: user melaporkan foto "not found" di grid setelah upload, meskipun upload berhasil secara programmatic (file tersimpan, API return URL benar, HTTP 200). Perlu debugging browser-side.
