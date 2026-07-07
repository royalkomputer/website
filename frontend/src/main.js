import './style.css'
import { Navbar, bindNavbarEvents } from './components/Navbar.js'
import { FilterSidebar, bindFilterEvents, updateCategoryButtons, updateConditionButtons, updateSortButtons } from './components/FilterSidebar.js'
import { ProductGrid, renderProductGrid, showSkeletonLoading, hideSkeletonLoading, loadProductInfoText } from './components/ProductGrid.js'
import { ProductModal, openModal, bindModalEvents } from './components/ProductModal.js'
import { Footer } from './components/Footer.js'
import { fetchProductsPage } from './lib/api.js'

const PAGE_SIZE = 32

const state = {
  products: [],
  categories: null,
  filters: {
    category: 'Semua',
    search: '',
    sortBy: 'default',
    condition: 'Semua',
  },
  viewMode: 'detail',
  hasActivated: false,
  currentPage: 1,
  totalProducts: 0,
  totalPages: 0,
  isLoading: false,
}

function renderApp() {
  const app = document.querySelector('#app')

  app.innerHTML = `
    ${Navbar({ onSearch: handleSearch })}

    <!-- banner removed -->
    <main class="px-4 md:px-8 lg:px-12 pb-8 flex-grow grid grid-cols-1 lg:grid-cols-5 gap-6">
      <div class="js-filter-container hidden"></div>
      ${ProductGrid({ viewMode: state.viewMode })}
    </main>
    <div class="js-footer-container"></div>
    ${ProductModal()}
  `

  const footerContainer = document.querySelector('.js-footer-container')
  if (footerContainer) {
    footerContainer.innerHTML = Footer(null)
  }

  bindNavbarEvents(handleSearch)
  bindModalEvents()
  bindViewToggleEvents()

  initApp()
}

async function initApp() {
  // Data API calls happen last — triggered after first product load
}



async function loadProducts(page = 1) {
  if (state.isLoading) return
  state.isLoading = true

  showSkeletonLoading(true)

  try {
    const cat = state.filters.category === 'Semua' ? '' : state.filters.category
    const result = await fetchProductsPage({
      page,
      limit: PAGE_SIZE,
      category: cat,
      search: state.filters.search,
      condition: state.filters.condition === 'Semua' ? '' : state.filters.condition,
      sort: state.filters.sortBy,
    })

    state.categories = result.categories || null

    if (!state.hasActivated) {
      state.hasActivated = true
      updateLayout()
    }

    state.products = result.data
    state.currentPage = result.page
    state.totalProducts = result.total
    state.totalPages = Math.ceil(result.total / PAGE_SIZE)

    const filterContainer = document.querySelector('.js-filter-container')
    if (filterContainer && state.categories) {
      filterContainer.innerHTML = FilterSidebar(state.filters, ['Semua', ...Object.keys(state.categories)])
      filterContainer.classList.remove('hidden')
      bindFilterEvents(state.filters, () => {
        loadProducts(1)
      }, () => state.hasActivated)
    }

    renderProducts()

    // Data API calls last
    await loadProductInfoText()
  } catch (err) {
    console.error('Failed to load products:', err)
    const emptyState = document.querySelector('.js-empty-state')
    if (emptyState) emptyState.classList.remove('hidden')
  } finally {
    hideSkeletonLoading()
    state.isLoading = false
  }
}

function updateLayout() {
  const section = document.querySelector('.js-product-section')
  if (section) {
    section.classList.remove('lg:col-span-5')
    section.classList.add('lg:col-span-4')
  }
}

function renderProducts() {
  const productGrid = document.querySelector('.js-product-grid')
  const infoBar = document.querySelector('.js-product-info-bar')

  if (!state.hasActivated) {
    if (productGrid) { productGrid.classList.add('hidden'); productGrid.innerHTML = '' }
    const emptyState = document.querySelector('.js-empty-state')
    if (emptyState) emptyState.classList.add('hidden')
    const countEl = document.querySelector('.js-product-count')
    if (countEl) countEl.textContent = '0'
    if (infoBar) { infoBar.classList.remove('notif-enter'); infoBar.classList.add('hidden') }
    const resetBtn = document.querySelector('.js-reset-filters')
    if (resetBtn) { resetBtn.classList.add('opacity-50', 'cursor-not-allowed') }
    return
  }

  if (productGrid) productGrid.classList.remove('hidden')

  if (infoBar) {
    infoBar.classList.remove('hidden')
  }

  updateCategoryButtons(state.filters.category)
  updateConditionButtons(state.filters.condition)
  updateSortButtons(state.filters.sortBy)

  renderProductGrid(state.products, handleProductClick, state.viewMode, state.currentPage, state.totalPages, goToPage)

  const resetBtn = document.querySelector('.js-reset-filters')
  if (resetBtn) {
    resetBtn.classList.toggle('opacity-50', !state.hasActivated)
    resetBtn.classList.toggle('cursor-not-allowed', !state.hasActivated)
  }
}

function handleSearch(query) {
  state.filters.search = query
  loadProducts(1)
}

function handleProductClick(id) {
  const product = state.products.find(p => p.id === id)
  if (product) openModal(product)
}

function bindViewToggleEvents() {
  document.querySelectorAll('.js-view-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const mode = btn.dataset.view
      if (mode && mode !== state.viewMode) {
        handleViewModeChange(mode)
      }
    })
  })
}

function handleViewModeChange(mode) {
  state.viewMode = mode
  renderProducts()
  updateViewToggleUI()
}

function updateViewToggleUI() {
  document.querySelectorAll('.js-view-toggle').forEach(btn => {
    const isActive = btn.dataset.view === state.viewMode
    btn.className = `js-view-toggle flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-all ${isActive ? 'bg-astra-700 text-white shadow-sm' : 'bg-slate-700 text-slate-400 hover:text-slate-200'}`
  })
}

function goToPage(page) {
  if (page < 1 || page > state.totalPages || page === state.currentPage) return
  loadProducts(page)
}

function resetToInitial() {
  state.hasActivated = false
  state.filters = { category: 'Semua', search: '', sortBy: 'default', condition: 'Semua' }
  state.products = []
  state.currentPage = 1
  state.totalProducts = 0
  state.totalPages = 0
  state.isLoading = false

  document.querySelectorAll('.js-search-input, .js-search-input-mobile').forEach(el => { if (el) el.value = '' })

  const filterContainer = document.querySelector('.js-filter-container')
  if (filterContainer) filterContainer.classList.add('hidden')

  const section = document.querySelector('.js-product-section')
  if (section) {
    section.classList.remove('lg:col-span-4')
    section.classList.add('lg:col-span-5')
  }

  renderProducts()
}

function bindLogoReset() {
  const logo = document.querySelector('.js-logo-reset')
  if (logo) {
    logo.addEventListener('click', (e) => {
      e.preventDefault()
      resetToInitial()
    })
  }
}

document.addEventListener('DOMContentLoaded', function() {
  renderApp()
  setTimeout(bindLogoReset, 0)
})
