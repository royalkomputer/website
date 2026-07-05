import { ProductCard } from './ProductCard.js'
import { ProductDetailRow } from './ProductDetailRow.js'
import { DATA_BASE } from '../lib/env.js'
import { icon } from '../lib/icons.js'

const PRODUCT_INFO_DEFAULT = 'Menampilkan {count} produk tersedia. Harga tidak selalu update, dan bisa berubah sewaktu-waktu. Hubungi kami di WhatsApp.'

let _productInfoText = PRODUCT_INFO_DEFAULT

export async function loadProductInfoText() {
  try {
    const res = await fetch(`${DATA_BASE}product_info.json`)
    const data = await res.json()
    if (data && data.text) {
      _productInfoText = data.text
    }
  } catch {
    _productInfoText = PRODUCT_INFO_DEFAULT
  }
}

export function ProductGrid({ viewMode = 'grid' } = {}) {
  const infoHtml = _productInfoText.replace('{count}', '<span class="js-product-count font-bold text-slate-900 dark:text-white">0</span>')

  const isGrid = viewMode === 'grid'

  return `
<section class="js-product-section lg:col-span-5 flex flex-col gap-6">

  <div class="js-search-prompt bg-gradient-to-r from-astra-950 to-slate-900 border border-astra-800 rounded-xl px-3 py-2 flex items-center gap-2 shadow-sm" role="status">
    <div class="flex-shrink-0 w-6 h-6 bg-astra-800 rounded-full flex items-center justify-center">
      ${icon('search', 'text-xs text-astra-300')}
    </div>
    <p class="text-xs text-slate-300 flex-1">Gunakan pencarian atau pilih kategori untuk menampilkan produk.</p>
  </div>

  <div class="js-banner-container hidden rounded-2xl mb-6 transition-all duration-500 ease-in-out"></div>

  <div class="js-product-info-bar hidden flex items-center justify-between bg-black p-4 rounded-xl border border-slate-700 shadow-sm">
    <div class="text-sm text-slate-300">
      ${infoHtml}
    </div>
    <div class="flex items-center gap-1 bg-slate-800 rounded-lg p-1 border border-slate-700">
      <button class="js-view-toggle flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-all ${isGrid ? 'bg-astra-700 text-white shadow-sm' : 'bg-slate-700 text-slate-400 hover:text-slate-200'}" data-view="grid">
        ${icon('th')}
        <span class="hidden sm:inline">Grid</span>
      </button>
      <button class="js-view-toggle flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-all ${!isGrid ? 'bg-astra-700 text-white shadow-sm' : 'bg-slate-700 text-slate-400 hover:text-slate-200'}" data-view="detail">
        ${icon('list')}
        <span class="hidden sm:inline">Detail</span>
      </button>
    </div>
  </div>

  <div class="js-skeleton-loading hidden">
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4">
      ${Array.from({ length: 8 }, () => `
        <div class="bg-black rounded-xl border border-slate-700 overflow-hidden">
          <div class="aspect-[4/3] shimmer"></div>
          <div class="p-3 space-y-2">
            <div class="h-3 shimmer rounded w-3/4"></div>
            <div class="h-3 shimmer rounded w-1/2"></div>
            <div class="h-4 shimmer rounded w-1/3 mt-2"></div>
          </div>
        </div>
      `).join('')}
    </div>
  </div>

  <div class="js-empty-state hidden bg-black rounded-xl border border-slate-700 p-12 text-center">
    <span class="text-5xl text-slate-600 mb-4 inline-flex">${icon('box-open')}</span>
    <h4 class="text-lg font-bold text-slate-100 mb-1">Produk Tidak Ditemukan</h4>
    <p class="text-slate-400 text-sm">Tidak ada produk yang sesuai dengan kriteria pencarian Anda.</p>
  </div>

  <div class="js-product-grid hidden ${isGrid ? 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4' : 'flex flex-col gap-3 sm:gap-4'}"></div>

</section>`
}

export function showSkeletonLoading(visible) {
  const el = document.querySelector('.js-skeleton-loading')
  if (el) {
    el.classList.toggle('hidden', !visible)
  }
  const grid = document.querySelector('.js-product-grid')
  if (grid) {
    grid.classList.toggle('hidden', visible)
  }
}

export function hideSkeletonLoading() {
  const el = document.querySelector('.js-skeleton-loading')
  if (el) {
    el.classList.add('hidden')
  }
}

export function renderProductGrid(products, onDetailClick, viewMode = 'grid') {
  const grid = document.querySelector('.js-product-grid')
  const emptyState = document.querySelector('.js-empty-state')
  const countEl = document.querySelector('.js-product-count')

  if (!grid) return

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
      div.innerHTML = ProductDetailRow(product, { hideImage: true })
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
