# Revisi Phase 2 — Optimasi & UI Cleanup

## ✅ SUDAH DIEKSEKUSI (belum di-commit)

### Frontend — 6 file berubah

| File | Perubahan |
|------|-----------|
| `src/main.js` | Hapus `StoreStatus` + `loadHeadingText`; lazy load produk (`ensureProductsLoaded()`); default `viewMode:'detail'`; sidebar `hidden` di awal; layout dinamis `lg:col-span-5` → `lg:col-span-4` |
| `src/components/ProductGrid.js` | Tambah skeleton loading (8 shimmer cards); class `js-product-section` + `lg:col-span-5`; fungsi `showSkeletonLoading()` / `hideSkeletonLoading()` |
| `src/components/ProductDetailRow.js` | Opsional `{ hideImage: true }` — detail view text-only |
| `src/lib/api.js` | Tidak berubah (fungsi `fetchStoreStatus` masih dipakai untuk footer hours) |
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

---

## 🔜 BELUM DIEKSEKUSI — Header Gradient Flow

### 1. `frontend/src/style.css` — Tambah animasi

```css
@keyframes gradient-flow {
  0%, 100% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
}
.gradient-flow {
  background: linear-gradient(270deg, #60a5fa, #a78bfa, #f472b6, #60a5fa);
  background-size: 300% 100%;
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  animation: gradient-flow 5s ease infinite;
}
```

### 2. `frontend/src/main.js:29-31` — Tambah header

**Sebelum:**
```js
  app.innerHTML = `
    ${Navbar({ onSearch: handleSearch })}
    <main class="px-4 md:px-8 lg:px-12 py-8 flex-grow grid grid-cols-1 lg:grid-cols-5 gap-6">
```

**Sesudah:**
```js
  app.innerHTML = `
    ${Navbar({ onSearch: handleSearch })}
    <header class="py-8 md:py-12 text-center">
      <h1 class="text-3xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold tracking-tight gradient-flow select-none">
        ROYAL KOMPUTER
      </h1>
    </header>
    <main class="px-4 md:px-8 lg:px-12 pb-8 flex-grow grid grid-cols-1 lg:grid-cols-5 gap-6">
```

---

## Hasil Akhir

```
┌──────────────────────────────────────┐
│  ROYAL◄KOMPUTER  ← gradient flow    │  ← Navbar (sticky)
├──────────────────────────────────────┤
│                                      │
│       ROYAL KOMPUTER                 │  ← Header animasi
│    (blue → purple → pink → blue)    │     gradient flow 5s
│                                      │
├──────────────────────────────────────┤
│  [Gunakan pencarian...]              │  ← search prompt
│                                      │
│  ┌─────────┐ ┌─────────┐            │
│  │ skeleton │ │ skeleton │            │  ← lazy load
│  └─────────┘ └─────────┘            │
├──────────────────────────────────────┤
│  Footer (logo, jam operasional,      │
│   sosial media, copyright)            │
└──────────────────────────────────────┘
```

---

## Yang TIDAK ADA di halaman user

- ❌ Badge status toko (buka/tutup)
- ❌ Tagline/deskripsi hero
- ❌ Heading "Solusi Hardware di..."
- ❌ Gambar produk di default view (detail = text-only)
- ❌ Sidebar kategori sebelum user search

## Yang TETAP ADA di halaman user

- ✅ Jam operasional di Footer
- ✅ Banner playlist (auto-slide)
- ✅ Produk (muncul setelah user search/klik kategori)
- ✅ Skeleton loading saat fetch produk
- ✅ Tombol Grid/Detail view toggle
