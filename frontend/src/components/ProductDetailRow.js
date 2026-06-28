import { formatIDR, isBekas } from '../lib/format.js'

const WA_NUMBER = '6281380686168'

function waUrl(product, formattedPrice) {
  const text = encodeURIComponent(
    `Halo Admin Royal Komputer,\nSaya ingin membeli produk ini:\n\n*${product.name}*\nHarga: ${formattedPrice}\n\nApakah stoknya masih ready?`
  )
  return `https://wa.me/${WA_NUMBER}?text=${text}`
}

/**
 * ProductDetailRow Component
 *
 * Renders a single product as a horizontal table-like row with image thumbnail,
 * product name, category, condition badge, price, and WhatsApp button.
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

const fallbackSvg = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='150' viewBox='0 0 200 150'%3E%3Crect fill='%23f1f5f9' width='200' height='150'/%3E%3Ctext fill='%2394a3b8' font-family='sans-serif' font-size='12' x='50%25' y='50%25' text-anchor='middle' dy='.3em'%3ETidak ada gambar%3C/text%3E%3C/svg%3E`

return `
<div class="js-product-card bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex flex-row group cursor-pointer" data-id="${product.id}">
  <!-- Image thumbnail -->
  <div class="w-24 sm:w-28 md:w-32 flex-shrink-0 bg-slate-100 dark:bg-slate-700 overflow-hidden">
    <img src="${product.image}" alt="${product.name}" loading="lazy" onerror="this.src='${fallbackSvg}'"
         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
  </div>

  <!-- Info -->
  <div class="flex-1 flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-3 p-3 sm:p-4 min-w-0">
    <div class="flex flex-col gap-1 min-w-0 flex-1 w-full sm:w-auto">
      <div class="flex items-center gap-2 flex-wrap">
        <h3 class="font-bold text-slate-800 dark:text-slate-100 text-sm sm:text-base leading-tight line-clamp-1">${product.name}</h3>
        <span class="hidden sm:inline-flex">${badgeKondisi}</span>
      </div>
      <div class="flex items-center gap-2 sm:hidden flex-wrap">
        ${badgeKondisi}
        <span class="bg-astra-50 dark:bg-astra-900/40 text-astra-700 dark:text-astra-300 text-[9px] font-bold px-2 py-0.5 rounded border border-astra-100 dark:border-astra-800">${product.category}</span>
      </div>
      <span class="hidden sm:inline text-xs text-slate-500 dark:text-slate-400 font-medium">${product.category}</span>
    </div>

    <div class="text-base sm:text-lg font-extrabold text-astra-700 dark:text-astra-400 whitespace-nowrap">${formattedPrice}</div>

    <a href="${waUrl(product, formattedPrice)}" target="_blank" onclick="event.stopPropagation()" class="flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white text-xs font-bold px-2.5 py-1.5 rounded-lg transition-colors shadow-sm flex-shrink-0" title="Pesan via WhatsApp">
      <i class="fa-brands fa-whatsapp text-sm"></i>
    </a>
  </div>
</div>`
}
