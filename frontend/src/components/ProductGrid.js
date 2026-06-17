import { ProductCard } from './ProductCard.js'

/**
 * ProductGrid Component
 *
 * Manages the main product area: count bar, loading spinner, empty state,
 * and product card grid.
 *
 * @returns {string} HTML string for the product section
 */
export function ProductGrid() {
  return `
<section class="lg:col-span-3 flex flex-col gap-6">

  <!-- Product count info -->
  <div class="flex items-center justify-between bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
    <div class="text-sm text-slate-600">
      Menampilkan <span class="js-product-count font-bold text-slate-900">0</span> produk tersedia.
      Harga tidak selalu update, dan bisa berubah sewaktu-waktu. Hubungi kami di WhatsApp.
    </div>
  </div>

  <!-- Loading spinner -->
  <div class="js-loading-spinner py-20 flex flex-col items-center justify-center gap-3">
    <i class="fa-solid fa-spinner text-4xl text-astra-700 animate-spin"></i>
    <p class="text-slate-500 text-sm">Sedang memuat data produk...</p>
  </div>

  <!-- Empty state -->
  <div class="js-empty-state hidden bg-white rounded-xl border border-slate-200 p-12 text-center">
    <i class="fa-solid fa-box-open text-5xl text-slate-300 mb-4"></i>
    <h4 class="text-lg font-bold text-slate-800 mb-1">Produk Tidak Ditemukan</h4>
    <p class="text-slate-500 text-sm">Semua stok habis atau di luar kriteria pencarian Anda.</p>
  </div>

  <!-- Product cards grid -->
  <div class="js-product-grid grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6"></div>

</section>`
}

/**
 * Render products into the grid.
 *
 * @param {Object[]} products — filtered/sorted product array
 * @param {(id: string) => void} onDetailClick — callback when a card is clicked
 */
export function renderProductGrid(products, onDetailClick) {
  const grid = document.querySelector('.js-product-grid')
  const emptyState = document.querySelector('.js-empty-state')
  const countEl = document.querySelector('.js-product-count')

  if (!grid) return

  grid.innerHTML = ''
  if (countEl) countEl.textContent = products.length

  if (products.length === 0) {
    if (emptyState) emptyState.classList.remove('hidden')
    return
  }

  if (emptyState) emptyState.classList.add('hidden')

  products.forEach(product => {
    const div = document.createElement('div')
    div.innerHTML = ProductCard(product, onDetailClick)
    const card = div.firstElementChild
    if (card) {
      grid.appendChild(card)
      card.addEventListener('click', () => onDetailClick(product.id))
    }
  })
}

/**
 * Show/hide the loading spinner.
 */
export function showLoading(visible) {
  const spinner = document.querySelector('.js-loading-spinner')
  if (spinner) {
    spinner.style.display = visible ? 'flex' : 'none'
  }
}
