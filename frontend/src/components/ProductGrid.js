import { ProductCard } from './ProductCard.js'
import { ProductDetailRow } from './ProductDetailRow.js'

/** Default fallback product info text */
const PRODUCT_INFO_DEFAULT = 'Menampilkan {count} produk tersedia. Harga tidak selalu update, dan bisa berubah sewaktu-waktu. Hubungi kami di WhatsApp.'

let _productInfoText = PRODUCT_INFO_DEFAULT

/**
 * Fetch the product info text from product_info.json (synced from admin).
 * Falls back to the default if the file isn't available.
 */
export async function loadProductInfoText() {
  try {
    const res = await fetch('/product_info.json')
    const data = await res.json()
    if (data && data.text) {
      _productInfoText = data.text
    }
  } catch {
    // Use default fallback
    _productInfoText = PRODUCT_INFO_DEFAULT
  }
}

/**
 * ProductGrid Component
 *
 * Manages the main product area: view toggle, count bar, loading spinner,
 * empty state, and product card grid/list.
 *
 * @param {Object} options
 * @param {'grid'|'detail'} [options.viewMode] — current view mode
 * @param {(mode: 'grid'|'detail') => void} [options.onViewModeChange] — callback when view mode changes
 * @returns {string} HTML string for the product section
 */
export function ProductGrid({ viewMode = 'grid', onViewModeChange } = {}) {
  const infoHtml = _productInfoText.replace('{count}', '<span class="js-product-count font-bold text-slate-900">0</span>')

  const isGrid = viewMode === 'grid'

  return `
<section class="lg:col-span-3 flex flex-col gap-6">

  <!-- Product count info + View toggle -->
  <div class="flex items-center justify-between bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
    <div class="text-sm text-slate-600">
      ${infoHtml}
    </div>
    <div class="flex items-center gap-1 bg-slate-50 rounded-lg p-1 border border-slate-200">
      <button class="js-view-toggle flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-all ${isGrid ? 'bg-astra-700 text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700'}" data-view="grid">
        <i class="fa-solid fa-grid-2"></i>
        <span class="hidden sm:inline">Grid</span>
      </button>
      <button class="js-view-toggle flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-all ${!isGrid ? 'bg-astra-700 text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700'}" data-view="detail">
        <i class="fa-solid fa-list"></i>
        <span class="hidden sm:inline">Detail</span>
      </button>
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

  <!-- Product cards grid/list -->
  <div class="js-product-grid ${isGrid ? 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-6' : 'flex flex-col gap-4'}"></div>

</section>`
}

/**
 * Render products into the grid or list based on current view mode.
 *
 * @param {Object[]} products — filtered/sorted product array
 * @param {(id: string) => void} onDetailClick — callback when a card is clicked
 * @param {'grid'|'detail'} [viewMode] — rendering mode
 */
export function renderProductGrid(products, onDetailClick, viewMode = 'grid') {
  const grid = document.querySelector('.js-product-grid')
  const emptyState = document.querySelector('.js-empty-state')
  const countEl = document.querySelector('.js-product-count')

  if (!grid) return

  // Ensure correct grid/list class
  if (viewMode === 'detail') {
    grid.classList.remove('grid', 'grid-cols-2', 'sm:grid-cols-3', 'lg:grid-cols-4', 'xl:grid-cols-5', '2xl:grid-cols-6', 'gap-6')
    grid.classList.add('flex', 'flex-col', 'gap-4')
  } else {
    grid.classList.remove('flex', 'flex-col', 'gap-4')
    grid.classList.add('grid', 'grid-cols-2', 'sm:grid-cols-3', 'lg:grid-cols-4', 'xl:grid-cols-5', '2xl:grid-cols-6', 'gap-6')
  }

  grid.innerHTML = ''
  if (countEl) countEl.textContent = products.length

  if (products.length === 0) {
    if (emptyState) emptyState.classList.remove('hidden')
    return
  }

  if (emptyState) emptyState.classList.add('hidden')

  products.forEach(product => {
    const div = document.createElement('div')
    if (viewMode === 'detail') {
      div.innerHTML = ProductDetailRow(product)
    } else {
      div.innerHTML = ProductCard(product, onDetailClick)
    }
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
