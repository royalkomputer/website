import './style.css'
import { Navbar, bindNavbarEvents } from './components/Navbar.js'
import { StoreStatus, loadHeadingText } from './components/StoreStatus.js'
import { FilterSidebar, bindFilterEvents, updateCategoryButtons, updateConditionButtons, updateSortButtons } from './components/FilterSidebar.js'
import { ProductGrid, renderProductGrid, showLoading, loadProductInfoText } from './components/ProductGrid.js'
import { ProductModal, openModal, bindModalEvents } from './components/ProductModal.js'
import { Footer } from './components/Footer.js'
import { renderPlaylist, bindAllCarousels } from './components/Banner.js'
import { fetchProducts, fetchStoreStatus, fetchBanners } from './lib/api.js'

import { isBekas } from './lib/format.js'

// ──────────────────────────────────────────────
//  App State
// ──────────────────────────────────────────────

const state = {
  allProducts: [],
  filteredProducts: [],
  filters: {
    category: 'Semua',
    search: '',
    sortBy: 'default',
    condition: 'Semua',
  },
  viewMode: 'grid',
  status: null,
  hours: null,
  hasActivated: false,
}

// ──────────────────────────────────────────────
//  Render the full page layout
// ──────────────────────────────────────────────

function renderApp() {
  const app = document.querySelector('#app')

  app.innerHTML = `
    ${Navbar({ onSearch: handleSearch })}
    <div class="js-status-container"></div>
    <main class="px-4 md:px-8 lg:px-12 py-8 flex-grow grid grid-cols-1 lg:grid-cols-5 gap-6">
      <div class="js-filter-container"></div>
      ${ProductGrid({ viewMode: state.viewMode })}
    </main>
    <div class="js-footer-container"></div>
    ${ProductModal()}
  `

  // Render the FilterSidebar into its container (will be re-rendered when data loads)
  const filterContainer = document.querySelector('.js-filter-container')
  if (filterContainer) {
    filterContainer.innerHTML = FilterSidebar(state.filters, ['Semua'], { 'Semua': 0 })
  }

  // Render the Footer placeholder (will be updated when hours load)
  const footerContainer = document.querySelector('.js-footer-container')
  if (footerContainer) {
    footerContainer.innerHTML = Footer(null)
  }

  // Bind events
  bindNavbarEvents(handleSearch)
  bindModalEvents()
  bindViewToggleEvents()

  // Load data
  loadData()
}

// ──────────────────────────────────────────────
//  Data Loading
// ──────────────────────────────────────────────

async function loadData() {
  // Load UI text (editable from admin)
  await loadProductInfoText()
  await loadHeadingText()

  showLoading(true)

  // Step 1: Load banners first (lightweight)
  await loadBanners()

  // Step 2: Load products (heavier)
  try {
    state.allProducts = await fetchProducts()
    state.filteredProducts = [...state.allProducts]

    // Re-render FilterSidebar with actual categories and counts
    const categories = getUniqueCategories()
    const categoryCounts = getCategoryCounts()
    const filterContainer = document.querySelector('.js-filter-container')
    if (filterContainer) {
      filterContainer.innerHTML = FilterSidebar(state.filters, categories, categoryCounts)
      bindFilterEvents(state.filters, function() {
        const isDefault = state.filters.category === 'Semua' && state.filters.search === '' && state.filters.sortBy === 'default' && state.filters.condition === 'Semua'
        if (isDefault) {
          state.hasActivated = true
          hideBanner()
        } else {
          hideBanner()
          state.hasActivated = true
        }
        applyFiltersAndRender()
      }, () => state.hasActivated)
    }
  } catch (err) {
    console.error('Failed to load products:', err)
    const emptyState = document.querySelector('.js-empty-state')
    if (emptyState) emptyState.classList.remove('hidden')
  } finally {
    showLoading(false)
  }

  // Load store status
  try {
    state.status = await fetchStoreStatus()
    const statusContainer = document.querySelector('.js-status-container')
    if (statusContainer) {
      statusContainer.innerHTML = StoreStatus(state.status)
    }
    const footerContainer = document.querySelector('.js-footer-container')
    if (footerContainer && state.status?.hours) {
      footerContainer.innerHTML = Footer(state.status.hours)
    }
  } catch (err) {
    console.error('Failed to load store status:', err)
  }
}

let bannerVisible = false

async function loadBanners() {
  try {
    const playlists = await fetchBanners()
    const container = document.querySelector('.js-banner-container')
    if (!container) return
    const active = playlists.filter(p => p.active !== false)
    if (active.length === 0) { container.classList.add('hidden'); bannerVisible = false; return }
    
    container.innerHTML = '<div class="js-banner-playlists flex flex-col gap-4"></div>'
    const list = container.querySelector('.js-banner-playlists')
    
    active.forEach((pl, idx) => {
      const html = renderPlaylist(pl, idx)
      if (html) list.innerHTML += html
    })
    
    container.classList.remove('hidden')
    bannerVisible = true
    if (active.length > 0) bindAllCarousels(active)
  } catch {
    bannerVisible = false
  }
}

function hideBanner() {
  const container = document.querySelector('.js-banner-container')
  if (!container || container.classList.contains('hidden') || container.classList.contains('banner-hiding')) return
  container.classList.add('banner-hiding')
  setTimeout(() => {
    container.classList.add('hidden')
    container.classList.remove('banner-hiding')
    bannerVisible = false
  }, 400)
}

function showInfoBar(el) {
  if (!el) return
  el.classList.remove('hidden', 'notif-enter')
  void el.offsetHeight
  el.classList.add('notif-enter')
}

function hideSearchPrompt() {
  const prompt = document.querySelector('.js-search-prompt')
  if (!prompt || prompt.classList.contains('hidden') || prompt.classList.contains('banner-hiding')) return
  prompt.classList.add('banner-hiding')
  setTimeout(() => {
    prompt.classList.add('hidden')
    prompt.classList.remove('banner-hiding')
  }, 400)
}

// ──────────────────────────────────────────────
//  Filtering & Sorting
// ──────────────────────────────────────────────

function getUniqueCategories() {
  const cats = [...new Set(state.allProducts.map(p => p.category))]
  return ['Semua', ...cats]
}

function getCategoryCounts() {
  const counts = { 'Semua': state.allProducts.length }
  state.allProducts.forEach(p => {
    counts[p.category] = (counts[p.category] || 0) + 1
  })
  return counts
}

function applyFiltersAndRender() {
  const { category, search, sortBy, condition } = state.filters

  const searchPrompt = document.querySelector('.js-search-prompt')
  const productGrid = document.querySelector('.js-product-grid')

  if (!state.hasActivated) {
    if (searchPrompt) { searchPrompt.classList.remove('hidden'); searchPrompt.classList.remove('banner-hiding') }
    if (productGrid) { productGrid.classList.add('hidden'); productGrid.innerHTML = '' }
    const emptyState = document.querySelector('.js-empty-state')
    if (emptyState) emptyState.classList.add('hidden')
    const countEl = document.querySelector('.js-product-count')
    if (countEl) countEl.textContent = '0'
    const infoBar = document.querySelector('.js-product-info-bar')
    if (infoBar) { infoBar.classList.remove('notif-enter'); infoBar.classList.add('hidden') }
    const resetBtn = document.querySelector('.js-reset-filters')
    if (resetBtn) { resetBtn.classList.add('opacity-50', 'cursor-not-allowed') }
    return
  }

  if (searchPrompt) hideSearchPrompt()
  if (productGrid) productGrid.classList.remove('hidden')
  const infoBar = document.querySelector('.js-product-info-bar')
  const banner = document.querySelector('.js-banner-container')
  if (infoBar) {
    if (banner && banner.classList.contains('banner-hiding')) {
      setTimeout(() => showInfoBar(infoBar), 400)
    } else {
      infoBar.classList.remove('hidden')
    }
  }

  state.filteredProducts = state.allProducts.filter(p => {
    const matchCategory = category === 'Semua' || p.category === category
    const searchStr = (search || '').toLowerCase()
    const matchSearch = (p.name || '').toLowerCase().includes(searchStr)
    const bekas = isBekas(p)
    let matchCondition = true
    if (condition === 'Baru') matchCondition = !bekas
    if (condition === 'Bekas') matchCondition = bekas
    return matchCategory && matchSearch && matchCondition
  })

  if (sortBy === 'low-high') {
    state.filteredProducts.sort((a, b) => (a.price || 0) - (b.price || 0))
  } else if (sortBy === 'high-low') {
    state.filteredProducts.sort((a, b) => (b.price || 0) - (a.price || 0))
  }

  // Update filter button visual states
  updateCategoryButtons(category)
  updateConditionButtons(condition)
  updateSortButtons(sortBy)

  renderProductGrid(state.filteredProducts, handleProductClick, state.viewMode)

  // Update reset button visual state (disabled while in banner view)
  const resetBtn = document.querySelector('.js-reset-filters')
  if (resetBtn) {
    const disabled = !state.hasActivated
    resetBtn.classList.toggle('opacity-50', disabled)
    resetBtn.classList.toggle('cursor-not-allowed', disabled)
  }
}

// ──────────────────────────────────────────────
//  Event Handlers
// ──────────────────────────────────────────────

function handleSearch(query) {
  state.filters.search = query
  hideBanner()
  state.hasActivated = true
  applyFiltersAndRender()
}

function handleProductClick(id) {
  const product = state.allProducts.find(p => p.id === id)
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
  applyFiltersAndRender()
  updateViewToggleUI()
}

function updateViewToggleUI() {
  document.querySelectorAll('.js-view-toggle').forEach(btn => {
    const isActive = btn.dataset.view === state.viewMode
    btn.className = `js-view-toggle flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-all ${isActive ? 'bg-astra-700 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-300 dark:bg-slate-600 dark:hover:bg-slate-500 dark:hover:text-white'}`
  })
}

// ──────────────────────────────────────────────
//  Initialize
// ──────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function() {
  renderApp()
})
