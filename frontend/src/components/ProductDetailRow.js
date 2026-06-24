import { formatIDR, isBekas } from '../lib/format.js'

/**
 * ProductDetailRow Component
 *
 * Renders a single product as a horizontal table-like row with image thumbnail,
 * product name, category, condition badge, price, and detail button.
 *
 * @param {Object} product — product object from the API
 * @returns {string} HTML string
 */
export function ProductDetailRow(product) {
  const formattedPrice = formatIDR(product.price)
  const bekas = isBekas(product)

  const badgeKondisi = bekas
    ? `<span class="bg-orange-500/90 text-white text-[10px] font-bold px-2 py-0.5 rounded border border-orange-400">BEKAS</span>`
    : `<span class="bg-sky-500/90 text-white text-[10px] font-bold px-2 py-0.5 rounded border border-sky-400">BARU</span>`

  return `
<div class="js-product-card bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex flex-row group cursor-pointer" data-id="${product.id}">
  <!-- Image thumbnail -->
  <div class="w-24 sm:w-28 md:w-32 flex-shrink-0 bg-slate-100 overflow-hidden">
    <img src="${product.image}" alt="${product.name}" loading="lazy"
         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
         style="aspect-ratio: 4/3;">
  </div>

  <!-- Info -->
  <div class="flex-1 flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4 p-3 sm:p-4 min-w-0">
    <!-- Name & badges (mobile) -->
    <div class="flex flex-col gap-1 min-w-0 flex-1 w-full sm:w-auto">
      <div class="flex items-center gap-2 flex-wrap">
        <h3 class="font-bold text-slate-800 text-sm sm:text-base leading-tight line-clamp-1">${product.name}</h3>
        <span class="hidden sm:inline">${badgeKondisi}</span>
      </div>
      <div class="flex items-center gap-2 sm:hidden">
        ${badgeKondisi}
        <span class="bg-astra-50 text-astra-700 text-[10px] font-bold px-2 py-0.5 rounded border border-astra-100">${product.category}</span>
      </div>
      <span class="hidden sm:inline text-xs text-slate-400 font-medium">${product.category}</span>
    </div>

    <!-- Price -->
    <div class="text-base sm:text-lg font-extrabold text-astra-700 whitespace-nowrap">${formattedPrice}</div>

    <!-- Action -->
    <div class="text-xs text-astra-600 font-bold bg-astra-50 hover:bg-astra-100 px-3 py-1.5 rounded-lg transition-colors whitespace-nowrap flex-shrink-0">
      Detail <i class="fa-solid fa-chevron-right ml-1"></i>
    </div>
  </div>
</div>`
}
