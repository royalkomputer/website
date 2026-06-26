import './style.css'
import { Navbar, bindNavbarEvents } from './components/Navbar.js'
import { StoreStatus, loadHeadingText } from './components/StoreStatus.js'
import { FilterSidebar, bindFilterEvents, updateCategoryButtons } from './components/FilterSidebar.js'
import { ProductGrid, renderProductGrid, showLoading, loadProductInfoText } from './components/ProductGrid.js'
import { ProductModal, openModal, bindModalEvents } from './components/ProductModal.js'
import { Footer } from './components/Footer.js'
import { fetchProducts, fetchStoreStatus } from './lib/api.js'
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

  // Load products
  showLoading(true)
  try {
    state.allProducts = await fetchProducts()
    state.filteredProducts = [...state.allProducts]

    // Re-render FilterSidebar with actual categories and counts
    const categories = getUniqueCategories()
    const categoryCounts = getCategoryCounts()
    const filterContainer = document.querySelector('.js-filter-container')
    if (filterContainer) {
      filterContainer.innerHTML = FilterSidebar(state.filters, categories, categoryCounts)
      bindFilterEvents(state.filters, applyFiltersAndRender)
    }

    applyFiltersAndRender()
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
  } catch (err) {
    console.error('Failed to load store status:', err)
  }

  // Load operating hours (for footer)
  try {
    const res = await fetch(`${import.meta.env.BASE_URL}jam_operasional.json`)
    state.hours = await res.json()
    const footerContainer = document.querySelector('.js-footer-container')
    if (footerContainer) {
      footerContainer.innerHTML = Footer(state.hours)
    }
  } catch (err) {
    console.error('Failed to load hours:', err)
  }
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

  // Update category button visual state
  updateCategoryButtons(category)

  renderProductGrid(state.filteredProducts, handleProductClick, state.viewMode)
}

// ──────────────────────────────────────────────
//  Event Handlers
// ──────────────────────────────────────────────

function handleSearch(query) {
  state.filters.search = query
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
    btn.className = `flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-all ${isActive ? 'bg-astra-700 text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700'}`
  })
}

// ──────────────────────────────────────────────
//  Initialize
// ──────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', renderApp)
