import { icon } from '../lib/icons.js'

/**
 * FilterSidebar Component
 *
 * Renders: collapsible sidebar with category buttons, condition dropdown,
 * sort dropdown, and reset button. Collapsible on mobile via click handler.
 *
 * @param {Object} filters — current filter state { category, search, sortBy, condition }
 * @param {string[]} categories — list of unique category names
 * @returns {string} HTML string
 */
export function FilterSidebar(filters, categories) {

  return `
<aside class="lg:col-span-1 bg-slate-800/80 rounded-xl border border-white/10 shadow-sm self-start overflow-hidden">

  <!-- Toggle header (desktop: no toggle, mobile: collapsible) -->
  <button class="js-filter-toggle w-full p-4 flex items-center justify-between lg:cursor-default focus:outline-none bg-slate-800/80 border-b border-white/10">
    <h3 class="font-bold text-white flex items-center gap-2">
      ${icon('sliders')} Filter & Urutkan
    </h3>
    <span class="js-filter-icon text-slate-400 transition-transform duration-300 lg:hidden">${icon('chevron-down')}</span>
  </button>

  <!-- Filter content (hidden on mobile by default, visible on desktop) -->
  <div class="js-filter-content hidden lg:block p-3">

    <!-- Reset button -->
    <div class="flex justify-end mb-5 lg:pb-3 lg:border-b lg:border-slate-700">
      <button class="js-reset-filters text-xs text-astra-400 font-semibold bg-astra-900/30 hover:bg-astra-900/50 lg:bg-transparent lg:hover:bg-transparent lg:p-0 px-3 py-1.5 rounded-lg transition-colors">
        <span class="mr-1 inline-flex">${icon('arrow-rotate-right')}</span> Reset Filter
      </button>
    </div>

    <!-- Category -->
    <div class="mb-6">
      <button type="button" class="js-category-toggle w-full flex items-center justify-between text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 focus:outline-none">
        <span>Kategori</span>
        <span class="js-category-icon text-slate-400 transition-transform duration-200">${icon('chevron-down')}</span>
      </button>
      <div class="js-category-panel flex flex-col items-start gap-1">
        ${categories.map(cat => {
          const isSelected = filters.category === cat
          return `<button class="js-cat-btn text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2 ${
            isSelected ? 'bg-astra-700 text-white font-semibold shadow-sm' : 'text-slate-300 hover:bg-slate-800'
          }" data-category="${cat}">
            ${isSelected ? '<span class="inline-flex">' + icon('check', 'text-white') + '</span>' : ''}
            <span>${cat}</span>
          </button>`
        }).join('')}
      </div>
    </div>

    <!-- Condition -->
    <div class="mb-6 border-t border-slate-700 pt-5">
      <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Kondisi</label>
      <div class="flex gap-2">
        <button class="js-cond-btn flex-1 px-3 py-2 rounded-lg text-sm font-medium transition-all text-center bg-slate-800/80 border border-white/10 text-slate-300 hover:bg-slate-800" data-condition="Semua">
          <span class="js-cond-check inline-flex hidden">${icon('check')}</span> Semua
        </button>
        <button class="js-cond-btn flex-1 px-3 py-2 rounded-lg text-sm font-medium transition-all text-center bg-slate-800/80 border border-white/10 text-slate-300 hover:bg-slate-800" data-condition="Baru">
          <span class="js-cond-check inline-flex hidden">${icon('check')}</span> Baru
        </button>
        <button class="js-cond-btn flex-1 px-3 py-2 rounded-lg text-sm font-medium transition-all text-center bg-slate-800/80 border border-white/10 text-slate-300 hover:bg-slate-800" data-condition="Bekas">
          <span class="js-cond-check inline-flex hidden">${icon('check')}</span> Bekas
        </button>
      </div>
    </div>

    <!-- Sort -->
    <div class="border-t border-slate-700 pt-5">
      <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Urutkan</label>
      <div class="space-y-1">
        <button class="js-sort-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2 text-slate-300 hover:bg-slate-800" data-sort="default">
          <span class="js-sort-icon text-slate-400">${icon('star')}</span> Rekomendasi Teratas
        </button>
        <button class="js-sort-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2 text-slate-300 hover:bg-slate-800" data-sort="low-high">
          <span class="js-sort-icon text-slate-400">${icon('arrow-up-wide-short')}</span> Harga: Rendah ke Tinggi
        </button>
        <button class="js-sort-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2 text-slate-300 hover:bg-slate-800" data-sort="high-low">
          <span class="js-sort-icon text-slate-400">${icon('arrow-down-wide-short')}</span> Harga: Tinggi ke Rendah
        </button>
      </div>
    </div>

  </div>

</aside>`
}

/**
 * Bind FilterSidebar event listeners using event delegation on the filter container.
 * This prevents duplicate listeners when bindFilterEvents is called multiple times.
 *
 * @param {Object} filters — mutable filter state reference
 * @param {Function} onFilterChange — called when any filter changes
 */
export function bindFilterEvents(filters, onFilterChange, canReset) {
  const aside = document.querySelector('.js-filter-container > aside')
  if (!aside) return

  // Prevent double-binding: skip if already bound
  if (aside.dataset.eventsBound) return
  aside.dataset.eventsBound = 'true'

  // Mobile toggle — event delegation on aside
  aside.addEventListener('click', (e) => {
    const toggle = e.target.closest('.js-filter-toggle')
    if (!toggle) return

    if (window.innerWidth < 1024) {
      const content = aside.querySelector('.js-filter-content')
      const icon = aside.querySelector('.js-filter-icon')
      if (content && icon) {
        content.classList.toggle('hidden')
        icon.classList.toggle('rotate-180')
      }
    }
  })

  // Category toggle (collapse/expand)
  aside.addEventListener('click', (e) => {
    const catToggle = e.target.closest('.js-category-toggle')
    if (!catToggle) return

    const panel = aside.querySelector('.js-category-panel')
    const icon = aside.querySelector('.js-category-icon')
    if (panel && icon) {
      const isHidden = panel.style.display === 'none'
      panel.style.display = isHidden ? '' : 'none'
      icon.classList.toggle('rotate-180')
    }
  })

  // Category buttons
  aside.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-cat-btn')
    if (!btn) return

    filters.category = btn.dataset.category
    onFilterChange()
  })

  // Condition buttons
  aside.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-cond-btn')
    if (!btn) return

    filters.condition = btn.dataset.condition
    onFilterChange()
  })

  // Sort buttons
  aside.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-sort-btn')
    if (!btn) return

    filters.sortBy = btn.dataset.sort
    onFilterChange()
  })

  // Reset button
  aside.addEventListener('click', (e) => {
    const resetBtn = e.target.closest('.js-reset-filters')
    if (!resetBtn) return

    if (canReset && !canReset()) return

    filters.category = 'Semua'
    filters.search = ''
    filters.sortBy = 'default'
    filters.condition = 'Semua'

    // Reset search inputs
    document.querySelectorAll('.js-search-input, .js-search-input-mobile').forEach(el => { if (el) el.value = '' })

    onFilterChange()
  })
}

/**
 * Update visual selection state for condition buttons.
 */
export function updateConditionButtons(selected) {
  document.querySelectorAll('.js-cond-btn').forEach(btn => {
    const isSelected = btn.dataset.condition === selected
    btn.className = `${isSelected ? 'bg-astra-700 text-white font-semibold shadow-sm' : 'bg-slate-800/80 border border-white/10 text-slate-300 hover:bg-slate-800'} js-cond-btn flex-1 px-3 py-2 rounded-lg text-sm font-medium transition-all text-center`
    const checkIcon = btn.querySelector('.js-cond-check')
    if (checkIcon) checkIcon.classList.toggle('hidden', !isSelected)
  })
}

export function updateSortButtons(selected) {
  document.querySelectorAll('.js-sort-btn').forEach(btn => {
    const isSelected = btn.dataset.sort === selected
    const base = 'js-sort-btn w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2'
    btn.className = isSelected
      ? `${base} bg-astra-700 text-white font-semibold shadow-sm`
      : `${base} text-slate-300 hover:bg-slate-800`
    const iconWrap = btn.querySelector('.js-sort-icon')
    if (iconWrap) {
      if (isSelected) {
        iconWrap.className = 'js-sort-icon'
        iconWrap.innerHTML = icon('check')
      } else {
        const iconName = btn.dataset.sort === 'default' ? 'star' : btn.dataset.sort === 'low-high' ? 'arrow-up-wide-short' : 'arrow-down-wide-short'
        iconWrap.className = 'js-sort-icon text-slate-400'
        iconWrap.innerHTML = icon(iconName)
      }
    }
  })
}

export function updateCategoryButtons(selectedCategory) {
  document.querySelectorAll('.js-cat-btn').forEach(btn => {
    const isSelected = btn.dataset.category === selectedCategory
    const name = btn.querySelector('span')
    if (isSelected) {
      btn.className = 'js-cat-btn text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2 bg-astra-700 text-white font-semibold shadow-sm'
      let checkIcon = btn.querySelector('.js-cat-check')
      if (!checkIcon) {
        checkIcon = document.createElement('span')
        checkIcon.className = 'js-cat-check inline-flex text-white'
        checkIcon.innerHTML = icon('check')
        btn.insertBefore(checkIcon, name)
      }
    } else {
      btn.className = 'js-cat-btn text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2 text-slate-300 hover:bg-slate-800'
      const checkIcon = btn.querySelector('.js-cat-check')
      if (checkIcon) checkIcon.remove()
    }
  })
}
