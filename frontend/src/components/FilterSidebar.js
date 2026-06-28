/**
 * FilterSidebar Component
 *
 * Renders: collapsible sidebar with category buttons, condition dropdown,
 * sort dropdown, and reset button. Collapsible on mobile via click handler.
 *
 * @param {Object} filters — current filter state { category, search, sortBy, condition }
 * @param {string[]} categories — list of unique category names
 * @param {Object} categoryCounts — map of category → product count (e.g., { 'Semua': 50, 'Processor': 12 })
 * @returns {string} HTML string
 */
export function FilterSidebar(filters, categories, categoryCounts) {
  categoryCounts = categoryCounts || {}

  return `
<aside class="lg:col-span-1 bg-white rounded-xl border border-slate-200 shadow-sm self-start overflow-hidden">

  <!-- Toggle header (mobile) -->
  <button class="js-filter-toggle w-full p-4 flex items-center justify-between lg:cursor-default focus:outline-none bg-slate-50 lg:bg-white border-b border-slate-100 lg:border-none">
    <h3 class="font-bold text-slate-900 flex items-center gap-2">
      <i class="fa-solid fa-sliders text-astra-700"></i> Filter & Urutkan
    </h3>
    <i class="js-filter-icon fa-solid fa-chevron-down text-slate-500 transition-transform duration-300 lg:hidden"></i>
  </button>

  <!-- Filter content -->
  <div class="js-filter-content hidden lg:block p-3 lg:p-4 lg:pt-0">

    <!-- Reset button -->
    <div class="flex justify-end mb-5 lg:pb-3 lg:border-b lg:border-slate-100">
      <button class="js-reset-filters text-xs text-astra-600 font-semibold bg-astra-50 hover:bg-astra-100 lg:bg-transparent lg:hover:bg-transparent lg:p-0 px-3 py-1.5 rounded-lg transition-colors">
        <i class="fa-solid fa-arrow-rotate-right mr-1"></i> Reset Filter
      </button>
    </div>

    <!-- Category -->
    <div class="mb-6">
      <button type="button" class="js-category-toggle w-full flex items-center justify-between text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 focus:outline-none">
        <span>Kategori</span>
        <i class="js-category-icon fa-solid fa-chevron-down text-slate-400 transition-transform duration-200"></i>
      </button>
      <div class="js-category-panel space-y-1">
        ${categories.map(cat => {
          const isSelected = filters.category === cat
          const count = categoryCounts[cat] || 0
          return `<button class="js-cat-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center justify-between ${
            isSelected ? 'bg-astra-700 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-slate-100'
          }" data-category="${cat}">
            ${isSelected ? '<i class="fa-solid fa-check mr-1.5 text-white"></i>' : ''}
            <span>${cat}</span>
            <span class="${isSelected ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'} text-xs px-2 py-0.5 rounded-full">${count}</span>
          </button>`
        }).join('')}
      </div>
    </div>

    <!-- Condition -->
    <div class="mb-6 border-t border-slate-100 pt-5">
      <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Kondisi</label>
      <div class="flex gap-2">
        <button class="js-cond-btn flex-1 px-3 py-2 rounded-lg text-sm font-medium transition-all text-center" data-condition="Semua">
          <i class="fa-solid fa-check hidden"></i> Semua
        </button>
        <button class="js-cond-btn flex-1 px-3 py-2 rounded-lg text-sm font-medium transition-all text-center" data-condition="Baru">
          <i class="fa-solid fa-check hidden"></i> Baru
        </button>
        <button class="js-cond-btn flex-1 px-3 py-2 rounded-lg text-sm font-medium transition-all text-center" data-condition="Bekas">
          <i class="fa-solid fa-check hidden"></i> Bekas
        </button>
      </div>
    </div>

    <!-- Sort -->
    <div class="border-t border-slate-100 pt-5">
      <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Urutkan</label>
      <div class="space-y-1">
        <button class="js-sort-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2" data-sort="default">
          <i class="fa-regular fa-star text-slate-400 w-4"></i> Rekomendasi Teratas
        </button>
        <button class="js-sort-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2" data-sort="low-high">
          <i class="fa-solid fa-arrow-up-wide-short text-slate-400 w-4"></i> Harga: Rendah ke Tinggi
        </button>
        <button class="js-sort-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2" data-sort="high-low">
          <i class="fa-solid fa-arrow-down-wide-short text-slate-400 w-4"></i> Harga: Tinggi ke Rendah
        </button>
      </div>
    </div>

  </div>

</aside>`
}

/**
 * Bind FilterSidebar event listeners.
 *
 * @param {Object} filters — mutable filter state reference
 * @param {Function} onFilterChange — called when any filter changes
 */
export function bindFilterEvents(filters, onFilterChange, canReset) {
  const filterContent = document.querySelector('.js-filter-content')
  const filterToggle = document.querySelector('.js-filter-toggle')
  const filterIcon = document.querySelector('.js-filter-icon')

  // Mobile toggle
  if (filterToggle && filterContent && filterIcon) {
    filterToggle.addEventListener('click', () => {
      if (window.innerWidth < 1024) {
        filterContent.classList.toggle('hidden')
        filterIcon.classList.toggle('rotate-180')
      }
    })
  }

  // Category toggle (collapse/expand)
  const catToggle = document.querySelector('.js-category-toggle')
  const catPanel = document.querySelector('.js-category-panel')
  const catIcon = document.querySelector('.js-category-icon')
  if (catToggle && catPanel && catIcon) {
    catToggle.addEventListener('click', () => {
      const isHidden = catPanel.style.display === 'none'
      catPanel.style.display = isHidden ? '' : 'none'
      catIcon.classList.toggle('rotate-180')
    })
  }

  // Category buttons
  document.querySelectorAll('.js-cat-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      filters.category = btn.dataset.category
      onFilterChange()
    })
  })

  // Condition buttons
  document.querySelectorAll('.js-cond-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      filters.condition = btn.dataset.condition
      onFilterChange()
    })
  })

  // Sort buttons
  document.querySelectorAll('.js-sort-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      filters.sortBy = btn.dataset.sort
      onFilterChange()
    })
  })

  // Reset button
  const resetBtn = document.querySelector('.js-reset-filters')
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      if (canReset && !canReset()) return
      filters.category = 'Semua'
      filters.search = ''
      filters.sortBy = 'default'
      filters.condition = 'Semua'

      // Reset UI
      document.querySelectorAll('.js-search-input, .js-search-input-mobile').forEach(el => { if (el) el.value = '' })
      if (condSelect) condSelect.value = 'Semua'
      if (sortSelect) sortSelect.value = 'default'

      onFilterChange()
    })
  }
}

/**
 * Update visual selection state for category buttons.
 */
export function updateConditionButtons(selected) {
  document.querySelectorAll('.js-cond-btn').forEach(btn => {
    const isSelected = btn.dataset.condition === selected
    btn.className = `${isSelected ? 'bg-astra-700 text-white font-semibold shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100'} js-cond-btn flex-1 px-3 py-2 rounded-lg text-sm font-medium transition-all text-center`
    const icon = btn.querySelector('.fa-check')
    if (icon) icon.classList.toggle('hidden', !isSelected)
  })
}

export function updateSortButtons(selected) {
  document.querySelectorAll('.js-sort-btn').forEach(btn => {
    const isSelected = btn.dataset.sort === selected
    btn.className = `js-sort-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2 ${isSelected ? 'bg-astra-700 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-slate-100'}`
    const icon = btn.querySelector('i')
    if (icon) icon.className = isSelected ? 'fa-solid fa-check text-white w-4' : btn.dataset.sort === 'default' ? 'fa-regular fa-star text-slate-400 w-4' : btn.dataset.sort === 'low-high' ? 'fa-solid fa-arrow-up-wide-short text-slate-400 w-4' : 'fa-solid fa-arrow-down-wide-short text-slate-400 w-4'
  })
}

export function updateCategoryButtons(selectedCategory) {
  document.querySelectorAll('.js-cat-btn').forEach(btn => {
    const isSelected = btn.dataset.category === selectedCategory
    const name = btn.querySelector('span')
    const countBadge = btn.querySelectorAll('span')[1]
    if (isSelected) {
      btn.className = 'js-cat-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center justify-between bg-astra-700 text-white font-semibold shadow-sm'
      if (!btn.querySelector('.fa-check')) {
        const icon = document.createElement('i')
        icon.className = 'fa-solid fa-check mr-1.5 text-white'
        btn.insertBefore(icon, name)
      }
      if (countBadge) {
        countBadge.className = 'bg-white/20 text-white text-xs px-2 py-0.5 rounded-full'
      }
    } else {
      btn.className = 'js-cat-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center justify-between text-slate-600 hover:bg-slate-100'
      const icon = btn.querySelector('.fa-check')
      if (icon) icon.remove()
      if (countBadge) {
        countBadge.className = 'bg-slate-100 text-slate-500 text-xs px-2 py-0.5 rounded-full'
      }
    }
  })
}
