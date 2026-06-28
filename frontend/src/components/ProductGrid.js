import { ProductCard } from './ProductCard.js'
import { ProductDetailRow } from './ProductDetailRow.js'
import { DATA_BASE } from '../lib/env.js'

/** Default fallback product info text */
const PRODUCT_INFO_DEFAULT = 'Menampilkan {count} produk tersedia. Harga tidak selalu update, dan bisa berubah sewaktu-waktu. Hubungi kami di WhatsApp.'

let _productInfoText = PRODUCT_INFO_DEFAULT

/**
 * Fetch the product info text from product_info.json (synced from admin).
 * Falls back to the default if the file isn't available.
 */
export async function loadProductInfoText() {
  try {
    const res = await fetch(`${DATA_BASE}/product_info.json`)
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
 * @returns {string} HTML string for the product section
 */
export function ProductGrid({ viewMode = 'grid' } = {}) {
  const infoHtml = _productInfoText.replace('{count}', '<span class="js-product-count font-bold text-slate-900">0</span>')

  const isGrid = viewMode === 'grid'

  return `
<section class="lg:col-span-4 flex flex-col gap-6">

  <!-- Search prompt (shown by default) - slim sticky notification above banner -->
  <div class="js-search-prompt sticky top-4 z-10 bg-gradient-to-r from-astra-50 to-blue-50 border border-astra-200 rounded-xl px-4 py-3 flex items-center gap-3 shadow-sm" role="status">
    <div class="flex-shrink-0 w-8 h-8 bg-astra-100 rounded-full flex items-center justify-center">
      <i class="fa-solid fa-magnifying-glass text-sm text-astra-600"></i>
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-sm font-semibold text-astra-800">Cari Produk</p>
      <p class="text-xs text-slate-500">Gunakan pencarian atau pilih kategori untuk menampilkan produk.</p>
    </div>
    <i class="fa-solid fa-circle-info text-astra-300 text-sm flex-shrink-0"></i>
  </div>

  <!-- Banner -->
  <div class="js-banner-container hidden rounded-2xl mb-6 transition-all duration-500 ease-in-out"></div>

  <!-- Product count info + View toggle -->
  <div class="js-product-info-bar hidden flex items-center justify-between bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
    <div class="text-sm text-slate-600">
      ${infoHtml}
    </div>
    <div class="flex items-center gap-1 bg-slate-50 rounded-lg p-1 border border-slate-200">
      <button class="js-view-toggle flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-all ${isGrid ? 'bg-astra-700 text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700'}" data-view="grid">
        <i class="fa-solid fa-th"></i>
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
    <p class="text-slate-500 text-sm">Tidak ada produk yang sesuai dengan kriteria pencarian Anda.</p>
  </div>

  <!-- Product cards grid/list -->
  <div class="js-product-grid hidden ${isGrid ? 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4' : 'flex flex-col gap-3 sm:gap-4'}"></div>

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
    grid.classList.remove('grid', 'grid-cols-2', 'sm:grid-cols-3', 'lg:grid-cols-3', 'xl:grid-cols-4', 'gap-3', 'sm:gap-4')
    grid.classList.add('flex', 'flex-col', 'gap-3', 'sm:gap-4')
  } else {
    grid.classList.remove('flex', 'flex-col', 'gap-3', 'sm:gap-4')
    grid.classList.add('grid', 'grid-cols-2', 'sm:grid-cols-3', 'lg:grid-cols-3', 'xl:grid-cols-4', 'gap-3', 'sm:gap-4')
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
