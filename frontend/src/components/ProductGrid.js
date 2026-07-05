import { ProductCard } from './ProductCard.js'
import { ProductDetailRow } from './ProductDetailRow.js'
import { DATA_BASE } from '../lib/env.js'
import { icon } from '../lib/icons.js'

const PRODUCT_INFO_DEFAULT = 'Menampilkan {count} produk. Harga tidak selalu update, hubungi kami di WhatsApp.'

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
<section class="js-product-section lg:col-span-5 flex flex-col gap-4">

  <div class="js-product-info-bar hidden flex items-center justify-between bg-slate-800/80 p-4 rounded-xl border border-white/10 shadow-sm">
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
        <div class="bg-slate-800/80 rounded-xl border border-white/10 overflow-hidden">
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

  <div class="js-empty-state hidden bg-slate-800/80 rounded-xl border border-white/10 p-12 text-center">
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

export function renderProductGrid(products, onDetailClick, viewMode = 'grid', currentPage = 1, totalPages = 0, onPageChange = null) {
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

  if (countEl) countEl.textContent = products.length

  // Pagination
  if (totalPages > 1 && onPageChange) {
    const nav = document.createElement('div')
    nav.className = 'flex items-center justify-center gap-1 pt-6 pb-2'

    // Prev
    const prev = document.createElement('button')
    prev.className = `px-3 py-1.5 rounded-lg text-sm font-semibold transition-all ${currentPage === 1 ? 'text-slate-600 cursor-default' : 'text-slate-300 hover:bg-slate-800'}`
    prev.innerHTML = '<span class="inline-flex">' + icon('chevron-left') + '</span>'
    if (currentPage > 1) prev.addEventListener('click', () => onPageChange(currentPage - 1))
    nav.appendChild(prev)

    // Page numbers
    const maxVisible = 5
    let start = Math.max(1, currentPage - Math.floor(maxVisible / 2))
    let end = Math.min(totalPages, start + maxVisible - 1)
    if (end - start < maxVisible - 1) start = Math.max(1, end - maxVisible + 1)

    if (start > 1) {
      nav.appendChild(createPageBtn(1, currentPage === 1, onPageChange))
      if (start > 2) {
        const dots = document.createElement('span')
        dots.className = 'px-1 text-slate-500 text-xs'
        dots.textContent = '...'
        nav.appendChild(dots)
      }
    }

    for (let i = start; i <= end; i++) {
      nav.appendChild(createPageBtn(i, i === currentPage, onPageChange))
    }

    if (end < totalPages) {
      if (end < totalPages - 1) {
        const dots = document.createElement('span')
        dots.className = 'px-1 text-slate-500 text-xs'
        dots.textContent = '...'
        nav.appendChild(dots)
      }
      nav.appendChild(createPageBtn(totalPages, currentPage === totalPages, onPageChange))
    }

    // Next
    const next = document.createElement('button')
    next.className = `px-3 py-1.5 rounded-lg text-sm font-semibold transition-all ${currentPage === totalPages ? 'text-slate-600 cursor-default' : 'text-slate-300 hover:bg-slate-800'}`
    next.innerHTML = '<span class="inline-flex">' + icon('chevron-right') + '</span>'
    if (currentPage < totalPages) next.addEventListener('click', () => onPageChange(currentPage + 1))
    nav.appendChild(next)

    grid.appendChild(nav)
  }
}

function createPageBtn(page, isActive, onClick) {
  const btn = document.createElement('button')
  btn.className = `w-9 h-9 rounded-lg text-sm font-semibold transition-all ${isActive ? 'bg-astra-700 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800'}`
  btn.textContent = page
  if (!isActive) btn.addEventListener('click', () => onClick(page))
  return btn
}
