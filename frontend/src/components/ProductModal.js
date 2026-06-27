import { formatIDR, isBekas } from '../lib/format.js'

/**
 * ProductModal Component
 *
 * Renders the product detail modal with image carousel (prev/next/dots),
 * product info, condition badge, description, stock, and WhatsApp button.
 * Uses event delegation to bind carousel controls.
 *
 * @returns {string} HTML string for the modal overlay
 */
export function ProductModal() {
  return `
<div class="js-detail-modal fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col md:flex-row relative">

    <!-- Close button -->
    <button class="js-modal-close absolute top-4 right-4 z-10 w-8 h-8 bg-black/20 hover:bg-black/40 text-white rounded-full flex items-center justify-center transition-colors">
      <i class="fa-solid fa-xmark"></i>
    </button>

    <!-- Left: Image gallery -->
    <div class="w-full md:w-1/2 bg-slate-100 relative group min-h-[300px] flex items-center justify-center">
      <img class="js-modal-image w-full h-full object-contain max-h-[500px]" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E" alt="Detail" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
      <div class="js-modal-image-fallback hidden absolute inset-0 flex-col items-center justify-center text-slate-400">
        <i class="fa-solid fa-image text-5xl mb-2"></i>
        <span class="text-sm">Gambar tidak tersedia</span>
      </div>

      <!-- Carousel navigation -->
      <button class="js-prev-img absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/80 hover:bg-white text-slate-800 rounded-full flex items-center justify-center shadow-lg hidden">
        <i class="fa-solid fa-chevron-left"></i>
      </button>
      <button class="js-next-img absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/80 hover:bg-white text-slate-800 rounded-full flex items-center justify-center shadow-lg hidden">
        <i class="fa-solid fa-chevron-right"></i>
      </button>

      <!-- Dot indicators -->
      <div class="js-img-indicators absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2"></div>
    </div>

    <!-- Right: Product info -->
    <div class="w-full md:w-1/2 p-6 md:p-8 flex flex-col max-h-[50vh] md:max-h-full overflow-y-auto">
      <div class="js-detail-badge mb-3"></div>
      <h2 class="js-detail-name text-2xl font-extrabold text-slate-800 mb-2"></h2>
      <div class="js-detail-price text-3xl font-black text-astra-700 mb-6"></div>

      <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 mb-6 flex-grow">
        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Deskripsi & Spesifikasi</h4>
        <p class="js-detail-desc text-sm text-slate-600 whitespace-pre-line leading-relaxed"></p>
      </div>

      <div class="mt-auto pt-4 border-t border-slate-100">
        <div class="flex items-center justify-between mb-4">
          <span class="text-sm text-slate-500">Sisa Stok: <strong class="js-detail-stock text-slate-800"></strong></span>
        </div>
        <a class="js-detail-wa-btn w-full flex justify-center items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-bold py-3.5 px-4 rounded-xl transition-colors shadow-lg text-sm" href="#" target="_blank">
          <i class="fa-brands fa-whatsapp text-xl"></i> Beli via WhatsApp
        </a>
      </div>
    </div>

  </div>
</div>`
}

/**
 * State for the carousel.
 */
const modalState = {
  images: [],
  currentIndex: 0,
}

/**
 * Open the detail modal with a product's data.
 *
 * @param {Object} product — product object
 */
export function openModal(product) {
  if (!product) return

  document.querySelector('.js-detail-name').textContent = product.name

  const formattedPrice = formatIDR(product.price)
  document.querySelector('.js-detail-price').textContent = formattedPrice
  document.querySelector('.js-detail-desc').textContent = product.description || 'Tidak ada deskripsi rinci untuk produk ini.'
  document.querySelector('.js-detail-stock').textContent = product.stock

  const bekas = isBekas(product)
  document.querySelector('.js-detail-badge').innerHTML = bekas
    ? `<span class="bg-orange-100 text-orange-700 text-xs font-bold px-2.5 py-1 rounded-md border border-orange-200">KONDISI: BEKAS</span>`
    : `<span class="bg-sky-100 text-sky-700 text-xs font-bold px-2.5 py-1 rounded-md border border-sky-200">KONDISI: BARU</span>`

  const waNumber = '6281380686168'
  const waText = encodeURIComponent(
    `Halo Admin Royal Komputer,\nSaya ingin membeli produk ini:\n\n*${product.name}*\nHarga: ${formattedPrice}\n\nApakah stoknya masih ready?`
  )
  document.querySelector('.js-detail-wa-btn').href = `https://wa.me/${waNumber}?text=${waText}`

  // Carousel setup
  modalState.images = product.images || [product.image]
  modalState.currentIndex = 0
  updateCarousel()

  const prevBtn = document.querySelector('.js-prev-img')
  const nextBtn = document.querySelector('.js-next-img')
  if (modalState.images.length > 1) {
    prevBtn.classList.remove('hidden')
    nextBtn.classList.remove('hidden')
  } else {
    prevBtn.classList.add('hidden')
    nextBtn.classList.add('hidden')
  }

  document.body.style.overflow = 'hidden'
  document.querySelector('.js-detail-modal').classList.remove('hidden')
}

/**
 * Close the modal.
 */
export function closeModal() {
  document.body.style.overflow = 'auto'
  document.querySelector('.js-detail-modal').classList.add('hidden')
}

/**
 * Navigate carousel.
 * @param {number} dir — -1 for prev, +1 for next
 */
export function changeImage(dir) {
  modalState.currentIndex += dir
  if (modalState.currentIndex >= modalState.images.length) modalState.currentIndex = 0
  if (modalState.currentIndex < 0) modalState.currentIndex = modalState.images.length - 1
  updateCarousel()
}

/**
 * Jump to a specific image index.
 */
function setImage(index) {
  modalState.currentIndex = index
  updateCarousel()
}

function updateCarousel() {
  const img = document.querySelector('.js-modal-image')
  const fallback = document.querySelector('.js-modal-image-fallback')
  img.style.display = ''
  fallback.style.display = 'none'
  img.src = modalState.images[modalState.currentIndex]

  const indicators = document.querySelector('.js-img-indicators')
  indicators.innerHTML = ''
  if (modalState.images.length > 1) {
    for (let i = 0; i < modalState.images.length; i++) {
      const dot = document.createElement('button')
      dot.className = `w-2 h-2 rounded-full transition-all ${i === modalState.currentIndex ? 'bg-astra-500 w-4' : 'bg-slate-300 hover:bg-slate-400'}`
      dot.addEventListener('click', () => setImage(i))
      indicators.appendChild(dot)
    }
  }
}

/**
 * Bind modal event listeners (close, prev, next).
 */
export function bindModalEvents() {
  // Close button
  document.querySelector('.js-modal-close').addEventListener('click', closeModal)

  // Click backdrop to close
  document.querySelector('.js-detail-modal').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) closeModal()
  })

  // Escape key to close
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const modal = document.querySelector('.js-detail-modal')
      if (!modal.classList.contains('hidden')) closeModal()
    }
  })

  // Prev / Next
  document.querySelector('.js-prev-img').addEventListener('click', () => changeImage(-1))
  document.querySelector('.js-next-img').addEventListener('click', () => changeImage(1))
}
