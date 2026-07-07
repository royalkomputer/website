# Revisi: Hapus Badge Angka pada Tombol Kategori & Rapikan Lebar Tombol

## Ringkasan
Hapus badge jumlah produk di samping nama kategori pada sidebar storefront, dan sesuaikan lebar tombol agar mengikuti panjang teks (tidak full-width).

## File yang Diubah

### 1. `frontend/src/components/FilterSidebar.js`
- **Hapus parameter `categoryCounts`** dari fungsi `FilterSidebar()`
- **Hapus badge angka** (`<span class="... rounded-full">${count}</span>`) dari template tombol kategori
- **Ubah lebar tombol**: hapus class `w-full` dan `justify-between`, tambah `gap-2` pada tombol kategori
- **Ubah container kategori**: dari `space-y-1` jadi `flex flex-col items-start gap-1` agar tombol tidak memanjang penuh
- **Fungsi `updateCategoryButtons()`**: hapus semua referensi `countBadge` (querySelectorAll('span')[1] dan 2 blok if)

### 2. `frontend/src/main.js`
- **Hapus komputasi `const counts`** yang menggabungkan `state.totalProducts` dengan `state.categories`
- **Sederhanakan pemanggilan `FilterSidebar()`**: dari 3 parameter jadi 2 parameter (tanpa `counts`)

## Detail Perubahan

### FilterSidebar.js

**Sebelum:**
```js
export function FilterSidebar(filters, categories, categoryCounts) {
  categoryCounts = categoryCounts || {}
  // ...
  <div class="js-category-panel space-y-1">
    ${categories.map(cat => {
      const isSelected = filters.category === cat
      const count = categoryCounts[cat] || 0
      return `<button class="js-cat-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center justify-between ${
        isSelected ? '...' : '...'
      }" data-category="${cat}">
        ${isSelected ? '<span class="mr-1.5 inline-flex">' + icon('check', 'text-white') + '</span>' : ''}
        <span>${cat}</span>
        <span class="${isSelected ? 'bg-white/20 text-white' : 'bg-slate-700 text-slate-400'} text-xs px-2 py-0.5 rounded-full">${count}</span>
      </button>`
    }).join('')}
  </div>
```

**Sesudah:**
```js
export function FilterSidebar(filters, categories) {
  // ...
  <div class="js-category-panel flex flex-col items-start gap-1">
    ${categories.map(cat => {
      const isSelected = filters.category === cat
      return `<button class="js-cat-btn text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2 ${
        isSelected ? '...' : '...'
      }" data-category="${cat}">
        ${isSelected ? '<span class="inline-flex">' + icon('check', 'text-white') + '</span>' : ''}
        <span>${cat}</span>
      </button>`
    }).join('')}
  </div>
```

### main.js

**Sebelum:**
```js
const counts = { 'Semua': state.totalProducts, ...state.categories }
filterContainer.innerHTML = FilterSidebar(state.filters, ['Semua', ...Object.keys(state.categories)], counts)
```

**Sesudah:**
```js
filterContainer.innerHTML = FilterSidebar(state.filters, ['Semua', ...Object.keys(state.categories)])
```

### `updateCategoryButtons()` — FilterSidebar.js

Hapus semua referensi ke `countBadge` (baris query `btn.querySelectorAll('span')[1]` dan 2 blok `if` yang memanipulasi class countBadge).

## Hasil Akhir
- Tombol kategori hanya selebar teksnya
- Rapi rata kiri
- Tanpa badge angka jumlah produk
- Tidak ada perubahan fungsi filter atau logika lainnya
