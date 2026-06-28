import { formatIDR, isBekas } from '../lib/format.js'

const WA_NUMBER = '6281380686168'

function waUrl(product, formattedPrice) {
  const text = encodeURIComponent(
    `Halo Admin Royal Komputer,\nSaya ingin membeli produk ini:\n\n*${product.name}*\nHarga: ${formattedPrice}\n\nApakah stoknya masih ready?`
  )
  return `https://wa.me/${WA_NUMBER}?text=${text}`
}

/**
 * ProductCard Component
 *
 * Renders a single product card with image (hover zoom), condition badge,
 * category badge, name, price, and WhatsApp button.
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

const fallbackSvg = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='300' viewBox='0 0 400 300'%3E%3Crect fill='%23f1f5f9' width='400' height='300'/%3E%3Ctext fill='%2394a3b8' font-family='sans-serif' font-size='14' x='50%25' y='50%25' text-anchor='middle' dy='.3em'%3ETidak ada gambar%3C/text%3E%3C/svg%3E`

return `
<div class="js-product-card bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col group cursor-pointer" ${clickAttr}>
  <div class="relative overflow-hidden aspect-[4/3] bg-slate-100 dark:bg-slate-700">
    <img src="${product.image}" alt="${product.name}" loading="lazy" onerror="this.src='${fallbackSvg}'"
         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
    ${badgeKondisi}
    <div class="absolute top-2 right-2 bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm text-astra-700 dark:text-astra-300 text-[9px] md:text-[10px] font-bold px-2 py-0.5 md:px-2 md:py-1 rounded-md shadow-sm">
      ${product.category}
    </div>
  </div>
  <div class="p-3 md:p-4 flex flex-col flex-grow">
    <h3 class="font-bold text-slate-800 dark:text-slate-100 text-sm md:text-base leading-tight mb-1 line-clamp-2">${product.name}</h3>
    <div class="mt-auto pt-2 md:pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2">
      <div class="text-sm md:text-base font-extrabold text-astra-700 dark:text-astra-400 truncate min-w-0">${formattedPrice}</div>
      <a href="${waUrl(product, formattedPrice)}" target="_blank" onclick="event.stopPropagation()" class="flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white text-xs font-bold px-2.5 py-1.5 rounded-lg transition-colors shadow-sm flex-shrink-0" title="Pesan via WhatsApp">
        <i class="fa-brands fa-whatsapp text-sm"></i>
      </a>
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
