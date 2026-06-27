export function Banner(banners) {
  if (!banners || banners.length === 0) return ''

  const active = banners.filter(b => b.active !== false).slice(0, 5)

  if (active.length === 0) return ''

  return `
  <div class="js-banner-carousel relative overflow-hidden rounded-2xl bg-slate-100 shadow-sm">
    <div class="js-banner-track flex transition-transform duration-500 ease-in-out">
      ${active.map(b => `
        <div class="js-banner-slide min-w-full">
          ${b.link ? `<a href="${escAttr(b.link)}" target="_blank" rel="noopener">` : ''}
            <img src="uploads/banners/${escAttr(b.image)}" alt="${escAttr(b.alt || 'Banner')}"
                 class="w-full h-auto rounded-2xl">
          ${b.link ? '</a>' : ''}
        </div>
      `).join('')}
    </div>
    ${active.length > 1 ? `
    <button class="js-banner-prev absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 bg-black/30 hover:bg-black/50 text-white rounded-full flex items-center justify-center transition-colors z-10 backdrop-blur-sm">
      <i class="fa-solid fa-chevron-left text-sm"></i>
    </button>
    <button class="js-banner-next absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 bg-black/30 hover:bg-black/50 text-white rounded-full flex items-center justify-center transition-colors z-10 backdrop-blur-sm">
      <i class="fa-solid fa-chevron-right text-sm"></i>
    </button>
    <div class="js-banner-dots absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-2 z-10">
      ${active.map((_, i) => `<button class="js-banner-dot w-2 h-2 rounded-full transition-all ${i === 0 ? 'bg-white w-4' : 'bg-white/50 hover:bg-white/80'}"></button>`).join('')}
    </div>
    ` : ''}
  </div>`
}

export function bindBannerCarousel() {
  const track = document.querySelector('.js-banner-track')
  const prev = document.querySelector('.js-banner-prev')
  const next = document.querySelector('.js-banner-next')
  const dots = document.querySelectorAll('.js-banner-dot')
  if (!track) return

  let current = 0
  const total = track.children.length
  if (total <= 1) return

  function goTo(index) {
    if (index < 0) index = total - 1
    if (index >= total) index = 0
    current = index
    track.style.transform = `translateX(-${current * 100}%)`
    dots.forEach((dot, i) => {
      dot.className = `js-banner-dot w-2 h-2 rounded-full transition-all ${i === current ? 'bg-white w-4' : 'bg-white/50 hover:bg-white/80'}`
    })
  }

  if (next) next.addEventListener('click', () => goTo(current + 1))
  if (prev) prev.addEventListener('click', () => goTo(current - 1))
  dots.forEach((dot, i) => dot.addEventListener('click', () => goTo(i)))

  let interval = setInterval(() => goTo(current + 1), 5000)
  const container = track.parentElement
  if (container) {
    container.addEventListener('mouseenter', () => clearInterval(interval))
    container.addEventListener('mouseleave', () => { interval = setInterval(() => goTo(current + 1), 5000) })
  }
}

function escAttr(str) {
  return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}
