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

---

## ✅ Phase 3 — Clean & Ringan (DONE — commit 79cc82f)

### 1. Pagination — Load More button
- `state.pageSize = 12`, `state.displayCount`
- `renderProductGrid()` render slice(0, displayCount)
- Tombol "Muat Lainnya (N)" di bawah grid
- Filter/sort → reset displayCount = 12
- Tombol hilang jika semua sudah tampil

### 2. Kurangi Elemen — 5 file berubah

| File | Perubahan |
|------|-----------|
| `ProductGrid.js` | Info bar: `"Menampilkan {count} produk ditemukan."` |
| `ProductCard.js` | Hapus badge kategori di pojok kanan gambar |
| `Footer.js` | Sosial media: 6 link label → icon-only row compact |
| `Navbar.js` | Hapus teks "Ikuti Kami:" |
| `Banner.js` | Hapus tombol prev/next, hanya dots autoplay |

### 3. Build
- JS: 55KB → 53KB (sebelum gzip)
- Total gzip: ~23KB (CSS 8.8KB + JS 14KB + HTML 0.3KB)

---

## ✅ Phase 4 — Pagination Server-Side + Lazy Load per Kategori (DONE)

### Backend (`backend/api_produk.php`)
- Accept `?page=N&limit=N&category=X&search=X&condition=X&sort=X`
- Filter by category, search (case-insensitive), condition (baru/bekas)
- Sort: low-high / high-low
- Return `{ data: [...], total, page, limit, categories: { name: count } }`
- DB fallback tetap (jika cache tidak ada)

### Frontend (`frontend/src/lib/api.js`)
- New `fetchProductsPage({ page, limit, category, search, condition, sort })`
- Fallback: paginate cache_produk.json lokal jika API gagal
- `fetchProducts()` kept as backward compat (calls fetchProductsPage with limit 9999)

### Frontend (`frontend/src/main.js`)
- State: `products[]`, `categories{}`, `currentPage`, `totalProducts`, `isLoading`
- `loadProducts(reset=true)` — fetch page dari API
  - reset=true: ganti filter/ganti kategori → page 1
  - reset=false: "Muat Lainnya" → page+1, append
- Sidebar categories di-populate dari response API (`result.categories`)

### Frontend (`frontend/src/components/ProductGrid.js`)
- `renderProductGrid(products, ..., hasMore, onLoadMore, total)`
- `hasMore` flag + `total` untuk menghitung sisa di tombol

### Flow
1. User search → API call `?page=1&limit=12&search=...` → render 12 produk + sidebar categories
2. Klik kategori → API call `?page=1&limit=12&category=Case` → render 12 produk Case
3. Klik "Muat Lainnya" → API call `?page=2&limit=12&category=Case` → append 12 produk
4. Filter/sort berubah → reset ke page 1
5. Jika `page * limit >= total` → tombol "Muat Lainnya" hilang

---

## ✅ Phase 5 — Cleanup & Pagination (DONE)

### 1. Hapus Jam Operasional dari Footer & Admin
- `Footer.js`: Hapus section "Jam Buka Toko" + parameter `hours` + kode `hoursRows`
- `admin.php`: Hapus tab **Jam Operasional** + panel jadwal + notifikasi status toko + `submitJam()` + `toggleLibur()` + dead PHP variables
- `main.js`: Hapus re-render footer dengan hours
- Build: JS 55.11KB → 53.92KB

### 2. Footer Single Column
- `Footer.js`: Grid responsive (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`) → `flex-col items-center` di semua layar

### 3. Hapus API Status Call
- `main.js`: Hapus `fetchStoreStatus()` dari `initApp()`, hapus `state.status`, hapus import
- Pindah `loadProductInfoText()` + `loadBanners()` ke akhir `loadProducts()`
- Build: JS 53.92KB → 51.91KB

### 4. Hapus Banner Sementara
- `main.js`: Hapus banner container, `loadBanners()`, `hideBanner()`, `bannerVisible`, import Banner.js
- `style.css`: Hapus `.banner-hiding` + `@keyframes slideRightFade`
- Build: JS 51.91KB → 48.49KB, CSS 48.20KB → 48.04KB

### 5. Pagination Pages (ganti "Muat Lainnya")
- `main.js`: `loadProducts(page)` → fetch halaman tertentu. `goToPage(page)` untuk navigasi
- `ProductGrid.js`: Render pagination buttons (prev, 1...N, next) dengan ellipsis
- Hapus semua `append` logic — setiap ganti page = fetch + render ulang
- State: `currentPage: 1`, `totalPages: Math.ceil(total / PAGE_SIZE)`
- Build: JS 48.23KB → 49.10KB

### 6. Page Size 12 → 50
- `main.js:9`: `PAGE_SIZE = 12` → `PAGE_SIZE = 50`
- Backend API support max `limit = 100` (already)

### 7. Filter Sidebar Collapsed by Default
- `FilterSidebar.js`: Hapus `lg:block`, `lg:cursor-default`, `lg:hidden`. Content starts `hidden` di semua layar
- `bindFilterEvents()`: Toggle bekerja di semua ukuran (tidak terbatas `<1024px`)
- Sidebar tetap `lg:col-span-1` saat visible, hanya konten yang collapse/expand via chevron rotate
