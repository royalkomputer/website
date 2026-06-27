/**
 * Render a single playlist carousel.
 * @param {Object} playlist - { id, name, photos: [{ image, link, alt }], interval, active }
 * @param {number} index - Playlist index
 * @returns {string} HTML string
 */
export function renderPlaylist(playlist, index) {
  const photos = playlist.photos || []
  if (photos.length === 0) return ''

  const hasMultiple = photos.length > 1
  const plId = 'pl-' + index

  return `
  <div class="rounded-2xl overflow-hidden transition-all duration-500 ease-in-out">
    <div class="pl-carousel relative overflow-hidden rounded-2xl bg-slate-100 shadow-sm" data-pl="${plId}">
      <div class="pl-track flex transition-transform duration-500 ease-in-out" data-pl="${plId}">
        ${photos.map(p => `
          <div class="pl-slide min-w-full flex-shrink-0" data-pl="${plId}">
            ${p.link ? `<a href="${escAttr(p.link)}" target="_blank" rel="noopener">` : ''}
              <img src="uploads/banners/${escAttr(p.image)}" alt="${escAttr(p.alt || playlist.name || 'Banner')}"
                   class="w-full h-auto rounded-2xl">
            ${p.link ? '</a>' : ''}
          </div>
        `).join('')}
      </div>
      ${hasMultiple ? `
      <button class="pl-prev absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 bg-black/30 hover:bg-black/50 text-white rounded-full flex items-center justify-center transition-colors z-10 backdrop-blur-sm" data-pl="${plId}">
        <i class="fa-solid fa-chevron-left text-sm"></i>
      </button>
      <button class="pl-next absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 bg-black/30 hover:bg-black/50 text-white rounded-full flex items-center justify-center transition-colors z-10 backdrop-blur-sm" data-pl="${plId}">
        <i class="fa-solid fa-chevron-right text-sm"></i>
      </button>
      <div class="pl-dots absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-2 z-10" data-pl="${plId}">
        ${photos.map((_, i) => `<button class="pl-dot w-2 h-2 rounded-full transition-all ${i === 0 ? 'bg-white w-4' : 'bg-white/50 hover:bg-white/80'}" data-pl="${plId}" data-index="${i}"></button>`).join('')}
      </div>
      ` : ''}
    </div>
  </div>`
}

/**
 * Bind carousel controls for all playlist carousels.
 */
export function bindAllCarousels(playlists) {
  document.querySelectorAll('.pl-carousel').forEach(carousel => {
    const plId = carousel.dataset.pl
    const track = carousel.querySelector('.pl-track')
    if (!track || track.children.length <= 1) return

    const idx = parseInt(plId.split('-')[1])
    const playlist = playlists && playlists[idx]
    const interval = (playlist && playlist.interval) || 5000
    const total = track.children.length

    let current = 0

    function goTo(index) {
      if (index < 0) index = total - 1
      if (index >= total) index = 0
      current = index
      track.style.transform = `translateX(-${current * 100}%)`
      carousel.querySelectorAll('.pl-dot').forEach((dot, i) => {
        dot.className = `pl-dot w-2 h-2 rounded-full transition-all ${i === current ? 'bg-white w-4' : 'bg-white/50 hover:bg-white/80'}`
      })
    }

    const nextBtn = carousel.querySelector('.pl-next')
    const prevBtn = carousel.querySelector('.pl-prev')
    if (nextBtn) nextBtn.addEventListener('click', () => goTo(current + 1))
    if (prevBtn) prevBtn.addEventListener('click', () => goTo(current - 1))
    carousel.querySelectorAll('.pl-dot').forEach(d => d.addEventListener('click', () => goTo(parseInt(d.dataset.index) || 0)))

    let autoInterval = setInterval(() => goTo(current + 1), interval)
    carousel.addEventListener('mouseenter', () => clearInterval(autoInterval))
    carousel.addEventListener('mouseleave', () => { autoInterval = setInterval(() => goTo(current + 1), interval) })
  })
}

function escAttr(str) {
  return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}
