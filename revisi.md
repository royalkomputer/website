# Revisi Phase 2 — Optimasi & UI Cleanup

## ✅ SUDAH DIEKSEKUSI (belum di-commit)

### Frontend — 6 file berubah

| File | Perubahan |
|------|-----------|
| `src/main.js` | Hapus `StoreStatus` + `loadHeadingText`; lazy load produk (`ensureProductsLoaded()`); default `viewMode:'detail'`; sidebar `hidden` di awal; layout dinamis `lg:col-span-5` → `lg:col-span-4` |
| `src/components/ProductGrid.js` | Tambah skeleton loading (8 shimmer cards); class `js-product-section` + `lg:col-span-5`; fungsi `showSkeletonLoading()` / `hideSkeletonLoading()` |
| `src/components/ProductDetailRow.js` | Opsional `{ hideImage: true }` — detail view text-only |
| `src/components/StoreStatus.js` | Tidak diimpor lagi oleh file manapun (bisa dihapus nanti) |
| Build | `npm run build` sukses — `dist/` 23KB gzipped (CSS 8.6KB + JS 14.2KB + HTML 0.3KB) |

### Backend — 2 file berubah

| File | Perubahan |
|------|-----------|
| `backend/admin.php` | Hapus tab **Tutup Sementara** (btn + panel); hapus tab **UI Toko** (btn + panel); hapus JS functions: `loadSchedules()`, `editSchedule()`, `submitSchedule()`, `deleteSchedule()`, `setManualStatus()`, `saveHeading()`, `updateHeadingPreview()`, `saveTagline()`, `saveProductInfo()`; update `panels[]` array; update `DOMContentLoaded` |
| `backend/update_admin.php` | Hapus handler: `set_manual_status`, `get_schedules`, `add_schedule`, `edit_schedule`, `delete_schedule`, `save_heading`, `get_heading`, `save_tagline`, `get_tagline`, `save_product_info`, `get_product_info` |

### Tidak berubah (dibiarkan)

| File | Alasan |
|------|--------|
| `backend/config.php` | Fungsi `loadHeading/saveHeading` dll masih dipakai `api_status.php` |
| `backend/api_status.php` | Masih return `heading`/`tagline` (frontend abaikan — harmless) |

### Frontend — Dark Refined (5 file berubah)

| File | Perubahan |
|------|-----------|
| `src/components/ProductGrid.js` | 3 baris: `bg-black` → `bg-slate-800/80`, `border-slate-700` → `border-white/10` |
| `src/components/ProductCard.js` | 1 baris: card bg & border |
| `src/components/ProductDetailRow.js` | 1 baris: row bg & border |
| `src/components/FilterSidebar.js` | 4 baris: aside, toggle, 3 condition btns + JS class |
| Build | `npm run build` sukses — total ~23KB gzipped (tidak berubah) |

### Frontend — Layout restruktur (4 file berubah)

| File | Perubahan |
|------|-----------|
| `src/style.css` | Tambah `@keyframes gradient-flow` + `.gradient-flow` class |
| `src/main.js` | `renderApp()`: tambah header "ROYAL KOMPUTER" + banner di luar `<main>` |
| `src/components/ProductGrid.js` | Hapus search prompt + banner container; `gap-6` → `gap-4` |
| `src/components/Banner.js` | `bg-slate-100 shadow-sm rounded-2xl` → `bg-astra-950` |
| Build | `npm run build` sukses — ~23KB gzipped (CSS 8.8KB, JS 14.2KB) |

## Flow User

```
User buka page
  │
  ├─ Navbar (sticky, logo + search bar + sosmed)
  │
  ├─ Header:  "ROYAL KOMPUTER" (gradient flow animasi)
  │
  ├─ Banner:  auto-slide playlist (full-width, rounded)
  │
  ├─ Footer:  jam operasional, sosial media, copyright
  │
  └─ User search / klik kategori di Navbar
       │
       ├─ Banner → slideRightFade (400ms) → hidden
       ├─ Skeleton loading (8 shimmer cards) muncul
       ├─ Fetch produk dari API (lazy)
       ├─ Sidebar kategori muncul (lg:col-span-5 → lg:col-span-4)
       ├─ Produk di-render (default: detail view, text-only, no images)
       ├─ Info bar "Menampilkan X produk tersedia..."
       └─ Skeleton → hilang, produk terlihat
```

## Image Behavior — Kapan Gambar Muncul

```
Pencarian / klik kategori
  │
  ├─ Detail (default) ─── text-only ─── tanpa gambar
  │   └─ ProductDetailRow({ hideImage: true })
  │      └─ Tidak ada tag <img>
  │
  └─ User klik tombol "Grid"
       └─ Produk di-render ulang dengan gambar
          └─ ProductCard + loading="lazy"
```

| Mode | Trigger | Gambar | Component |
|------|---------|--------|-----------|
| **Detail** | Default setelah search | ❌ Tidak ada | `ProductDetailRow` + `{ hideImage: true }` |
| **Grid** | User klik tombol Grid | ✅ Muncul (lazy load) | `ProductCard` |

**Alasan:** Detail view = cepat, ringan, tanpa download gambar. Grid view = opsional, user pilih sendiri saat ingin lihat foto.

---

## Yang TIDAK ADA di halaman user

- ❌ Badge status toko (buka/tutup/sementara)
- ❌ Tagline / deskripsi hero
- ❌ Heading "Solusi Hardware di..."
- ❌ Search prompt "Gunakan pencarian..." (banner sudah cukup)
- ❌ Gambar produk di default view (detail = text-only)
- ❌ Sidebar kategori sebelum user search
- ❌ Font Awesome (diganti inline SVG)
- ❌ Google Fonts (pakai system font stack)

## Yang TETAP ADA di halaman user

- ✅ Navbar sticky dengan logo + search
- ✅ Header "ROYAL KOMPUTER" gradient flow animation
- ✅ Banner playlist (auto-slide, full-width, nav buttons + dots)
- ✅ Produk (muncul setelah user search/klik kategori)
- ✅ Skeleton loading saat fetch produk
- ✅ View toggle (Grid / Detail)
- ✅ Modal detail produk (dengan carousel + WA order)
- ✅ Jam operasional di Footer
- ✅ Sosial media links di Navbar + Footer
