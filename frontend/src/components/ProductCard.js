import { formatIDR, isBekas } from '../lib/format.js'

/**
 * ProductCard Component
 *
 * Renders a single product card with image (hover zoom), condition badge,
 * category badge, name, price, and detail button.
 *
 * @param {Object} product — product object from the API
 * @param {(id: string) => void} onDetail — callback when card is clicked
 * @returns {string} HTML string
 */
export function ProductCard(product, onDetail) {
  const formattedPrice = formatIDR(product.price)
  const bekas = isBekas(product)
  const clickAttr = `data-id="${product.id}"`

  const badgeKondisi = bekas
    ? `<div class="absolute top-3 left-3 bg-orange-500/90 backdrop-blur-sm text-white text-[10px] font-bold px-2.5 py-1 rounded-lg shadow-sm border border-orange-400">BEKAS</div>`
    : `<div class="absolute top-3 left-3 bg-sky-500/90 backdrop-blur-sm text-white text-[10px] font-bold px-2.5 py-1 rounded-lg shadow-sm border border-sky-400">BARU</div>`

  return `
<div class="js-product-card bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col group cursor-pointer" ${clickAttr}>
  <div class="relative overflow-hidden aspect-video bg-slate-100">
    <img src="${product.image}" alt="${product.name}" loading="lazy"
         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
    ${badgeKondisi}
    <div class="absolute top-3 right-3 bg-white/90 backdrop-blur-sm text-astra-700 text-[10px] font-bold px-2 py-1 rounded-lg shadow-sm">
      ${product.category}
    </div>
  </div>
  <div class="p-3 md:p-4 flex flex-col flex-grow">
    <h3 class="font-bold text-slate-800 text-sm md:text-base leading-tight mb-1 line-clamp-2">${product.name}</h3>
    <div class="mt-auto pt-2 md:pt-3 border-t border-slate-100 flex items-center justify-between gap-1">
      <div class="text-sm md:text-base font-extrabold text-astra-700 truncate">${formattedPrice}</div>
      <div class="text-[10px] md:text-xs text-astra-600 font-bold bg-astra-50 px-2 md:px-3 py-1 rounded-lg whitespace-nowrap">
        Detail <i class="fa-solid fa-chevron-right ml-0.5"></i>
      </div>
    </div>
  </div>
</div>`
}

/**
 * Bind click events on all product cards to open the detail modal.
 * Uses event delegation on the container element.
 *
 * @param {HTMLElement} container — the product grid container
 * @param {(id: string) => void} onCardClick
 */
export function bindProductCardClicks(container, onCardClick) {
  container.addEventListener('click', (e) => {
    const card = e.target.closest('.js-product-card')
    if (card && card.dataset.id) {
      onCardClick(card.dataset.id)
    }
  })
}
